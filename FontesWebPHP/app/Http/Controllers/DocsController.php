<?php

namespace App\Http\Controllers;

use App\Support\AgentScope;
use App\Support\StoragePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\NFe\Danfce;
use NFePHP\DA\CTe\Dacte;
use NFePHP\DA\MDFe\Damdfe;
use App\Models\EventDocument;
use NFePHP\DA\CTe\Daevento;
use Exception;
use App\Support\CancellationStatus;

class DocsController extends Controller
{
    function xml_attribute($object, $attribute)
    {
        if (isset($object[$attribute]))
            return (string) $object[$attribute];
    }

    protected function invalidUploadResponse($message = 'Arquivo XML invalido ou ausente.')
    {
        return response()->json([
            'msg' => $message
        ], 422);
    }

    /**
     * Nota ainda NÃO autorizada: raiz `<NFe>`/`<CTe>`/`<MDFe>` (em vez do
     * envelope `*Proc`) e sem protocolo. Não é documento fiscal; quando a SEFAZ
     * autoriza, o emissor regrava o arquivo e ele sobe de novo.
     *
     * Quem chama deve aceitar e descartar (msg 100), não 422 — pelo contrato do
     * agente, 422 vira retry a cada 30s para sempre.
     */
    protected function ehNotaSemProtocolo(\SimpleXMLElement $xml): bool
    {
        return in_array($xml->getName(), ['NFe', 'CTe', 'MDFe'], true)
            && ! isset($xml->protNFe)
            && ! isset($xml->protCTe)
            && ! isset($xml->protMDFe);
    }

    protected function hasValidSystemKey(Request $request)
    {
        // Quem autentica (token ou chave legada) é o middleware CheckSystem;
        // aqui só se lê o resultado. Não duplicar a lógica dele.
        return $request->attributes->getBoolean('agent_authenticated');
    }

    /** Nota com evento de cancelamento homologado entra como 101 (ver CancellationStatus). */
    protected function statusComCancelamento($chave, $cStat)
    {
        return CancellationStatus::resolve($chave, $cStat);
    }

    /**
     * Grava (ou regrava) o documento de forma atômica.
     *
     * Deduplica por (key, cnpj_cpf), NÃO só por key: a mesma nota existe como
     * saída e como entrada, com a mesma chave e CNPJs diferentes. O `3` re-tenta
     * a transação em deadlock, para não virar 500 sem `msg`.
     */
    protected function salvarDocumento($chave, array $campos): void
    {
        DB::transaction(function () use ($chave, $campos) {
            DB::table('documents')
                ->where('key', '=', $chave)
                ->where('cnpj_cpf', '=', $campos['cnpj_cpf'])
                ->delete();
            DB::table('documents')->insert($campos);
        }, 3);
    }

