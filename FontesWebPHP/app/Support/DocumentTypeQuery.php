<?php

namespace App\Support;

use App\Livewire\Panel\Documents\Index;
use App\Models\Company;
use App\Support\DashboardStatusScope;
use App\Models\DisableDocument;
use App\Models\DiscardedDocument;
use App\Models\Document;
use App\Models\EventDocument;
use App\Models\FiscalStatus;
use App\Models\NfseDocument;
use Carbon\Carbon;

/**
 * Monta as queries da tela Documentos (por tipo). Compartilhada com o
 * relatório/download, para tela e PDF nunca divergirem. Imutável: os filtros
 * chegam pelo construtor.
 *
 * Mexer aqui afeta: /panel/documents, o relatório e o ZIP.
 */
class DocumentTypeQuery
{
    /** Teto de documentos por download/relatório; acima disso a tela trava com aviso. */
    public const MAX_ITEMS = 15000;

    public function __construct(
        public readonly string $type,
        public readonly array $statusFilter = [],
        public readonly string $companyFilter = '',
        public readonly ?string $firstDate = null,
        public readonly ?string $lastDate = null,
        /** Ambiente do documento: '1' produção, '2' homologação, '' = todos. */
        public readonly string $ambiente = '',
    ) {
    }

    /**
     * Recorta por ambiente (tpAmb) — vale para todas as abas, pois toda tabela
     * de documento tem a coluna. `environment_type` nulo conta como produção.
     */
    protected function applyAmbiente($query, string $column = 'environment_type'): void
    {
        if ($this->ambiente === '') {
            return;
        }

        if ($this->ambiente === '1') {
            $query->where(function ($q) use ($column) {
                $q->where($column, '1')->orWhereNull($column);
            });

            return;
        }

        $query->where($column, $this->ambiente);
    }

    public function config(): array
    {
        return Index::types()[$this->type];
    }

    /**
     * Tabela que alimenta a listagem principal. Marcar SÓ "Inutilizada" numa
     * página por modelo troca a fonte para disable_documents; combinada com
     * outros status, elas vão para a tabela secundária (ver extraInutilizacoes).
     */
    public function effectiveSource(): string
    {
        $cfg = $this->config();

        if ($cfg['source'] === 'documents' && $this->statusFilter === ['inutilizada']) {
            return 'disables';
        }

        // Só chips do ledger numa página NF-e/NFC-e: a principal vira
        // fiscal_status — nota recusada não existe em `documents`.
        if ($cfg['source'] === 'documents' && $this->fiscalModels() !== []
            && $this->statusFilter !== []
            && array_diff($this->statusFilter, array_keys(self::LEDGER_CHIPS)) === []) {
            return 'rejeicoes';
        }

        return $cfg['source'];
    }

    /**
     * Chips que saem do ledger `fiscal_status` (não existem em `documents`):
     * chip => categorias cobertas. "Duplicidade" não tem chip próprio — lista
     * dentro de "Rejeitada" e aparece pelo badge da linha.
     */
    public const LEDGER_CHIPS = [
        'rejeitada' => [FiscalStatus::CATEGORY_REJEITADA, FiscalStatus::CATEGORY_DUPLICIDADE],
    ];

    /** Categorias do ledger pedidas pelos chips marcados. */
    public function ledgerCategorias(): array
    {
        $categorias = [];

        foreach (self::LEDGER_CHIPS as $chip => $cobertas) {
            if (in_array($chip, $this->statusFilter, true)) {
                $categorias = array_merge($categorias, $cobertas);
            }
        }

        return $categorias;
    }

    /** Modelos da página que existem no ledger fiscal_status (NF-e/NFC-e/CT-e/MDF-e/CT-e OS). */
    public function fiscalModels(): array
    {
        return array_values(array_intersect((array) $this->config()['model'], [55, 65, 57, 58, 67]));
    }

    /** Status selecionados que filtram a tabela `documents` (tudo menos inutilizada). */
    protected function docStatusKeys(): array
    {
        return array_values(array_diff($this->statusFilter, ['inutilizada']));
    }

    /** CNPJs visíveis ao usuário (respeita o global scope linked_user). */
    protected function companies()
    {
        return Company::pluck('cnpj_cpf');
    }

    /**
     * CNPJs a consultar. Só aceita o filtro se a empresa estiver entre as
     * visíveis — impede que um CNPJ arbitrário fure o escopo do usuário.
     */
    public function scopedCnpjs()
    {
        $visible = $this->companies();

        if ($this->companyFilter !== '' && $visible->contains($this->companyFilter)) {
            return collect([$this->companyFilter]);
        }

        return $visible;
    }

    /** [inicio, fim] em Y-m-d H:i:s; sem filtro = TODOS. */
    public function periodBounds(): array
    {
        return [
            $this->parseDate($this->firstDate),
            $this->parseDate($this->lastDate, true),
        ];
    }

