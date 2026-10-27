<?php

namespace App\Livewire\Panel\Dashboard;

use Livewire\Component;
use Livewire\Attributes\Lazy;
use App\Livewire\Concerns\FiltersDocuments;
use Illuminate\Support\Facades\DB;

// Lazy AGRUPADO (isolate:false): os 6 widgets carregam numa unica requisicao.
// Com sessao em arquivo, requisicoes isoladas viram fila e o dashboard trava.
#[Lazy(isolate: false)]
class InvoiceQtyTotal extends Component
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

        return view('livewire.panel.dashboard.invoice-qty-total');
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
                            COUNT(id) as qty,
                            CASE model
                                WHEN 55 THEN "NF-e"
                                WHEN 57 THEN "CT-e"
                                WHEN 67 THEN "CT-e"
                                WHEN 58 THEN "MDF-e"
                                WHEN 59 THEN "Entrada"
                                WHEN 65 THEN "NFC-e"
                            END AS model,
                            SUM(vNF) AS total
                        ')
            ->where(function ($query) {
                $this->querySearch($query);
            })
            ->whereIn('cnpj_cpf', $this->getCompanies())
            ->groupBy('documents.model')
            ->get()
            ->toArray();

        $this->invoices = json_decode(json_encode($invoices), true);

        $this->dispatch('eventInitChartQtyTotal', invoices: $this->invoices);
    }
}
