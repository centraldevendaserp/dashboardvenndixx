<?php

namespace App\Http\Controllers;

use App\Models\FiscalStatus;
use App\Support\AgentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Canal de status pelo BANCO DO ERP. O agente lê NFE_MASTER/NFCE_MASTER e manda
 * a SITUACAO crua em JSON; o mapa mora aqui, não no Delphi — mudar o mapa é
 * deploy da web, não recompilar o agente.
 *
 * ⚠️ O domínio de SITUACAO é POR TABELA (NF-e usa números, NFC-e usa letras) e
 * o mesmo símbolo pode significar coisas diferentes. Nunca reaproveite o mapa
 * de um modelo no outro. Situação fora do mapa é ignorada e logada, nunca
 * chutada.
 *
 * `source='erp'` marca a linha como autoridade: o canal XML não a sobrescreve.
 */
class ErpStatusController extends Controller
{
    /** Teto de linhas por lote (o agente manda 200). */
    private const MAX_LINHAS = 5000;

    /**
     * Letras aceitas nos DOIS modelos: T/C/R/O querem dizer o mesmo nas duas
     * tabelas. Já os NÚMEROS são exclusivos da NF-e — não os reaproveite.
     */
    private const LETRAS = [
        'T' => 'autorizada',
        'C' => 'cancelada',
        'R' => FiscalStatus::CATEGORY_REJEITADA,
        'O' => FiscalStatus::CATEGORY_CONTINGENCIA,
    ];

    /** SITUACAO -> categoria, POR MODELO da chave (dígitos 21-22). */
    private const CATEGORIA = [
        55 => self::LETRAS + [   // NFE_MASTER: numeros
            '2' => 'autorizada',
            '3' => 'cancelada',
        ],
        65 => self::LETRAS + [   // NFCE_MASTER: letras
            'D' => FiscalStatus::CATEGORY_DUPLICIDADE,
        ],
    ];

    /**
     * Situações conhecidas que não viram status fiscal (log só em info):
     * "Aberta/Gravada" nunca foi à SEFAZ e "Inutilizada" no ERP é venda
     * descartada, que tem canal próprio (ErpDiscardController).
     */
    private const IGNORADAS = [
        55 => ['1' => 'Aberta (nunca transmitida)', '5' => 'Inutilizada (canal de descartes)'],
        65 => ['G' => 'Gravada (nunca transmitida)', 'I' => 'Inutilizada (canal de descartes)'],
    ];

    public function upload(Request $request)
    {
        $rows = $request->input('rows');

        if (! is_array($rows) || $rows === []) {
            return response()->json(['msg' => 'Sem linhas de status.'], 422);
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
     * Uma linha do lote. Inválida = false (pulada e contada, sem derrubar o lote).
     *
     * @param  array<int,string>|null  $permitidos  CNPJs do dono da chave; null = sem restrição
     */
    protected function gravar(array $r, ?array $permitidos = null): bool
    {
        $chave = (string) ($r['chave'] ?? '');
        $situacao = strtoupper((string) ($r['situacao'] ?? ''));

        if (preg_match('/\A\d{44}\z/', $chave) !== 1) {
            return false;
        }

        // O CNPJ do emitente são os dígitos 7..20 da chave de acesso.
        $cnpjDaChave = substr($chave, 6, 14);

        if ($permitidos !== null && ! in_array($cnpjDaChave, $permitidos, true)) {
            Log::warning("ERP status: CNPJ {$cnpjDaChave} fora do escopo da chave do agente - linha ignorada.");

            return false;
        }

        $modelo = (int) substr($chave, 20, 2);
        $categoria = self::CATEGORIA[$modelo][$situacao] ?? null;

        if ($categoria === null) {
            $this->logaIgnorada($modelo, $situacao, $chave);

            return false;
        }

        $cstat = $r['cstat'] ?? null;
        $xmotivo = trim((string) ($r['xmotivo'] ?? ''));

        try {
            $dh = filled($r['emissao'] ?? null) ? \Carbon\Carbon::parse($r['emissao']) : null;
        } catch (\Throwable) {
            $dh = null;
        }

        $atual = FiscalStatus::where('key', $chave)->first();

        // Sem régua de dh_recbto: o ERP é autoridade, a última palavra vale.
        FiscalStatus::updateOrCreate(
            ['key' => $chave],
            [
                'model'            => $modelo,
                'cnpj_emit'        => substr($chave, 6, 14),
                'series'           => (int) substr($chave, 22, 3),
                'number'           => (int) substr($chave, 25, 9),
                'category'         => $categoria,
                'source'           => 'erp',
                'dh_recbto'        => $dh ?? $atual?->dh_recbto,
            ] + $this->detalhe($atual, $categoria, $cstat, $xmotivo)
        );

        return true;
    }

    /**
     * cStat / motivo / protocolo / ambiente — o que o ERP não tem e o canal XML
     * traz. Enquanto o ERP CONFIRMA a categoria, o detalhe do XML fica; quando
     * ele MUDA a categoria, o detalhe antigo é limpo. O ambiente nunca é
     * apagado: é da emissão, não da situação.
     */
    protected function detalhe(?FiscalStatus $atual, string $categoria, $cstat, string $xmotivo): array
    {
        $manteve = $atual !== null && $atual->category === $categoria;

        return [
            'cstat'            => is_numeric($cstat) ? (int) $cstat : ($manteve ? $atual->cstat : null),
            'x_motivo'         => $xmotivo !== '' ? mb_substr($xmotivo, 0, 255) : ($manteve ? $atual->x_motivo : null),
            'n_prot'           => $manteve ? $atual->n_prot : null,
            'environment_type' => $atual?->environment_type,   // o ERP não guarda tpAmb
        ];
    }

    /** Situação sem mapa: conhecida-e-ignorada é info; o resto é aviso. */
    protected function logaIgnorada(int $modelo, string $situacao, string $chave): void
    {
        $conhecida = self::IGNORADAS[$modelo][$situacao] ?? null;

        if ($conhecida !== null) {
            Log::info("ERP status: modelo {$modelo} situacao \"{$situacao}\" ({$conhecida}) - ignorada.", ['chave' => $chave]);

            return;
        }

        Log::warning(
            "ERP status: SITUACAO \"{$situacao}\" DESCONHECIDA no modelo {$modelo} - linha ignorada. " .
            'Confirme o significado com o time antes de mapear.',
            ['chave' => $chave]
        );
    }
}
