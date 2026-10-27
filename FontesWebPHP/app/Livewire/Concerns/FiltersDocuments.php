<?php

namespace App\Livewire\Concerns;

use App\Models\Company;
use App\Support\DashboardStatusScope;
use Carbon\Carbon;

/**
 * Filtros compartilhados dos widgets do dashboard que consultam `documents`:
 * escopo por empresa (getCompanies) e o recorte do QuickFilter inteiro
 * (querySearch). O componente precisa ter a propriedade `public $search;`.
 *
 * ⚠️ Filtro NOVO entra em querySearch(), para valer em todos os widgets de uma
 * vez; applyExtraFilters() é só para o que for exclusivo de um componente.
 */
trait FiltersDocuments
{
    /** CNPJs das empresas visíveis ao usuário (respeita o global scope linked_user). */
    public function getCompanies()
    {
        return Company::pluck('cnpj_cpf');
    }

    /** Gancho para o que é exclusivo de um componente — não repita filtro daqui. */
    protected function applyExtraFilters($query): void
    {
        // Sobrescrito por cada componente conforme necessário.
    }

    protected function querySearch($query): void
    {
        $this->searchDefault($query);

        if (is_null($this->search)) {
            return;
        }

        $query->when($this->search['first_date'] ?? null, function ($query, $first_date) {
            return $query->where('issue_dh', '>=', $first_date);
        })->when($this->search['last_date'] ?? null, function ($query, $last_date) {
            return $query->where('issue_dh', '<=', $last_date);
        });

        $query->when($this->search['related_companies'] ?? null, function ($query, $related_companies) {
            return $query->whereIn('cnpj_cpf', $related_companies);
        });

        $query->when($this->search['environment_types'] ?? null, function ($query, $environment_types) {
            return $query->whereIn('environment_type', $environment_types);
        });

        $query->when($this->search['doc_types'] ?? null, function ($query, $doc_types) {
            return $query->whereIn('model', $doc_types);
        });

        // Só os status do domínio das NOTAS: 102 é faixa de números, não nota,
        // e não pode zerar esta consulta.
        $statusNotas = DashboardStatusScope::paraNotas($this->search['doc_status'] ?? []);

        if ($statusNotas) {
            $query->whereIn('status_xml', $statusNotas);
        }

        $query->when($this->search['doc_number'] ?? null, function ($query, $doc_number) {
            return $query->where('number', $doc_number);
        });

        $query->when($this->search['protocol_number'] ?? null, function ($query, $protocol_number) {
            return $query->where('protocol', $protocol_number);
        });

        // Busca rápida do topo: um termo, comparado com número, protocolo e chave.
        $query->when($this->search['quick_search'] ?? null, function ($query, $term) {
            return $query->where(function ($q) use ($term) {
                $q->where('number', $term)
                    ->orWhere('protocol', $term)
                    ->orWhere('key', $term);
            });
        });

        $this->applyExtraFilters($query);
    }

    protected function searchDefault($query): void
    {
        // Sem filtro = TODOS os documentos; o período só entra quando o
        // usuário filtra.
    }
}
