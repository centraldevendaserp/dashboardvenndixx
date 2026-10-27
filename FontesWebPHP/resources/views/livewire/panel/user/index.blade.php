<div class="pnl-dash pnl-listpage">

    <div class="pnl-section">

        <div class="pnl-eyebrow">Acessos</div>

        <div class="pnl-card">

            <div class="pnl-card__head">
                <div>
                    <h3>Usuários</h3>
                    <span class="pnl-sub">
                        {{ $users->total() }} {{ $users->total() == 1 ? 'acesso cadastrado' : 'acessos cadastrados' }}
                    </span>
                </div>
                <div class="pnl-card__actions">
                    <a href="#" class="pnl-iconbtn pnl-iconbtn--add" title="Adicionar usuário"
                        data-trigger="modal" data-modal="#modal-data-to-user"
                        wire:click.prevent="$dispatchTo('panel.user.modal-data-to-user', 'eventAction', 'store')">
                        <img src="{{ asset('assets/images/adicionar_usuario.png') }}" alt="Adicionar usuário">
                    </a>
                </div>
            </div>

            <div class="pnl-toolbar">
                <label class="pnl-search">
                    <i class="fas fa-search"></i>
                    <input type="search" wire:model.live.debounce.350ms="search"
                        placeholder="Buscar por nome, e-mail, CNPJ ou CPF…">
                    @if (trim($search) !== '')
                        <button type="button" class="pnl-search__clear" title="Limpar busca"
                            wire:click="$set('search', '')"><i class="fas fa-times"></i></button>
                    @endif
                </label>
            </div>

            <div class="pnl-card__body">

                @if ($users->total() == 0)
                    @if (trim($search) !== '')
                        <div class="pnl-empty">
                            <i class="fas fa-search"></i>
                            <p>Nenhum usuário encontrado para “{{ $search }}”.</p>
                        </div>
                    @else
                        <div class="pnl-empty">
                            <i class="fas fa-user-slash"></i>
                            <p>Nenhum usuário cadastrado ainda.</p>
                            <a href="#" class="u-add-link" data-trigger="modal" data-modal="#modal-data-to-user"
                                wire:click.prevent="$dispatchTo('panel.user.modal-data-to-user', 'eventAction', 'store')">
                                Cadastrar o primeiro acesso
                            </a>
                        </div>
                    @endif
                @else
                    <div class="users-list">
                        @foreach ($users as $user)
                            @php
                                $parts = preg_split('/\s+/', trim((string) $user['name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                                $initials = strtoupper(mb_substr($parts[0] ?? 'U', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
                            @endphp
                            <div class="user-row" wire:key="user_{{ $user['id'] }}">

                                <span class="user-row__avatar {{ $user['is_admin'] == 'S' ? 'is-admin' : '' }}">{{ $initials }}</span>

                                <div class="user-row__main">
                                    <b>{{ $user['name'] }}</b>
                                    <span>{{ $user['email'] }}</span>
                                </div>

                                <div class="user-row__meta">
                                    @if ($user['is_admin'] == 'S')
                                        <span class="u-badge u-badge--yes">Administrador</span>
                                    @else
                                        <span class="u-badge u-badge--no">Usuário</span>
                                    @endif
                                    <span class="u-count" title="Empresas vinculadas">
                                        <i class="far fa-building"></i> {{ $user['companies_count'] }}
                                    </span>
                                </div>

                                <div class="user-row__actions">
                                    <div class="dropdown">
                                        <a href="#" aria-label="Ações"><i class="fas fa-ellipsis-v"></i></a>
                                        <div class="dropdown-menu right text-left">
                                            <a class="dropdown-item" href="#" data-trigger="modal"
                                                data-modal="#modal-data-to-user"
                                                wire:click.prevent="$dispatchTo('panel.user.modal-data-to-user', 'eventAction', { action: 'edit', user_id: {{ $user['id'] }} })">
                                                <i class="far fa-edit"></i>
                                                Editar
                                            </a>

                                            @if ((int) $user['id'] !== (int) auth('web')->id())
                                                <a class="dropdown-item" href="#"
                                                    wire:click.prevent="$dispatch('eventCuteConfirmDeleteUser', { 'id': {{ $user['id'] }} })">
                                                    <i class="far fa-minus-square"></i>
                                                    Excluir
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    </div>

                    <div class="u-pagination">
                        {{ $users->links() }}
                    </div>
                @endif

            </div>

        </div>

    </div>

</div>

@section('title', 'Usuários')

{{-- plugins (select2/mask/cute-alert) carregam no layout, uma vez só (p/ wire:navigate) --}}

@push('component-styles')
@endpush

@script
    <script>
        Livewire.on('eventCuteConfirmDeleteUser', (event) => {

            cuteAlert({
                'type': 'question',
                'title': "Confirmação!",
                'message': "Quer excluir permanentemente?",
                'confirmText': "Sim",
                'cancelText': "Não",
            }).then((e) => {

                if (e == "confirm") {
                    $wire.deleteUser(event.id)
                }

            });

        });

        Livewire.hook('message.processed', (message, component) => {
            //
        });
    </script>
@endscript
