<?php

namespace App\Http\Controllers;

use App\Models\FiscalStatus;
use App\Support\CancellationStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Canal de status fiscal por XML (NF-e/NFC-e/CT-e/MDF-e). Recebe os envelopes
 * que carregam o cStat POR NOTA, que a rota de documentos nunca vê — rejeição
 * não gera procNFe e mudança de situação só aparece na consulta:
 *   - NN-pro-lot.xml  -> retEnviNFe / retConsReciNFe (e os equivalentes CT-e/MDF-e)
 *   - {chave}-sit.xml -> retConsSit* (situação ATUAL da nota)
 *
 * Mantém `fiscal_status` (1 linha por chave, vence o dhRecbto mais novo) e
 * espelha em `documents.status_xml` quando a nota já foi importada.
 */
class StatusController extends Controller
{
    public function upload(Request $request)
    {
        // Quem autentica é o middleware CheckSystem.
        if (! $request->attributes->getBoolean('agent_authenticated')) {
            return response()->json(['msg' => 'Voce nao tem permissao.'], 403);
        }

        if (! ($request->hasFile('file') && $request->file('file')->isValid())) {
            return $this->invalidUploadResponse();
        }

        $xml = @simplexml_load_file($request->file('file')->getRealPath());
        if ($xml === false) {
            return $this->invalidUploadResponse();
        }

        $records = match ($xml->getName()) {
            'retEnviNFe', 'retConsReciNFe',
            'retCTe', 'retEnviCTe', 'retConsReciCTe',
            'retMDFe', 'retEnviMDFe', 'retConsReciMDFe' => $this->recordsFromLote($xml),
            'retConsSitNFe', 'retConsSitCTe', 'retConsSitMDFe' =>
                $this->recordsFromSit($xml, (string) $request->file('file')->getClientOriginalName()),
            default => null,
        };

        if ($records === null) {
            return $this->invalidUploadResponse();
        }

        foreach ($records as $record) {
            $this->upsert($record);
        }

        // Layout reconhecido responde sempre 100, mesmo com 0 registros: só com
        // msg 100 o agente marca o arquivo como enviado.
        return response()->json(['msg' => '100'], 200);
    }

    protected function invalidUploadResponse($message = 'Arquivo XML invalido ou ausente.')
    {
        return response()->json(['msg' => $message], 422);
    }

    /**
     * Envelopes de lote: o cStat do topo é do LOTE e nunca vira registro — o
     * que vale é o protocolo por nota (0..N).
     */
    protected function recordsFromLote(\SimpleXMLElement $xml): array
    {
        $records = [];

        foreach (['protNFe', 'protCTe', 'protMDFe'] as $protocolo) {
            foreach ($xml->{$protocolo} as $prot) {
                $inf = $prot->infProt;
                $key = (string) $inf->chNFe;
                if ($key === '') {
                    $key = (string) $inf->chCTe;
                }
                if ($key === '') {
                    $key = (string) $inf->chMDFe;
                }

                $records[] = [
                    'key'              => $key,
                    'cstat'            => (int) $inf->cStat,
                    'x_motivo'         => (string) $inf->xMotivo,
                    'n_prot'           => (string) $inf->nProt,
                    'dh_recbto'        => (string) $inf->dhRecbto,
                    'environment_type' => (string) $inf->tpAmb,
                    'source'           => 'pro-lot',
                ];
            }
        }

        return $records;
    }

