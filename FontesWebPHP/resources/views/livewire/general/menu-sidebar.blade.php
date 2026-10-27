@php
    $pnlName = trim((string) ($user->name ?? ''));
    $parts = preg_split('/\s+/', $pnlName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = strtoupper(
        mb_substr($parts[0] ?? 'U', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '')
    );
    $isAdmin = ($user->is_admin ?? '') === 'S';
@endphp

<div class="sidebar">

    <div class="menu-wrap">

        <nav class="pnl-nav" aria-label="Navegação principal">

            <div class="pnl-nav__scroll">

                <div class="pnl-nav__eyebrow">Navegação</div>
                <ul>
                    <li class="{{ request()->is('panel/dashboard') ? 'is-active' : '' }}">
                        <a href="{{ route('panel.dashboard.index') }}" data-tip="Dashboard">
                            <span class="pnl-ico"><i class="fas fa-chart-line"></i></span>
                            <span class="pnl-side__label">Dashboard</span>
                        </a>
                    </li>
                </ul>

                @php($docTypes = \App\Livewire\Panel\Documents\Index::types())
                @php($docOpen = request()->is('panel/documents/*'))

                <div class="pnl-nav__eyebrow">Documentos Fiscais</div>
                <ul>
                    <li class="pnl-acc {{ $docOpen ? 'is-open' : '' }}">
                        <a href="#" class="pnl-acc__toggle" data-acc-toggle
                            aria-expanded="{{ $docOpen ? 'true' : 'false' }}" data-tip="Documentos">
                            <span class="pnl-ico"><i class="fas fa-file-invoice"></i></span>
                            <span class="pnl-side__label">Documentos</span>
                            <span class="pnl-acc__caret"><i class="fas fa-chevron-down"></i></span>
                        </a>
                        <ul class="pnl-acc__sub">
                            @foreach ($docTypes as $slug => $t)
                                <li class="{{ request()->is('panel/documents/' . $slug) ? 'is-active' : '' }}">
                                    <a href="{{ route('panel.documents.type', $slug) }}" wire:navigate
                                        data-tip="{{ $t['label'] }}">
                                        <span class="pnl-side__label">{{ $t['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                    <li class="{{ request()->is('panel/fiscal-status') ? 'is-active' : '' }}">
                        <a href="{{ route('panel.fiscal_status.index') }}" data-tip="Status SEFAZ">
                            <span class="pnl-ico"><i class="fas fa-clipboard-check"></i></span>
                            <span class="pnl-side__label">Status SEFAZ</span>
                        </a>
                    </li>
                </ul>

                @if ($isAdmin)
                    <div class="pnl-nav__eyebrow">Administração</div>
                    <ul>
                        <li class="{{ request()->is('panel/companies') ? 'is-active' : '' }}">
                            <a href="{{ route('panel.companies.index') }}" wire:navigate data-tip="Empresas">
                                <span class="pnl-ico"><i class="far fa-building"></i></span>
                                <span class="pnl-side__label">Empresas</span>
                            </a>
                        </li>
                        <li class="{{ request()->is('panel/users') ? 'is-active' : '' }}">
                            <a href="{{ route('panel.users.index') }}" wire:navigate data-tip="Usuários">
                                <span class="pnl-ico"><i class="fas fa-users"></i></span>
                                <span class="pnl-side__label">Usuários</span>
                            </a>
                        </li>
                    </ul>
                @endif

            </div>

            <div class="pnl-side__foot">
                <div class="pnl-side__user">
                    <span class="pnl-avatar">{{ $initials }}</span>
                    <span class="pnl-side__id">
                        <b>{{ $pnlName ?: 'Usuário' }}</b>
                        <span>{{ $isAdmin ? 'Administrador' : 'Usuário' }}</span>
                    </span>
                </div>
                <a href="#" class="pnl-side__logout" wire:click.prevent="logout" data-tip="Sair"
                    aria-label="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </a>
            </div>

        </nav>

    </div>

</div>
