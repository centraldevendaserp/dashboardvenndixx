<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use App\Models\Document;
use App\Models\Company;
use App\Models\DisableDocument;
use App\Models\EventDocument;
use App\Support\DashboardStatusScope;
use App\Support\DisablesRecorte;
use App\Support\DocumentTypeQuery;
use App\Support\InutilizacoesOverview;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function invoices()
    {
        // $pdf = App::make('dompdf.wrapper');
        // $pdf->loadView('report.invoices', [
        //     'invoices' => $this->getInvoices(),
        //     'invoicesOverview' => $this->getInvoicesOverview(),
        //     'searchArgsToDocReport' => session('searchArgsToDocReport'),
        // ]);

        // return $pdf->stream();

        [$invoices, $capNotice] = $this->capList($this->getInvoices());

        return view('report.invoices', [
            'invoices' => $invoices,
            'invoicesOverview' => $this->getInvoicesOverview(),
            'searchArgsToDocReport' => session('searchArgsToDocReport'),
            'capNotice' => $capNotice,
            'extraDisables' => $this->extraDisables(),
        ]);
    }

    /**
     * Inutilizações que acompanham o relatório de notas (null quando a regra
     * não as inclui). Elas vivem em `disable_documents`, então sem isto o PDF
     * da aba "Documento Fiscal" nunca as mostraria.
     *
     * Base única da lista secundária e do resumo, para os dois não discordarem
     * dentro do mesmo PDF. A regra é a do DashboardStatusScope; a sessão guarda
     * cStat e a regra fala grupos, daí o grupos().
     */
    protected function disablesDoRecorte()
    {
        if (session('docType') === 'authorized') {
            return null;
        }

        $codigos = session('searchArgsToDocReport')['doc_status'] ?? [];

        if (! DashboardStatusScope::incluiInutilizacoes(DashboardStatusScope::grupos($codigos))) {
            return null;
        }

        // Lista e resumo saem do mesmo lugar, de propósito.
        return DisableDocument::where(function ($query) {
            $this->querySearchDisables($query);
        })->whereIn('cnpj', $this->getCompanies('cnpj_cpf'));
    }

    /** As inutilizações do recorte, materializadas (lista secundária do PDF). */
    protected function extraDisables()
    {
        return $this->disablesDoRecorte()
            ?->orderBy('cnpj')->orderBy('series')->orderBy('model')->orderBy('event_dh')
            ->get();
    }

    public function events()
    {
        [$events, $capNotice] = $this->capList($this->getEvents());

        return view('report.events', [
            'events' => $events,
            'searchArgsToDocReport' => session('searchArgsToDocReport'),
            'capNotice' => $capNotice,
        ]);
    }

    public function disables()
    {
        [$disables, $capNotice] = $this->capList($this->getDisables());

        return view('report.disables', [
            'disables' => $disables,
            'searchArgsToDocReport' => session('searchArgsToDocReport'),
            'capNotice' => $capNotice,
        ]);
    }

    /**
     * Teto do relatório: lista só os primeiros MAX_ITEMS e avisa no banner —
     * sem ele, o volume de produção estoura a memória. A Visão geral, agregada
     * no banco, continua cobrindo o filtro completo.
     */
    private function capList($all): array
    {
        $total = $all->count();

        if ($total <= DocumentTypeQuery::MAX_ITEMS) {
            return [$all, null];
        }

        return [
            $all->take(DocumentTypeQuery::MAX_ITEMS),
            'Mostrando os primeiros ' . number_format(DocumentTypeQuery::MAX_ITEMS, 0, ',', '.')
                . ' de ' . number_format($total, 0, ',', '.') . ' registros — refine o período para o relatório completo.',
        ];
    }

    public function getInvoices($ins = null)
    {
        $documents = Document::where(function ($query) {
            $this->querySearchInvoices($query);
        })->whereIn('cnpj_cpf', $this->getCompanies('cnpj_cpf'));

        if (session('docType') === 'authorized') {
            $documents->where('status_xml', 100);
        }

        $documents->when($ins, function ($query, $ins) {
            return $query->whereIn('id', $ins);
        })
        ->orderBy('cnpj_cpf', 'ASC')
        ->orderBy('series', 'ASC')
        ->orderBy('model', 'ASC')
        ->orderBy('number', 'ASC')
        ->orderBy('issue_dh', 'ASC');

        return $documents->get();
    }

    public function getEvents($ins = null)
    {
        $events = EventDocument::where(function ($query) {
            $this->querySearchEvents($query);
        })->whereIn('cnpj', $this->getCompanies('cnpj_cpf'));

        $events->when($ins, function ($query, $ins) {
            return $query->whereIn('id', $ins);
        })
        ->orderBy('cnpj', 'ASC')
        ->orderBy('model', 'ASC')
        ->orderBy('event_number', 'ASC')
        ->orderBy('event_dh', 'ASC');

        return $events->get();
    }

    public function getDisables($ins = null)
    {
        $disables = DisableDocument::where(function ($query) {
            $this->querySearchDisables($query);
        })->whereIn('cnpj', $this->getCompanies('cnpj_cpf'));

        $disables->when($ins, function ($query, $ins) {
            return $query->whereIn('id', $ins);
        })
        ->orderBy('cnpj', 'ASC')
        ->orderBy('series', 'ASC')
        ->orderBy('model', 'ASC')
        ->orderBy('event_dh', 'ASC');

        return $disables->get();
    }

    public function getInvoicesOverview($ins = null)
    {
        DB::statement('SET sql_mode=""');
        DB::statement('SET lc_time_names = "pt_BR"');

        // Sem 102/135: nenhum existe em `documents`. Inutilização é faixa de
        // numeração (vem do InutilizacoesOverview) e o 135 colapsa em 101.
        $documents = Document::selectRaw('
            COUNT(id) as qty,
            CASE status_xml
                WHEN 100 THEN "Autorizada"
                WHEN 101 THEN "Cancelada"
                WHEN 110 THEN "Uso denegado"
            END AS status_xml,
            CASE model
                WHEN 55 THEN "NF-e"
                WHEN 57 THEN "CT-e"
                WHEN 58 THEN "MDF-e"
                WHEN 59 THEN "Entrada"
                WHEN 65 THEN "NFC-e"
            END AS model,
            SUM(vNF) AS total
        ')->where(function ($query) {
            $this->querySearchInvoices($query);
        })->whereIn('cnpj_cpf', $this->getCompanies('cnpj_cpf'));

        $documents->when($ins, function ($query, $ins) {
            return $query->whereIn('id', $ins);
        });

        $documents->groupBy('model', 'status_xml');

        // Inutilizações no resumo: não estão em `documents`, então sem isto a
        // Visão geral omitiria o que a seção do mesmo PDF lista.
        return array_merge(
            $documents->get()->toArray(),
            InutilizacoesOverview::linhas($this->disablesDoRecorte())
        );
    }

    public function getCompanies($pluck)
    {
        return Company::get()->pluck($pluck);
    }

    protected function querySearchInvoices($query)
    {
        $searchArgs = session('searchArgsToDocReport');

        $this->searchDefault($query, $searchArgs, 'invoice');

        if (session('docType') === 'authorized') {
            $query->where('status_xml', 100);
        }

        if (is_null(session('searchArgsToDocReport'))) {
            return;
        }

        $query->when($searchArgs['first_date'], function ($query, $first_date) {
            return $query->where('issue_dh', '>=', $first_date);
        })->when($searchArgs['last_date'], function ($query, $last_date) {
            return $query->where('issue_dh', '<=', $last_date);
        });

        $query->when($searchArgs['doc_number'], function ($query, $doc_number) {
            return $query->where('number', $doc_number);
        });

        $query->when($searchArgs['protocol_number'], function ($query, $protocol_number) {
            return $query->where('protocol', $protocol_number);
        });

        $query->when($searchArgs['related_companies'], function ($query, $related_companies) {
            return $query->whereIn('cnpj_cpf', $related_companies);
        });

        $query->when($searchArgs['doc_types'], function ($query, $doc_types) {
            return $query->whereIn('model', $doc_types);
        });

        $query->when($searchArgs['environment_types'], function ($query, $environment_types) {
            return $query->whereIn('environment_type', $environment_types);
        });

        $query->when($searchArgs['doc_status'], function ($query, $doc_status) {
            return $query->whereIn('status_xml', $doc_status);
        });

        // Busca rápida do dashboard: o relatório espelha a tela.
        $query->when($searchArgs['quick_search'] ?? null, function ($query, $term) {
            return $query->where(function ($q) use ($term) {
                $q->where('number', $term)
                    ->orWhere('protocol', $term)
                    ->orWhere('key', $term);
            });
        });
    }

    protected function querySearchEvents($query)
    {
        $searchArgs = session('searchArgsToDocReport');

        $this->searchDefault($query, $searchArgs, 'event');

        if (is_null($searchArgs)) {
            return;
        }

        $query->when($searchArgs['first_date'], function ($query, $first_date) {
            return $query->where('event_dh', '>=', $first_date);
        })->when($searchArgs['last_date'], function ($query, $last_date) {
            return $query->where('event_dh', '<=', $last_date);
        });

        $query->when($searchArgs['doc_number'], function ($query, $doc_number) {
            return $query->where('event_number', $doc_number);
        });

        $query->when($searchArgs['protocol_number'], function ($query, $protocol_number) {
            return $query->where('protocol_number', $protocol_number);
        });

        $query->when($searchArgs['related_companies'], function ($query, $related_companies) {
            return $query->whereIn('cnpj', $related_companies);
        });

        $query->when($searchArgs['doc_types'], function ($query, $doc_types) {
            return $query->whereIn('model', $doc_types);
        });

        $query->when($searchArgs['environment_types'], function ($query, $environment_types) {
            return $query->whereIn('environment_type', $environment_types);
        });

        $query->when($searchArgs['doc_status'], function ($query, $doc_status) {
            return $query->whereIn('event_status', $doc_status);
        });

        $query->when($searchArgs['quick_search'] ?? null, function ($query, $term) {
            return $query->where(function ($q) use ($term) {
                $q->where('nfe_key', $term)
                    ->orWhere('protocol_number', $term);
            });
        });
    }

    protected function querySearchDisables($query)
    {
        // Mesmo recorte do "Baixar XML" — ver App\Support\DisablesRecorte.
        DisablesRecorte::aplicar($query, session('searchArgsToDocReport'));
    }

    protected function searchDefault($query, $search, $docType)
    {
        // Whitelist tipo -> coluna de data. O default ('invoice'/issue_dh)
        // garante que o periodo sempre e aplicado, mesmo com tipo desconhecido.
        $dateColumns = [
            'invoice' => 'issue_dh',
            'event'   => 'event_dh',
            'disable' => 'event_dh',
        ];

        $dateColumn = $dateColumns[$docType] ?? $dateColumns['invoice'];

        // Sem filtro = TODOS: o relatório espelha a tela.
        unset($dateColumn);
    }
}
