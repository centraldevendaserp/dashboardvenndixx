<?php

namespace App\Livewire\Panel\Dashboard;

use Livewire\Component;
use App\Models\Document;
use App\Models\EventDocument;
use App\Models\DisableDocument;
use Illuminate\Support\Facades\Gate;

class DetailDoc extends Component
{
    public $user;

    public $details = [];

    protected $listeners = ['eventDetailDoc'];

    public function mount($user)
    {
        $this->user = $user;
    }

    public function render()
    {
        return view('livewire.panel.dashboard.detail-doc');
    }

    public function eventDetailDoc($id, $type)
    {
        if ($type == 'invoice') {
            $this->detailInvoice($id);
        } elseif ($type == 'event') {
            $this->detailEvent($id);
        } elseif ($type == 'disable') {
            $this->detailDisable($id);
        }
    }

    public function detailInvoice($id)
    {
        $document = Document::find($id);

        if (is_null($document)) {
            return;
        }

        $policy = Gate::inspect('access-invoice', $document);

        if ($policy->denied()) {
            return;
        }

        $this->details = $document;

        $this->dispatch('eventOpenModal', modalId: "#modal-detail-doc");
    }

    public function detailEvent($id)
    {
        $document = EventDocument::find($id);

        if (is_null($document)) {
            return;
        }

        $policy = Gate::inspect('access-event-document', $document);

        if ($policy->denied()) {
            return;
        }

        $this->details = $document;

        $this->dispatch('eventOpenModal', modalId: "#modal-detail-doc");
    }

    public function detailDisable($id)
    {
        $document = DisableDocument::find($id);

        if (is_null($document)) {
            return;
        }

        $policy = Gate::inspect('access-disable-document', $document);

        if ($policy->denied()) {
            return;
        }

        $this->details = $document;

        $this->dispatch('eventOpenModal', modalId: "#modal-detail-doc");
    }
}
