<?php

namespace App\Http\Controllers;

use App\Support\AgentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * NFS-e pelo BANCO DO ERP (NFSE_MASTER). O ERP é a fonte primária da situação:
 * o XML do provedor nem sempre é regravado no cancelamento, e há nota que
 * sequer tem arquivo em disco.
 *
 * ⚠️ Aqui a SITUACAO é NUMÉRICA — o ErpStatusController usa letras. Não
 * reaproveite o mapa de um no outro.
 *
 * O vínculo com a nota é o `cod_verificacao`, estável entre banco, retorno
 * autorizado e recibo de cancelamento.
 */
class ErpNfseController extends Controller
{
    /** Teto de linhas por lote (o agente manda 200). */
    private const MAX_LINHAS = 5000;

    /** NFSE_MASTER.SITUACAO -> vocabulário de Documents\Index::statusGroups(). */
    private const SITUACAO = [
        '2' => 'Autorizada',
        '3' => 'Cancelada',
    ];

    public function upload(Request $request)
    {
        $rows = $request->input('rows');

        if (! is_array($rows) || $rows === []) {
            return response()->json(['msg' => 'Sem linhas de NFS-e.'], 422);
        }

        // Teto de linhas: cada linha dispara operação de banco, e sem limite um
        // POST grande esgota o banco num request só.
        if (count($rows) > self::MAX_LINHAS) {
            return response()->json([
                'msg' => 'Lote com ' . count($rows) . ' linhas; o maximo e ' . self::MAX_LINHAS . '.',
            ], 422);
        }

        $atualizados = 0;
        $criados = 0;
        $ignorados = 0;

        $permitidos = AgentScope::cnpjsPermitidos($request);

        foreach ($rows as $r) {
            match ($this->gravar(is_array($r) ? $r : [], $permitidos)) {
                'atualizado' => $atualizados++,
                'criado'     => $criados++,
                default      => $ignorados++,
            };
        }

        // O agente só lê "msg"; os contadores são para diagnóstico/log.
        return response()->json([
            'msg'         => '100',
            'atualizados' => $atualizados,
            'criados'     => $criados,
            'ignorados'   => $ignorados,
        ]);
    }

    /**
     * Uma linha do lote. Inválida = 'ignorado' (pulada, sem derrubar o lote).
     *
     * @param  array<int,string>|null  $permitidos  CNPJs do dono da chave; null = sem restrição
     */
    protected function gravar(array $r, ?array $permitidos = null): string
    {
        $codVerificacao = trim((string) ($r['cod_verificacao'] ?? ''));
        $protocolo = trim((string) ($r['protocolo'] ?? ''));
        $situacao = trim((string) ($r['situacao'] ?? ''));

        // O protocolo é único por linha do NFSE_MASTER; o CODIGO_VERIFICACAO
        // pode estar sobrescrito pela emissão seguinte (caso das homologações).
        $base = $protocolo !== '' ? $protocolo : $codVerificacao;

        if ($base === '' || ! isset(self::SITUACAO[$situacao])) {
            return 'ignorado';
        }

        // Em produção o protocolo É o código de verificação, então a identidade
        // bate com a do parser XML e os dois canais convergem.
        $identidade = 'IPM:' . mb_substr($base, 0, 187);
        $rotulo = self::SITUACAO[$situacao];

        // Protocolo diferente do código de autenticidade = homologação.
        $homologacao = $protocolo !== '' && $codVerificacao !== '' && $protocolo !== $codVerificacao;

        $existente = DB::table('nfse_documents')->where('identidade', '=', $identidade)->first();

        // Aqui o escopo é conferido contra o dono da linha JÁ GRAVADA: a
        // identidade é o protocolo, previsível, e o payload ainda não foi lido.
        if ($existente && $permitidos !== null
            && ! in_array(preg_replace('/\D/', '', (string) $existente->cnpj_prestador), $permitidos, true)) {
            Log::warning(
                "ERP NFS-e: identidade {$identidade} pertence a CNPJ fora do escopo da chave - linha ignorada."
            );

            return 'ignorado';
        }

        if ($existente) {
            DB::table('nfse_documents')
                ->where('identidade', '=', $identidade)
                ->update([
                    'situacao'        => $rotulo,
                    'situacao_source' => 'erp',
                    'updated_at'      => now(),
                ]);

            return 'atualizado';
        }

        // Nota que nunca chegou por XML: entra sem `path_xml`, então só ela
        // fica sem download.
        $cnpj = preg_replace('/\D/', '', (string) ($r['cnpj_prestador'] ?? ''));
        if ($cnpj === '') {
            return 'ignorado';
        }

        // Fora das empresas do dono da chave: PULA a linha (ver AgentScope).
        if ($permitidos !== null && ! in_array($cnpj, $permitidos, true)) {
            Log::warning("ERP NFS-e: CNPJ {$cnpj} fora do escopo da chave do agente - linha ignorada.");

            return 'ignorado';
        }

        [$emissao, $mesAno] = $this->data($r['emissao'] ?? null);
        $chave = preg_replace('/\D/', '', (string) ($r['chave'] ?? ''));

        DB::table('nfse_documents')->insert([
            'padrao'           => 'ipm',
            'cnpj_prestador'   => mb_substr($cnpj, 0, 45),
            'cnpj_cpf_tomador' => $this->texto($r['cnpj_cpf_tomador'] ?? null, 45),
            'municipio'        => $chave !== '' && strlen($chave) >= 7 ? substr($chave, 0, 7) : null,
            'numero'           => $this->texto($r['numero'] ?? null, 60),
            'serie'            => $this->texto($r['serie'] ?? null, 20),
            // O código DAQUELA emissão: é ele que autentica no site da prefeitura.
            'cod_verificacao'  => mb_substr($base, 0, 60),
            'chave'            => $chave !== '' ? mb_substr($chave, 0, 60) : null,
            'identidade'       => $identidade,
            'month_year'       => $mesAno,
            'issue_dh'         => $emissao,
            'situacao'         => $rotulo,
            'situacao_source'  => 'erp',
            'environment_type' => $homologacao ? '2' : '1',
            'valor'            => (float) ($r['valor'] ?? 0),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return 'criado';
    }

    /** [Y-m-d, Ym] a partir do que o ERP mandar; tolera nulo/formato inválido. */
    protected function data($valor): array
    {
        if (blank($valor)) {
            return [null, null];
        }

        try {
            $d = \Carbon\Carbon::parse($valor);
        } catch (\Throwable) {
            return [null, null];
        }

        return [$d->format('Y-m-d'), $d->format('Ym')];
    }

    protected function texto($valor, int $max): ?string
    {
        $v = trim((string) ($valor ?? ''));

        return $v !== '' ? mb_substr($v, 0, $max) : null;
    }
}
