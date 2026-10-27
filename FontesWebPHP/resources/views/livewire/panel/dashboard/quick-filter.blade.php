<div>

    {{-- Filtro do topo do dashboard: período, ambiente, empresas, tipos, status,
         nº, protocolo e busca. Ao Aplicar, dispara eventDocsSearch +
         eventDocsPerPeriodSearch e controla a tela inteira. --}}
    <form class="pnl-quickbar" wire:submit="submit">

        {{-- Período --}}
        <div class="pnl-qf-field pnl-qf-a-periodo">
            <label class="pnl-qf-label"><i class="fas fa-calendar-alt"></i> Período</label>
            <div class="pnl-qf-period">
                <input type="text" class="pnl-qf-input mask-date @error('first_date') has-error @enderror"
                    placeholder="De __/__/____" wire:model="first_date" title="Data inicial (emissão)">
                <span class="pnl-qf-sep">—</span>
                <input type="text" class="pnl-qf-input mask-date @error('last_date') has-error @enderror"
                    placeholder="Até __/__/____" wire:model="last_date" title="Data final (emissão)">
            </div>
        </div>

        {{-- Ambiente --}}
        <div class="pnl-qf-field pnl-qf-a-ambiente">
            <label class="pnl-qf-label"><i class="fas fa-globe"></i> Ambiente</label>
            <select class="pnl-qf-native" wire:model="environment_type" title="Ambiente de emissão">
                <option value="">Todos</option>
                <option value="1">Produção</option>
                <option value="2">Homologação</option>
            </select>
        </div>

        {{-- Empresas --}}
        <div class="pnl-qf-field pnl-qf-a-empresas">
            <label class="pnl-qf-label"><i class="far fa-building"></i> Empresas</label>
            <div wire:ignore class="pnl-qf-s2wrap">
                <select id="qf_related_companies" class="pnl-qf-s2" wire:model="related_companies" multiple>
                    @foreach ($companies as $company)
                        <option value="{{ $company->cnpj_cpf }}">
                            @if ($company->fantasy_name)
                                {{ Str::upper($company->fantasy_name) }}
                            @else
                                {{ Str::upper($company->corporate_name) }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Tipos de documento --}}
        <div class="pnl-qf-field pnl-qf-a-tipos">
            <label class="pnl-qf-label"><i class="far fa-file-alt"></i> Tipos</label>
            <div wire:ignore class="pnl-qf-s2wrap">
                <select id="qf_doc_types" class="pnl-qf-s2" wire:model="doc_types" multiple>
                    {{-- Mesma ordem da sidebar e das abas do agente. SEM NFS-e: este
                         filtro recorta `documents.model` e a NFS-e vive em tabela
                         própria, sem `model`. --}}
                    <option value="65">NFC-e</option>
                    <option value="55">NF-e</option>
                    <option value="58">MDF-e</option>
                    <option value="57">CT-e</option>
                    <option value="67">CT-e OS</option>
                    <option value="59">Entrada/Compras</option>
                </select>
            </div>
        </div>

        {{-- Status --}}
        <div class="pnl-qf-field pnl-qf-a-status">
            <label class="pnl-qf-label"><i class="fas fa-check-circle"></i> Status</label>
            <div wire:ignore class="pnl-qf-s2wrap">
                {{-- Grupos, não cStat cru — mesmo vocabulário da tela "Documentos".
                     O QuickFilter expande para os códigos ao aplicar. --}}
                <select id="qf_doc_status" class="pnl-qf-s2" wire:model="doc_status" multiple>
                    @foreach ($statusOptions as $chave => $rotulo)
                        <option value="{{ $chave }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Nº documento --}}
        <div class="pnl-qf-field pnl-qf-a-numdoc">
            <label class="pnl-qf-label">N.º documento</label>
            <input type="text" class="pnl-qf-input @error('doc_number') has-error @enderror"
                wire:model="doc_number" placeholder="Nº" title="Número do documento">
        </div>

        {{-- Nº protocolo --}}
        <div class="pnl-qf-field pnl-qf-a-numprot">
            <label class="pnl-qf-label">N.º protocolo</label>
            <input type="text" class="pnl-qf-input @error('protocol_number') has-error @enderror"
                wire:model="protocol_number" placeholder="Protocolo" title="Número do protocolo">
        </div>

        {{-- Busca livre --}}
        <div class="pnl-qf-field pnl-qf-a-busca">
            <label class="pnl-qf-label"><i class="fas fa-search"></i> Busca</label>
            <div class="pnl-qf-searchbox">
                <i class="fas fa-search"></i>
                <input type="text" class="pnl-qf-input" wire:model="term" placeholder="Nº, protocolo ou chave"
                    title="Número do documento, número do protocolo ou chave de acesso (44 dígitos)">
            </div>
        </div>

        {{-- Ações --}}
        <div class="pnl-qf-actions">
            <button type="submit" class="pnl-quickbar__btn" id="pnl-quickbar-aplicar" wire:ignore>
                <i class="fas fa-filter pnl-quickbar__ico-idle"></i>
                <i class="fas fa-spinner fa-spin pnl-quickbar__ico-busy"></i>
                <span>Aplicar</span>
            </button>
            <a href="#" class="pnl-quickbar__clear" wire:click.prevent="resetSearch" title="Limpar filtros">
                <i class="fas fa-redo-alt"></i>
            </a>
        </div>

    </form>

    @error('first_date')
        <div class="pnl-quickbar__error">{{ $message }}</div>
    @enderror
    @error('last_date')
        <div class="pnl-quickbar__error">{{ $message }}</div>
    @enderror
    @error('doc_number')
        <div class="pnl-quickbar__error">Nº documento: {{ $message }}</div>
    @enderror
    @error('protocol_number')
        <div class="pnl-quickbar__error">Nº protocolo: {{ $message }}</div>
    @enderror