    public function nfe_nfce(Request $request)
    {
        if (!$this->hasValidSystemKey($request)) {
            return response()->json([
                'msg' => 'Voce nao tem permissao.'
            ], 403);
        }

        if ($this->hasValidSystemKey($request)) {
            if ($request->hasFile('file') && $request->file('file')->isValid()) {
                $file = $request->file('file');

                $extension = $file->extension();
                $nameFile = $file->getClientOriginalName();
                $size = $file->getSize();

                $xml = @simplexml_load_file($file->getRealPath());
                if ($xml === false) {
                    return $this->invalidUploadResponse('Nao foi possivel ler o XML enviado.');
                }

                // XML fora do layout -> 422 (em vez de 500). Nota sem protocolo:
                // aceita e descarta, senao vira retry eterno (ehNotaSemProtocolo).
                if ($this->ehNotaSemProtocolo($xml)) {
                    return response()->json(['msg' => '100'], 200);
                }

                if (!isset($xml->NFe->infNFe->ide, $xml->NFe->infNFe->emit, $xml->protNFe->infProt)) {
                    return $this->invalidUploadResponse('XML fora do layout esperado (NF-e/NFC-e).');
                }

                $nNF       = $xml->NFe->infNFe->ide->nNF;
                $serie     = $xml->NFe->infNFe->ide->serie;
                $mod       = $xml->NFe->infNFe->ide->mod;
                $chNFe     = $xml->protNFe->infProt->chNFe;
                $nProt     = $xml->protNFe->infProt->nProt;
                $IE        = $xml->NFe->infNFe->emit->IE;
                $dhEmi     = $xml->NFe->infNFe->ide->dhEmi;
                $tpAmb     = $xml->NFe->infNFe->ide->tpAmb;
                $cStat     = $xml->protNFe->infProt->cStat;

                switch ($cStat) {
                    case 100:
                        $cStat = 100;
                        break;
                    case 101:
                        $cStat = 101;
                        break;
                    case 135:
                        $cStat = 101;
                        break;
                    case 155:
                        $cStat = 101;
                        break;
                    case 150:
                        $cStat = 100;
                        break;
                    case 151:
                        $cStat = 101;
                        break;
                    case 110:
                        $cStat = 110;
                        break;
                    case 301:
                        $cStat = 110;
                        break;
                    case 302:
                        $cStat = 110;
                        break;
                    case 303:
                        $cStat = 110;
                        break;
                }

                $vNF = $xml->NFe->infNFe->total->ICMSTot->vNF;

                // CPF ou CNPJ pela presenca do no, nao pela serie.
                if (isset($xml->NFe->infNFe->emit->CPF) && trim((string) $xml->NFe->infNFe->emit->CPF) !== '') {
                    $CNPJCPF = $xml->NFe->infNFe->emit->CPF;
                } else {
                    $CNPJCPF = $xml->NFe->infNFe->emit->CNPJ;
                }

                $razao_social = $xml->NFe->infNFe->emit->xNome;
                // So grava nos CNPJs vinculados ao dono da chave (AgentScope).
                if (! AgentScope::permite($request, $CNPJCPF)) {
                    return AgentScope::recusa($CNPJCPF);
                }

                $nome_fantasia = $xml->NFe->infNFe->emit->xFant;
                $logradouro   = $xml->NFe->infNFe->emit->enderEmit->xLgr;
                $numero   = $xml->NFe->infNFe->emit->enderEmit->nro;

                $bairro   = $xml->NFe->infNFe->emit->enderEmit->xBairro;
                $cep   = $xml->NFe->infNFe->emit->enderEmit->CEP;
                $municipio   = $xml->NFe->infNFe->emit->enderEmit->xMun;
                $uf   = $xml->NFe->infNFe->emit->enderEmit->UF;
                $telefone   = $xml->NFe->infNFe->emit->enderEmit->fone;
                //$email   = $xml->NFe->infNFe->emit->email;


                $data = str_replace('/', '-', $dhEmi);
                $mesano    = date('Ym', strtotime($data));
                $data_emissao  = date('Y-m-d', strtotime($dhEmi));

                $nameFile = "{$nameFile}";

                $upload = $file->storeAs('docs', $nameFile);
                $url = Storage::url($upload);
                //$xml->load($url);

                // Caminho definitivo do arquivo no Storage
                $url_new = StoragePath::montar('/docs', $CNPJCPF, $IE, $mod, $mesano, StoragePath::arquivo($nameFile));

                if (Storage::exists($url_new)) {
                    Storage::delete($url_new);
                }

                // Storage::delete($upload);
                Storage::move($upload, $url_new);

                // Cadastra a empresa emitente se ainda nao existe
                $empresas =  DB::table('companies')->Where('cnpj_cpf', '=', $CNPJCPF)->first();

                if (!$empresas) {

                    $campos = [
                        'cnpj_cpf' => $CNPJCPF,
                        'corporate_name' => mb_strtoupper($razao_social),
                        'fantasy_name' => mb_strtoupper($nome_fantasia),
                        //'email' => $email,
                        'public_place' => $logradouro,
                        'home_number' => $numero,
                        //'complement' => $complemento,
                        'district' => $bairro,
                        'zip_code' => $cep,
                        'county' => $municipio,
                        'uf' => $uf,
                        'phone_number' => $telefone
                    ];

                    DB::table('companies')->insert($campos);
                }

                $campos = [
                    'cnpj_cpf' => $CNPJCPF,
                    'ie' => $IE,
                    'model' => $mod,
                    'series' => $serie,
                    'number' => $nNF,
                    'key' => $chNFe,
                    'month_year' => $mesano,
                    'issue_dh' => $data_emissao,
                    'path_xml' => $url_new,
                    'protocol' => $nProt,
                    'environment_type' => $tpAmb,
                    'status_xml' => $this->statusComCancelamento($chNFe, $cStat),
                    'vNF' => $vNF,
                    'size' => $size,
                ];

                $this->salvarDocumento($chNFe, $campos);

                if (!$upload) {
                    return response()->json([
                        'msg' => 'Erro ao fazer upload.'
                    ], 200);
                } else {
                    return response()->json([
                        'msg' => '100'
                    ], 200);
                }
            }

            return $this->invalidUploadResponse();
        } else {
            return response()->json([
                'msg' => 'Você não tem permissão.'
            ], 200);
        }
    }
	
//NF Entrada
    public function nfe(Request $request)
    {
        if (!$this->hasValidSystemKey($request)) {
            return response()->json([
                'msg' => 'Voce nao tem permissao.'
            ], 403);
        }

        if ($this->hasValidSystemKey($request)) {
            if ($request->hasFile('file') && $request->file('file')->isValid()) {
                $file = $request->file('file');

                $extension = $file->extension();
                $nameFile = $file->getClientOriginalName();
                $size = $file->getSize();

                $xml = @simplexml_load_file($file->getRealPath());
                if ($xml === false) {
                    return $this->invalidUploadResponse('Nao foi possivel ler o XML enviado.');
                }

                // XML fora do layout -> 422 (em vez de 500). Nota sem protocolo:
                // aceita e descarta, senao vira retry eterno (ehNotaSemProtocolo).
                if ($this->ehNotaSemProtocolo($xml)) {
                    return response()->json(['msg' => '100'], 200);
                }

                if (!isset($xml->NFe->infNFe->ide, $xml->NFe->infNFe->emit, $xml->NFe->infNFe->dest, $xml->protNFe->infProt)) {
                    return $this->invalidUploadResponse('XML fora do layout esperado (NF-e/NFC-e).');
                }

                $nNF       = $xml->NFe->infNFe->ide->nNF;
                $serie     = $xml->NFe->infNFe->ide->serie;
                $mod       = $xml->NFe->infNFe->ide->mod;
                $chNFe     = $xml->protNFe->infProt->chNFe;
                $nProt     = $xml->protNFe->infProt->nProt;
                $IE        = $xml->NFe->infNFe->emit->IE;
                $dhEmi     = $xml->NFe->infNFe->ide->dhEmi;
                $tpAmb     = $xml->NFe->infNFe->ide->tpAmb;
                $cStat     = $xml->protNFe->infProt->cStat;

                switch ($cStat) {
                    case 100:
                        $cStat = 100;
                        break;
                    case 101:
                        $cStat = 101;
                        break;
                    case 135:
                        $cStat = 101;
                        break;
                    case 155:
                        $cStat = 101;
                        break;
                    case 150:
                        $cStat = 100;
                        break;
                    case 151:
                        $cStat = 101;
                        break;
                    case 110:
                        $cStat = 110;
                        break;
                    case 301:
                        $cStat = 110;
                        break;
                    case 302:
                        $cStat = 110;
                        break;
                    case 303:
                        $cStat = 110;
                        break;
                }

                $vNF = $xml->NFe->infNFe->total->ICMSTot->vNF;

                // CPF ou CNPJ pela presenca do no, nao pela serie.
                if (isset($xml->NFe->infNFe->dest->CPF) && trim((string) $xml->NFe->infNFe->dest->CPF) !== '') {
                    $CNPJCPF = $xml->NFe->infNFe->dest->CPF;
                } else {
                    $CNPJCPF = $xml->NFe->infNFe->dest->CNPJ;
                }

                $razao_social = $xml->NFe->infNFe->dest->xNome;
                // So grava nos CNPJs vinculados ao dono da chave (AgentScope).
                if (! AgentScope::permite($request, $CNPJCPF)) {
                    return AgentScope::recusa($CNPJCPF);
                }

                $nome_fantasia = $xml->NFe->infNFe->dest->xNome;
                $logradouro   = $xml->NFe->infNFe->dest->enderDest->xLgr;
                $numero   = $xml->NFe->infNFe->dest->enderDest->nro;

                $bairro   = $xml->NFe->infNFe->dest->enderDest->xBairro;
                $cep   = $xml->NFe->infNFe->dest->enderDest->CEP;
                $municipio   = $xml->NFe->infNFe->dest->enderDest->xMun;
                $uf   = $xml->NFe->infNFe->dest->enderDest->UF;
                $telefone   = $xml->NFe->infNFe->dest->enderDest->fone;
                //$email   = $xml->NFe->infNFe->emit->email;
				$cnpj_emit = $xml->NFe->infNFe->emit->CNPJ;


                $data = str_replace('/', '-', $dhEmi);
                $mesano    = date('Ym', strtotime($data));
                $data_emissao  = date('Y-m-d', strtotime($dhEmi));

                $nameFile = "{$nameFile}";

                $upload = $file->storeAs('docs', $nameFile);
                $url = Storage::url($upload);
                //$xml->load($url);

                // Caminho definitivo do arquivo no Storage
                //$url_new = '/docs/entrada/' . $CNPJCPF . '/' . $IE . '/' . $mod . '/' . $mesano . '/' . $nameFile;
				$url_new = StoragePath::montar('/docs/entrada', $CNPJCPF, StoragePath::arquivo($nameFile));

                if (Storage::exists($url_new)) {
                    Storage::delete($url_new);
                }

                // Storage::delete($upload);
                Storage::move($upload, $url_new);

                // Cadastra a empresa emitente se ainda nao existe
                $empresas =  DB::table('companies')->Where('cnpj_cpf', '=', $CNPJCPF)->first();

                if (!$empresas) {

                    $campos = [
                        'cnpj_cpf' => $CNPJCPF,
                        'corporate_name' => mb_strtoupper($razao_social),
                        'fantasy_name' => mb_strtoupper($nome_fantasia),
                        //'email' => $email,
                        'public_place' => $logradouro,
                        'home_number' => $numero,
                        //'complement' => $complemento,
                        'district' => $bairro,
                        'zip_code' => $cep,
                        'county' => $municipio,
                        'uf' => $uf,
                        'phone_number' => $telefone
                    ];

                    DB::table('companies')->insert($campos);
                }

                $campos = [
                    'cnpj_cpf' => $CNPJCPF,
					'cnpj_emit' => $cnpj_emit,
                    'ie' => $IE,
                    'model' => '59',//=> $mod,
                    'series' => $serie,
                    'number' => $nNF,
                    'key' => $chNFe,
                    'month_year' => $mesano,
                    'issue_dh' => $data_emissao,
                    'path_xml' => $url_new,
                    'protocol' => $nProt,
                    'environment_type' => $tpAmb,
                    'status_xml' => $cStat,
                    'vNF' => $vNF,
                    'size' => $size,
					'entrada' => 'S',
                ];

                $this->salvarDocumento($chNFe, $campos);

                if (!$upload) {
                    return response()->json([
                        'msg' => 'Erro ao fazer upload.'
                    ], 200);
                } else {
                    return response()->json([
                        'msg' => '100'
                    ], 200);
                }
            }

            return $this->invalidUploadResponse();
        } else {
            return response()->json([
                'msg' => 'Você não tem permissão.'
            ], 200);
        }
    }
//NF Entrada	