    /**
     * Envelopes de consulta: a situação ATUAL é o cStat do topo. NF-e ecoa
     * chave e data no topo; CT-e/MDF-e não, então a chave cai para o protocolo
     * embutido e, por último, para o prefixo de 44 dígitos do nome do arquivo.
     */
    protected function recordsFromSit(\SimpleXMLElement $xml, string $filename): array
    {
        $key = (string) $xml->chNFe;
        if ($key === '') {
            $key = (string) $xml->chCTe;
        }
        if ($key === '') {
            $key = (string) $xml->chMDFe;
        }
        if ($key === '') {
            $key = (string) ($xml->protNFe->infProt->chNFe
                ?? $xml->protCTe->infProt->chCTe
                ?? $xml->protMDFe->infProt->chMDFe
                ?? '');
        }
        if ($key === '' && preg_match('/^(\d{44})/', $filename, $m)) {
            $key = $m[1];
        }
        if ($key === '') {
            return [];
        }

        $dh = (string) $xml->dhRecbto;
        if ($dh === '') {
            $dh = (string) ($xml->protNFe->infProt->dhRecbto
                ?? $xml->protCTe->infProt->dhRecbto
                ?? $xml->protMDFe->infProt->dhRecbto
                ?? '');
        }

        return [[
            'key'              => $key,
            'cstat'            => (int) $xml->cStat,
            'x_motivo'         => (string) $xml->xMotivo,
            'n_prot'           => (string) ($xml->protNFe->infProt->nProt
                ?? $xml->protCTe->infProt->nProt
                ?? $xml->protMDFe->infProt->nProt
                ?? ''),
            'dh_recbto'        => $dh,
            'environment_type' => (string) $xml->tpAmb,
            'source'           => 'sit',
        ]];
    }

    /**
     * Upsert por chave, com precedência por dhRecbto: rejeição antiga não
     * regride autorização posterior. Registro sem data só preenche linha nova;
     * registro inválido é pulado, e o arquivo continua respondendo 100.
     */
    protected function upsert(array $r): void
    {
        $key = $r['key'];
        if (! preg_match('/^\d{44}$/', $key)) {
            return;
        }

        $model = (int) substr($key, 20, 2);
        if (! in_array($model, [55, 57, 58, 65, 67], true)) {
            return; // modelos do canal (NF-e, CT-e, MDF-e, NFC-e, CT-e OS)
        }

        $dh = null;
        if ($r['dh_recbto'] !== '') {
            try {
                $dh = Carbon::parse($r['dh_recbto'])->setTimezone(config('app.timezone'));
            } catch (\Throwable $e) {
                $dh = null;
            }
        }

        $current = FiscalStatus::where('key', $key)->first();

        // Linha vinda do BANCO DO ERP é autoridade: o canal XML não a
        // sobrescreve, nem roda a ponte syncDocuments para ela.
        if ($current && $current->source === 'erp') {
            return;
        }

        if ($current && ($dh === null || ($current->dh_recbto !== null && $dh->lt($current->dh_recbto)))) {
            return; // o guardado é mais novo (ou o novo não tem data): mantém
        }

        $values = [
            'model'            => $model,
            'cnpj_emit'        => substr($key, 6, 14),
            'series'           => (int) substr($key, 22, 3),
            'number'           => (int) substr($key, 25, 9),
            'cstat'            => $r['cstat'],
            'category'         => FiscalStatus::categoryFor($r['cstat']),
            'x_motivo'         => $r['x_motivo'] !== '' ? mb_substr($r['x_motivo'], 0, 255) : null,
            'n_prot'           => $r['n_prot'] !== '' ? $r['n_prot'] : null,
            'dh_recbto'        => $dh,
            'environment_type' => $r['environment_type'] !== '' ? $r['environment_type'] : null,
            'source'           => $r['source'],
        ];

        if ($current) {
            $current->update($values);
        } else {
            FiscalStatus::create($values + ['key' => $key]);
        }

        $this->syncDocuments($key, (int) $r['cstat']);
    }

    /**
     * Ponte para `documents`: a nota já importada acompanha a mudança de status.
     * Só o conjunto conhecido do import entra ali — rejeição nunca suja um
     * documento autorizado, e a verdade completa fica em `fiscal_status`.
     * Atualiza TODAS as linhas da chave (saída e entrada da mesma nota).
     */
    protected function syncDocuments(string $key, int $cstat): void
    {
        $map = [
            100 => 100, 150 => 100,
            101 => 101, 135 => 101, 155 => 101, 151 => 101,
            110 => 110, 205 => 110, 301 => 110, 302 => 110, 303 => 110,
            132 => 132, // Encerrado (MDF-e)
        ];

        if (! array_key_exists($cstat, $map)) {
            return;
        }

        // Cancelamento prevalece: consulta antiga não pode "descancelar" a nota.
        $final = CancellationStatus::resolve($key, $map[$cstat]);

        DB::table('documents')->where('key', $key)->update(['status_xml' => $final]);
    }
}
