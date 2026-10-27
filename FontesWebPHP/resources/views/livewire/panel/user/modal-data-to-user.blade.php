<div>

    {{-- Estilo deste modal: public/assets/css/custom.css (escopo #modal-data-to-user).
         Fica lá porque @push de componente Livewire aninhado não chega ao @stack do layout. --}}

    @php
        $isAdminUser = Auth::user()->is_admin === 'S';

        $lockNameEmail        = in_array($mode, ['password', 'self_locked'], true);
        $showAdminField       = $isAdminUser && in_array($mode, ['create', 'manage', 'self_locked'], true);
        // Admin pode editar as empresas vinculadas TAMBÉM no próprio perfil (Minhas
        // configurações). Não-admin no perfil segue só-leitura (não pode se auto-vincular).
        $showCompaniesEdit    = $isAdminUser && in_array($mode, ['create', 'manage', 'self_locked', 'profile'], true);
        $showCompaniesReadonly = $mode === 'profile' && ! $isAdminUser;
        $showPassword         = in_array($mode, ['create', 'manage', 'password'], true);
        $showPasswordConfirm  = $mode === 'password';
        $showSave             = $mode !== 'self_locked';
        $disableManageFields  = $mode === 'self_locked';
        $showAgentTokens      = $isAdminUser && in_array($mode, ['create', 'manage'], true);

        // Gestão (admin editando outro) abre em somente-leitura até "Habilitar edição".
        $manageLocked = $mode === 'manage' && ! $editEnabled;

        $passwordPlaceholder = $mode === 'password'
            ? 'Nova senha'
            : ($action == 'edit' ? 'Deixe em branco para manter' : '••••••••');

        switch ($mode) {
            case 'password':
                $headIcon = 'fa-key'; $headTitle = 'Alterar senha'; $headSub = 'Defina a nova senha do acesso.'; break;
            case 'profile':
                $headIcon = 'fa-user-cog'; $headTitle = 'Minhas configurações'; $headSub = 'Atualize seus dados de acesso.'; break;
            case 'self_locked':
                $headIcon = 'fa-user-lock'; $headTitle = 'Minha conta'; $headSub = 'Seus dados são editados pelo menu do perfil.'; break;
            case 'manage':
                $headIcon = 'fa-user-edit'; $headTitle = 'Editar usuário'; $headSub = 'Atualize os dados e os vínculos do acesso.'; break;
            default:
                $headIcon = 'fa-user-plus'; $headTitle = 'Novo usuário'; $headSub = 'Preencha os dados para cadastrar um novo acesso.';
        }
    @endphp

    <!-- modal form -->
    <div wire:ignore.self class="modal-main" id="modal-data-to-user">

        <div class="dialog">

            <div class="content">

                <a href="#" class="close"><i class="fas fa-times"></i></a>

                <div class="header">
                    <div class="modal-head">
                        <span class="modal-head__icon">
                            <i class="fas {{ $headIcon }}"></i>
                        </span>
                        <div class="modal-head__txt">
                            <p>{{ $headTitle }}</p>
                            <small>{{ $headSub }}</small>
                        </div>
                    </div>
                </div>

                <div class="body">

                    @if ($showTokenPanel)
                        <div class="agent-token-panel">
                            @if ($userJustCreated)
                                <p class="agent-token-panel__ok">
                                    <i class="fas fa-check-circle"></i> Usuário cadastrado com sucesso.
                                </p>
                            @endif
                            <p class="agent-token-panel__title">
                                <i class="fas fa-key"></i> Chave gerada — {{ $generatedTokenName }}
                            </p>
                            <span class="agent-tokens__hint">
                                <i class="fas fa-info-circle"></i>
                                Copie o valor e cole no <b>Key=</b> do <code>.ini</code> deste cliente,
                                depois reinicie o agente. <b>Ele não será exibido de novo.</b>
                            </span>
                            <div class="agent-token-panel__row">
                                <input type="text" readonly id="agent-token-value" class="agent-token-panel__value"
                                    value="{{ $generatedToken }}">
                                <button type="button" class="agent-tk-btn agent-tk-btn--primary"
                                    onclick="const i=document.getElementById('agent-token-value');i.select();navigator.clipboard&&navigator.clipboard.writeText(i.value);this.querySelector('span').textContent='Copiado!';">
                                    <i class="far fa-copy"></i> <span>Copiar</span>
                                </button>
                            </div>
                        </div>
                    @else

                    @if ($manageLocked)
                        <p class="lock-banner">
                            <i class="fas fa-lock"></i> Somente leitura. Clique em <strong>Habilitar edição</strong> para alterar os dados e os vínculos.
                        </p>
                    @endif

                    <fieldset @disabled($manageLocked) class="lock-fieldset" style="border:0;margin:0;padding:0;min-width:0">
                    <div class="form-wrap row pt-30 pb-15">

                        <div class="group mb-15 col-50">
                            <label><i class="fas fa-user"></i> Nome</label>
                            {{-- .blur: o aviso de nome repetido precisa do valor no
                                 servidor, mas uma ida por tecla seria exagero. --}}
                            <input type="text" wire:model.blur="name" placeholder="Nome completo" @disabled($lockNameEmail)>
                            @error('name')
                                <span class="error">{{ $message }}</span>
                            @enderror
                            @if ($emailComMesmoNome)
                                <span class="hint hint--aviso">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Já existe <b>{{ $name }}</b> ({{ $emailComMesmoNome }}).
                                    Se for outra pessoa, pode salvar.
                                </span>
                            @endif
                        </div>

                        <div class="group mb-15 col-50">
                            <label><i class="fas fa-envelope"></i> E-mail</label>
                            <input type="email" wire:model="email" placeholder="usuario@email.com" @disabled($lockNameEmail)>
                            @error('email')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>

                        @if ($showAdminField)
                            <div class="group mb-15 col-50" wire:ignore>
                                <label><i class="fas fa-user-shield"></i> Administrador</label>
                                <select id="is_admin" class="select-two-modal-user" @disabled($disableManageFields)>
                                    <option value="">---</option>
                                    <option value="S">Sim</option>
                                    <option value="N">Não</option>
                                </select>

                                @error('is_admin')
                                    <span class="error">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif

                        @if ($showPassword)
                            <div class="group mb-15 {{ $showPasswordConfirm ? 'col-50' : (Auth::user()->is_admin == 'S' ? 'col-50' : 'col-100') }}">
                                <label><i class="fas fa-lock"></i> Senha</label>
                                {{-- Olho para conferir o que foi digitado: quem cadastra não
                                     é o dono da senha. --}}
                                <div class="senha-campo">
                                    <input type="password" wire:model="password" placeholder="{{ $passwordPlaceholder }}"
                                        data-senha>
                                    <button type="button" class="senha-olho" data-senha-toggle
                                        aria-label="Mostrar senha" title="Mostrar senha">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                @error('password')
                                    <span class="error">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif

                        @if ($showPasswordConfirm)
                            <div class="group mb-15 col-50">
                                <label><i class="fas fa-lock"></i> Confirmar senha</label>
                                <input type="password" wire:model="password_confirmation" placeholder="Repita a nova senha">
                                @error('password_confirmation')
                                    <span class="error">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif

                        @if ($showCompaniesEdit)
                            <div class="group mb-15 col-100">
                                <label><i class="fas fa-building"></i> Empresas vinculadas</label>
                                <div class="link-grid">
                                    @forelse ($companies as $company)
                                        <label class="link-chip">
                                            <input type="checkbox" wire:model="related_companies"
                                                value="{{ $company->id }}" @disabled($disableManageFields)>
                                            <span class="link-chip__body">
                                                <i class="fas fa-check link-chip__tick"></i>
                                                {{ Str::upper($company->fantasy_name ?: $company->corporate_name) }}
                                            </span>
                                        </label>
                                    @empty
                                        <p class="readonly-empty">Nenhuma empresa cadastrada.</p>
                                    @endforelse
                                </div>
                                @if ($is_admin === 'S')
                                    <span class="hint"><i class="fas fa-crown"></i> Administrador enxerga <b>todas</b> as empresas — por isso ficam marcadas e travadas.</span>
                                @else
                                    <span class="hint"><i class="fas fa-hand-pointer"></i> Clique para vincular/desvincular — as vinculadas ficam marcadas.</span>
                                @endif
                            </div>
                        @endif

                        @if ($showCompaniesReadonly)
                            <div class="group mb-15 col-100">
                                <label><i class="fas fa-building"></i> Empresas vinculadas</label>
                                @if ($linkedCompanies->isEmpty())
                                    <p class="readonly-empty">Nenhuma empresa vinculada.</p>
                                @else
                                    <div class="readonly-chips">
                                        @foreach ($linkedCompanies as $c)
                                            <span class="readonly-chip">{{ Str::upper($c->fantasy_name ?: $c->corporate_name) }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif

                    </div>
                    </fieldset>

                    @if (!$showSave)
                        <p class="self-locked-hint">
                            <i class="fas fa-lock"></i> Edite seus dados pelo menu do perfil, no canto superior direito.
                        </p>
                    @endif

                        @if ($showAgentTokens)
                            <div class="agent-tokens">
                                <span class="agent-tokens__label"><i class="fas fa-key"></i> Chave de acesso do agente</span>

                                @if ($mode === 'manage' && $agentTokens->isNotEmpty())
                                    <div class="agent-tokens__list">
                                        @foreach ($agentTokens as $t)
                                            <div class="agent-token-row">
                                                <span class="agent-token-row__info">
                                                    <span class="agent-token-row__name">{{ $t->name }}</span>
                                                    <span class="agent-token-row__meta">Último uso:
                                                        {{ $t->last_used_at ? \Illuminate\Support\Carbon::parse($t->last_used_at)->diffForHumans() : 'nunca' }}</span>
                                                </span>
                                                {{-- Só com a edição habilitada: revogar derruba o
                                                     envio do cliente na hora. O servidor também
                                                     recusa; isto aqui é para o botão não mentir. --}}
                                                <button type="button" class="agent-tk-btn agent-tk-btn--danger"
                                                    @disabled($manageLocked)
                                                    title="{{ $manageLocked ? 'Clique em Habilitar edição para revogar' : 'Revogar esta chave' }}"
                                                    wire:click.prevent="revokeAgentToken({{ $t->id }})"
                                                    onclick="return confirm('Revogar esta chave? O cliente para de enviar até receber uma nova.')">
                                                    <i class="fas fa-ban"></i> Revogar
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="agent-tokens__new">
                                    <div class="agent-tokens__field">
                                        <label><i class="fas fa-tag"></i> Nome da chave</label>
                                        @if ($mode === 'manage')
                                            <div class="agent-tokens__inputgroup">
                                                <input type="text" wire:model="tokenInstallationName"
                                                    placeholder="Ex.: PC da recepção">
                                                <button type="button" class="agent-tk-btn agent-tk-btn--primary"
                                                    @disabled($manageLocked)
                                                    title="{{ $manageLocked ? 'Clique em Habilitar edição para gerar' : 'Gerar uma chave para esta instalação' }}"
                                                    wire:click.prevent="generateAgentToken"
                                                    wire:loading.attr="data-loading" wire:target="generateAgentToken">
                                                    <i class="fas fa-plus"></i> Gerar chave
                                                </button>
                                            </div>
                                        @else
                                            <input type="text" wire:model="tokenInstallationName"
                                                placeholder="Ex.: PC da recepção">
                                        @endif

                                        @error('tokenInstallationName')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <span class="agent-tokens__hint">
                                    <i class="fas fa-info-circle"></i>
                                    @if ($mode === 'create')
                                        Uma chave será gerada ao <b>cadastrar</b>. Dê um nome que identifique a instalação (o computador do cliente); você copia o valor e cola no <code>.ini</code>.
                                    @else
                                        Dê um nome que identifique a instalação (o computador do cliente) e clique em <b>Gerar chave</b>.
                                    @endif
                                </span>
                            </div>
                        @endif

                    @endif

                </div>

                <div class="footer">
                    <div class="modal-actions">
                        @if ($showTokenPanel)
                            <a href="#" class="modal-btn modal-btn--primary" wire:click.prevent="finishTokenPanel"
                                wire:loading.attr="data-loading" wire:target="finishTokenPanel">
                                <i class="fas fa-check"></i> <span>Concluir</span>
                            </a>
                        @else
                            <button type="button" class="modal-btn modal-btn--ghost"
                                onclick="jQuery('#modal-data-to-user').modal('close')">
                                {{ $showSave && !$manageLocked ? 'Cancelar' : 'Fechar' }}
                            </button>
                            @if ($manageLocked)
                                <a href="#" class="modal-btn modal-btn--primary" wire:click.prevent="enableEdit"
                                    wire:loading.attr="data-loading" wire:target="enableEdit">
                                    <i class="fas fa-unlock-alt" wire:loading.remove wire:target="enableEdit"></i>
                                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="enableEdit"></i>
                                    <span>Habilitar edição</span>
                                </a>
                            @elseif ($showSave)
                                <a href="#" class="modal-btn modal-btn--primary" wire:click.prevent="submit"
                                    wire:loading.attr="data-loading" wire:target="submit">
                                    <i class="far fa-paper-plane" wire:loading.remove wire:target="submit"></i>
                                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="submit"></i>
                                    <span>Salvar</span>
                                </a>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

@script
<script>
    // ⚠️ 'livewire:initialized' dispara uma vez por carregamento REAL: chegando
    // pela sidebar (wire:navigate) ele ja passou, e nada aqui dentro seria
    // registrado. Por isso: se o Livewire ja esta de pe, roda agora.
    (function () {
        var iniciar = () => {
        // Admin enxerga TODAS as empresas -> na grade, marca TODAS e trava (nao da pra
        // desmarcar). Usuario comum -> grade editavel normalmente.
        const applyAdminCompanyRule = (isAdmin) => {
            document.querySelectorAll('#modal-data-to-user .link-chip input').forEach((cb) => {
                if (isAdmin === 'S') { cb.checked = true; cb.disabled = true; }
                else { cb.disabled = false; }
            });
        };

        const syncUserModalFields = (event = {}) => {
            const isAdmin = event.is_admin || '';

            if ($('#is_admin').length) {
                $('#is_admin').val(isAdmin).trigger('change.select2');
            }

            // Grade de chips: o morph de checkbox do Livewire é instável, então
            // a marcação é feita aqui pelos ids vinculados.
            if (event.related_companies !== undefined) {
                const ids = (event.related_companies || []).map(String);
                document.querySelectorAll('#modal-data-to-user .link-chip input').forEach((cb) => {
                    cb.checked = ids.includes(String(cb.value));
                });
            }

            applyAdminCompanyRule(isAdmin);
        };

        Livewire.on('syncUserModalFields', (event) => {
            syncUserModalFields(event);
        });

        // Atualiza a saudacao "Ola, <primeiro nome>" no header apos salvar o perfil,
        // sem reload (a saudacao fica no layout, fora do componente Livewire).
        Livewire.on('eventProfileUpdated', (event) => {
            const cfg = Array.isArray(event) ? (event[0] || {}) : event;
            const name = String(cfg.name || '').trim();
            const first = name.split(/\s+/)[0] || 'bem-vindo';
            const el = document.querySelector('.pnl-greet b');
            if (el) el.textContent = 'Olá, ' + first;
        });

        // Configura o modal no client: trava campos (Alterar senha / self_locked /
        // auto-edição de admin) e dá foco. Chega depois do round-trip do eventAction.
        Livewire.on('configureUserModal', (event = {}) => {
            const cfg = Array.isArray(event) ? (event[0] || {}) : event;
            const lockInputs = !!cfg.lockInputs;            // nome/e-mail/empresas travados
            const lockAdmin = !!cfg.lockAdmin || lockInputs; // não pode mexer no admin

            setTimeout(() => {
                const modal = document.querySelector('#modal-data-to-user');
                if (!modal) return;

                modal.querySelectorAll('input[wire\\:model="name"], input[wire\\:model="email"]')
                    .forEach((el) => { el.disabled = lockInputs; });

                if ($('#is_admin').length) {
                    $('#is_admin').prop('disabled', lockAdmin).trigger('change.select2');
                }
                // os checkboxes de empresas ja vem desabilitados do servidor (self_locked)

                if (cfg.focus) {
                    const f = modal.querySelector('input[wire\\:model="' + cfg.focus + '"]');
                    if (f && !f.disabled) f.focus();
                }
            }, 180);
        });

        // Observer do modal (select2 do "Administrador" + sync dos chips):
        // reanexa apos navegar, senao o modal recriado fica sem select2.
        // ⚠️ Nunca use arroba-script nem tags HTML neste comentario — o Blade e
        // o Livewire os interpretam e quebram o componente.
        const setupUserModal = () => {
            const el = document.getElementById('modal-data-to-user');
            if (!el || el.__userModalSetup) return;
            el.__userModalSetup = true;

            afterModalShown('#modal-data-to-user', () => {
                const modal = $('#modal-data-to-user');

                if ($('#is_admin').length) {
                    if ($('#is_admin').hasClass('select2-hidden-accessible')) {
                        $('#is_admin').off('change');
                        $('#is_admin').select2('destroy');
                    }

                    $('#is_admin').select2({
                        language: 'pt-BR',
                        placeholder: '---',
                        allowClear: true,
                        dropdownParent: modal,
                        width: 'resolve'
                    });

                    $('#is_admin').on('change', function () {
                        const val = $(this).val();
                        // admin -> marca e trava TODAS as empresas na grade (o backend
                        // grava todas de qualquer forma). Nao-admin -> libera a grade.
                        applyAdminCompanyRule(val);
                        // o $wire.set dispara um commit que re-renderiza os chips (o morph
                        // reseta o "disabled"); reaplica a regra quando o commit terminar.
                        Promise.resolve($wire.set('is_admin', val)).then(() => applyAdminCompanyRule(val));
                    });
                }

                syncUserModalFields({
                    is_admin: $wire.get('is_admin'),
                    related_companies: $wire.get('related_companies')
                });
            });
        };

        setupUserModal();
        document.addEventListener('livewire:navigated', setupUserModal);

        // Olho da senha. Delegado no document porque o campo e recriado pelo morph
        // do Livewire a cada commit — listener preso no elemento morreria junto.
        if (!document.__senhaOlhoBound) {
            document.__senhaOlhoBound = true;
            document.addEventListener('click', function (e) {
                var botao = e.target.closest('[data-senha-toggle]');
                if (!botao) return;

                var campo = botao.parentElement.querySelector('[data-senha]');
                if (!campo) return;

                var mostrando = campo.type === 'text';
                campo.type = mostrando ? 'password' : 'text';
                botao.querySelector('i').className = mostrando ? 'fas fa-eye' : 'fas fa-eye-slash';
                botao.setAttribute('aria-label', mostrando ? 'Mostrar senha' : 'Ocultar senha');
                botao.setAttribute('title', mostrando ? 'Mostrar senha' : 'Ocultar senha');
            });
        }
        };

        if (window.Livewire) {
            iniciar();
        } else {
            document.addEventListener('livewire:initialized', iniciar);
        }
    })();
</script>
@endscript
