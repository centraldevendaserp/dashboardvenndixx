<?php

namespace App\Livewire\Panel\Dashboard;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Document;
use App\Models\Company;
use App\Models\DisableDocument;
use App\Support\DashboardStatusScope;
use App\Support\DisablesRecorte;
use App\Support\DocumentTypeQuery;
use App\Support\DownloadCleanup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;
use ZipArchive;

class Invoice extends Component
{
    use WithPagination;

    public $user;
    public string $doc_type = 'invoice';

    public $search;
    protected $listeners = ['eventDocsSearch', 'eventDownloadCompressedDoc', 'eventDocType'];

    public function mount($user, $doc_type = 'invoice')
    {
        $this->user = $user;
        $this->doc_type = $doc_type;
    }

    public function render()
    {
        return view('livewire.panel.dashboard.invoice', [
            'invoices' => $this->getInvoices(true)
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

    public function eventDocType($type): void
    {
        $this->doc_type = (string) $type;
        $this->resetPage();
    }

    public function eventDownloadCompressedDoc()
    {
        $documents = $this->getInvoices(false);
        $extras = $this->extraDisables();
        $total = $documents->count() + $extras->count();

        if ($total === 0) {
            $this->dispatch(
                'eventCuteToast',
                msg: "Nenhuma nota disponível para download!",
                code: 400
            );
            $this->dispatch('zip-pronto'); // spinner do botão solta com folga
            return;
        }

        // Teto por download: acima disso o zip leva minutos e o envio estoura
        // a memória. As inutilizações contam, senão o aviso de limite mentiria.
        if ($total > DocumentTypeQuery::MAX_ITEMS) {
            $this->dispatch(
                'eventCuteToast',
                msg: 'São ' . number_format($total, 0, ',', '.') . ' documentos no filtro — o limite por download é '
                    . number_format(DocumentTypeQuery::MAX_ITEMS, 0, ',', '.') . '. Refine o período.',
                code: 400
            );
            $this->dispatch('zip-pronto'); // spinner do botão solta com folga
            return;
        }

        $zip = new ZipArchive();

        $time = time();
        $name = "invoice-{$this->user->id}-{$time}.zip";
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

            $policy = Gate::inspect('access-invoice', $document);

            if ($policy->denied()) {
                continue;
            }

            $status = 'Outros';

            switch ($document->status_xml) {
                case 100:
                    $status = 'Autorizadas';
                    break;
                case 101:
                    $status = 'Canceladas';
                    break;
                case 110:
                    $status = 'Denegadas';
                    break;
                default:
                    $status = 'Outros';
                    break;
            }

            $model = 'Outros';

            switch ($document->model) {
                case 55:
                    $model = 'NF-e';
                    break;
                case 57:
                    $model = 'CT-e';
                    break;
                case 58:
                    $model = 'MDF-e';
                    break;
                case 59:
                    $model = 'Entrada';
                    break;
                case 65:
                    $model = 'NFC-e';
                    break;
                default:
                    $model = 'Outros';
                    break;
            }
            $path_download = "{$document->cnpj_cpf}/{$model}/{$status}/{$document->key}";
            //$path_download = "{$document->cnpj_cpf}/{$document->ie}/{$model}/{$status}/{$document->month_year}/{$document->key}";

            if (File::exists(storage_path("app{$document->path_xml}"))) {
                $zip->addFile(storage_path("app{$document->path_xml}"), "{$path_download}.xml");
            }
        }

        // Inutilizações vão em pasta própria: não têm chave nem status para
        // entrar na árvore CNPJ/Modelo/Status acima.
        foreach ($extras as $disable) {
            if (Gate::inspect('access-disable-document', $disable)->denied()) {
                continue;
            }

            $abs = storage_path("app{$disable->path_xml}");

            if (blank($disable->path_xml) || ! File::exists($abs)) {
                continue;
            }

            $zip->addFile($abs, 'Inutilizadas/' . basename((string) $disable->path_xml));
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

    /**
     * Inutilizações que acompanham o zip da aba "Documento Fiscal". Vivem em
     * `disable_documents`, então sem isto o "Baixar XML" nunca as traria.
     *
     * A regra é a do DashboardStatusScope e o recorte sai do DisablesRecorte —
     * os mesmos do Relatório, para o zip e o PDF não discordarem.
     */
    protected function extraDisables()
    {
        // AUTORIZADAS é recorte de status_xml=100: não é "todos", não traz.
        if ($this->doc_type === 'authorized') {
            return collect();
        }

        $grupos = DashboardStatusScope::grupos($this->search['doc_status'] ?? []);

        if (! DashboardStatusScope::incluiInutilizacoes($grupos)) {
            return collect();
        }

        $disables = DisableDocument::whereIn('cnpj', $this->getCompanies());

        DisablesRecorte::aplicar($disables, $this->search);

        return $disables->orderByDesc('event_dh')->get();
    }

    public function getInvoices($paginate = false)
    {
        $documents = Document::query()->where(function ($query) {
            $this->querySearch($query);
        })->whereIn('cnpj_cpf', $this->getCompanies());

        if ($this->doc_type === 'authorized') {
            $documents->where('status_xml', 100);
        }

        // Mais recentes primeiro; o número desempata.
        $documents->orderBy('issue_dh', 'DESC')
            ->orderBy('number', 'DESC');

        if ($paginate) {
            return $documents->paginate(100, pageName: 'invoices');
        }

        return $documents->get();
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
        $document = Document::where('id', $id)->first();

        if (is_null($document)) {
            $this->dispatch(
                'eventCuteToast',
                msg: "Nao existe o arquivo para download.",
                code: 404
            );
            return;
        }

        $policy = Gate::inspect('access-invoice', $document);

        if ($policy->denied()) {
            $this->dispatch(
                'eventCuteToast',
                msg: "Não autorizado.",
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
            return $query->where('issue_dh', '>=', $first_date);
        })->when($this->search['last_date'] ?? null, function ($query, $last_date) {
            return $query->where('issue_dh', '<=', $last_date);
        });

        $query->when($this->search['doc_number'] ?? null, function ($query, $doc_number) {
            return $query->where('number', $doc_number);
        });

        $query->when($this->search['protocol_number'] ?? null, function ($query, $protocol_number) {
            return $query->where('protocol', $protocol_number);
        });

        $query->when($this->search['related_companies'] ?? null, function ($query, $related_companies) {
            return $query->whereIn('cnpj_cpf', $related_companies);
        });

        $query->when($this->search['doc_types'] ?? null, function ($query, $doc_types) {
            return $query->whereIn('model', $doc_types);
        });

        $query->when($this->search['environment_types'] ?? null, function ($query, $environment_types) {
            return $query->whereIn('environment_type', $environment_types);
        });

        // Só os status do domínio das NOTAS (ver DashboardStatusScope).
        $statusNotas = DashboardStatusScope::paraNotas($this->search['doc_status'] ?? []);

        if ($statusNotas) {
            $query->whereIn('status_xml', $statusNotas);
        }

        // Busca rápida do topo: um termo, comparado com número, protocolo e chave.
        $query->when($this->search['quick_search'] ?? null, function ($query, $term) {
            return $query->where(function ($q) use ($term) {
                $q->where('number', $term)
                    ->orWhere('protocol', $term)
                    ->orWhere('key', $term);
            });
        });
    }

    protected function searchDefault($query)
    {
        // Sem filtro = TODOS os documentos, de 100 em 100. O período só entra
        // quando o usuário filtra.
    }

}
