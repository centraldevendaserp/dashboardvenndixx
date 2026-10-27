<?php

namespace App\Livewire\Panel\Dashboard;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\DisableDocument;
use App\Models\Company;
use App\Support\DashboardStatusScope;
use App\Support\DocumentTypeQuery;
use App\Support\DownloadCleanup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;
use ZipArchive;

class Disable extends Component
{
    use WithPagination;

    public $user;

    public $search;

    protected $listeners = ['eventDocsSearch', 'eventDownloadCompressedDoc'];

    public function mount($user)
    {
        $this->user = $user;
    }

    public function render()
    {
        return view('livewire.panel.dashboard.disable', [
            'disables' => $this->getDisables(true)
        ]);
    }

    public function getQueryString()
    {
        return [];
    }

    public function paginationView()
    {
        return 'components.layouts.pagination';
    }

    public function eventDocsSearch($args)
    {
        $this->search = $args;

        $this->resetPage();
    }

    public function eventDownloadCompressedDoc()
    {
        $documents = $this->getDisables(false);

        if ($documents->isEmpty()) {
            $this->dispatch(
                'eventCuteToast',
                msg: "Nenhuma documento disponível para download!",
                code: 400
            );
            $this->dispatch('zip-pronto'); // spinner do botão solta com folga
            return;
        }

        // Teto por download: acima disso o zip leva minutos e o envio estoura
        // a memória.
        if ($documents->count() > DocumentTypeQuery::MAX_ITEMS) {
            $this->dispatch(
                'eventCuteToast',
                msg: 'São ' . number_format($documents->count(), 0, ',', '.') . ' documentos no filtro — o limite por download é '
                    . number_format(DocumentTypeQuery::MAX_ITEMS, 0, ',', '.') . '. Refine o período.',
                code: 400
            );
            $this->dispatch('zip-pronto'); // spinner do botão solta com folga
            return;
        }

        $zip = new ZipArchive();

        $time = time();
        $name = "disabled-{$this->user->id}-{$time}.zip";
        $path = storage_path("app/downloads");
        $file = "{$path}/{$name}";

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        // Zip abandonado (aba fechada, timeout) fica em disco. Todo gerador de
        // zip tem de coletar — ver App\Support\DownloadCleanup.
        DownloadCleanup::limpar($path);

        if ($zip->open($file, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) !== true) {
            $this->dispatch(
                'eventCuteToast',
                msg: 'Opss, não foi possível compactar o download',
                code: 500
            );
            $this->dispatch('zip-pronto'); // spinner do botão solta com folga
            return;
        }

        foreach ($documents as $document) {

            $policy = Gate::inspect('access-disable-document', $document);

            if ($policy->denied()) {
                continue;
            }

            $path_download = str_replace('/docs/eventos/', '', $document->path_xml);

            if (File::exists(storage_path("app{$document->path_xml}"))) {
                $zip->addFile(storage_path("app{$document->path_xml}"), $path_download);
            }
        }

        $zip->close();

        if (File::exists($file)) {
            // Fora do Livewire, que bufferiza o arquivo em base64 e estoura a
            // memória: a rota faz streaming do disco.
            $this->dispatch('zip-pronto'); // spinner do botão solta com folga

            return redirect()->route('panel.documents.download', ['file' => $name]);
        } else {
            $this->dispatch(
                'eventCuteToast',
                msg: "Não existe nenhuma nota compactada para download.",
                code: 404
            );
            $this->dispatch('zip-pronto'); // spinner do botão solta com folga
            return;
        }
    }

    public function getDisables($paginate = false)
    {
        $disables = DisableDocument::where(function ($query) {
            $this->querySearch($query);
        })->whereIn('cnpj', $this->getCompanies());

        // Mais recentes primeiro.
        $disables->orderBy('event_dh', 'DESC')
            ->orderBy('number_start', 'DESC');

        if ($paginate) {
            return $disables->paginate(100, pageName: 'disables');
        }

        return $disables->get();
    }

    public function getCompanies()
    {
        return Company::get()->pluck('cnpj_cpf');
    }

    public function downloadDocById($id, $type)
    {
        if ($type == 'xml') {
            return $this->downloadDocXmlById($id);
        }
    }

    protected function downloadDocXmlById($id)
    {
        $document = DisableDocument::find($id);

        if (is_null($document)) {
            $this->dispatch(
                'eventCuteToast',
                msg: "Nao existe o arquivo para download.",
                code: 404
            );
            return;
        }

        $policy = Gate::inspect('access-disable-document', $document);

        if ($policy->denied()) {
            $this->dispatch(
                'eventCuteToast',
                msg: "Nao autorizado.",
                code: 403
            );
            return;
        }

        $file = storage_path("app{$document->path_xml}");

        if (File::exists($file)) {
            return response()->download($file, null, [
                'Content-Type' => 'application/xml',
            ]);
        } else {
            $this->dispatch(
                'eventCuteToast',
                msg: "Não existe o arquivo para download.",
                code: 404
            );
            return;
        }
    }

    protected function querySearch($query)
    {
        $this->searchDefault($query);

        if (is_null($this->search)) {
            return;
        }

        $query->when($this->search['first_date'] ?? null, function ($query, $first_date) {
            return $query->where('event_dh', '>=', $first_date);
        })->when($this->search['last_date'] ?? null, function ($query, $last_date) {
            return $query->where('event_dh', '<=', $last_date);
        });

        $query->when($this->search['doc_number'] ?? null, function ($query, $doc_number) {
            return $query->where('number_start', $doc_number);
        });

        $query->when($this->search['protocol_number'] ?? null, function ($query, $protocol_number) {
            return $query->where('protocol_number', $protocol_number);
        });

        $query->when($this->search['related_companies'] ?? null, function ($query, $related_companies) {
            return $query->whereIn('cnpj', $related_companies);
        });

        $query->when($this->search['doc_types'] ?? null, function ($query, $doc_types) {
            return $query->whereIn('model', $doc_types);
        });

        $query->when($this->search['environment_types'] ?? null, function ($query, $environment_types) {
            return $query->whereIn('environment_type', $environment_types);
        });

        // Só os status do domínio das INUTILIZAÇÕES (102): status de nota não
        // pertence a esta fonte e não a zera (ver DashboardStatusScope).
        $statusInutilizacoes = DashboardStatusScope::paraInutilizacoes($this->search['doc_status'] ?? []);

        if ($statusInutilizacoes) {
            $query->whereIn('event_status', $statusInutilizacoes);
        }

        // Busca rápida do topo: protocolo ou número da faixa inutilizada.
        $query->when($this->search['quick_search'] ?? null, function ($query, $term) {
            return $query->where(function ($q) use ($term) {
                $q->where('protocol_number', $term)
                    ->orWhere('number_start', $term)
                    ->orWhere('number_end', $term);
            });
        });
    }

    protected function searchDefault($query)
    {
        // Sem filtro = TODOS os registros, de 100 em 100. O período só entra
        // quando o usuário filtra.
    }

}
