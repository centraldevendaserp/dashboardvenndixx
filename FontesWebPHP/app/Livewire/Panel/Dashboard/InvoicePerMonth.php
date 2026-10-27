<?php

namespace App\Livewire\Panel\Dashboard;

use Livewire\Component;
use Livewire\Attributes\Lazy;
use App\Livewire\Concerns\FiltersDocuments;
use Illuminate\Support\Facades\DB;

// Lazy AGRUPADO (isolate:false): os 6 widgets carregam numa unica requisicao.
// Com sessao em arquivo, requisicoes isoladas viram fila e o dashboard trava.
#[Lazy(isolate: false)]
class InvoicePerMonth extends Component
{
    use FiltersDocuments;

    public $user;

    public $invoices = [];

    public $search;

    protected $listeners = ['eventDocsPerPeriodSearch'];

    public function mount($user)
    {
        $this->user = $user;
    }

    public function placeholder()
    {
        return view('livewire.panel.dashboard.partials.skeleton', ['h' => 300]);
    }

    public function render()
    {
        // Popula no render, sem depender de roundtrip de evento.
        $this->getInvoices();

        return view('livewire.panel.dashboard.invoice-per-month');
    }

    public function eventDocsPerPeriodSearch($args)
    {
        // render() re-executa getInvoices com o novo filtro.
        $this->search = $args;
    }

    public function getInvoices()
    {
        DB::statement('SET sql_mode=""');
        DB::statement('SET lc_time_names = "pt_BR"');

        $invoices = DB::table('documents')
            ->selectRaw('
                            DATE_FORMAT(issue_dh, "%b/%y") AS month,
                            SUM(IF(model = 55, 1, 0)) AS "55",
                            SUM(IF(model IN (57, 67), 1, 0)) AS "57",
                            SUM(IF(model = 58, 1, 0)) AS "58",
                            SUM(IF(model = 59, 1, 0)) AS "59",
                            SUM(IF(model = 65, 1, 0)) AS "65"
                        ')
            ->where(function ($query) {
                $this->querySearch($query);
            })
            ->whereIn('cnpj_cpf', $this->getCompanies())
            ->orderBy('issue_dh')
            ->groupBy('month')
            ->get()
            ->toArray();

        $this->invoices = json_decode(json_encode($invoices), true);

        $this->dispatch('eventInitChartQtyPerMonth', invoices: $this->invoices);
    }
}
