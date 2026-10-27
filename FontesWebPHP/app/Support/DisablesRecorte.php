<?php

namespace App\Support;

/**
 * Filtros do topo do dashboard aplicados a uma query de `disable_documents`.
 * Usado pelo relatório, pelo "Baixar XML" e pela aba Inutilização — os três têm
 * de recortar igual.
 *
 * Colunas são as de `disable_documents`: período em `event_dh` (não `issue_dh`),
 * empresa em `cnpj` e status em `event_status`.
 * `$args` vem do QuickFilter::searchArgs(); null = todos.
 */
class DisablesRecorte
{
    public static function aplicar($query, ?array $args): void
    {
        if (is_null($args)) {
            return;
        }

        $query->when($args['first_date'] ?? null, function ($query, $first_date) {
            return $query->where('event_dh', '>=', $first_date);
        });

        $query->when($args['last_date'] ?? null, function ($query, $last_date) {
            return $query->where('event_dh', '<=', $last_date);
        });

        $query->when($args['doc_number'] ?? null, function ($query, $doc_number) {
            return $query->where('number_start', $doc_number);
        });

        $query->when($args['protocol_number'] ?? null, function ($query, $protocol_number) {
            return $query->where('protocol_number', $protocol_number);
        });

        $query->when($args['related_companies'] ?? null, function ($query, $related_companies) {
            return $query->whereIn('cnpj', $related_companies);
        });

        $query->when($args['doc_types'] ?? null, function ($query, $doc_types) {
            return $query->whereIn('model', $doc_types);
        });

        $query->when($args['environment_types'] ?? null, function ($query, $environment_types) {
            return $query->whereIn('environment_type', $environment_types);
        });

        // cStat do EVENTO de inutilização, não status de nota.
        $query->when($args['doc_status'] ?? null, function ($query, $doc_status) {
            return $query->whereIn('event_status', $doc_status);
        });

        $query->when($args['quick_search'] ?? null, function ($query, $term) {
            return $query->where(function ($q) use ($term) {
                $q->where('protocol_number', $term)
                    ->orWhere('number_start', $term)
                    ->orWhere('number_end', $term);
            });
        });
    }
}
