<div class="pnl-dash">

    <div class="pnl-section">
        <div class="pnl-card">

            <div class="pnl-card__head">
                <div>
                    <h3>{{ $cfg['label'] }}</h3>
                    <span class="pnl-sub">
                        @if ($first_date || $last_date)
                            {{ $first_date ?: '…' }} até {{ $last_date ?: '…' }}
                        @else
                            todos os períodos
                        @endif
                    </span>
                </div>

                @if (!empty($statusCounts))
                    <div class="doc-counts" role="group" aria-label="Contagem por status">
                        @foreach ($statusCounts as $c)
                            {{-- O card "Valor" mostra dinheiro; os demais, quantidade. Fonte
                                 sem valor exibe R$ 0,00 em vez de sumir. --}}
                            @if (array_key_exists('valor', $c))
                                <span class="doc-count doc-count--valor">
                                    {{ $c['label'] }}<b>R$ {{ number_format($c['valor'], 2, ',', '.') }}</b>
                                </span>
                            @else
                                <span class="doc-count doc-count--{{ $c['color'] }}">
                                    {{ $c['label'] }}<b>{{ number_format($c['qty'], 0, ',', '.') }}</b>
                                </span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="pnl-card__body">

                {{-- Filtros: período + status (chips contextuais) --}}
                <div class="doc-filterbar">

                    <div class="doc-period">
                        <input type="date" class="doc-period__date" aria-label="Data inicial"
                            min="{{ now()->subYears(100)->format('Y-m-d') }}"
                            max="{{ now()->addYears(100)->format('Y-m-d') }}"
                            wire:model="first_date" wire:keydown.enter="applyPeriod">
                        <span class="doc-period__sep">até</span>
                        <input type="date" class="doc-period__date" aria-label="Data final"
                            min="{{ now()->subYears(100)->format('Y-m-d') }}"
                            max="{{ now()->addYears(100)->format('Y-m-d') }}"
                            wire:model="last_date" wire:keydown.enter="applyPeriod">
                        {{-- Ambiente (tpAmb): emissão de teste convive com a real na
                             mesma tabela. --}}
                        <select class="doc-period__ambiente" wire:model.live="ambiente"
                            aria-label="Ambiente de emissão" title="Ambiente de emissão">
                            <option value="">Todos os ambientes</option>
                            <option value="1">Produção</option>
                            <option value="2">Homologação</option>
                        </select>

                        <button type="button" class="doc-period__apply" wire:click="applyPeriod">Aplicar</button>

                        {{-- Só aparece com algo fora do padrão. "Limpar" não esvazia:
                             restaura o estado com que a aba abre. --}}
                        @if ($this->temFiltroAtivo())
                            <button type="button" class="doc-period__clear" wire:click="limparFiltros"
                                title="Restaurar os filtros padrão desta aba">
                                <i class="fas fa-times"></i> Limpar
                            </button>
                        @endif

                        {{-- Ações: relatório imprimível (nova aba, filtros na URL) e zip
                             dos XMLs do filtro atual. Só ícone + tooltip, antes do
                             select de empresa, que fecha a linha. --}}
                        <div class="doc-actions">
                            <a href="{{ route('panel.documents.report', array_filter([
                                'type'       => $type,
                                'company'    => $company_filter,
                                'first_date' => $first_date,
                                'last_date'  => $last_date,
                                'status'     => $statusFilter,
                                'ambiente'   => $ambiente,
                            ], fn ($v) => $v !== '' && $v !== null && $v !== [])) }}"
                                target="_blank" class="pnl-iconbtn" title="Relatório" aria-label="Relatório">
                                <i class="fas fa-file-alt"></i>
                            </a>

                            {{-- Spinner pelo Alpine (dl): liga no clique e só desliga 2,5s
                                 depois do 'zip-pronto' do backend, cobrindo a montagem do
                                 zip. Fallback de 120s se a requisição morrer. --}}
                            <button type="button" class="pnl-iconbtn" title="Baixar XML" aria-label="Baixar XML"
                                x-data="{ dl: false }"
                                x-on:click="dl = true; setTimeout(() => dl = false, 120000)"
                                x-on:zip-pronto.window="setTimeout(() => dl = false, 2500)"
                                x-bind:disabled="dl"
                                wire:click="downloadXmls">
                                <i class="fas fa-download" x-show="! dl"></i>
                                <i class="fas fa-spinner fa-spin" x-show="dl" style="display: none"></i>
                            </button>
                        </div>

                        @if ($companies->count() > 1)
                            <select class="doc-period__company" wire:model.live="company_filter"
                                aria-label="Filtrar por empresa">
                                <option value="">Todas as empresas</option>
                                @foreach ($companies as $c)
                                    <option value="{{ $c->cnpj_cpf }}">
                                        {{ \Illuminate\Support\Str::upper($c->fantasy_name ?: $c->corporate_name) }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    {{-- Descartes: o ERP chama de "Inutilizada" tanto isto quanto o
                         evento fiscal de faixa, e os dois somam valores diferentes.
                         A explicação fica NA TELA, onde a dúvida aparece. --}}
                    @if ($type === 'descartes')
                        <div class="doc-aviso" role="note">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>O que é esta aba:</strong> vendas que ganharam número no sistema e foram
                                <strong>jogadas fora antes de virar nota fiscal</strong> — nunca foram transmitidas à
                                SEFAZ. Por isso têm valor e não têm protocolo nem XML (não há o que baixar).
                                <br>
                                <strong>Não confunda com "Inutilizações gerais":</strong> lá é o evento fiscal de
                                <em>inutilização de faixa de numeração</em>, que foi à SEFAZ, tem protocolo e não tem
                                valor. O ERP chama as duas coisas de "Inutilizada", e é daí que vem a diferença de
                                totais entre uma tela e outra.
                            </div>
                        </div>
                    @endif

                    @if (!empty($statuses))
                        <div class="doc-chips" role="group" aria-label="Filtrar por status">
                            <button type="button" class="doc-chip {{ empty($statusFilter) ? 'is-on' : '' }}"
                                wire:click="clearStatus">Todos</button>
                            @foreach ($statuses as $key)
                                @php($grp = $statusGroups[$key] ?? ['label' => $key, 'color' => 'default'])
                                <button type="button"
                                    class="doc-chip doc-chip--{{ $grp['color'] }} {{ in_array($key, $statusFilter, true) ? 'is-on' : '' }}"
                                    wire:click="toggleStatus('{{ $key }}')">{{ $grp['label'] }}</button>
                            @endforeach
                        </div>
                    @endif

                </div>

                {{-- Tabelas por fonte: a principal vem de $effectiveSource, e chips
                     como "Inutilizada"/"Rejeitada" marcados JUNTO com outros status
                     ganham tabelas secundárias logo abaixo. --}}
                @php($temPrincipal = $rows->total() > 0)
                @php($temInut = $extraInutilizacoes && $extraInutilizacoes->count() > 0)
                @php($temRejeicoes = $extraRejeicoes && $extraRejeicoes->count() > 0)

                @if (!$temPrincipal && !$temInut && !$temRejeicoes)
                    <div class="alert alert-default mb-0">
                        <p>Nenhum documento no período/filtro selecionado.</p>
                    </div>
                @else
                    @if ($temPrincipal)
                        @includeWhen($effectiveSource === 'documents', 'livewire.panel.documents._table-documents')
                        @includeWhen($effectiveSource === 'events', 'livewire.panel.documents._table-events')
                        @includeWhen($effectiveSource === 'disables', 'livewire.panel.documents._table-disables')
                        @includeWhen($effectiveSource === 'rejeicoes', 'livewire.panel.documents._table-rejeicoes')
                        @includeWhen($effectiveSource === 'nfse', 'livewire.panel.documents._table-nfse')
                        @includeWhen($effectiveSource === 'discards', 'livewire.panel.documents._table-discards')

                        {{ $rows->links() }}
                    @endif

                    @if ($temInut)
                        <div class="doc-subhead">Inutilizações</div>
                        @include('livewire.panel.documents._table-disables', ['rows' => $extraInutilizacoes])
                    @endif

                    @if ($temRejeicoes)
                        <div class="doc-subhead">Rejeições (sem nota no portal)</div>
                        @include('livewire.panel.documents._table-rejeicoes', ['rows' => $extraRejeicoes])
                    @endif
                @endif

            </div>
        </div>
    </div>

</div>

@section('title', $cfg['label'])

@push('modals')
    <livewire:panel.dashboard.detail-doc :user="$user" />
@endpush

{{-- plugins (cute-alert) carregam no layout, uma vez só (p/ wire:navigate).
     Os inputs de período usam <input type="date"> nativo — sem máscara jQuery. --}}
