<?php

namespace App\Livewire\Panel\FiscalStatus;

use App\Models\Company;
use Carbon\Carbon;
use App\Support\FiscalStatusQuery;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Aba "Status SEFAZ" (/panel/fiscal-status). Lê o ledger `fiscal_status` — o
 * último status conhecido por chave — e mostra só o que precisa de atenção:
 * rejeitada ou contingência. Autorizada nunca aparece aqui.
 *
 * Os chips são mutuamente exclusivos (a nota tem UMA situação), então marcar os
 * dois é união. Contingência = emitida em contingência e a SEFAZ ainda não tem
 * a nota (cStat 217); quando ela responde, vira autorizada ou rejeitada.
 *
 * Tela somente leitura: o recorte é de exibição, o ledger segue recebendo tudo.
 */
class Index extends Component
{
    use WithPagination;

    /** Chips marcados: subconjunto de ['rejeitada', 'contingencia']. [] = o universo. */
    public array $filters = [];

    /** Busca por chave ou número. */
    public string $search = '';

    /** CNPJ (só dígitos) da empresa selecionada ('' = todas as visíveis). */
    public string $company_filter = '';

    /**
     * Período por dh_recbto (inputs type=date, Y-m-d). Abre em 01/01/2000 ->
     * hoje em vez de vazio: campo em branco não dizia se era tudo ou nada.
     */
    public $first_date = '2000-01-01';
    public $last_date = '';

    public function mount(): void
    {
        $this->last_date = Carbon::now()->format('Y-m-d');
    }

    /**
     * Ambiente (tpAmb): '1' = produção (padrão), '2' = homologação, '' = todos.
     * Abre em produção porque a aba mostra PENDÊNCIA, e rejeição de homologação
     * é teste. Linha com tpAmb nulo conta como produção, então nada some.
     */
    public string $ambiente = '1';

    public function paginationView()
    {
        return 'components.layouts.pagination';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCompanyFilter(): void
    {
        $this->resetPage();
    }

    /** Aplica o período digitado (os inputs usam wire:model deferido). */
    public function applyPeriod(): void
    {
        $this->resetPage();
    }

    public function updatedAmbiente(): void
    {
        $this->resetPage();
    }

    /** Liga/desliga um chip. Padrão do Documents\Index::toggleStatus. */
    public function toggleFilter($key): void
    {
        $key = (string) $key;

        if (in_array($key, $this->filters, true)) {
            $this->filters = array_values(array_diff($this->filters, [$key]));
        } else {
            $this->filters[] = $key;
        }

        $this->resetPage();
    }

    /** Chip "Todas": volta ao universo (rejeitada ∪ contingência). */
    public function clearFilters(): void
    {
        $this->filters = [];
        $this->resetPage();
    }

    /** Fonte única compartilhada com o relatório (FiscalStatusReportController). */
    protected function query(): FiscalStatusQuery
    {
        return new FiscalStatusQuery(
            filters: $this->filters,
            companyFilter: $this->company_filter,
            firstDate: (string) $this->first_date,
            lastDate: (string) $this->last_date,
            search: $this->search,
            ambiente: $this->ambiente,
        );
    }

    public function render()
    {
        $q = $this->query();

        $statuses = $q->rowsQuery()->paginate(config('app.pagination_limit'));

        return view('livewire.panel.fiscal-status.index', [
            'statuses'  => $statuses,
            'counts'    => $q->counts(),
            'total'     => $q->total(),
            'companies' => Company::orderBy('corporate_name')->get(['cnpj_cpf', 'corporate_name', 'fantasy_name']),
        ]);
    }
}
