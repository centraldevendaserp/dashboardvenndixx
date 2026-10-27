<?php

namespace App\Livewire\Panel\Dashboard;

use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $user;

    public $doc_type = 'invoice';

    public $docs_per_period_search;
    public $docs_search;

    public $reportRoute;

    protected $listeners = ['eventDocType', 'eventDocsPerPeriodSearch', 'eventDocsSearch', 'eventDownloadCompressed'];

    public function mount()
    {
        $this->user = auth('web')->user();

        $this->setReportRoute();
        $this->setSessionDocType();

        // Limpa a sessao dos relatorios de documentos, eventos e inutilizadas.
        session()->forget('searchArgsToDocReport');
    }

    public function render()
    {
        return view('livewire.panel.dashboard.index');
    }

    public function eventDocType($type)
    {
        $this->doc_type = $type;

        $this->setReportRoute();
        $this->setSessionDocType();
        // A tabela de notas recebe o mesmo eventDocType e aplica sozinha o
        // recorte "AUTORIZADAS" (status_xml = 100).
    }

    public function eventDocsSearch($args)
    {
        $this->docs_search = $args;
    }

    public function eventDocsPerPeriodSearch($args)
    {
        $this->docs_per_period_search = $args;
    }

    public function eventDownloadCompressed(string $doc_type)
    {
        switch ($doc_type) {
            case 'authorized':
            case 'invoice':
                $this->dispatch('eventDownloadCompressedDoc')->to(
                    'panel.dashboard.invoice'
                );
                break;

            case 'event':
                $this->dispatch('eventDownloadCompressedDoc')->to(
                    'panel.dashboard.event'
                );
                break;

            case 'disable':
                $this->dispatch('eventDownloadCompressedDoc')->to(
                    'panel.dashboard.disable'
                );
                break;
        }
    }

    protected function setSessionDocType()
    {
        session()->put('docType', $this->doc_type);
    }

    protected function setReportRoute()
    {
        if ($this->doc_type == 'invoice' || $this->doc_type == 'authorized') {
            $this->reportRoute =  route('panel.reports.invoices');
        } elseif ($this->doc_type == 'event') {
            $this->reportRoute =  route('panel.reports.events');
        } elseif ($this->doc_type == 'disable') {
            $this->reportRoute =  route('panel.reports.disables');
        }
    }
}