    public function sat(Request $request)
    {
        if (!$this->hasValidSystemKey($request)) {
            return response()->json([
                'msg' => 'Voce nao tem permissao.'
            ], 403);
        }

        if ($this->hasValidSystemKey($request)) {
            if ($request->hasFile('file') && $request->file('file')->isValid()) {
                $file = $request->file('file');

                $extension = $file->extension();
                $nameFile = $file->getClientOriginalName();
                $size = $file->getSize();

                $xml = @simplexml_load_file($file->getRealPath());
                if ($xml === false) {
                    return $this->invalidUploadResponse('Nao foi possivel ler o XML enviado.');
                }

                // XML fora do layout -> 422 (em vez de 500).
                if (!isset($xml->infCFe->ide, $xml->infCFe->emit, $xml->infCFe->total)) {
                    return $this->invalidUploadResponse('XML fora do layout esperado (SAT/CF-e).');
                }

                $nNF       = $xml->infCFe->ide->nCFe;
                $serie     = $xml->infCFe->ide->nserieSAT;
                $mod       = $xml->infCFe->ide->mod;
                $chNFe     = substr($this->xml_attribute($xml->infCFe, 'Id'), 3, 46);

                $IE        = $xml->infCFe->emit->IE;
                $dhEmi     = date('Y-m-d', strtotime($xml->infCFe->ide->dEmi));
                $tpAmb     = $xml->infCFe->ide->tpAmb;

                // O CF-e-SAT não traz cStat no XML: o status vem do POST, e só
                // vale se for numérico de 3 digitos. Sem status valido = 100.
                $satStatus = (string) $request->input('sat_status', '');
                $cStat = (ctype_digit($satStatus) && strlen($satStatus) === 3) ? $satStatus : '100';

                // "Fora do prazo" colapsa: 150 = autorizada, 151 = cancelada.
                $cStat = match ($cStat) {
                    '150'   => '100',
                    '151'   => '101',
                    default => $cStat,
                };
                $vNF       = $xml->infCFe->total->vCFe;

                //emitente
                $CNPJCPF = $xml->infCFe->emit->CNPJ;
                // So grava nos CNPJs vinculados ao dono da chave (AgentScope).
                if (! AgentScope::permite($request, $CNPJCPF)) {
                    return AgentScope::recusa($CNPJCPF);
                }

                $razao_social =  $xml->infCFe->emit->xNome;
                $nome_fantasia = $xml->infCFe->emit->xNome;

                
                $data = str_replace('/', '-', $dhEmi);
                $mesano    = date('Ym', strtotime($data));
                $data_emissao  = date('Y-m-d', strtotime($dhEmi));

                $nameFile = "{$nameFile}";

                $upload = $file->storeAs('docs', $nameFile);
                $url = Storage::url($upload);
                //$xml->load($url);

                // Caminho definitivo do arquivo no Storage
                $url_new = StoragePath::montar('/docs', $CNPJCPF, $IE, $mod, $mesano, StoragePath::arquivo($nameFile));

                if (Storage::exists($url_new)) {
                    Storage::delete($url_new);
                }

                //Storage::delete($upload);
                Storage::move($upload, $url_new);

                $empresas =  DB::table('companies')->Where('cnpj_cpf', '=', $CNPJCPF)->first();

                if (!$empresas) {

                    $campos = [
                        'cnpj_cpf' => $CNPJCPF,
                        'corporate_name' => mb_strtoupper($razao_social),
                        'fantasy_name' => mb_strtoupper($nome_fantasia)
                    ];

                    DB::table('companies')->insert($campos);
                }

                $campos = [
                    'cnpj_cpf' => $CNPJCPF,
                    'ie' => $IE,
                    'model' => $mod,
                    'series' => $serie,
                    'number' => $nNF,
                    'key' => $chNFe,
                    'month_year' => $mesano,
                    'issue_dh' => $data_emissao,
                    'path_xml' => $url_new,
                    'protocol' => 'SEM PROTOCOLO',
                    'environment_type' => $tpAmb,
                    'status_xml' => $cStat,
                    'vNF' => $vNF,
                    'size' => $size,
                ];


                $this->salvarDocumento($chNFe, $campos);

                if (!$upload) {
                    return response()->json([
                        'msg' => 'Erro ao fazer upload.'
                    ], 200);
                } else {
                    return response()->json([
                        'msg' => '100'
                    ], 200);
                }
            }

            return $this->invalidUploadResponse();
        } else {
            return response()->json([
                'msg' => 'Você não tem permissão.'
            ], 200);
        }
    }


