<?php

namespace App\Support;

use App\Models\Company;
use App\Models\FiscalStatus;

/**
 * Queries da aba "Status SEFAZ" (/panel/fiscal-status) e do relatório dela.
 * O universo é rejeitada ∪ contingência, com escopo por empresa, período,
 * busca e chips.
 *
 * ⚠️ Admin NÃO é escopado (diferente do DocumentTypeQuery): ele vê inclusive
 * CNPJ sem Company cadastrada, senão o relatório sairia com menos linhas que a
 * tela.
 */
class FiscalStatusQuery
{
    /** Teto de linhas do relatório (espelha DocumentTypeQuery::MAX_ITEMS). */
    public const MAX_ITEMS = 15000;

    /** Whitelist dos chips — vale para a tela E para a query string do relatório. */
    public const FILTERS = ['rejeitada', 'contingencia'];

    protected array $filters;

    public function __construct(
        array $filters = [],
        protected string $companyFilter = '',
        protected ?string $firstDate = null,
        protected ?string $lastDate = null,
        protected string $search = '',
        /** Ambiente: '1' produção, '2' homologação, '' = todos. */
        protected string $ambiente = '',
    ) {
        // is_string antes do intersect: tela e relatório aceitam entrada
        // arbitrária, e valor aninhado estouraria "Array to string conversion".
        $this->filters = array_values(array_intersect(
            array_filter($filters, 'is_string'),
            self::FILTERS
        ));
    }

    /** CNPJs visíveis ao usuário (Company já vem escopado pelo linked_user). */
    public function visibleCnpjs()
    {
        return Company::query()->pluck('cnpj_cpf')
            ->map(fn ($c) => preg_replace('/\D/', '', (string) $c))
            ->filter()->unique()->values();
    }

    /** Universo da aba + escopo/período/busca — sem os chips, que contam sobre ela. */
    public function baseQuery()
    {
        $cnpjs = $this->visibleCnpjs();

        return FiscalStatus::query()
            ->rejeitadaOuContingencia()
            ->when(! auth('web')->user()?->isAdmin(),
                fn ($q) => $q->whereIn('cnpj_emit', $cnpjs->all() ?: ['__nenhum__']))
            ->when($this->companyFilter !== '' && $cnpjs->contains($this->companyFilter),
                fn ($q) => $q->where('cnpj_emit', $this->companyFilter))
            // Período NULL-safe: comparação direta apagaria linha sem dh_recbto
            // (o 217 não ecoa a data). O OR fica preso na closure para não
            // vazar o escopo por empresa.
            ->when((string) $this->firstDate !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('dh_recbto')->orWhere('dh_recbto', '>=', $this->firstDate . ' 00:00:00')))
            ->when((string) $this->lastDate !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('dh_recbto')->orWhere('dh_recbto', '<=', $this->lastDate . ' 23:59:59')))
            ->when(trim($this->search) !== '', function ($q) {
                $s = trim($this->search);
                $q->where(fn ($qq) => $qq
                    ->where('key', 'like', "%{$s}%")
                    ->orWhere('number', 'like', "%{$s}%"));
            })
            // Ambiente (tpAmb): homologação convive com produção no mesmo
            // ledger. NULL conta como produção (o canal do ERP não guarda tpAmb).
            ->when($this->ambiente === '1', fn ($q) => $q->where(fn ($w) => $w
                ->where('environment_type', '1')->orWhereNull('environment_type')))
            ->when($this->ambiente === '2', fn ($q) => $q->where('environment_type', '2'));
    }

    /**
     * baseQuery + chips + ordenação. Os chips são mutuamente exclusivos (uma
     * nota tem UMA situação), então marcar os dois é união, não interseção.
     */
    public function rowsQuery()
    {
        $filters = $this->filters;

        return $this->baseQuery()
            ->when($filters !== [], fn ($q) => $q->where(function ($w) use ($filters) {
                if (in_array('rejeitada', $filters, true)) {
                    $w->orWhere(fn ($x) => $x->rejeitada());
                }
                if (in_array('contingencia', $filters, true)) {
                    $w->orWhere(fn ($x) => $x->emContingencia());
                }
            }))
            ->orderByDesc('dh_recbto')
            ->orderByDesc('id');
    }

    /**
     * Contagem de cada chip sobre o universo. Como são exclusivos,
     * rejeitada + contingencia === total (invariante travado em teste).
     *
     * @return array{rejeitada: int, contingencia: int}
     */
    public function counts(): array
    {
        return [
            'rejeitada'    => $this->baseQuery()->rejeitada()->count(),
            'contingencia' => $this->baseQuery()->emContingencia()->count(),
        ];
    }

    /** Total do universo (com escopo/período/busca, sem os chips). */
    public function total(): int
    {
        return (int) $this->baseQuery()->count();
    }
}
