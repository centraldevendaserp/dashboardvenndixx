<?php

namespace App\Support;

/**
 * Linhas de inutilização da "Visão geral" do relatório de notas. Fonte única
 * do resumo do dashboard e da tela Documentos.
 *
 * Duas regras visíveis na saída:
 * 1. `qty` = NÚMEROS inutilizados, não registros (a faixa 100→200 é 1 linha e
 *    101 números) — as outras linhas contam 1 por nota, a unidade tem de bater.
 * 2. `total` = null e a view imprime "—": inutilização não tem valor, e
 *    "R$ 0,00" seria lido como venda zerada.
 */
class InutilizacoesOverview
{
    /** Mesmo vocabulário do resto do relatório. */
    private const MODELOS = [55 => 'NF-e', 57 => 'CT-e', 58 => 'MDF-e', 59 => 'Entrada', 65 => 'NFC-e', 67 => 'CT-e OS'];

    /**
     * Agrega por modelo a query de `disable_documents` já recortada por quem
     * chama. Devolve [model, status_xml, qty, total]; `null` = sem linhas.
     *
     * O reorder() é obrigatório: sob ONLY_FULL_GROUP_BY um ORDER BY herdado
     * quebra o GROUP BY.
     */
    public static function linhas($queryDisables): array
    {
        if (is_null($queryDisables)) {
            return [];
        }

        // A faixa vem CRUA do XML (BIGINT UNSIGNED, sem validação na ingestão):
        //  - CAST p/ SIGNED: faixa invertida estouraria "1690 out of range" (500);
        //  - COALESCE: `nNFFin` ausente grava NULL e sumiria do SUM;
        //  - GREATEST(...,0): faixa invertida não subtrai da contagem.
        return $queryDisables
            ->reorder()
            ->selectRaw(
                'model, SUM(GREATEST('
                . 'CAST(COALESCE(number_end, number_start) AS SIGNED) - CAST(number_start AS SIGNED) + 1'
                . ', 0)) as qty'
            )
            ->groupBy('model')
            ->get()
            ->map(fn ($row) => [
                'model'      => self::MODELOS[(int) $row->model] ?? 'Outros',
                'status_xml' => 'Inutilizada',
                'qty'        => (int) $row->qty,
                'total'      => null,   // não tem valor — a view mostra "—"
            ])
            ->all();
    }
}