    public function cte(Request $request)
    {
        if (!$this->hasValidSystemKey($request)) {
            return response()->json([
                'msg' => 'Voce nao tem permissao.'
            ], 403);
        }

        if ($this->hasValidSystemKey($request)) {
            if ($request->hasFile('file') && $request->file('file')->isValid()) {
                $file = $request->file('file');

                $extension = $file->extension();
                $nameFile = $file->getClientOriginalName();
                $size = $file->getSize();

                $xml = @simplexml_load_file($file->getRealPath());
                if ($xml === false) {
                    return $this->invalidUploadResponse('Nao foi possivel ler o XML enviado.');
                }

                // CT-e (57) e CT-e OS (67) compartilham pasta no ERP e o mesmo
                // miolo (infCte/protCTe); muda só a raiz do envelope.
                $cte = isset($xml->CTe) ? $xml->CTe : (isset($xml->CTeOS) ? $xml->CTeOS : null);

                // XML fora do layout -> 422 (em vez de 500). Nota sem protocolo:
                // aceita e descarta, senao vira retry eterno (ehNotaSemProtocolo).
                if ($this->ehNotaSemProtocolo($xml)) {
                    return response()->json(['msg' => '100'], 200);
                }

                if (is_null($cte) || !isset($cte->infCte->ide, $cte->infCte->emit, $xml->protCTe->infProt)) {
                    return $this->invalidUploadResponse('XML fora do layout esperado (CT-e).');
                }

                $nNF       = $cte->infCte->ide->nCT;
                $serie     = $cte->infCte->ide->serie;
                $mod       = $cte->infCte->ide->mod;
                $chNFe     = $xml->protCTe->infProt->chCTe;
                $nProt     = $xml->protCTe->infProt->nProt;
                $IE        = $cte->infCte->emit->IE;
                $dhEmi     = $cte->infCte->ide->dhEmi;
                $tpAmb     = $cte->infCte->ide->tpAmb;
                $cStat     = $xml->protCTe->infProt->cStat;

                switch ($cStat) {
                    case 100:
                        $cStat = 100;
                        break;
                    case 101:
                        $cStat = 101;
                        break;
                    case 135:
                        $cStat = 101;
                        break;
                    case 155:
                        $cStat = 101;
                        break;
                    case 150:
                        $cStat = 100;
                        break;
                    case 151:
                        $cStat = 101;
                        break;
                    case 110:
                        $cStat = 110;
                        break;
                    case 301:
                        $cStat = 110;
                        break;
                    case 302:
                        $cStat = 110;
                        break;
                    case 303:
                        $cStat = 110;
                        break;
                }

                $vNF = $cte->infCte->vPrest->vTPrest;

                // CPF ou CNPJ pela presenca do no, nao pela serie.
                if (isset($cte->infCte->emit->CPF) && trim((string) $cte->infCte->emit->CPF) !== '') {
                    $CNPJ = $cte->infCte->emit->CPF;
                } else {
                    $CNPJ = $cte->infCte->emit->CNPJ;
                }
                // So grava nos CNPJs vinculados ao dono da chave (AgentScope).
                if (! AgentScope::permite($request, $CNPJ)) {
                    return AgentScope::recusa($CNPJ);
                }

                $razao_social = $cte->infCte->emit->xNome;
                $nome_fantasia = $cte->infCte->emit->xFant;
                $logradouro   = $cte->infCte->emit->enderEmit->xLgr;
                $numero   = $cte->infCte->emit->enderEmit->nro;

                $bairro   = $cte->infCte->emit->enderEmit->xBairro;
                $cep   = $cte->infCte->emit->enderEmit->CEP;
                $municipio   = $cte->infCte->emit->enderEmit->xMun;
                $uf   = $cte->infCte->emit->enderEmit->UF;
                $telefone   = $cte->infCte->emit->enderEmit->fone;
                //$email   = $xml->NFe->infNFe->emit->email;

                $data = str_replace('/', '-', $dhEmi);
                $mesano    = date('Ym', strtotime($data));
                $data_emissao  = date('Y-m-d', strtotime($dhEmi));

                $nameFile = "{$nameFile}";

                $upload = $file->storeAs('docs', $nameFile);
                $url = Storage::url($upload);
                //$xml->load($url);

                // Caminho definitivo do arquivo no Storage
                $url_new = StoragePath::montar('/docs', $CNPJ, $IE, $mod, $mesano, StoragePath::arquivo($nameFile));

                if (Storage::exists($url_new)) {
                    Storage::delete($url_new);
                }

                // Storage::delete($upload);
                Storage::move($upload, $url_new);

                // Cadastra a empresa emitente se ainda nao existe
                $empresas =  DB::table('companies')->Where('cnpj_cpf', '=', $CNPJ)->first();

                if (!$empresas) {

                    $campos = [
                        'cnpj_cpf' => $CNPJ,
                        'corporate_name' => mb_strtoupper($razao_social),
                        'fantasy_name' => mb_strtoupper($nome_fantasia),
                        //'email' => $email,
                        'public_place' => $logradouro,
                        'home_number' => $numero,
                        //'complement' => $complemento,
                        'district' => $bairro,
                        'zip_code' => $cep,
                        'county' => $municipio,
                        'uf' => $uf,
                        'phone_number' => $telefone
                    ];
                    DB::table('companies')->insert($campos);
                }

                $campos = [
                    'cnpj_cpf' => $CNPJ,
                    'ie' => $IE,
                    'model' => $mod,
                    'series' => $serie,
                    'number' => $nNF,
                    'key' => $chNFe,
                    'month_year' => $mesano,
                    'issue_dh' => $data_emissao,
                    'path_xml' => $url_new,
                    'protocol' => $nProt,
                    'environment_type' => $tpAmb,
                    'status_xml' => $this->statusComCancelamento($chNFe, $cStat),
                    'vNF' => $vNF,
                    'size' => $size,
                ];

                $this->salvarDocumento($chNFe, $campos);

                if (!$upload) {
                    return response()->json([
                        'msg' => 'Erro ao fazer upload.'
                    ], 200);
                } else {
                    return response()->json([
                        'msg' => '100'
                    ], 200);
                }
            }

            return $this->invalidUploadResponse();
        } else {
            return response()->json([
                'msg' => 'Você não tem permissão.'
            ], 200);
        }
    }

    public function mdfe(Request $request)
    {
        if (!$this->hasValidSystemKey($request)) {
            return response()->json([
                'msg' => 'Voce nao tem permissao.'
            ], 403);
        }

        if ($this->hasValidSystemKey($request)) {
            if ($request->hasFile('file') && $request->file('file')->isValid()) {
                $file = $request->file('file');

                $extension = $file->extension();
                $nameFile = $file->getClientOriginalName();
                $size = $file->getSize();

                $xml = @simplexml_load_file($file->getRealPath());
                if ($xml === false) {
                    return $this->invalidUploadResponse('Nao foi possivel ler o XML enviado.');
                }

                // XML fora do layout -> 422 (em vez de 500). Nota sem protocolo:
                // aceita e descarta, senao vira retry eterno (ehNotaSemProtocolo).
                if ($this->ehNotaSemProtocolo($xml)) {
                    return response()->json(['msg' => '100'], 200);
                }

                if (!isset($xml->MDFe->infMDFe->ide, $xml->MDFe->infMDFe->emit, $xml->protMDFe->infProt)) {
                    return $this->invalidUploadResponse('XML fora do layout esperado (MDF-e).');
                }

                $nNF       = $xml->MDFe->infMDFe->ide->nMDF;
                $serie     = $xml->MDFe->infMDFe->ide->serie;
                $mod       = $xml->MDFe->infMDFe->ide->mod;
                $chNFe     = $xml->protMDFe->infProt->chMDFe;
                $nProt     = $xml->protMDFe->infProt->nProt;
                $IE        = $xml->MDFe->infMDFe->emit->IE;
                $dhEmi     = $xml->MDFe->infMDFe->ide->dhEmi;
                $tpAmb     = $xml->MDFe->infMDFe->ide->tpAmb;
                $cStat     = $xml->protMDFe->infProt->cStat;

                switch ($cStat) {
                    case 100:
                        $cStat = 100;
                        break;
                    case 101:
                        $cStat = 101;
                        break;
                    case 135:
                        $cStat = 101;
                        break;
                    case 155:
                        $cStat = 101;
                        break;
                    case 150:
                        $cStat = 100;
                        break;
                    case 151:
                        $cStat = 101;
                        break;
                    case 110:
                        $cStat = 110;
                        break;
                    case 301:
                        $cStat = 110;
                        break;
                    case 302:
                        $cStat = 110;
                        break;
                    case 303:
                        $cStat = 110;
                        break;
                }

                $vNF = $xml->MDFe->infMDFe->tot->vCarga;

                // CPF ou CNPJ pela presenca do no, nao pela serie.
                if (isset($xml->MDFe->infMDFe->emit->CPF) && trim((string) $xml->MDFe->infMDFe->emit->CPF) !== '') {
                    $CNPJ = $xml->MDFe->infMDFe->emit->CPF;
                } else {
                    $CNPJ = $xml->MDFe->infMDFe->emit->CNPJ;
                }
                // So grava nos CNPJs vinculados ao dono da chave (AgentScope).
                if (! AgentScope::permite($request, $CNPJ)) {
                    return AgentScope::recusa($CNPJ);
                }

                $razao_social = $xml->MDFe->infMDFe->emit->xNome;
                $nome_fantasia = $xml->MDFe->infMDFe->emit->xFant;

                $logradouro   = $xml->MDFe->infMDFe->emit->enderEmit->xLgr;
                $numero   = $xml->MDFe->infMDFe->emit->enderEmit->nro;

                $bairro   = $xml->MDFe->infMDFe->emit->enderEmit->xBairro;
                $cep   = $xml->MDFe->infMDFe->emit->enderEmit->CEP;
                $municipio   = $xml->MDFe->infMDFe->emit->enderEmit->xMun;
                $uf   = $xml->MDFe->infMDFe->emit->enderEmit->UF;
                $telefone   = $xml->MDFe->infMDFe->emit->enderEmit->fone;
                //$email   = $xml->NFe->infNFe->emit->email;

                $data = str_replace('/', '-', $dhEmi);
                $mesano    = date('Ym', strtotime($data));
                $data_emissao  = date('Y-m-d', strtotime($dhEmi));

                $nameFile = "{$nameFile}";

                $upload = $file->storeAs('docs', $nameFile);
                $url = Storage::url($upload);
                //$xml->load($url);

                // Caminho definitivo do arquivo no Storage
                $url_new = StoragePath::montar('/docs', $CNPJ, $IE, $mod, $mesano, StoragePath::arquivo($nameFile));

                if (Storage::exists($url_new)) {
                    Storage::delete($url_new);
                }

                // Storage::delete($upload);
                Storage::move($upload, $url_new);

                // Cadastra a empresa emitente se ainda nao existe
                $empresas =  DB::table('companies')->Where('cnpj_cpf', '=', $CNPJ)->first();

                if (!$empresas) {

                    $campos = [
                        'cnpj_cpf' => $CNPJ,
                        'corporate_name' => mb_strtoupper($razao_social),
                        'fantasy_name' => mb_strtoupper($nome_fantasia),
                        //'email' => $email,
                        'public_place' => $logradouro,
                        'home_number' => $numero,
                        //'complement' => $complemento,
                        'district' => $bairro,
                        'zip_code' => $cep,
                        'county' => $municipio,
                        'uf' => $uf,
                        'phone_number' => $telefone
                    ];
                    DB::table('companies')->insert($campos);
                }

                $campos = [
                    'cnpj_cpf' => $CNPJ,
                    'ie' => $IE,
                    'model' => $mod,
                    'series' => $serie,
                    'number' => $nNF,
                    'key' => $chNFe,
                    'month_year' => $mesano,
                    'issue_dh' => $data_emissao,
                    'path_xml' => $url_new,
                    'protocol' => $nProt,
                    'environment_type' => $tpAmb,
                    'status_xml' => $this->statusComCancelamento($chNFe, $cStat),
                    'vNF' => $vNF,
                    'size' => $size,
                ];

                $this->salvarDocumento($chNFe, $campos);

                if (!$upload) {
                    return response()->json([
                        'msg' => 'Erro ao fazer upload.'
                    ], 200);
                } else {
                    return response()->json([
                        'msg' => '100'
                    ], 200);
                }
            }

            return $this->invalidUploadResponse();
        } else {
            return response()->json([
                'msg' => 'Você não tem permissão.'
            ], 200);
        }
    }

