<div class="pnl-dash">

    <div class="pnl-section">
        <div class="pnl-card">

            <div class="pnl-card__head">
                <div>
                    <h3>Status SEFAZ</h3>
                    <span class="pnl-sub">último status conhecido por chave (retorno de lote / consulta de situação)</span>
                </div>
            </div>

            <div class="pnl-card__body">

                @php($catMeta = [
                    'autorizada'  => ['label' => 'Autorizada',  'color' => 'green'],
                    'cancelada'   => ['label' => 'Cancelada',   'color' => 'red'],
                    'denegada'    => ['label' => 'Denegada',    'color' => 'default'],
                    'inutilizada' => ['label' => 'Inutilizada', 'color' => 'red'],
                    'encerrado'   => ['label' => 'Encerrado',   'color' => 'blue'],
                    'rejeitada'   => ['label' => 'Rejeitada',   'color' => 'red'],
                    'contingencia' => ['label' => 'Contingência', 'color' => 'amber'],
                ])
                @php($tipos = [55 => 'NF-e', 65 => 'NFC-e', 57 => 'CT-e', 58 => 'MDF-e', 67 => 'CT-e OS'])
                @php($classesModelo = [55 => 'nfe', 65 => 'nfce', 57 => 'cte', 67 => 'cte', 58 => 'mdfe'])

                {{-- Chips mutuamente exclusivos (a nota tem UMA situação), então
                     marcar os dois é a união. Rejeitada = a SEFAZ recusou;
                     Contingência = emitida offline e a SEFAZ ainda não a tem. --}}
                @php($chips = [
                    'rejeitada'    => ['label' => 'Rejeitada',    'color' => 'red'],
                    'contingencia' => ['label' => 'Contingência', 'color' => 'amber'],
                ])

                {{-- Filtros: busca + empresa + chips por categoria (com contagem) --}}
                <div class="doc-filterbar">

                    <div class="doc-period">
                        <input type="date" class="doc-period__date" aria-label="Data inicial"
                            wire:model="first_date" wire:keydown.enter="applyPeriod">
                        <span class="doc-period__sep">até</span>
                        <input type="date" class="doc-period__date" aria-label="Data final"
                            wire:model="last_date" wire:keydown.enter="applyPeriod">
                        {{-- Ambiente (tpAmb): rejeicao de homologacao convive com a real
                             no mesmo ledger. --}}
                        <select class="doc-period__ambiente" wire:model.live="ambiente"
                            aria-label="Ambiente de emissão" title="Ambiente de emissão">
                            <option value="">Todos os ambientes</option>
                            <option value="1">Produção</option>
                            <option value="2">Homologação</option>
                        </select>

                        <button type="button" class="doc-period__apply" wire:click="applyPeriod">Aplicar</button>

                        {{-- Relatório imprimível (nova aba, filtros na URL), no mesmo
                             arranjo da tela Documentos. Sem "Baixar XML": rejeição não
                             tem XML no portal. --}}
                        <div class="doc-actions">
                            <a href="{{ route('panel.fiscal_status.report', array_filter([
                                'company'    => $company_filter,
                                'first_date' => $first_date,
                                'ambiente'   => $ambiente,
                                'last_date'  => $last_date,
                                'filters'    => $filters,
                                'search'     => $search,
                            ], fn ($v) => $v !== '' && $v !== null && $v !== [])) }}"
                                target="_blank" class="pnl-iconbtn" title="Relatório" aria-label="Relatório">
                                <i class="fas fa-file-alt"></i>
                            </a>
                        </div>

                        @if ($companies->count() > 1)
                            <select class="doc-period__company" wire:model.live="company_filter"
                                aria-label="Filtrar por empresa">
                                <option value="">Todas as empresas</option>
                                @foreach ($companies as $company)
                                    <option value="{{ preg_replace('/\D/', '', $company->cnpj_cpf) }}">
                                        {{ \Illuminate\Support\Str::upper($company->fantasy_name ?: $company->corporate_name) }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    {{-- Busca no padrão das listagens (pnl-search, custom.css) --}}
                    <label class="pnl-search">
                        <i class="fas fa-search"></i>
                        <input type="search" wire:model.live.debounce.400ms="search"
                            placeholder="Buscar por chave ou número…" aria-label="Buscar por chave ou número">
                        @if (trim($search) !== '')
                            <button type="button" class="pnl-search__clear" title="Limpar busca"
                                wire:click="$set('search', '')"><i class="fas fa-times"></i></button>
                        @endif
                    </label>

                    <div class="doc-chips" role="group" aria-label="Filtrar por situação">
                        <button type="button" class="doc-chip {{ $filters === [] ? 'is-on' : '' }}"
                            wire:click="clearFilters">Todas ({{ number_format($total, 0, ',', '.') }})</button>
                        @foreach ($chips as $slug => $meta)
                            <button type="button"
                                class="doc-chip doc-chip--{{ $meta['color'] }} {{ in_array($slug, $filters, true) ? 'is-on' : '' }}"
                                wire:click="toggleFilter('{{ $slug }}')">
                                {{ $meta['label'] }} ({{ number_format($counts[$slug] ?? 0, 0, ',', '.') }})
                            </button>
                        @endforeach
                    </div>

                </div>

                <div class="table-wrap fs-table {{ $statuses->lastPage() == 1 ? 'mb-30' : '' }}">
                    <table>
                        <thead>
                            <tr>
                                <th class="text-center">Data</th>
                                <th class="text-center">Tipo</th>
                                <th class="text-center">N.º / Série</th>
                                <th class="text-center">Chave</th>
                                <th class="text-center">Situação</th>
                                <th class="text-left">Motivo</th>
                                <th class="text-center">Ambiente</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($statuses as $st)
                                @php($meta = $catMeta[$st->categoriaEfetiva()] ?? ['label' => $st->categoriaEfetiva(), 'color' => 'default'])
                                <tr wire:key="fs-{{ $st->id }}">
                                    {{-- Sem hora, mostra só a data: o ERP manda a emissão e a
                                         hora às vezes vem nula — "00:00" seria lido como fato. --}}
                                    <td class="text-center">{{ $st->dataHoraLegivel() }}</td>
                                    {{-- Cor por modelo, mesmo vocabulario dos cards do dashboard
                                         (--m-nfe, --m-nfce...). --}}
                                    <td class="text-center">
                                        <span class="fs-modelo fs-modelo--{{ $classesModelo[(int) $st->model] ?? 'outros' }}">
                                            {{ $tipos[(int) $st->model] ?? $st->model }}
                                        </span>
                                    </td>
                                    <td class="text-center fs-mono">{{ $st->number }} / {{ $st->series }}</td>
                                    <td class="text-center fs-mono"><span title="{{ $st->key }}">…{{ substr($st->key, -10) }}</span></td>
                                    <td class="text-center">
                                        {{-- ⚠️ cStat em TERNÁRIO, não @if: o Livewire envolve @if de
                                             conteúdo em comentários de morph, que entrariam entre o
                                             rótulo e o "(cstat)". --}}
                                        <span class="badge badge-{{ $meta['color'] }} badge-round"
                                            title="{{ $st->x_motivo }}@if ($st->estaEmContingencia()) — Contingência: {{ $st->contingenciaLabel() }}@endif">{{ $meta['label'] }}{{ is_null($st->cstat) ? '' : ' ('.$st->cstat.')' }}</span>
                                    </td>
                                    <td class="text-left fs-motivo">{{ $st->x_motivo ?? '—' }}</td>
                                    {{-- Selo, nao texto solto: em homologacao a linha nao e
                                         pendencia real, e passava despercebida na coluna. --}}
                                    <td class="text-center">
                                        @if ($st->environment_type === '2')
                                            @include('livewire.panel.documents._selo-homolog', ['ambiente' => $st->environment_type])
                                        @elseif ($st->environment_type === '1')
                                            Produção
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">
                                        Nenhuma nota rejeitada ou em contingência no filtro atual.
                                        Os status chegam pelo agente (campo status_upload_xml do .ini).
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($statuses->hasPages())
                    {{ $statuses->links() }}
                @endif

            </div>

        </div>
    </div>

</div>
