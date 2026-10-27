<?php

namespace App\Livewire\Panel\Dashboard;

use Livewire\Component;
use Livewire\Attributes\Lazy;
use App\Livewire\Concerns\FiltersDocuments;
use App\Models\Document;

// Lazy AGRUPADO (isolate:false): os 6 widgets carregam numa unica requisicao.
// Com sessao em arquivo, requisicoes isoladas viram fila e o dashboard trava.
#[Lazy(isolate: false)]
class RecentDocuments extends Component
{
    use FiltersDocuments;

    public $user;

    /** Recorte vindo do filtro do topo (QuickFilter). */
    public $search;

    protected $listeners = ['eventDocsSearch'];

    public function mount($user)
    {
        $this->user = $user;
    }

    public function eventDocsSearch($args)
    {
        $this->search = $args;
        // render() re-executa getRecentDocuments() com o novo filtro.
    }

    public function placeholder()
    {
        return view('livewire.panel.dashboard.partials.skeleton', ['h' => 220]);
    }

    public function render()
    {
        return view('livewire.panel.dashboard.recent-documents', [
            'documents' => $this->getRecentDocuments(),
        ]);
    }

    /** As 6 emissões mais recentes DENTRO do recorte do filtro do topo. */
    public function getRecentDocuments()
    {
        return Document::query()
            ->with('company')
            ->where(function ($query) {
                $this->querySearch($query);
            })
            ->whereIn('cnpj_cpf', $this->getCompanies())
            ->orderByDesc('issue_dh')
            ->limit(6)
            ->get();
    }
}
