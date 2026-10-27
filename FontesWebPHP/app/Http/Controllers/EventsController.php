<?php

namespace App\Http\Controllers;

use App\Support\AgentScope;
use App\Support\StoragePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use App\Models\EventDocument;
use NFePHP\DA\NFe\Daevento;
use Exception;

class EventsController extends Controller
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
     * Grava (regrava) de forma atômica: delete-then-insert pela chave de dedup,
     * numa transação. Usado por eventos e inutilizações.
     */
    protected function salvarComDedup(string $tabela, array $where, array $campos): void
    {
        DB::transaction(function () use ($tabela, $where, $campos) {
            DB::table($tabela)->where($where)->delete();
            DB::table($tabela)->insert($campos);
        });
    }

    /**
     * Cancelamento homologado (110111/110112 com cStat 101/135/155) vira a nota
     * para 101. CC-e e evento rejeitado não mexem nela; nota que ainda não
     * chegou é no-op, porque a ingestão cobre a ordem invertida.
     */
    protected function syncCancelledDocument($chave, $tipoEvento, $statusEvento): void
    {
        if (!in_array((string) $tipoEvento, ['110111', '110112'], true)) {
            return;
        }

        if (!in_array((int) $statusEvento, [101, 135, 155], true)) {
            return;
        }

        DB::table('documents')
            ->where('key', (string) $chave)
            ->update(['status_xml' => 101]);
    }

    public function cancelamento_cce(Request $request)
    {
        if (!($request->hasFile('file') && $request->file('file')->isValid())) {
            return $this->invalidUploadResponse();
        }

        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file = $request->file('file');
            if ($request->hasFile('file') && $request->file('file')->isValid()) {

                $extension = $file->extension();
                $nameFile = $file->getClientOriginalName();
                $size = $file->getSize();

                $xml = @simplexml_load_file($file->getRealPath());
                if ($xml === false) {
                    return $this->invalidUploadResponse('Nao foi possivel ler o XML enviado.');
                }

                // XML fora do layout -> 422 (em vez de 500).
                if (!isset($xml->evento[0]->infEvento[0], $xml->retEvento[0]->infEvento)) {
                    return $this->invalidUploadResponse('XML fora do layout esperado (evento NF-e/NFC-e).');
                }

                $tipo_ambiente    = $xml->evento[0]->infEvento[0]->tpAmb;
                $cnpj             = $xml->evento[0]->infEvento[0]->CNPJ;
                // So grava nos CNPJs vinculados ao dono da chave (AgentScope).
                if (! AgentScope::permite($request, $cnpj)) {
                    return AgentScope::recusa($cnpj);
                }

                $chave_nfe        = $xml->evento[0]->infEvento[0]->chNFe;
                $dh_evento        = $xml->evento[0]->infEvento[0]->dhEvento;
                $tipo_evento      = $xml->evento[0]->infEvento[0]->tpEvento;
                $numero_evento    = $xml->evento[0]->infEvento[0]->nSeqEvento;

                $data = str_replace('/', '-', $dh_evento);
                $mesano    = date('Ym', strtotime($data));
                $dh_evento  = date('Y-m-d', strtotime($dh_evento));

                // Rótulo vem do tpEvento, não da pasta: o agente varre CC-e,
                // manifestação e pastas de transição, e tudo chega nesta rota.
                // xJust/xCorrecao são opcionais — leitura null-safe.
                $desc_evento      = (string) ($xml->evento[0]->infEvento[0]->detEvento->descEvento ?? '');
                $numero_protocolo = $xml->retEvento[0]->infEvento->nProt;
                $status_evento    = $xml->retEvento[0]->infEvento->cStat;
                $tipo             = (string) $tipo_evento;

                if ($tipo === '110110') {
                    $correcao      = (string) ($xml->evento[0]->infEvento[0]->detEvento->xCorrecao ?? '');
                    $justificativa = '';
                    $doc = 'CCe';
                } else {
                    $correcao      = '';
                    $justificativa = (string) ($xml->evento[0]->infEvento[0]->detEvento->xJust ?? '');

                    if (in_array($tipo, ['110111', '110112'], true)) {
                        $doc = 'Cancelamento';
                    } elseif (in_array($tipo, ['210200', '210210', '210220', '210240'], true)) {
                        $doc = 'Manifestacao';
                    } else {
                        $doc = 'Evento';
                    }
                }

                $mod = substr($chave_nfe, 20, 2);

                $nameFile = "{$nameFile}";

                $upload = $file->storeAs('docs', $nameFile);
                $url = Storage::url($upload);

                // $xml->load($url);

                // Caminho definitivo do arquivo no Storage
                $url_new = StoragePath::montar('/docs/eventos', $cnpj, $mod, $doc, $mesano, StoragePath::arquivo($nameFile));

                if (Storage::exists($url_new)) {
                    Storage::delete($url_new);
                }

                //Storage::delete($upload);
                Storage::move($upload, $url_new);

                $campos = [
                    'environment_type' => $tipo_ambiente,
                    'cnpj' => $cnpj,
                    'model' => $mod,
                    'nfe_key' => $chave_nfe,
                    'event_dh' => $dh_evento,
                    'event_type' => $tipo_evento,
                    'event_number' => $numero_evento,
                    'event_desc' => $desc_evento,
                    'protocol_number' => $numero_protocolo,
                    'justification' => mb_strtoupper($justificativa),
                    'event_status' => $status_evento,
                    'correction' => $correcao,
                    'size' => $size,
                    'path_xml' => $url_new
                ];

                // Reenvio do mesmo evento: apaga a linha antiga antes de gravar
                try {
                    $this->salvarComDedup('event_documents',
                        [['nfe_key', '=', $chave_nfe], ['protocol_number', '=', $numero_protocolo]], $campos);
                } catch (\Throwable $e) {
                    report($e);

                    return response()->json([
                        'msg' => 'Nao foi possivel salvar o evento.'
                    ], 500);
                }

                $this->syncCancelledDocument($chave_nfe, $tipo_evento, $status_evento);

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
        }
    }

    public function cancelamento_cce_cte(Request $request)
    {
        if (!($request->hasFile('file') && $request->file('file')->isValid())) {
            return $this->invalidUploadResponse();
        }

        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file = $request->file('file');
            if ($request->hasFile('file') && $request->file('file')->isValid()) {

                $extension = $file->extension();
                $nameFile = $file->getClientOriginalName();
                $size = $file->getSize();

                $xml = @simplexml_load_file($file->getRealPath());
                if ($xml === false) {
                    return $this->invalidUploadResponse('Nao foi possivel ler o XML enviado.');
                }

                // XML fora do layout -> 422 (em vez de 500).
                if (!isset($xml->eventoCTe[0]->infEvento[0], $xml->retEventoCTe[0]->infEvento)) {
                    return $this->invalidUploadResponse('XML fora do layout esperado (evento CT-e).');
                }

                $tipo_ambiente    = $xml->eventoCTe[0]->infEvento[0]->tpAmb;
                $cnpj             = $xml->eventoCTe[0]->infEvento[0]->CNPJ;
                // So grava nos CNPJs vinculados ao dono da chave (AgentScope).
                if (! AgentScope::permite($request, $cnpj)) {
                    return AgentScope::recusa($cnpj);
                }

                $chave_cte        = $xml->eventoCTe[0]->infEvento[0]->chCTe;
                $dh_evento        = $xml->eventoCTe[0]->infEvento[0]->dhEvento;
                $tipo_evento      = $xml->eventoCTe[0]->infEvento[0]->tpEvento;
                $numero_evento    = $xml->eventoCTe[0]->infEvento[0]->nSeqEvento;

                $data = str_replace('/', '-', $dh_evento);
                $mesano    = date('Ym', strtotime($data));
                $dh_evento  = date('Y-m-d', strtotime($dh_evento));

                // No CT-e o detEvento embrulha o conteúdo num nó interno, mas
                // alguns emissores põem direto. Lê dos dois jeitos, null-safe.
                $detEvento = $xml->eventoCTe[0]->infEvento[0]->detEvento;
                $detInterno = null;
                foreach ($detEvento->children() as $filho) {
                    $detInterno = $filho;
                    break;
                }
                $origem = is_null($detInterno) ? $detEvento : $detInterno;

                $desc_evento      = (string) ($origem->descEvento ?? '');
                $numero_protocolo = $xml->retEventoCTe[0]->infEvento->nProt;
                $status_evento    = $xml->retEventoCTe[0]->infEvento->cStat;
                $tipo             = (string) $tipo_evento;

                if ($tipo === '110110') {
                    $correcao      = (string) ($origem->xCorrecao ?? '');
                    $justificativa = '';
                    $doc = 'Carta de correção';
                } else {
                    $correcao      = '';
                    $justificativa = (string) ($origem->xJust ?? '');
                    $doc = $tipo === '110111' ? 'Cancelamento' : 'Evento';
                }

                $mod = substr($chave_cte, 20, 2);

                $nameFile = "{$nameFile}";

                $upload = $file->storeAs('docs', $nameFile);
                $url = Storage::url($upload);

                // $xml->load($url);

                // Caminho definitivo do arquivo no Storage
                $url_new = StoragePath::montar('/docs/eventos', $cnpj, $mod, $doc, $mesano, StoragePath::arquivo($nameFile));

                if (Storage::exists($url_new)) {
                    Storage::delete($url_new);
                }

                //Storage::delete($upload);
                Storage::move($upload, $url_new);

                $campos = [
                    'environment_type' => $tipo_ambiente,
                    'cnpj' => $cnpj,
                    'model' => $mod,
                    'nfe_key' => $chave_cte,
                    'event_dh' => $dh_evento,
                    'event_type' => $tipo_evento,
                    'event_number' => $numero_evento,
                    'event_desc' => $doc,
                    'protocol_number' => $numero_protocolo,
                    'justification' => mb_strtoupper($justificativa),
                    'event_status' => $status_evento,
                    'correction' => $correcao,
                    'size' => $size,
                    'path_xml' => $url_new
                ];

                // Reenvio do mesmo evento: apaga a linha antiga antes de gravar
                try {
                    $this->salvarComDedup('event_documents',
                        [['nfe_key', '=', $chave_cte], ['protocol_number', '=', $numero_protocolo]], $campos);
                } catch (\Throwable $e) {
                    report($e);

                    return response()->json([
                        'msg' => 'Nao foi possivel salvar o evento.'
                    ], 500);
                }

                $this->syncCancelledDocument($chave_cte, $tipo_evento, $status_evento);

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
        }
    }

    public function inutilizacao_nfenfce(Request $request)
    {
        if (!($request->hasFile('file') && $request->file('file')->isValid())) {
            return $this->invalidUploadResponse();
        }

        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file = $request->file('file');
            if ($request->hasFile('file') && $request->file('file')->isValid()) {

                $extension = $file->extension();
                $nameFile = $file->getClientOriginalName();
                $size = $file->getSize();

                $xml = @simplexml_load_file($file->getRealPath());
                if ($xml === false) {
                    return $this->invalidUploadResponse('Nao foi possivel ler o XML enviado.');
                }

                // XML fora do layout -> 422 (em vez de 500).
                if (!isset($xml->inutNFe[0]->infInut[0], $xml->retInutNFe[0]->infInut[0])) {
                    return $this->invalidUploadResponse('XML fora do layout esperado (inutilizacao NF-e/NFC-e).');
                }

                $tipo_ambiente      = $xml->inutNFe[0]->infInut[0]->tpAmb;
                $dh_evento          = $xml->retInutNFe[0]->infInut[0]->dhRecbto;
                $servico            = $xml->inutNFe[0]->infInut[0]->xServ;
                $uf                 = $xml->inutNFe[0]->infInut[0]->cUF;
                $ano                = $xml->inutNFe[0]->infInut[0]->ano;
                $cnpj               = $xml->inutNFe[0]->infInut[0]->CNPJ;
                // So grava nos CNPJs vinculados ao dono da chave (AgentScope).
                if (! AgentScope::permite($request, $cnpj)) {
                    return AgentScope::recusa($cnpj);
                }

                $modelo             = $xml->inutNFe[0]->infInut[0]->mod;
                $serie              = $xml->inutNFe[0]->infInut[0]->serie;
                $numero_inicio      = $xml->inutNFe[0]->infInut[0]->nNFIni;
                $numero_fim         = $xml->inutNFe[0]->infInut[0]->nNFFin;
                $justificativa      = $xml->inutNFe[0]->infInut[0]->xJust;
                $status_evento      = $xml->retInutNFe[0]->infInut[0]->cStat;
                $numero_protocolo   = $xml->retInutNFe[0]->infInut[0]->nProt;

                $data = str_replace('/', '-', $dh_evento);
                $mesano    = date('Ym', strtotime($data));
                $dh_evento  = date('Y-m-d', strtotime($dh_evento));

                $nameFile = "{$nameFile}";

                $upload = $file->storeAs('docs', $nameFile);
                $url = Storage::url($upload);

                //$xml->load($url);

                // Caminho definitivo do arquivo no Storage
                $url_new = StoragePath::montar('/docs/eventos', $cnpj, $modelo, 'Inutilizacao', $mesano, StoragePath::arquivo($nameFile));

                if (Storage::exists($url_new)) {
                    Storage::delete($url_new);
                }

                //Storage::delete($upload);
                Storage::move($upload, $url_new);

                $campos = [
                    'environment_type' => $tipo_ambiente,
                    'event_dh' => $dh_evento,
                    'service' => $servico,
                    'uf' => $uf,
                    'year' => $ano,
                    'cnpj' => $cnpj,
                    'model' => $modelo,
                    'series' => $serie,
                    'number_start' => $numero_inicio,
                    'number_end' => $numero_fim,
                    'justification' => mb_strtoupper($justificativa),
                    'event_status' => $status_evento,
                    'protocol_number' => $numero_protocolo,
                    'size' => $size,
                    'path_xml' => $url_new
                ];

                // Reenvio do mesmo evento: apaga a linha antiga antes de gravar
                try {
                    $this->salvarComDedup('disable_documents',
                        [['cnpj', '=', $cnpj], ['protocol_number', '=', $numero_protocolo]], $campos);
                } catch (\Throwable $e) {
                    report($e);

                    return response()->json([
                        'msg' => 'Nao foi possivel salvar o evento.'
                    ], 500);
                }

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
        }
    }
    public function inutilizacao_cte(Request $request)
    {
        if (!($request->hasFile('file') && $request->file('file')->isValid())) {
            return $this->invalidUploadResponse();
        }

        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file = $request->file('file');
            if ($request->hasFile('file') && $request->file('file')->isValid()) {

                $extension = $file->extension();
                $nameFile = $file->getClientOriginalName();
                $size = $file->getSize();

                $xml = @simplexml_load_file($file->getRealPath());
                if ($xml === false) {
                    return $this->invalidUploadResponse('Nao foi possivel ler o XML enviado.');
                }

                // XML fora do layout -> 422 (em vez de 500).
                if (!isset($xml->inutCTe[0]->infInut[0], $xml->retInutCTe[0]->infInut[0])) {
                    return $this->invalidUploadResponse('XML fora do layout esperado (inutilizacao CT-e).');
                }

                $tipo_ambiente      = $xml->inutCTe[0]->infInut[0]->tpAmb;
                $dh_evento          = $xml->retInutCTe[0]->infInut[0]->dhRecbto;
                $servico            = $xml->inutCTe[0]->infInut[0]->xServ;
                $uf                 = $xml->inutCTe[0]->infInut[0]->cUF;
                $ano                = $xml->inutCTe[0]->infInut[0]->ano;
                $cnpj               = $xml->inutCTe[0]->infInut[0]->CNPJ;
                // So grava nos CNPJs vinculados ao dono da chave (AgentScope).
                if (! AgentScope::permite($request, $cnpj)) {
                    return AgentScope::recusa($cnpj);
                }

                $modelo             = $xml->inutCTe[0]->infInut[0]->mod;
                $serie              = $xml->inutCTe[0]->infInut[0]->serie;
                $numero_inicio      = $xml->inutCTe[0]->infInut[0]->nCTIni;
                $numero_fim         = $xml->inutCTe[0]->infInut[0]->nCTFin;
                $justificativa      = $xml->inutCTe[0]->infInut[0]->xJust;
                $status_evento      = $xml->retInutCTe[0]->infInut[0]->cStat;
                $numero_protocolo   = $xml->retInutCTe[0]->infInut[0]->nProt;

                $data = str_replace('/', '-', $dh_evento);
                $mesano    = date('Ym', strtotime($data));
                $dh_evento  = date('Y-m-d', strtotime($dh_evento));

                $nameFile = "{$nameFile}";

                $upload = $file->storeAs('docs', $nameFile);
                $url = Storage::url($upload);

                //$xml->load($url);

                // Caminho definitivo do arquivo no Storage
                $url_new = StoragePath::montar('/docs/eventos', $cnpj, $modelo, 'Inutilizacao', $mesano, StoragePath::arquivo($nameFile));

                if (Storage::exists($url_new)) {
                    Storage::delete($url_new);
                }

                //Storage::delete($upload);
                Storage::move($upload, $url_new);

                $campos = [
                    'environment_type' => $tipo_ambiente,
                    'event_dh' => $dh_evento,
                    'service' => $servico,
                    'uf' => $uf,
                    'year' => $ano,
                    'cnpj' => $cnpj,
                    'model' => $modelo,
                    'series' => $serie,
                    'number_start' => $numero_inicio,
                    'number_end' => $numero_fim,
                    'justification' => mb_strtoupper($justificativa),
                    'event_status' => $status_evento,
                    'protocol_number' => $numero_protocolo,
                    'size' => $size,
                    'path_xml' => $url_new
                ];

                // Reenvio do mesmo evento: apaga a linha antiga antes de gravar
                try {
                    $this->salvarComDedup('disable_documents',
                        [['cnpj', '=', $cnpj], ['protocol_number', '=', $numero_protocolo]], $campos);
                } catch (\Throwable $e) {
                    report($e);

                    return response()->json([
                        'msg' => 'Nao foi possivel salvar o evento.'
                    ], 500);
                }

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
        }
    }

    /**
     * Eventos de MDF-e: encerramento (110112) vira a nota para 132 e
     * cancelamento (110111) para 101; os demais só registram.
     */
    public function eventos_mdfe(Request $request)
    {
        if (!($request->hasFile('file') && $request->file('file')->isValid())) {
            return $this->invalidUploadResponse();
        }

        $file = $request->file('file');
        $nameFile = $file->getClientOriginalName();
        $size = $file->getSize();

        $xml = @simplexml_load_file($file->getRealPath());
        if ($xml === false) {
            return $this->invalidUploadResponse('Nao foi possivel ler o XML enviado.');
        }

        if (!isset($xml->eventoMDFe[0]->infEvento[0], $xml->retEventoMDFe[0]->infEvento)) {
            return $this->invalidUploadResponse('XML fora do layout esperado (evento MDF-e).');
        }

        $infEvento = $xml->eventoMDFe[0]->infEvento[0];

        $tipo_ambiente = $infEvento->tpAmb;
        $cnpj          = $infEvento->CNPJ;
        // So grava nos CNPJs vinculados ao dono da chave (AgentScope).
        if (! AgentScope::permite($request, $cnpj)) {
            return AgentScope::recusa($cnpj);
        }

        $chave         = $infEvento->chMDFe;
        $dh_evento     = $infEvento->dhEvento;
        $tipo_evento   = (string) $infEvento->tpEvento;
        $numero_evento = $infEvento->nSeqEvento;

        // detEvento embrulha o conteúdo (evCancMDFe/evEncMDFe/...); lê null-safe.
        $detInterno = null;
        foreach ($infEvento->detEvento->children() as $filho) {
            $detInterno = $filho;
            break;
        }
        $origem = is_null($detInterno) ? $infEvento->detEvento : $detInterno;

        $desc_evento      = (string) ($origem->descEvento ?? '');
        $justificativa    = (string) ($origem->xJust ?? '');
        $numero_protocolo = $xml->retEventoMDFe[0]->infEvento->nProt;
        $status_evento    = $xml->retEventoMDFe[0]->infEvento->cStat;

        $data = str_replace('/', '-', $dh_evento);
        $mesano = date('Ym', strtotime($data));
        $dh_evento = date('Y-m-d', strtotime($dh_evento));

        $mod = substr($chave, 20, 2);

        $doc = match ($tipo_evento) {
            '110111' => 'Cancelamento',
            '110112' => 'Encerramento',
            default => 'Evento',
        };

        $upload = $file->storeAs('docs', $nameFile);

        $url_new = StoragePath::montar('/docs/eventos', $cnpj, $mod, $doc, $mesano, StoragePath::arquivo($nameFile));

        if (Storage::exists($url_new)) {
            Storage::delete($url_new);
        }

        Storage::move($upload, $url_new);

        $campos = [
            'environment_type' => $tipo_ambiente,
            'cnpj' => $cnpj,
            'model' => $mod,
            'nfe_key' => $chave,
            'event_dh' => $dh_evento,
            'event_type' => $tipo_evento,
            'event_number' => $numero_evento,
            'event_desc' => $desc_evento,
            'protocol_number' => $numero_protocolo,
            'justification' => mb_strtoupper($justificativa),
            'event_status' => $status_evento,
            'correction' => '',
            'size' => $size,
            'path_xml' => $url_new,
        ];

        try {
            $this->salvarComDedup('event_documents',
                [['nfe_key', '=', (string) $chave], ['protocol_number', '=', (string) $numero_protocolo]], $campos);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'msg' => 'Nao foi possivel salvar o evento.'
            ], 500);
        }

        // Reflexo no documento: encerramento homologado -> 132; cancelamento -> 101.
        if (in_array((int) $status_evento, [135, 136], true)) {
            if ($tipo_evento === '110111') {
                DB::table('documents')->where('key', (string) $chave)->update(['status_xml' => 101]);
            } elseif ($tipo_evento === '110112') {
                DB::table('documents')->where('key', (string) $chave)->update(['status_xml' => 132]);
            }
        }

        if (!$upload) {
            return response()->json([
                'msg' => 'Erro ao fazer upload.'
            ], 200);
        }

        return response()->json([
            'msg' => '100'
        ], 200);
    }

    public function printEvent_nfenfce($id)
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
        $credits = 'Loja do Desenvolvedor';

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