</div>

@script
    <script>
        (function () {

            /* -------- select2 do toolbar (empresas / tipos / status) --------
               Selects com wire:ignore: o morph não os toca, então inicializam uma
               vez aqui. A sincronia é manual e deferida: o valor só vai no submit. */
            function initToolbarSelects() {
                if (!window.jQuery || !jQuery.fn.select2) return;
                var $sel = jQuery('.pnl-qf-s2');
                $sel.each(function () {
                    var $s = jQuery(this);
                    if ($s.data('select2')) $s.select2('destroy');
                    $s.select2({
                        language: 'pt-BR',
                        placeholder: '---',
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false
                    });
                });
                $sel.off('change.qf').on('change.qf', function () {
                    var prop = this.getAttribute('wire:model');
                    if (prop) $wire.set(prop, jQuery(this).val() || [], false);
                });

                /* A caixa tem altura fixa com overflow:hidden, então o navegador
                   rola o container para manter o campo de busca visível — e esse
                   scrollLeft permanece depois de fechar, cortando os primeiros
                   chips. Aqui ele volta ao início. */
                $sel.off('select2:close.qf').on('select2:close.qf', function () {
                    var $c = jQuery(this).next('.select2-container');
                    // No próximo tick: o select2 ainda remove o campo de busca aqui.
                    setTimeout(function () {
                        $c.find('.select2-selection__rendered').each(function () {
                            this.scrollLeft = 0;
                        });
                    }, 0);
                });
            }

            initToolbarSelects();

            // Ao Limpar, o servidor zera as props: reflete nos select2.
            $wire.on('quickFilterCleared', function () {
                if (!window.jQuery) return;
                jQuery('.pnl-qf-s2').val(null).trigger('change.select2');
            });

            /* --------------------- loading do botão Aplicar ---------------------
               O submit é rápido (só dispara eventos); o peso está na atualização
               dos outros componentes, em requisições próprias. Por isso o botão
               segura o "carregando" até a fila de commits esvaziar. */
            var btn = document.getElementById('pnl-quickbar-aplicar');
            if (!btn || btn.dataset.busyBound) return;
            btn.dataset.busyBound = '1';

            /* Aplicar rola ate a lista: o recorte muda no topo e o resultado fica
               no fim da pagina. Respeita prefers-reduced-motion. */
            btn.addEventListener('click', function () {
                var alvo = document.getElementById('pnl-documentos');
                if (!alvo) return;
                var suave = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                // Espera o Livewire repintar as tabelas.
                setTimeout(function () {
                    alvo.scrollIntoView({ behavior: suave ? 'smooth' : 'auto', block: 'start' });
                }, 250);
            });

            var observando = false, emVoo = 0, desligar = null;

            function setLoading(on) {
                btn.classList.toggle('is-loading', on);
                btn.disabled = on;
            }

            function comecar() { observando = true; emVoo = 0; setLoading(true); agendarDesligar(); }

            function agendarDesligar() {
                clearTimeout(desligar);
                desligar = setTimeout(function () {
                    if (observando && emVoo <= 0) { observando = false; setLoading(false); }
                }, 400);
            }

            var form = btn.closest('form');
            if (form) form.addEventListener('submit', comecar);
            var limpar = form ? form.querySelector('.pnl-quickbar__clear') : null;
            if (limpar) limpar.addEventListener('click', comecar);

            Livewire.hook('commit', function (ctx) {
                if (!observando) return;
                emVoo++;
                var fim = function () { emVoo--; agendarDesligar(); };
                ctx.succeed(fim);
                ctx.fail(fim);
            });
        })();
    </script>
@endscript
