<?php

namespace App\Livewire\Panel\Dashboard;

use Livewire\Component;
use Livewire\Attributes\Lazy;
use App\Livewire\Concerns\FiltersDocuments;
use Illuminate\Support\Facades\DB;

// Lazy AGRUPADO (isolate:false): os 6 widgets carregam numa unica requisicao.
// Com sessao em arquivo, requisicoes isoladas viram fila e o dashboard trava.
#[Lazy(isolate: false)]
class StatusBreakdown extends Component
{
    use FiltersDocuments;

    public $user;

    public $rows = [];

    public $search;

    protected $listeners = ['eventDocsSearch'];

    public function mount($user)
    {
        $this->user = $user;
    }

    public function placeholder()
    {
        return view('livewire.panel.dashboard.partials.skeleton', ['h' => 220]);
    }

    public function render()
    {
        // Popula no render, sem depender de roundtrip de evento.
        $this->getStatus();

        return view('livewire.panel.dashboard.status-breakdown');
    }

    public function eventDocsSearch($args)
    {
        // render() re-executa getStatus com o novo filtro.
        $this->search = $args;
    }

    public function getStatus()
    {
        DB::statement('SET sql_mode=""');

        $data = DB::table('documents')
            ->selectRaw('status_xml, COUNT(id) AS qty')
            ->where(function ($query) {
                $this->querySearch($query);
            })
            ->whereIn('cnpj_cpf', $this->getCompanies())
            ->groupBy('status_xml')
            ->get();

        $labels = [
            100 => 'Autorizada',
            101 => 'Cancelada',
            110 => 'Denegada',
        ];

        $rows = [];

        foreach ($data as $d) {
            $code = (int) $d->status_xml;
            $rows[] = [
                'label' => $labels[$code] ?? ('Status ' . $code),
                'qty' => (int) $d->qty,
                'code' => $code,
            ];
        }

        $this->rows = $rows;
        $this->dispatch('eventInitStatusChart', rows: $rows);
    }
}
