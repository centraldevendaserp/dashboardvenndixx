<div class="profile profile-menu" x-data="{ open: false }"
    :class="{ 'is-open': open }"
    @click.outside="open = false"
    @keydown.escape.window="open = false">

    {{-- Estilo deste menu: public/assets/css/custom.css (escopo .profile-menu).
         Fica lá porque @push de componente Livewire aninhado não chega ao @stack do layout. --}}

    <button type="button" class="profile-menu__trigger"
        @click="open = !open"
        :aria-expanded="open" aria-haspopup="true" aria-label="Menu do perfil">
        <img src="{{ asset('assets/images/config_perfil.png') }}" alt="Perfil">
        <i class="fas fa-chevron-down profile-menu__caret" aria-hidden="true"></i>
    </button>

    <div class="profile-menu__balloon" role="menu" x-show="open" x-cloak x-transition>

        <div class="profile-menu__head">
            <span class="profile-menu__avatar"><i class="fas fa-user"></i></span>
            <div class="profile-menu__id">
                <p class="profile-menu__name">{{ auth()->user()->name }}</p>
                <small class="profile-menu__email">{{ auth()->user()->email }}</small>
            </div>
        </div>

        <div class="profile-menu__items">
            <a href="#" class="profile-menu__item" role="menuitem"
                @click="open = false"
                data-trigger="modal" data-modal="#modal-data-to-user"
                wire:click.prevent="$dispatchTo('panel.user.modal-data-to-user', 'eventAction', { action: 'edit', user_id: '{{ auth()->user()->id }}', mode: 'profile' })">
                <i class="fas fa-cog" aria-hidden="true"></i>
                <span>Configurações</span>
            </a>

            <a href="#" class="profile-menu__item" role="menuitem"
                @click="open = false"
                data-trigger="modal" data-modal="#modal-data-to-user"
                wire:click.prevent="$dispatchTo('panel.user.modal-data-to-user', 'eventAction', { action: 'edit', user_id: '{{ auth()->user()->id }}', focus: 'password' })">
                <i class="fas fa-key" aria-hidden="true"></i>
                <span>Alterar senha</span>
            </a>

            <div class="profile-menu__sep" role="separator"></div>

            <a href="#" class="profile-menu__item profile-menu__item--danger" role="menuitem"
                @click="open = false"
                wire:click.prevent="logout">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                <span>Sair</span>
            </a>
        </div>
    </div>
</div>

@push('modals')
    <livewire:panel.user.modal-data-to-user />
@endpush
