<?php

namespace App\Livewire\Panel\Dashboard;

use App\Models\Company;
use App\Support\DashboardStatusScope;
use Carbon\Carbon;
use Livewire\Component;

/**
 * Filtro do topo do dashboard — o único ponto de controle da tela: período,
 * ambiente, empresas, tipos, status, nº do documento, nº do protocolo e busca.
 *
 * Ao "Aplicar", dispara os dois eventos que o dashboard inteiro escuta:
 * eventDocsSearch (KPIs, tabelas, recentes) e eventDocsPerPeriodSearch
 * (gráficos), com o conjunto completo de args.
 */
class QuickFilter extends Component
{
    public $user;

    // período + busca
    public $first_date;
    public $last_date;
    public $term;

    // filtros que subiram do lateral
    public $environment_type = '';        // '' = Todos | '1' = Produção | '2' = Homologação
    public $related_companies = [];
    public $doc_types = [];
    public $doc_status = [];
    public $doc_number;
    public $protocol_number;

    protected $rules = [
        'first_date' => 'required_with:last_date|nullable|date_format:d/m/Y',
        'last_date' => 'required_with:first_date|nullable|date_format:d/m/Y',
        'doc_number' => 'numeric|nullable',
        'protocol_number' => 'numeric|nullable',
    ];

    protected $messages = [
        'first_date.required_with' => 'Informe as duas datas',
        'last_date.required_with' => 'Informe as duas datas',
        'first_date.date_format' => 'Data inválida (dd/mm/aaaa)',
        'last_date.date_format' => 'Data inválida (dd/mm/aaaa)',
        'doc_number.numeric' => 'Apenas números',
        'protocol_number.numeric' => 'Apenas números',
    ];

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function mount($user)
    {
        $this->user = $user;
    }

    public function render()
    {
        return view('livewire.panel.dashboard.quick-filter', [
            // Global scope linked_user: não-admin só vê as empresas dele.
            'companies' => Company::get(),
            // Mesmo vocabulário da tela "Documentos".
            'statusOptions' => DashboardStatusScope::opcoes(),
        ]);
    }

    public function submit()
    {
        $this->validate();

        $args = $this->searchArgs();

        if (is_null($args)) {
            // Nada preenchido = mesmo efeito do "Limpar".
            $this->resetSearch();
            return;
        }

        // Mesma sessão que o relatório lê: o botão "Relatório" imprime o que
        // está na tela.
        session()->put('searchArgsToDocReport', $args);

        $this->dispatch('eventDocsSearch', $args);
        $this->dispatch('eventDocsPerPeriodSearch', $args);

        // Abre a aba onde o status filtrado aparece. As abas ficam sob
        // wire:ignore, então quem troca é o JS do index — o mesmo caminho do
        // clique do usuário. Rola até a aba de propósito.
        $this->dispatch(
            'pnlSelecionarAba',
            tipo: DashboardStatusScope::abaPara($this->doc_status ?: []),
            rolar: true
        );
    }

    public function resetSearch()
    {
        $this->reset([
            'first_date',
            'last_date',
            'term',
            'environment_type',
            'related_companies',
            'doc_types',
            'doc_status',
            'doc_number',
            'protocol_number',
        ]);
        $this->resetErrorBag();

        session()->forget('searchArgsToDocReport');

        $this->dispatch('eventDocsSearch', null);
        $this->dispatch('eventDocsPerPeriodSearch', null);

        // Sem filtro, a aba padrão é a lista geral. rolar: false — quem clica
        // em "Limpar" está no topo e não pediu para sair de lá.
        $this->dispatch('pnlSelecionarAba', tipo: 'invoice', rolar: false);

        // Limpa os select2 (wire:ignore) no navegador.
        $this->dispatch('quickFilterCleared');
    }

    /**
     * Args no formato que tabelas, gráficos e relatórios entendem, mais a chave
     * quick_search. Devolve null quando nada foi preenchido (o submit vira reset).
     */
    protected function searchArgs(): ?array
    {
        $term = trim((string) $this->term);
        $env = $this->environmentTypes();

        $vazio = empty($this->first_date)
            && empty($this->last_date)
            && $term === ''
            && empty($env)
            && empty($this->related_companies)
            && empty($this->doc_types)
            && empty($this->doc_status)
            && $this->limpo($this->doc_number)
            && $this->limpo($this->protocol_number);

        if ($vazio) {
            return null;
        }

        return [
            'first_date' => $this->dataPtbrParaMysql($this->first_date),
            'last_date' => $this->dataPtbrParaMysql($this->last_date),
            'doc_number' => $this->limpo($this->doc_number) ? null : $this->doc_number,
            'protocol_number' => $this->limpo($this->protocol_number) ? null : $this->protocol_number,
            'related_companies' => $this->related_companies ?: [],
            'doc_types' => $this->doc_types ?: [],
            'environment_types' => $env,
            // O select fala GRUPOS e os widgets falam cStat: a expansão fica
            // aqui, no único ponto de saída.
            'doc_status' => DashboardStatusScope::expandir($this->doc_status ?: []),
            'quick_search' => $term !== '' ? $term : null,
        ];
    }

    /** Ambiente: '' (Todos) -> [] (sem recorte); '1'/'2' -> ['1']/['2']. */
    protected function environmentTypes(): array
    {
        return in_array((string) $this->environment_type, ['1', '2'], true)
            ? [(string) $this->environment_type]
            : [];
    }

    protected function limpo($valor): bool
    {
        return is_null($valor) || $valor === '';
    }

    protected function dataPtbrParaMysql($date)
    {
        if (is_null($date) || empty($date)) {
            return null;
        }

        return Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');
    }
}
