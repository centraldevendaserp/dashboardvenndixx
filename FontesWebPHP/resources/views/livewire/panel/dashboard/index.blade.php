<div class="pnl-dash">

    {{-- Linha 0 — filtro rápido: período (esq.) + busca número/protocolo/chave (dir.) --}}
    <div class="pnl-section">
        <livewire:panel.dashboard.quick-filter :user="$user" />
    </div>

    {{-- Linha 1 — KPIs de contagem --}}
    <div class="pnl-section">
        <div class="pnl-eyebrow">Resumo</div>
        <livewire:panel.dashboard.card-info :user="$user" lazy />
    </div>

    {{-- Linha 2 — faturamento por modelo (com sparkline) --}}
    <div class="pnl-section">
        <div class="pnl-eyebrow">Faturamento por modelo</div>
        <livewire:panel.dashboard.card-info-document :user="$user" lazy />
    </div>

    {{-- Linha 3 — gráficos principais --}}
    <div class="pnl-section">
        <div class="pnl-grid pnl-grid--2-1">

            <div class="pnl-card">
                <div class="pnl-card__head">
                    <div>
                        <h3>Emissões por período</h3>
                        <span class="pnl-sub">
                            @if ($docs_per_period_search)
                                {!! App\Helpers\Format::periodHtml($docs_per_period_search) !!}
                            @else
                                todos os períodos
                            @endif
                        </span>
                    </div>
                    {{-- Funil de período próprio removido: o filtro da seção "Documentos"
                         (lá embaixo) agora é o ÚNICO e move estes gráficos também. --}}
                </div>
                <div class="pnl-card__body">
                    <livewire:panel.dashboard.invoice-per-month :user="$user" lazy />
                </div>
            </div>

            <div class="pnl-card">
                <div class="pnl-card__head">
                    <div>
                        <h3>Distribuição por modelo</h3>
                        <span class="pnl-sub">
                            @if ($docs_per_period_search)
                                {!! App\Helpers\Format::periodHtml($docs_per_period_search) !!}
                            @else
                                todos os períodos
                            @endif
                        </span>
                    </div>
                </div>
                <div class="pnl-card__body">
                    <livewire:panel.dashboard.invoice-qty-total :user="$user" lazy />
                </div>
            </div>

        </div>
    </div>

    {{-- Linha 4 — widgets (status + recentes) --}}
    <div class="pnl-section">
        <div class="pnl-grid pnl-grid--1-1">

            <div class="pnl-card">
                <div class="pnl-card__head">
                    <div>
                        <h3>Status das notas</h3>
                        <span class="pnl-sub">{!! App\Helpers\Format::periodHtml($docs_search) !!}</span>
                    </div>
                </div>
                <div class="pnl-card__body">
                    <livewire:panel.dashboard.status-breakdown :user="$user" lazy />
                </div>
            </div>

            <div class="pnl-card">
                <div class="pnl-card__head">
                    <div>
                        <h3>Documentos recentes</h3>
                        <span class="pnl-sub">últimas emissões</span>
                    </div>
                </div>
                <div class="pnl-card__body">
                    <livewire:panel.dashboard.recent-documents :user="$user" lazy />
                </div>
            </div>

        </div>
    </div>

    {{-- Linha 5 — tabelas em abas (fiação preservada) --}}
    {{-- Âncora do "rolar até a lista" ao Aplicar: o filtro fica no topo e o
         resultado, no fim da página. --}}
    <div class="pnl-section" id="pnl-documentos">
        <div class="pnl-card">

            <div class="pnl-card__head">
                <div>
                    <h3>Documentos</h3>
                    <span class="pnl-sub">{!! App\Helpers\Format::periodHtml($docs_search) !!}</span>
                </div>
                <div class="pnl-card__actions">
                    <a href="{{ $reportRoute }}" target="_blank" class="pnl-iconbtn pnl-iconbtn--labeled" title="Relatório">
                        <i class="fas fa-file-alt"></i><span>Relatório</span>
                    </a>
                    {{-- Spinner pelo Alpine (dl): o zip é montado numa 2ª requisição, então
                         wire:loading apagaria cedo demais. O backend avisa 'zip-pronto'
                         e o spinner solta 2,5s depois. --}}
                    <a href="#" class="pnl-iconbtn pnl-iconbtn--labeled" title="Baixar todos (XML)"
                        x-data="{ dl: false }"
                        x-on:click="dl = true; setTimeout(() => dl = false, 120000)"
                        x-on:zip-pronto.window="setTimeout(() => dl = false, 2500)"
                        x-bind:style="dl ? 'pointer-events: none; opacity: .65' : ''"
                        wire:click="eventDownloadCompressed('{{ $doc_type }}')">
                        <i class="fas fa-download" x-show="! dl"></i>
                        <i class="fas fa-spinner fa-spin" x-show="dl" style="display: none"></i>
                        <span>Baixar XML</span>
                    </a>
                </div>
            </div>

            <div class="pnl-card__body">

                <div wire:ignore class="tab-main">

                    {{-- data-doc-type: o filtro do topo usa para abrir a aba do
                         status escolhido (ver o @script no fim deste arquivo). --}}
                    <ul class="nav">
                        <li data-doc-type="invoice" class="{{ $doc_type === 'invoice' ? 'active' : '' }}"
                            wire:click.prevent="$dispatch('eventDocType', 'invoice')">
                            <a href="#invoices">Documento Fiscal</a>
                        </li>
                        <li data-doc-type="authorized" class="{{ $doc_type === 'authorized' ? 'active' : '' }}"
                            wire:click.prevent="$dispatch('eventDocType', 'authorized')">
                            <a href="#invoices">AUTORIZADAS</a>
                        </li>
                        <li data-doc-type="event">
                            <a href="#events"
                                wire:click.prevent="$dispatch('eventDocType', 'event')">Cancelamento
                                / Carta de Correção</a>
                        </li>
                        <li data-doc-type="disable">
                            <a href="#disable"
                                wire:click.prevent="$dispatch('eventDocType', 'disable')">Inutilização</a>
                        </li>
                    </ul>

                    <div class="content pt-30 pb-0">

                        <div id="invoices" class="body active">
                            <livewire:panel.dashboard.invoice :user="$user" :doc_type="$doc_type" />
                        </div>

                        <div id="events" class="body">
                            <livewire:panel.dashboard.event :user="$user" />
                        </div>

                        <div id="disable" class="body">
                            <livewire:panel.dashboard.disable :user="$user" />
                        </div>

                    </div>

                </div>

            </div>

        </div>
    </div>

