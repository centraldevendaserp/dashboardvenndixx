<div>

    {{-- Estilo deste modal: public/assets/css/custom.css (escopo #modal-data-to-company).
         Fica lá porque @push de componente Livewire aninhado não chega ao @stack do layout. --}}

    @php
        $headTitle = $action == 'edit' ? 'Editar empresa' : 'Nova empresa';
        $headSub = $action == 'edit' ? 'Atualize os dados e os vínculos da empresa.' : 'Preencha os dados para cadastrar uma nova empresa.';

        // Ao editar, o modal abre em somente-leitura até "Habilitar edição".
        $locked = $action == 'edit' && ! $editEnabled;
    @endphp

    <!-- modal form -->
    <div wire:ignore.self class="modal-main" id="modal-data-to-company">

        <div class="dialog">

            <div class="content">

                <a href="#" class="close"><i class="fas fa-times"></i></a>

                <div class="header">
                    <div class="modal-head">
                        <span class="modal-head__icon"><i class="fas fa-building"></i></span>
                        <div class="modal-head__txt">
                            <p>{{ $headTitle }}</p>
                            <small>{{ $headSub }}</small>
                        </div>
                    </div>
                </div>

                <div class="body">

                    @if ($locked)
                        <p class="lock-banner">
                            <i class="fas fa-lock"></i> Somente leitura. Clique em <strong>Habilitar edição</strong> para alterar os dados e os vínculos.
                        </p>
                    @endif

                    <fieldset @disabled($locked) class="lock-fieldset" style="border:0;margin:0;padding:0;min-width:0">
                    <div class="form-wrap row">

                        <div class="col-100"><div class="box-heading">Dados gerais</div></div>

                        <div class="group col-60">
                            <label><i class="fas fa-briefcase"></i> Razão social</label>
                            <input type="text" wire:model="corporate_name" placeholder="Razão social">
                            @error('corporate_name')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="group col-40">
                            <label><i class="fas fa-id-card"></i> CNPJ/CPF</label>
                            <div class="field-search">
                                <input type="text" class="mask-cnpj_cpf" wire:model="cnpj_cpf" placeholder="00.000.000/0000-00">
                                <button type="button" class="btn-search" title="Buscar dados pelo CNPJ"
                                    wire:click.prevent="buscarCnpj"
                                    wire:loading.attr="disabled" wire:target="buscarCnpj">
                                    <i class="fas fa-search" wire:loading.remove wire:target="buscarCnpj"></i>
                                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="buscarCnpj"></i>
                                </button>
                            </div>
                            @error('cnpj_cpf')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="group col-100">
                            <label><i class="fas fa-store"></i> Nome fantasia</label>
                            <input type="text" wire:model="fantasy_name" placeholder="Nome fantasia">
                            @error('fantasy_name')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="col-100"><div class="box-heading">Endereço</div></div>

                        <div class="group col-100">
                            <label><i class="fas fa-road"></i> Logradouro</label>
                            <input type="text" wire:model="public_place" placeholder="Rua / Avenida">
                            @error('public_place')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="group col-40">
                            <label>CEP</label>
                            <div class="field-search">
                                <input type="text" wire:model="zip_code" placeholder="00000-000">
                                <button type="button" class="btn-search" title="Buscar endereço pelo CEP"
                                    wire:click.prevent="buscarCep"
                                    wire:loading.attr="disabled" wire:target="buscarCep">
                                    <i class="fas fa-search" wire:loading.remove wire:target="buscarCep"></i>
                                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="buscarCep"></i>
                                </button>
                            </div>
                            @error('zip_code')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="group col-20">
                            <label>Número</label>
                            <input type="text" wire:model="home_number" placeholder="Nº">
                            @error('home_number')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="group col-40">
                            <label>Complemento</label>
                            <input type="text" wire:model="complement" placeholder="Sala, bloco...">
                            @error('complement')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="group col-40">
                            <label>Bairro</label>
                            <input type="text" wire:model="district" placeholder="Bairro">
                            @error('district')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="group col-20">
                            <label>UF</label>
                            <input type="text" wire:model="uf" placeholder="UF">
                            @error('uf')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="group col-40">
                            <label>Município</label>
                            <input type="text" wire:model="county" placeholder="Município">
                            @error('county')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="col-100"><div class="box-heading">Contato</div></div>

                        <div class="group col-50">
                            <label><i class="fas fa-envelope"></i> E-mail</label>
                            <input type="text" wire:model="email" placeholder="empresa@email.com">
                            @error('email')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="group col-50">
                            <label><i class="fas fa-phone"></i> Telefone</label>
                            <input type="text" class="mask-phone" wire:model="phone_number" placeholder="(00) 00000-0000">
                            @error('phone_number')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="group col-100">
                            <label><i class="fas fa-users"></i> Usuários vinculados</label>
                            <div class="link-grid">
                                @forelse ($users as $user)
                                    <label class="link-chip">
                                        <input type="checkbox" wire:model="related_users" value="{{ $user->id }}">
                                        <span class="link-chip__body">
                                            <i class="fas fa-check link-chip__tick"></i>
                                            {{ Str::upper($user->name) }}
                                        </span>
                                    </label>
                                @empty
                                    <p class="readonly-empty">Nenhum usuário cadastrado.</p>
                                @endforelse
                            </div>
                            <span class="hint"><i class="fas fa-hand-pointer"></i> Clique para vincular/desvincular — os vinculados ficam marcados.</span>
                            @error('related_users')<span class="error">{{ $message }}</span>@enderror
                        </div>

                    </div>
                    </fieldset>

                </div>

                <div class="footer">
                    <div class="modal-actions">
                        <button type="button" class="modal-btn modal-btn--ghost"
                            onclick="jQuery('#modal-data-to-company').modal('close')">
                            {{ $locked ? 'Fechar' : 'Cancelar' }}
                        </button>
                        @if ($locked)
                            <a href="#" class="modal-btn modal-btn--primary" wire:click.prevent="enableEdit"
                                wire:loading.attr="data-loading" wire:target="enableEdit">
                                <i class="fas fa-unlock-alt" wire:loading.remove wire:target="enableEdit"></i>
                                <i class="fas fa-spinner fa-spin" wire:loading wire:target="enableEdit"></i>
                                <span>Habilitar edição</span>
                            </a>
                        @else
                            <a href="#" class="modal-btn modal-btn--primary" wire:click.prevent="submit"
                                wire:loading.attr="data-loading" wire:target="submit">
                                <i class="far fa-paper-plane" wire:loading.remove wire:target="submit"></i>
                                <i class="fas fa-spinner fa-spin" wire:loading wire:target="submit"></i>
                                <span>Salvar</span>
                            </a>
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
    // por wire:navigate ele já passou, e nada registrado aqui dentro roda
    // (máscara morta, chips sem marcar). Sempre teste window.Livewire antes.
    const iniciarModalEmpresa = () => {
        const applyMasks = () => {
            if (typeof MASK_CNPJ_CPF !== 'undefined') {
                $('#modal-data-to-company .mask-cnpj_cpf').mask('00.000.000/0000-00', MASK_CNPJ_CPF);
            }
            if (typeof SPMaskBehavior !== 'undefined') {
                $('#modal-data-to-company .mask-phone').mask(SPMaskBehavior, spOptions);
            }
        };

        // Grade de chips: o morph de checkbox do Livewire é instável, então a
        // marcação é feita aqui pelos ids vinculados.
        const syncCompanyModalFields = (event = {}) => {
            const cfg = Array.isArray(event) ? (event[0] || {}) : event;
            const ids = (cfg.related_users || []).map(String);
            document.querySelectorAll('#modal-data-to-company .link-chip input').forEach((cb) => {
                cb.checked = ids.includes(String(cb.value));
            });
        };

        Livewire.on('syncCompanyModalFields', (event) => syncCompanyModalFields(event));

        afterModalShown('#modal-data-to-company', () => {
            applyMasks();
            syncCompanyModalFields({ related_users: $wire.get('related_users') });
        });
    };

    if (window.Livewire) {
        iniciarModalEmpresa();
    } else {
        document.addEventListener('livewire:initialized', iniciarModalEmpresa);
    }
</script>
@endscript
