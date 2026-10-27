<?php

namespace App\Http\Controllers;

use App\Support\AgentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Notas DESCARTADAS no sistema, lidas do banco do ERP. A venda ganhou número e
 * foi jogada fora antes de virar documento: não tem XML e não é evento de
 * inutilização de faixa, então só o ERP sabe dela.
 *
 * ⚠️ O ERP chama isso de "Inutilizada", o mesmo nome do EVENTO FISCAL de
 * inutilização de faixa (esse vive em `disable_documents`). Somar os dois é o
 * que faz os totais não baterem. Situação fora do mapa é ignorada, nunca chutada.
 */
class ErpDiscardController extends Controller
{
    /** Teto de linhas por lote (o agente manda 200). */
    private const MAX_LINHAS = 5000;

    /** SITUACAO do ERP que significa "descartada", por modelo. */
    private const DESCARTE = [
        55 => '5',   // NFE_MASTER
        65 => 'I',   // NFCE_MASTER
    ];

    public function upload(Request $request)
    {
        $rows = $request->input('rows');

        if (! is_array($rows) || $rows === []) {
            return response()->json(['msg' => 'Sem linhas de descarte.'], 422);
        }

        // Teto de linhas: cada linha dispara operação de banco, e sem limite um
        // POST grande esgota o banco num request só.
        if (count($rows) > self::MAX_LINHAS) {
            return response()->json([
                'msg' => 'Lote com ' . count($rows) . ' linhas; o maximo e ' . self::MAX_LINHAS . '.',
            ], 422);
        }

        $gravados = 0;
        $ignorados = 0;

        $permitidos = AgentScope::cnpjsPermitidos($request);

        foreach ($rows as $r) {
            if ($this->gravar(is_array($r) ? $r : [], $permitidos)) {
                $gravados++;
            } else {
                $ignorados++;
            }
        }

        // O agente só lê "msg"; os contadores são para diagnóstico/log.
        return response()->json(['msg' => '100', 'gravados' => $gravados, 'ignorados' => $ignorados]);
    }

    /**
     * @param  array<int,string>|null  $permitidos  CNPJs do dono da chave; null = sem restrição
     */
    protected function gravar(array $r, ?array $permitidos = null): bool
    {
        $modelo = (int) ($r['model'] ?? 0);
        $situacao = trim((string) ($r['situacao'] ?? ''));
        $cnpj = preg_replace('/\D/', '', (string) ($r['cnpj_cpf'] ?? ''));
        $numero = trim((string) ($r['number'] ?? ''));

        // Modelo e situação têm de casar com o mapa; no resto, ignora. Marcar
        // como descarte uma nota autorizada é pior que não mostrar nada.
        if (! isset(self::DESCARTE[$modelo])
            || $situacao !== self::DESCARTE[$modelo]
            || $cnpj === ''
            || $numero === '') {
            return false;
        }

        // Fora das empresas do dono da chave: pula a linha, o lote segue.
        if ($permitidos !== null && ! in_array($cnpj, $permitidos, true)) {
            \Illuminate\Support\Facades\Log::warning(
                "ERP descartes: CNPJ {$cnpj} fora do escopo da chave do agente - linha ignorada."
            );

            return false;
        }

        [$emissao, $mesAno] = $this->data($r['emissao'] ?? null);
        $serie = trim((string) ($r['series'] ?? ''));

        // A NFC-e descartada traz o TEXTO "INUTILIZADA" no lugar da chave.
        $chave = preg_replace('/\D/', '', (string) ($r['key'] ?? ''));
        $chave = strlen($chave) === 44 ? $chave : null;

        $identidade = implode('|', [$cnpj, $modelo, $serie, $numero, $mesAno ?? '']);

        DB::table('discarded_documents')->updateOrInsert(
            ['identidade' => mb_substr($identidade, 0, 191)],
            [
                'cnpj_cpf'         => mb_substr($cnpj, 0, 45),
                'model'            => $modelo,
                'series'           => $serie !== '' ? (int) $serie : null,
                'number'           => (int) $numero,
                'key'              => $chave,
                'issue_dh'         => $emissao,
                'month_year'       => $mesAno,
                'value'            => (float) ($r['valor'] ?? 0),
                'situacao_erp'     => mb_substr($situacao, 0, 4),
                // Sem ambiente de propósito: o ERP não tem tpAmb e o descarte
                // nunca foi à SEFAZ. Gravar '1' afirmaria produção sem prova.
                'environment_type' => null,
                'updated_at'       => now(),
                'created_at'       => now(),
            ]
        );

        return true;
    }

    /** [Y-m-d, Ym]; tolera nulo/formato inválido. */
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
}