</div>

@section('title', 'Dashboard')

@push('modals')
    <livewire:panel.dashboard.detail-doc :user="$user" />
@endpush

{{-- plugins (select2/mask/apexcharts/cute-alert) agora carregam no layout,
     uma vez só (necessário para o wire:navigate não redeclarar) --}}

@push('component-scripts')
    <script>
        (function () {
            function aplicarMascaras() {
                if (!window.jQuery || !jQuery.fn.mask) return;
                jQuery('.mask-date').mask('00/00/0000');
                if (window.__pnlMaskHooked) return;
                window.__pnlMaskHooked = true;
                Livewire.hook('message.processed', function () {
                    jQuery('.mask-date').mask('00/00/0000');
                });
            }

            // ⚠️ 'livewire:init' dispara uma vez por carregamento REAL: chegando
            // por wire:navigate ele já passou, e os campos ficam sem máscara.
            if (window.Livewire) {
                aplicarMascaras();
            } else {
                document.addEventListener('livewire:init', aplicarMascaras);
            }
        })();
    </script>
@endpush

@push('component-scripts')
    <script>
        (function () {
            // O filtro do topo escolhe a aba do status. As abas ficam sob
            // wire:ignore, então o Blade não repinta o "active" sozinho: aqui
            // clicamos no link, o mesmo caminho do clique do usuário.
            if (window.__tabAutoSelectBound) return;
            window.__tabAutoSelectBound = true;

            function selecionarAba(event) {
                var payload = Array.isArray(event) ? event[0] : event;
                var tipo = payload && payload.tipo;
                if (!tipo) return;

                var li = document.querySelector('.tab-main ul.nav li[data-doc-type="' + tipo + '"]');
                if (!li || li.classList.contains('active')) return;   // já é a aba atual

                var link = li.querySelector('a');
                if (!link) return;

                // O "Limpar" manda rolar: false. O handler de scroll reage a
                // qualquer clique no link, inclusive este programático. A flag é
                // lida dentro do click() (síncrono), daí desligar logo depois.
                window.__pnlSkipTabScroll = (payload.rolar === false);
                link.click();
                window.__pnlSkipTabScroll = false;
            }

            function registrar() {
                Livewire.on('pnlSelecionarAba', selecionarAba);
            }

            // ⚠️ 'livewire:init' dispara uma vez por carregamento REAL: chegando
            // pela sidebar (wire:navigate) ele já passou, e o listener nunca
            // seria registrado. Sempre teste window.Livewire antes.
            if (window.Livewire) {
                registrar();
            } else {
                document.addEventListener('livewire:init', registrar);
            }
        })();
    </script>
@endpush

@push('component-scripts')
    <script>
        (function () {
            if (window.__tabScrollBound) return;
            window.__tabScrollBound = true;
            // Ao trocar de aba, rola suave ate a secao (evita o "pulo" quando a
            // aba vazia encurta a pagina).
            document.addEventListener('click', function (e) {
                var link = e.target.closest('.tab-main ul.nav li a');
                if (!link) return;
                // Troca de aba programatica que pediu para NAO rolar.
                if (window.__pnlSkipTabScroll) return;
                requestAnimationFrame(function () {
                    var tabs = document.querySelector('.tab-main');
                    if (!tabs) return;
                    var header = document.querySelector('.box-general > .header');
                    var offset = (header ? header.offsetHeight : 0) + 15;
                    var y = tabs.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
                });
            });
        })();
    </script>
@endpush

@push('component-scripts')
    <script>
        (function () {
            // "Documento Fiscal" e "AUTORIZADAS" usam o MESMO #invoices, então o
            // fade do tema não re-dispara sozinho e é re-tocado aqui.
            // ⚠️ Nada de Livewire.hook('commit'): o reflow síncrono no succeed
            // atrapalha o morph e a tabela fica um clique atrás.
            if (window.__tabInvoiceFadeBound) return;
            window.__tabInvoiceFadeBound = true;

            function replayFade() {
                var body = document.getElementById('invoices');
                if (!body || !body.classList.contains('active')) return;
                body.style.animation = 'none';
                void body.offsetWidth;   // reflow → reinicia a animação
                body.style.animation = '';
            }

            // Re-toca no clique, no próximo frame: roda antes do morph, então
            // não interfere no re-render da tabela.
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.tab-main ul.nav li a[href="#invoices"]')) return;
                requestAnimationFrame(replayFade);
            });
        })();
    </script>
@endpush