    protected function parseDate($value, bool $endOfDay = false): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // O <input type="date"> entrega Y-m-d; d/m/Y fica como fallback.
        foreach (['Y-m-d', 'd/m/Y'] as $fmt) {
            try {
                $date = Carbon::createFromFormat($fmt, $value);
            } catch (\Throwable $e) {
                $date = null;
            }

            if ($date) {
                return ($endOfDay ? $date->endOfDay() : $date->startOfDay())->toDateTimeString();
            }
        }

        return null;
    }

    /**
     * Inutilizações do recorte (null quando a regra não as inclui). Fonte única
     * da tabela secundária, da pasta `Inutilizadas/` do zip e da Visão geral —
     * a regra vem do DashboardStatusScope, a mesma do dashboard.
     */
    public function inutilizacoesDoRecorte()
    {
        $cfg = $this->config();

        if ($cfg['source'] !== 'documents') {
            return null;
        }
        if (! DashboardStatusScope::incluiInutilizacoes($this->statusFilter)) {
            return null;
        }

        [$first, $last] = $this->periodBounds();

        return $this->inutilizacoesQuery((array) $cfg['model'], $this->scopedCnpjs(), $first, $last);
    }

    /** As inutilizações do recorte, materializadas (lista secundária / zip). */
    public function extraInutilizacoes()
    {
        return $this->inutilizacoesDoRecorte()?->get();
    }

    /**
     * Linhas do ledger sem nota para a tabela secundária — quando um chip do
     * ledger está marcado junto com outros status. Sozinho, ele já é a tabela
     * principal.
     */
    public function extraRejeicoes()
    {
        if ($this->config()['source'] !== 'documents' || $this->fiscalModels() === []) {
            return null;
        }

        $categorias = $this->ledgerCategorias();

        if ($categorias === [] || $this->effectiveSource() === 'rejeicoes') {
            return null;
        }

        [$first, $last] = $this->periodBounds();

        return $this->rejeicoesQuery($this->fiscalModels(), $this->scopedCnpjs(), $first, $last, $categorias)->get();
    }

    /**
     * Linhas do ledger sem nota, por modelo/empresa/período. `cnpj_emit` é só
     * dígitos, então normaliza antes de comparar; o whereNotExists evita
     * duplicar chave que já está em `documents`.
     */
    public function rejeicoesQuery(array $models, $companies, ?string $first, ?string $last, ?array $categorias = null)
    {
        $cnpjs = collect($companies)
            ->map(fn ($c) => preg_replace('/\D/', '', (string) $c))
            ->filter()->unique()->values();

        $q = FiscalStatus::query()
            ->whereIn('category', $categorias ?: [FiscalStatus::CATEGORY_REJEITADA])
            ->whereIn('model', $models)
            ->whereIn('cnpj_emit', $cnpjs->all())
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('documents')
                    ->whereColumn('documents.key', 'fiscal_status.key');
            });

        // Rejeição sem data (o 217 não traz dhRecbto) não pode sumir no recorte
        // de período: NULL passa sempre.
        if ($first) {
            $q->where(fn ($w) => $w->whereNull('dh_recbto')->orWhere('dh_recbto', '>=', $first));
        }
        if ($last) {
            $q->where(fn ($w) => $w->whereNull('dh_recbto')->orWhere('dh_recbto', '<=', $last));
        }

        $this->applyAmbiente($q);

        return $q->orderByDesc('dh_recbto')->orderByDesc('id');
    }

    /** Total de rejeições sem nota do tipo/filtros atuais (p/ o contador do chip). */
    public function rejeicoesCount(): int
    {
        return $this->ledgerCount('rejeitada');
    }

    /**
     * Total sem nota de um chip do ledger, para o contador do cabeçalho. Ignora
     * os chips marcados: o contador é do tipo, não do recorte.
     */
    public function ledgerCount(string $chip): int
    {
        $categorias = self::LEDGER_CHIPS[$chip] ?? null;

        if ($categorias === null || $this->config()['source'] !== 'documents' || $this->fiscalModels() === []) {
            return 0;
        }

        [$first, $last] = $this->periodBounds();

        return (int) $this->rejeicoesQuery(
            $this->fiscalModels(), $this->scopedCnpjs(), $first, $last, $categorias
        )->count();
    }

    /**
     * Query base das inutilizações (disable_documents) por modelo/empresa/período.
     * Fonte única de tudo que mostra inutilização nesta tela.
     *
     * Não filtra por event_status de propósito: recusa costuma ser cStat 563
     * ("já existe pedido com a mesma faixa"), ou seja, a faixa JÁ está
     * inutilizada — esconder faria o portal mostrar número indisponível como livre.
     */
    public function inutilizacoesQuery(array $models, $companies, ?string $first, ?string $last)
    {
        $q = DisableDocument::query()
            ->whereIn('cnpj', $companies)
            ->whereIn('model', $models);

        if ($first) {
            $q->where('event_dh', '>=', $first);
        }
        if ($last) {
            $q->where('event_dh', '<=', $last);
        }

        $this->applyAmbiente($q);

        return $q->orderByDesc('event_dh')->orderByDesc('id');
    }

    public function baseQuery()
    {
        $cfg = $this->config();
        $source = $this->effectiveSource();
        $companies = $this->scopedCnpjs();
        [$first, $last] = $this->periodBounds();

        if ($source === 'documents') {
            $query = Document::query()
                ->whereIn('cnpj_cpf', $companies)
                ->whereIn('model', (array) $cfg['model']);

            if ($first) {
                $query->where('issue_dh', '>=', $first);
            }
            if ($last) {
                $query->where('issue_dh', '<=', $last);
            }
            // Só status de NOTA; inutilizada, se marcada, vira tabela à parte.
            $this->applyStatusFilter($query, 'status_xml', $this->docStatusKeys());

            $this->applyAmbiente($query);

            return $query->orderByDesc('issue_dh')->orderByDesc('number');
        }

        if ($source === 'nfse') {
            $query = NfseDocument::query()->whereIn('cnpj_prestador', $companies);

            if ($first) {
                $query->where('issue_dh', '>=', $first);
            }
            if ($last) {
                $query->where('issue_dh', '<=', $last);
            }
            $this->applyStatusFilterTextual($query, 'situacao');

            $this->applyAmbiente($query);

            return $query->orderByDesc('issue_dh')->orderByDesc('numero');
        }

        // Só inutilizada numa página por modelo: a principal é a de inutilizações.
        if ($source === 'disables' && $cfg['source'] === 'documents') {
            return $this->inutilizacoesQuery((array) $cfg['model'], $companies, $first, $last);
        }

        // Só chips do ledger numa página NF-e/NFC-e: a principal é o fiscal_status.
        if ($source === 'rejeicoes') {
            return $this->rejeicoesQuery(
                $this->fiscalModels(), $companies, $first, $last, $this->ledgerCategorias()
            );
        }

        // Descartes do sistema: tabela propria, alimentada so pelo canal do ERP.
        if ($source === 'discards') {
            $query = DiscardedDocument::query()->whereIn('cnpj_cpf', $companies);

            if ($first) {
                $query->where('issue_dh', '>=', $first);
            }
            if ($last) {
                $query->where('issue_dh', '<=', $last);
            }
            $this->applyAmbiente($query);

            return $query->orderByDesc('issue_dh')->orderByDesc('number');
        }

        // tipos nativos: cancelamentos (events) e inutilizacoes (disables)
        $model = $source === 'events' ? EventDocument::query() : DisableDocument::query();
        $model->whereIn('cnpj', $companies);

        if ($first) {
            $model->where('event_dh', '>=', $first);
        }
        if ($last) {
            $model->where('event_dh', '<=', $last);
        }
        $this->applyStatusFilter($model, 'event_status');

        $this->applyAmbiente($model);

        return $model->orderByDesc('event_dh')->orderByDesc('id');
    }

    /**
     * Aplica o filtro de status por GRUPOS: reúne os códigos dos grupos marcados
     * e, se "rejeitada" estiver marcado, inclui tudo fora dos códigos conhecidos.
     */
    protected function applyStatusFilter($query, string $column, ?array $keys = null): void
    {
        $keys = $keys ?? $this->statusFilter;

        if (empty($keys)) {
            return;
        }

        $groups = Index::statusGroups();
        $codes = [];
        $catchall = false;

        foreach ($keys as $key) {
            $g = $groups[$key] ?? null;
            if (! $g) {
                continue;
            }
            if ($g['catchall'] ?? false) {
                $catchall = true;
                continue;
            }
            $codes = array_merge($codes, $g['codes'] ?? []);
        }

        // Se só há grupos textuais (NFS-e, sem código) e nada mais, não filtra por código.
        if (empty($codes) && ! $catchall) {
            return;
        }

        $known = Index::knownCodes();

        $query->where(function ($q) use ($codes, $catchall, $column, $known) {
            if (! empty($codes)) {
                $q->orWhereIn($column, $codes);
            }
            if ($catchall) {
                // Faixa de rejeição (2xx–7xx), igual a groupForCode() e
                // tallyForGroup() — senão a listagem diverge do contador do chip.
                $q->orWhere(function ($w) use ($column, $known) {
                    $w->where($column, '>=', 200)->whereNotIn($column, $known);
                });
            }
        });
    }

    /**
     * Filtro de status TEXTUAL (NFS-e): mapeia os grupos nfse_* marcados para as
     * strings de `situacao` gravadas no import (o rótulo do grupo = a situação).
     */
    protected function applyStatusFilterTextual($query, string $column): void
    {
        if (empty($this->statusFilter)) {
            return;
        }

        $groups = Index::statusGroups();
        $labels = [];
        foreach ($this->statusFilter as $key) {
            $g = $groups[$key] ?? null;
            if ($g && ($g['text'] ?? false)) {
                $labels[] = $g['label'];
            }
        }

        if (! empty($labels)) {
            $query->whereIn($column, $labels);
        }
    }
}