    /* -------------------------------- NFS-e -------------------------------- */

    /**
     * NFS-e (nota de serviço). Diferente dos documentos SEFAZ: status TEXTUAL,
     * chave opcional e layout que varia por prefeitura. Grava em
     * `nfse_documents`, com dedup por `identidade`.
     *
     * ⚠️ Prefeitura nova costuma exigir ajuste em parseNfseNacional()/
     * parseNfseMunicipal() — validar com amostra antes de liberar.
     */
    public function nfse(Request $request)
    {
        if (!$this->hasValidSystemKey($request)) {
            return response()->json(['msg' => 'Voce nao tem permissao.'], 403);
        }

        if (!($request->hasFile('file') && $request->file('file')->isValid())) {
            return $this->invalidUploadResponse();
        }

        $file = $request->file('file');
        $xml = $this->lerXmlNfse($file->getRealPath());
        if ($xml === false) {
            return $this->invalidUploadResponse('Nao foi possivel ler o XML enviado.');
        }

        $dados = $this->parseNfse($xml);

        // A pasta da NFS-e não tem só nota: chegam também XML de envio (RPS),
        // homologação e recibo <retorno>. Nada disso vira documento, mas tudo
        // responde msg=100 — outra resposta vira retry a cada 30s para sempre.
        //
        // ⚠️ A ordem importa: o descarte é por nome da tag raiz e só roda DEPOIS
        // de o parseNfse() falhar, senão uma nota válida sumiria em silêncio.
        if ($dados === null) {
            if ($xml->getName() === 'retorno') {
                return $this->nfseRetornoIpm($xml);   // cancelamento ou erro do provedor
            }

            // Envio (RPS) / homologação: descarta de propósito, mas loga — sem
            // o log, descarte e nota perdida ficam indistinguíveis.
            if ($xml->getName() === 'nfse' && !$this->ehNfseAutorizadaIpm($xml)) {
                Log::info('NFS-e: XML aceito e descartado (nao e nota).', [
                    'arquivo' => $file->getClientOriginalName(),
                    'numero'  => $this->nfseValor($xml, 'numero_nfse'),
                ]);

                return response()->json(['msg' => '100'], 200);
            }

            // Parece nota e o parser não completou: 422 de propósito, para o
            // agente re-tentar. Responder 100 aqui perderia a nota para sempre.
            Log::warning('NFS-e: parece nota mas o parser nao completou.', [
                'arquivo' => $file->getClientOriginalName(),
                'raiz'    => $xml->getName(),
                'numero'  => $this->nfseValor($xml, 'numero_nfse'),
            ]);

            return $this->invalidUploadResponse('XML fora do layout esperado (NFS-e).');
        }

        // O documento do prestador entra no CAMINHO do arquivo: só dígitos.
        $dados['cnpj_prestador'] = preg_replace('/\D/', '', (string) $dados['cnpj_prestador']);
        if ($dados['cnpj_prestador'] === '') {
            return $this->invalidUploadResponse('NFS-e sem CNPJ/CPF do prestador.');
        }
        // So grava nos CNPJs vinculados ao dono da chave (AgentScope).
        if (! AgentScope::permite($request, $dados['cnpj_prestador'])) {
            return AgentScope::recusa($dados['cnpj_prestador']);
        }

        if ($dados['cnpj_cpf_tomador'] !== null) {
            $dados['cnpj_cpf_tomador'] = preg_replace('/\D/', '', (string) $dados['cnpj_cpf_tomador']) ?: null;
        }

        $nameFile = $file->getClientOriginalName();
        $size = $file->getSize();
        $mesano = $dados['month_year'] ?: 'sem_data';

        $upload = $file->storeAs('docs', $nameFile);
        $url_new = StoragePath::montar('/docs/nfse', $dados['cnpj_prestador'], $mesano, StoragePath::arquivo($nameFile));
        if (Storage::exists($url_new)) {
            Storage::delete($url_new);
        }
        Storage::move($upload, $url_new);

        // Cadastra o prestador se ainda não existe. Nem todo provedor manda o
        // nome no retorno, daí o fallback para o CNPJ (senão nasce sem nome).
        $empresa = DB::table('companies')->where('cnpj_cpf', '=', $dados['cnpj_prestador'])->first();
        if (!$empresa) {
            $nome = mb_strtoupper((string) ($dados['razao_social'] ?: $dados['cnpj_prestador']));
            DB::table('companies')->insert([
                'cnpj_cpf'       => $dados['cnpj_prestador'],
                'corporate_name' => $nome,
                'fantasy_name'   => $nome,
            ]);
        }

        $campos = [
            'padrao'           => $dados['padrao'],
            'cnpj_prestador'   => $dados['cnpj_prestador'],
            'ie'               => $dados['ie'],
            'cnpj_cpf_tomador' => $dados['cnpj_cpf_tomador'],
            'municipio'        => $dados['municipio'],
            'numero'           => $dados['numero'],
            'serie'            => $dados['serie'],
            'cod_verificacao'  => $dados['cod_verificacao'],
            'chave'            => $dados['chave'],
            'identidade'       => $dados['identidade'],
            'month_year'       => $dados['month_year'],
            'issue_dh'         => $dados['issue_dh'],
            'situacao'         => $dados['situacao'],
            'protocol'         => $dados['protocol'],
            'environment_type' => $dados['environment_type'],
            'valor'            => $dados['valor'],
            'path_xml'         => $url_new,
            'size'             => $size,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        $this->salvarNfse($dados['identidade'], $this->limitarCamposNfse($campos));

        return response()->json(['msg' => '100'], 200);
    }

    /**
     * Trunca os textos no tamanho da coluna: em modo strict, campo maior vira
     * 500 sem `msg` no corpo, e o agente re-tenta para sempre.
     */
    protected function limitarCamposNfse(array $campos): array
    {
        $limites = [
            'padrao' => 20, 'cnpj_prestador' => 45, 'ie' => 45, 'cnpj_cpf_tomador' => 45,
            'municipio' => 60, 'numero' => 60, 'serie' => 20, 'cod_verificacao' => 60,
            'chave' => 60, 'identidade' => 191, 'month_year' => 6, 'situacao' => 40,
            'protocol' => 80, 'environment_type' => 1,
        ];

        foreach ($limites as $campo => $max) {
            if (isset($campos[$campo]) && is_string($campos[$campo])) {
                $campos[$campo] = mb_substr($campos[$campo], 0, $max);
            }
        }

        return $campos;
    }

    /** Dedup da NFS-e: delete-then-insert por `identidade`, igual ao salvarDocumento(). */
    protected function salvarNfse($identidade, array $campos): void
    {
        DB::transaction(function () use ($identidade, $campos) {
            // ERP vence o XML: o arquivo do provedor nem sempre é regravado no
            // cancelamento. Situação vinda do ERP não é rebaixada pelo reimport.
            $atual = DB::table('nfse_documents')->where('identidade', '=', $identidade)->first();
            if ($atual && ($atual->situacao_source ?? null) === 'erp') {
                $campos['situacao'] = $atual->situacao;
                $campos['situacao_source'] = 'erp';
            }

            DB::table('nfse_documents')->where('identidade', '=', $identidade)->delete();
            DB::table('nfse_documents')->insert($campos);
        }, 3);
    }

    /** Detecta o padrão da NFS-e e delega. Retorna null se não for reconhecível. */
    protected function parseNfse(\SimpleXMLElement $xml): ?array
    {
        if (!empty($xml->xpath('//*[local-name()="infNFSe"]'))) {
            return $this->parseNfseNacional($xml);   // Sefin Nacional
        }
        if (!empty($xml->xpath('//*[local-name()="InfNfse"]'))) {
            return $this->parseNfseMunicipal($xml);  // ABRASF (municipal)
        }
        if ($xml->getName() === 'nfse' && $this->ehNfseAutorizadaIpm($xml)) {
            return $this->parseNfseIpm($xml);        // IPM/Atende.Net
        }
        return null;
    }

    /**
     * Lê o XML da NFS-e tolerando retorno sem prolog e bytes ANSI num arquivo
     * declarado UTF-8. Nunca injeta prolog: há retorno sem prolog já em UTF-8,
     * e forçar ISO-8859-1 corromperia justamente esses.
     */
    protected function lerXmlNfse(string $caminho)
    {
        $bruto = @file_get_contents($caminho);
        if ($bruto === false || trim($bruto) === '') {
            return false;
        }

        $xml = @simplexml_load_string($bruto);
        if ($xml !== false) {
            return $xml;
        }

        if (!mb_check_encoding($bruto, 'UTF-8')) {
            // CP1252 é superset do ISO-8859-1 — uma conversão cobre os dois casos.
            $convertido = mb_convert_encoding($bruto, 'UTF-8', 'Windows-1252');
            // O prolog agora mente sobre o encoding: tira a declaração.
            $convertido = preg_replace('/(<\?xml\b[^>]*?)\s+encoding\s*=\s*(["\']).*?\2/i', '$1', $convertido, 1);
            $xml = @simplexml_load_string($convertido);
            if ($xml !== false) {
                return $xml;
            }
        }

        return false;
    }

    /** Primeiro valor de texto por local-name (ignora namespace), escopado a $ctx. */
    protected function nfseValor(\SimpleXMLElement $ctx, string $nome, bool $filhoDireto = false): ?string
    {
        $r = $ctx->xpath(($filhoDireto ? './*' : './/*') . '[local-name()="' . $nome . '"]');
        if (!empty($r)) {
            $v = trim((string) $r[0]);
            return $v !== '' ? $v : null;
        }
        return null;
    }

    /** Primeiro NÓ por local-name, para escopar a busca de campos homônimos. */
    protected function nfseNo(\SimpleXMLElement $ctx, string $nome): ?\SimpleXMLElement
    {
        $r = $ctx->xpath('.//*[local-name()="' . $nome . '"]');
        return !empty($r) ? $r[0] : null;
    }

    /** "1.250,90" (formato BR do IPM) e "1250.90" (nacional/ABRASF) -> float. */
    protected function nfseNumero(?string $valor): float
    {
        $v = trim((string) $valor);
        if ($v === '') {
            return 0.0;
        }
        if (str_contains($v, ',')) {          // formato BR: o ponto é separador de milhar
            $v = str_replace(['.', ','], ['', '.'], $v);
        }
        return (float) $v;
    }

    /** Normaliza a data de emissão em [Y-m-d, Ym]; tolera ausência/formato inválido. */
    protected function nfseData(?string $dhStr): array
    {
        $dhStr = trim((string) $dhStr);
        if ($dhStr === '') {
            return [null, null];
        }

        // d/m/aaaa, aceitando sem zero à esquerda: "5/3/2027" cairia no
        // strtotime(), que lê m/d/Y e devolveria 3 de maio.
        if (preg_match('#^(\d{1,2}/\d{1,2}/\d{4})#', $dhStr, $m)) {
            $d = \DateTime::createFromFormat('!j/n/Y', $m[1]);
            $erros = \DateTime::getLastErrors();
            $invalida = $d === false
                || (is_array($erros) && ($erros['warning_count'] || $erros['error_count']))
                // 30/12/1899 é o "zero" do TDateTime: nota que não existe.
                || (int) $d->format('Y') < 1900;

            return $invalida ? [null, null] : [$d->format('Y-m-d'), $d->format('Ym')];
        }

        $ts = strtotime($dhStr);
        return $ts === false ? [null, null] : [date('Y-m-d', $ts), date('Ym', $ts)];
    }

    protected function parseNfseNacional(\SimpleXMLElement $xml): ?array
    {
        $inf = $xml->xpath('//*[local-name()="infNFSe"]')[0];

        // Prestador escopado ao nó emit (evita pegar o CNPJ do tomador).
        $emitArr = $inf->xpath('.//*[local-name()="emit"]');
        $emit = !empty($emitArr) ? $emitArr[0] : $inf;
        $cnpj  = $this->nfseValor($emit, 'CNPJ') ?? $this->nfseValor($emit, 'CPF');
        $razao = $this->nfseValor($emit, 'xNome');
        $ie    = $this->nfseValor($emit, 'IM');

        // Chave de 50 díg.: atributo Id ("NFS"+50) ou elemento chNFSe.
        $chave = preg_replace('/\D/', '', (string) ($inf->attributes()->Id ?? ''));
        if ($chave === '') {
            $chave = $this->nfseValor($inf, 'chNFSe');
        }

        if ($cnpj === null || $chave === null || $chave === '') {
            return null;   // sem prestador ou sem chave nacional não dá para identificar
        }

        $tomaArr = $inf->xpath('.//*[local-name()="toma"]');
        $tomador = !empty($tomaArr) ? ($this->nfseValor($tomaArr[0], 'CNPJ') ?? $this->nfseValor($tomaArr[0], 'CPF')) : null;

        $valor = $this->nfseValor($inf, 'vLiq') ?? $this->nfseValor($inf, 'vServ') ?? '0';
        [$issue, $mesano] = $this->nfseData($this->nfseValor($inf, 'dhProc') ?? $this->nfseValor($inf, 'dhEmi'));

        return [
            'padrao'           => 'nacional',
            'cnpj_prestador'   => $cnpj,
            'razao_social'     => $razao,
            'ie'               => $ie,
            'cnpj_cpf_tomador' => $tomador,
            'municipio'        => $this->nfseValor($inf, 'cLocEmi') ?? $this->nfseValor($inf, 'cMunNFSeGerada'),
            'numero'           => $this->nfseValor($inf, 'nNFSe'),
            'serie'            => null,
            'cod_verificacao'  => null,
            'chave'            => $chave,
            'identidade'       => $chave,   // nacional: a própria chave de acesso
            'month_year'       => $mesano,
            'issue_dh'         => $issue,
            'situacao'         => 'Autorizada',
            'protocol'         => $this->nfseValor($inf, 'nProt'),
            'environment_type' => $this->nfseValor($inf, 'tpAmb') ?? '1',
            'valor'            => $this->nfseNumero($valor),
        ];
    }

    protected function parseNfseMunicipal(\SimpleXMLElement $xml): ?array
    {
        $inf = $xml->xpath('//*[local-name()="InfNfse"]')[0];

        // Prestador escopado (evita pegar dados do tomador).
        $prestArr = $inf->xpath('.//*[local-name()="PrestadorServico"]');
        $prest = !empty($prestArr) ? $prestArr[0] : $inf;
        $cnpj  = $this->nfseValor($prest, 'Cnpj') ?? $this->nfseValor($prest, 'CpfCnpj') ?? $this->nfseValor($prest, 'Cpf');
        $razao = $this->nfseValor($prest, 'RazaoSocial');
        $ie    = $this->nfseValor($prest, 'InscricaoMunicipal');

        // Numero como filho DIRETO da InfNfse (evita <Endereco><Numero>).
        $numero = $this->nfseValor($inf, 'Numero', true) ?? $this->nfseValor($inf, 'Numero');
        $codVer = $this->nfseValor($inf, 'CodigoVerificacao');

        if ($cnpj === null || $numero === null) {
            return null;
        }

        $mun   = $this->nfseValor($inf, 'CodigoMunicipio');
        $valor = $this->nfseValor($inf, 'ValorLiquidoNfse') ?? $this->nfseValor($inf, 'ValorServicos') ?? '0';
        $cancelada = !empty($xml->xpath('//*[local-name()="NfseCancelamento"]'));
        [$issue, $mesano] = $this->nfseData($this->nfseValor($inf, 'DataEmissao'));

        // Chave lógica composta — municipal não tem chave de acesso padronizada.
        $identidade = 'MUN:' . (string) $mun . '|' . $numero . '|' . (string) $codVer . '|' . $cnpj;

        return [
            'padrao'           => 'municipal',
            'cnpj_prestador'   => $cnpj,
            'razao_social'     => $razao,
            'ie'               => $ie,
            'cnpj_cpf_tomador' => null,
            'municipio'        => $mun,
            'numero'           => $numero,
            'serie'            => null,
            'cod_verificacao'  => $codVer,
            'chave'            => null,
            'identidade'       => $identidade,
            'month_year'       => $mesano,
            'issue_dh'         => $issue,
            'situacao'         => $cancelada ? 'Cancelada' : 'Autorizada',
            'protocol'         => null,
            'environment_type' => '1',
            'valor'            => $this->nfseNumero($valor),
        ];
    }

    /* --------------------------- IPM/Atende.Net -------------------------- */

    /**
     * O retorno é de uma NFS-e autorizada? Envio, consulta e homologação também
     * têm raiz <nfse> na mesma pasta; o que separa é número, situação e código
     * de autenticidade, que só existem depois da autorização.
     *
     * ⚠️ Não exija aqui a `chave_acesso_nfse_nacional`: município que não aderiu
     * ao convênio manda o retorno sem ela, e a prefeitura inteira seria
     * descartada em silêncio.
     */
    protected function ehNfseAutorizadaIpm(\SimpleXMLElement $xml): bool
    {
        // Pelo VALOR, não pela presença: município que marca produção com
        // `<nfse_teste>0</nfse_teste>` seria descartado inteiro.
        $teste = $this->nfseValor($xml, 'nfse_teste');

        return ($teste === null || $teste === '0')
            && $this->nfseValor($xml, 'numero_nfse') !== null
            && $this->nfseValor($xml, 'situacao_codigo_nfse') !== null
            && $this->nfseValor($xml, 'cod_verificador_autenticidade') !== null;
    }

    /**
     * Recibo <retorno> do IPM: cancelamento homologado ou erro do provedor.
     * Nenhum dos dois é documento — o cancelamento só vira a situação da nota
     * já importada, pelo `cod_verificador_autenticidade`.
     *
     * O recibo não traz o CNPJ do prestador, então não dá para criar a nota a
     * partir dele. Cancelamento que chegue antes da nota não se perde: o
     * emissor regrava o XML de `Enviadas` e ele sobe de novo, já cancelado.
     */
    protected function nfseRetornoIpm(\SimpleXMLElement $xml)
    {
        $codVerificacao = $this->nfseValor($xml, 'cod_verificador_autenticidade');
        $situacao       = $this->nfseValor($xml, 'situacao_codigo_nfse');

        if ($codVerificacao !== null && $situacao === '2') {
            // Escopado ao padrão: o `cod_verificacao` do ABRASF é curto e só
            // único dentro do município — sem isso o recibo cancelaria a nota errada.
            $atualizadas = DB::table('nfse_documents')
                ->where('cod_verificacao', '=', $codVerificacao)
                ->where('padrao', '=', 'ipm')
                ->update(['situacao' => 'Cancelada', 'updated_at' => now()]);

            if ($atualizadas === 0) {
                Log::info('NFS-e: recibo de cancelamento sem nota importada.', [
                    'cod_verificacao' => $codVerificacao,
                    'numero'          => $this->nfseValor($xml, 'numero_nfse'),
                ]);
            }
        }

        return response()->json(['msg' => '100'], 200);
    }

    /**
     * IPM/Atende.Net: layout próprio, fora do ABRASF e do Sefin Nacional. Tags
     * snake_case sem namespace, vírgula decimal e datas dd/mm/aaaa.
     *
     * `identidade` = código verificador de autenticidade: é o único campo
     * repetido em todos os retornos da mesma emissão (a nota chega várias vezes,
     * com nomes diferentes) e no recibo de cancelamento. `numero_nfse` não serve
     * — o provedor recicla o número entre homologação e definitiva.
     */
    protected function parseNfseIpm(\SimpleXMLElement $xml): ?array
    {
        // Um arquivo = uma nota. Caindo aqui uma LISTA, só a primeira seria
        // gravada — daí o log, para não perder em silêncio.
        $blocos = $xml->xpath('.//*[local-name()="nf"]');
        if (count($blocos) > 1) {
            Log::warning('NFS-e: XML com mais de uma nota; so a primeira foi importada.', [
                'notas' => count($blocos),
            ]);
        }

        $nf        = $this->nfseNo($xml, 'nf') ?? $xml;
        $prestador = $this->nfseNo($xml, 'prestador');
        $tomador   = $this->nfseNo($xml, 'tomador');

        $cnpj           = $prestador ? $this->nfseValor($prestador, 'cpfcnpj') : null;
        $numero         = $this->nfseValor($nf, 'numero_nfse');
        $codVerificacao = $this->nfseValor($nf, 'cod_verificador_autenticidade');

        if ($cnpj === null || $numero === null || $codVerificacao === null) {
            return null;
        }

        $chave = $this->nfseValor($nf, 'chave_acesso_nfse_nacional');
        [$issue, $mesano] = $this->nfseData($this->nfseValor($nf, 'data_nfse'));

        // O <cidade> do prestador é código INTERNO do provedor; a coluna guarda
        // o IBGE, que é o que os outros padrões gravam — sai da chave nacional.
        $municipio = $chave !== null && strlen($chave) >= 7
            ? substr($chave, 0, 7)
            : ($prestador ? $this->nfseValor($prestador, 'cidade') : null);

        return [
            'padrao'           => 'ipm',
            'cnpj_prestador'   => $cnpj,
            'razao_social'     => null,   // o retorno do IPM não traz o nome do prestador
            'ie'               => $prestador ? $this->nfseValor($prestador, 'inscricao_municipal') : null,
            'cnpj_cpf_tomador' => $tomador ? $this->nfseValor($tomador, 'cpfcnpj') : null,
            'municipio'        => $municipio,
            'numero'           => $numero,
            'serie'            => $this->nfseValor($nf, 'serie_nfse'),
            'cod_verificacao'  => $codVerificacao,
            'chave'            => $chave,
            'identidade'       => 'IPM:' . $codVerificacao,
            'month_year'       => $mesano,
            'issue_dh'         => $issue,
            'situacao'         => $this->situacaoNfseIpm(
                $this->nfseValor($nf, 'situacao_codigo_nfse'),
                $this->nfseValor($nf, 'situacao_descricao_nfse')
            ),
            'protocol'         => null,
            'environment_type' => '1',
            'valor'            => $this->nfseNumero($this->nfseValor($nf, 'valor_total')),
        ];
    }

    /**
     * Situação do IPM -> vocabulário do portal.
     *
     * ⚠️ Os rótulos têm de bater LETRA A LETRA com Documents\Index::statusGroups():
     * o filtro da NFS-e é textual, e string fora do vocabulário some dos chips.
     * Código desconhecido preserva a descrição do provedor em vez de chutar.
     */
    protected function situacaoNfseIpm(?string $codigo, ?string $descricao): string
    {
        return match ((string) $codigo) {
            '1'     => 'Autorizada',      // o provedor chama de "Emitida"
            '2'     => 'Cancelada',
            default => $descricao !== null ? mb_substr($descricao, 0, 40) : 'Situacao ' . $codigo,
        };
    }

    public function printInvoice($id)
    {
        $document = DB::table('documents')->where('id', '=', $id)->first();

        if (is_null($document)) {
            abort(404);
        }

        $file = storage_path('app') . $document->path_xml;

        if (!File::exists($file)) {
            abort(404);
        }

        // Modelo sem DANFE -> 404. Fora do try, senão o catch engole o abort.
        if (!in_array((int) $document->model, [55, 57, 58, 59, 65], true)) {
            abort(404);
        }

        $xml = file_get_contents($file);

        $fontDefault = 'arial';
        $credits = '';
        $pdf = null;

        try {
            switch ($document->model) {
                case 55: // NF-e
                    $danfe = new Danfe($xml);
                    $danfe->debugMode(false);
                    $danfe->setDefaultFont($fontDefault);
                    $danfe->creditsIntegratorFooter($credits);
                    $pdf = $danfe->render();
                    break;

                case 57: // CT-e
                    $dacte = new Dacte($xml);
                    $dacte->debugMode(false);
                    $dacte->printParameters('P', 'A4', 2, 2);
                    $dacte->setDefaultFont($fontDefault);
                    $dacte->creditsIntegratorFooter($credits);
                    $dacte->setDefaultDecimalPlaces(2);
                    $pdf = $dacte->render();
                    break;

                case 58: // MDF-e
                    $damdfe = new Damdfe($xml);
                    $damdfe->debugMode(false);
                    $damdfe->setDefaultFont($fontDefault);
                    $damdfe->creditsIntegratorFooter($credits);
                    $pdf = $damdfe->render();
                    break;
					
				case 59: // Entrada
                    $danfe = new Danfe($xml);
                    $danfe->debugMode(false);
                    $danfe->setDefaultFont($fontDefault);
                    $danfe->creditsIntegratorFooter($credits);
                    $pdf = $danfe->render();
                    break;	

                case 65: // NFC-e
                    $danfce = new Danfce($xml);
                    $danfce->debugMode(false);
                    $danfce->setPaperWidth(80);
                    $danfce->setMargins(2);
                    $danfce->setDefaultFont($fontDefault);
                    $danfce->setOffLineDoublePrint(true);
                    $danfce->creditsIntegratorFooter($credits);
                    $pdf = $danfce->render();
                    break;
            }

            header('Content-Type: application/pdf');
            ob_end_clean();
            echo $pdf;
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Ocorreu um erro durante o processamento.');
        }
    }

    public function printEvent_cte($id)
    {
        $document = EventDocument::with('company')->where('id', '=', $id)->first();

        if (is_null($document)) {
            abort(404);
        }

        $policy = Gate::inspect('access-event-document', $document);

        if ($policy->denied()) {
            abort(403);
        }

        $company = $document->company;

        if (is_null($company)) {
            abort(404);
        }

        $file = storage_path('app') . $document->path_xml;

        if (!File::exists($file)) {
            abort(404);
        }

        $xml = file_get_contents($file);

        $fontDefault = 'arial';
        $credits = '';

        $dadosEmitente = [
            'razao' => $company->corporate_name,
            'logradouro' => $company->public_place,
            'numero' => $company->home_number,
            'complemento' => $company->complement,
            'bairro' => $company->district,
            'CEP' => $company->zip_code,
            'municipio' => $company->county,
            'UF' => $company->uf,
            'telefone' => $company->phone_number,
            'email' => $company->email
        ];

        try {

            $daevento = new Daevento($xml, $dadosEmitente);
            $daevento->debugMode(false);
            $daevento->setDefaultFont($fontDefault);
            $daevento->creditsIntegratorFooter($credits);
            $pdf = $daevento->render();
            header('Content-Type: application/pdf');
            ob_end_clean();

            echo $pdf;

        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Ocorreu um erro durante o processamento.');
        }
    }
    
}
