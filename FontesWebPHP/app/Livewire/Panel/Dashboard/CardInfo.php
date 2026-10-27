<?php

namespace App\Livewire\Panel\Dashboard;

use Livewire\Component;
use Livewire\Attributes\Lazy;
use App\Models\Company;
use App\Models\DisableDocument;
use App\Models\Document;
use App\Models\User;
use App\Support\DashboardStatusScope;
use Carbon\Carbon;

// Lazy AGRUPADO (isolate:false): os 6 widgets carregam numa unica requisicao.
// Com sessao em arquivo, requisicoes isoladas viram fila e o dashboard trava.
#[Lazy(isolate: false)]
class CardInfo extends Component
{
    public $user;

    public $users_count = 0;
    public $companies_count = 0;
    public $invoices_count = 0;
    public $invoices_month_count = 0;

    /** Recorte vindo do filtro do topo (QuickFilter). */
    public $search;

    protected $listeners = ['eventDocsSearch'];

    public function mount($user)
    {
        $this->user = $user;
    }

    public function placeholder()
    {
        return view('livewire.panel.dashboard.partials.skeleton', ['h' => 96]);
    }

    public function render()
    {
        // Recalcula a cada render, inclusive quando chega o filtro do topo.
        $this->counter();

        return view('livewire.panel.dashboard.card-info');
    }

    public function eventDocsSearch($args)
    {
        $this->search = $args;
        // render() recalcula counter() com o novo recorte
    }

    protected function counter()
    {
        if ($this->user->is_admin == "S") {
            $this->users_count = User::count();
        }

        $this->companies_count = Company::count();

        // Notas das empresas do usuário + o recorte INTEIRO do filtro do topo,
        // período incluído: o card se chama "Total filtrado" e tem de bater com
        // a listagem ao lado.
        $base = Document::query()
            ->whereIn('cnpj_cpf', Company::pluck('cnpj_cpf'))
            ->where(function ($query) {
                $this->applyScope($query);
            });

        $this->aplicaPeriodo($base, 'issue_dh');

        // As inutilizações entram no KPI: não estão em `documents`, então sem
        // isto filtrar por "Inutilizada" zeraria o total.
        $inut = $this->inutilizacoesDoRecorte();

        if ($inut) {
            // Mesmo período do card, mas a coluna de data é a do EVENTO.
            $this->aplicaPeriodo($inut, 'event_dh');
        }

        $this->invoices_count = (clone $base)->count()
            + ($inut ? (clone $inut)->count() : 0);

        // Notas emitidas no mês corrente, com o mesmo recorte.
        $this->invoices_month_count = (clone $base)
            ->whereBetween('issue_dh', [
                Carbon::now()->startOfMonth()->toDateTimeString(),
                Carbon::now()->toDateTimeString(),
            ])
            ->count()
            + ($inut ? (clone $inut)->whereBetween('event_dh', [
                Carbon::now()->startOfMonth()->toDateTimeString(),
                Carbon::now()->toDateTimeString(),
            ])->count() : 0);
    }

    /** Aplica o período De/Até do topo; data vazia = sem limite daquele lado. */
    protected function aplicaPeriodo($query, string $coluna): void
    {
        $de = trim((string) ($this->search['first_date'] ?? ''));
        $ate = trim((string) ($this->search['last_date'] ?? ''));

        if ($de !== '') {
            $query->where($coluna, '>=', $de . ' 00:00:00');
        }
        if ($ate !== '') {
            $query->where($coluna, '<=', $ate . ' 23:59:59');
        }
    }

    /**
     * Inutilizações do recorte, ou null quando o filtro de status não as inclui.
     * Mesma regra do resto do painel, para o KPI não divergir da tabela.
     */
    protected function inutilizacoesDoRecorte()
    {
        $status = (array) ($this->search['doc_status'] ?? []);
        $codigos = DashboardStatusScope::paraInutilizacoes($status);

        // Só entram quando o filtro PEDE inutilização (102); sem filtro de
        // status o KPI segue sendo o total de notas.
        //
        // ⚠️ Não use DashboardStatusScope::incluiInutilizacoes() aqui: ela
        // decide a tabela SECUNDÁRIA da tela Documentos e devolve false
        // justamente quando o filtro é só "Inutilizada".
        if ($codigos === []) {
            return null;
        }

        $q = DisableDocument::query()
            ->whereIn('cnpj', Company::pluck('cnpj_cpf'))
            ->whereIn('event_status', $codigos);

        if ($empresas = ($this->search['related_companies'] ?? null)) {
            $q->whereIn('cnpj', $empresas);
        }
        if ($modelos = ($this->search['doc_types'] ?? null)) {
            $q->whereIn('model', $modelos);
        }
        if ($ambientes = ($this->search['environment_types'] ?? null)) {
            $q->whereIn('environment_type', $ambientes);
        }

        return $q;
    }

    /**
     * Todos os filtros do topo MENOS o período — cada KPI recorta o seu (total
     * do recorte e mês corrente). A busca entra aqui, senão procurar uma chave
     * deixaria a tabela com 1 nota e o card com o total geral.
     */
    protected function applyScope($query): void
    {
        if (is_null($this->search)) {
            return;
        }

        $query->when($this->search['related_companies'] ?? null, function ($query, $related_companies) {
            return $query->whereIn('cnpj_cpf', $related_companies);
        });

        $query->when($this->search['environment_types'] ?? null, function ($query, $environment_types) {
            return $query->whereIn('environment_type', $environment_types);
        });

        $query->when($this->search['doc_types'] ?? null, function ($query, $doc_types) {
            return $query->whereIn('model', $doc_types);
        });

        // Só os status do domínio das NOTAS (ver DashboardStatusScope).
        $statusFiltro = (array) ($this->search['doc_status'] ?? []);
        $statusNotas = DashboardStatusScope::paraNotas($statusFiltro);

        if ($statusNotas) {
            $query->whereIn('status_xml', $statusNotas);
        } elseif ($statusFiltro !== []) {
            // Há filtro de status, mas nenhum código é do domínio das notas
            // (caso de "Inutilizada" sozinha). Sem este corte, a interseção
            // vazia seria lida como "não recorta" e o KPI mostraria o total geral.
            $query->whereRaw('1 = 0');
        }

        $query->when($this->search['doc_number'] ?? null, function ($query, $doc_number) {
            return $query->where('number', $doc_number);
        });

        $query->when($this->search['protocol_number'] ?? null, function ($query, $protocol_number) {
            return $query->where('protocol', $protocol_number);
        });

        $query->when($this->search['quick_search'] ?? null, function ($query, $term) {
            return $query->where(function ($q) use ($term) {
                $q->where('number', $term)
                    ->orWhere('protocol', $term)
                    ->orWhere('key', $term);
            });
        });
    }
}
