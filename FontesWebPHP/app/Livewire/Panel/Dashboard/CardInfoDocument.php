<?php

namespace App\Livewire\Panel\Dashboard;

use Livewire\Component;
use Livewire\Attributes\Lazy;
use App\Livewire\Concerns\FiltersDocuments;
use Illuminate\Support\Facades\DB;

// Lazy AGRUPADO (isolate:false): os 6 widgets carregam numa unica requisicao.
// Com sessao em arquivo, requisicoes isoladas viram fila e o dashboard trava.
#[Lazy(isolate: false)]
class CardInfoDocument extends Component
{
    use FiltersDocuments;

    public $user;

    public $total_nfe = 0;
    public $total_nfce = 0;
    public $total_cte = 0;
    public $total_mdfe = 0;
    /** Modelo 59 = notas de ENTRADA (compras). O nome da variável é histórico. */
    public $total_cfesat = 0;
    public $total_nfse = 0;

    public $qty_nfe = 0;
    public $qty_nfce = 0;
    public $qty_cte = 0;
    public $qty_mdfe = 0;
    public $qty_cfesat = 0;
    public $qty_nfse = 0;

    public $search;

    protected $listeners = ['eventDocsSearch'];

    public function mount($user)
    {
        $this->user = $user;
    }

    public function placeholder()
    {
        return view('livewire.panel.dashboard.partials.skeleton', ['h' => 130]);
    }

    public function render()
    {
        $this->getTotals();

        return view('livewire.panel.dashboard.card-info-document');
    }

    public function eventDocsSearch($args)
    {
        $this->search = $args;

        $this->reset([
            'total_nfe',
            'total_cte',
            'total_mdfe',
            'total_cfesat',
            'total_nfce',

            'total_nfse',

            'qty_nfe',
            'qty_cte',
            'qty_mdfe',
            'qty_cfesat',
            'qty_nfce',
            'qty_nfse',

        ]);
        // render() recalcula getTotals() neste mesmo request (evita query dupla).
    }

    public function getTotals()
    {
        DB::statement('SET sql_mode=""');
        DB::statement('SET lc_time_names = "pt_BR"');

        $documents = DB::table('documents')
            ->selectRaw('
                model,
                COUNT(id) AS qty,
                SUM(vNF) as total
            ')
            ->where(function ($query) {
                $this->querySearch($query);
            })
            ->whereIn('cnpj_cpf', $this->getCompanies())
            ->orderBy('model')
            ->groupBy('documents.model')
            ->get();

        foreach ($documents as $doc) {

            switch ($doc->model) {
                case "55":
                    $this->total_nfe = $doc->total;
                    $this->qty_nfe = $doc->qty;
                    break;

                case "57":
                case "67": // CT-e OS soma no card do CT-e
                    $this->total_cte += $doc->total;
                    $this->qty_cte += $doc->qty;
                    break;

                case "58":
                    $this->total_mdfe = $doc->total;
                    $this->qty_mdfe = $doc->qty;
                    break;

                case "59":
                    $this->total_cfesat = $doc->total;
                    $this->qty_cfesat = $doc->qty;
                    break;

                case "65":
                    $this->total_nfce = $doc->total;
                    $this->qty_nfce = $doc->qty;
                    break;
            }
        }

        $this->totaisNfse();
        $this->buildSparklines();
    }

    /**
     * A NFS-e mora em `nfse_documents`, então a query é separada e os filtros do
     * QuickFilter são traduzidos para as colunas de lá (`cnpj_prestador`,
     * `valor`). No vocabulário do portal o modelo dela é 99.
     */
    protected function totaisNfse(): void
    {
        $tipos = $this->search['doc_types'] ?? null;
        if (! empty($tipos) && ! in_array('99', array_map('strval', (array) $tipos), true)) {
            return;
        }

        $query = DB::table('nfse_documents')->whereIn('cnpj_prestador', $this->getCompanies());

        $query->when($this->search['first_date'] ?? null, fn ($q, $d) => $q->where('issue_dh', '>=', $d));
        $query->when($this->search['last_date'] ?? null, fn ($q, $d) => $q->where('issue_dh', '<=', $d));
        $query->when($this->search['related_companies'] ?? null,
            fn ($q, $empresas) => $q->whereIn('cnpj_prestador', $empresas));
        $query->when($this->search['environment_types'] ?? null,
            fn ($q, $ambientes) => $q->whereIn('environment_type', $ambientes));

        $linha = $query->selectRaw('COUNT(id) AS qty, SUM(valor) AS total')->first();

        $this->qty_nfse = (int) ($linha->qty ?? 0);
        $this->total_nfse = (float) ($linha->total ?? 0);
    }

    /** Série diária por modelo (os sparklines). Mesmo filtro/escopo de getTotals. */
    protected function buildSparklines()
    {
        $rows = DB::table('documents')
            ->selectRaw('model, DATE(issue_dh) AS d, COUNT(id) AS c')
            ->where(function ($query) {
                $this->querySearch($query);
            })
            ->whereIn('cnpj_cpf', $this->getCompanies())
            ->groupBy('model', 'd')
            ->orderBy('d')
            ->get();

        $spark = ['55' => [], '65' => [], '57' => [], '58' => [], '59' => []];

        foreach ($rows as $r) {
            $model = (string) $r->model;
            if (array_key_exists($model, $spark)) {
                $spark[$model][] = (int) $r->c;
            }
        }

        $this->dispatch('eventInitSparklines', spark: $spark);
    }
}
