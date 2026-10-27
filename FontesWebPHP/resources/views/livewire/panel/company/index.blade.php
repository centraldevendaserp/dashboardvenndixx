<div class="pnl-dash pnl-listpage">

    <div class="pnl-section">

        <div class="pnl-eyebrow">Cadastro</div>

        <div class="pnl-card">

            <div class="pnl-card__head">
                <div>
                    <h3>Empresas</h3>
                    <span class="pnl-sub">
                        {{ $companies->total() }} {{ $companies->total() == 1 ? 'empresa cadastrada' : 'empresas cadastradas' }}
                    </span>
                </div>
                <div class="pnl-card__actions">
                    <a href="#" class="pnl-iconbtn pnl-iconbtn--add" title="Adicionar empresa"
                        data-trigger="modal" data-modal="#modal-data-to-company"
                        wire:click.prevent="$dispatchTo('panel.company.modal-data-to-company', 'eventAction', { 'action': 'store' })">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
            </div>

            <div class="pnl-toolbar">
                <label class="pnl-search">
                    <i class="fas fa-search"></i>
                    <input type="search" wire:model.live.debounce.350ms="search"
                        placeholder="Buscar por nome da empresa ou CNPJ…">
                    @if (trim($search) !== '')
                        <button type="button" class="pnl-search__clear" title="Limpar busca"
                            wire:click="$set('search', '')"><i class="fas fa-times"></i></button>
                    @endif
                </label>
            </div>

            <div class="pnl-card__body">

                @if ($companies->total() == 0)
                    @if (trim($search) !== '')
                        <div class="pnl-empty">
                            <i class="fas fa-search"></i>
                            <p>Nenhuma empresa encontrada para “{{ $search }}”.</p>
                        </div>
                    @else
                        <div class="pnl-empty">
                            <i class="far fa-building"></i>
                            <p>Nenhuma empresa cadastrada ainda.</p>
                            <a href="#" class="u-add-link" data-trigger="modal" data-modal="#modal-data-to-company"
                                wire:click.prevent="$dispatchTo('panel.company.modal-data-to-company', 'eventAction', { 'action': 'store' })">
                                Cadastrar a primeira empresa
                            </a>
                        </div>
                    @endif
                @else
                    <div class="users-list">
                        @foreach ($companies as $company)
                            <div class="user-row" wire:key="company_{{ $company['id'] }}">

                                <span class="user-row__avatar"><i class="far fa-building"></i></span>

                                <div class="user-row__main">
                                    <b>{{ $company['fantasy_name'] ?: $company['corporate_name'] }}</b>
                                    <span>
                                        @if (strlen($company['cnpj_cpf']) > 11)
                                            {{ App\Helpers\Mask::run($company['cnpj_cpf'], '##.###.###/####-##') }}
                                        @else
                                            {{ App\Helpers\Mask::run($company['cnpj_cpf'], '###.###.###-##') }}
                                        @endif
                                    </span>
                                </div>

                                <div class="user-row__meta">
                                    <span class="u-count" title="Usuários vinculados">
                                        <i class="fas fa-users"></i> {{ $company['users_count'] }}
                                    </span>
                                </div>

                                <div class="user-row__actions">
                                    <div class="dropdown">
                                        <a href="#" aria-label="Ações"><i class="fas fa-ellipsis-v"></i></a>
                                        <div class="dropdown-menu right text-left">
                                            <a class="dropdown-item" href="#" data-trigger="modal"
                                                data-modal="#modal-data-to-company"
                                                wire:click.prevent="$dispatchTo('panel.company.modal-data-to-company', 'eventAction', { 'action': 'edit', 'company_id': {{ $company['id'] }} })">
                                                <i class="far fa-edit"></i>
                                                Editar
                                            </a>

                                            <a class="dropdown-item" href="#"
                                                wire:click.prevent="$dispatch('eventCuteConfirmDeleteCompany', { 'id': {{ $company['id'] }} })">
                                                <i class="far fa-minus-square"></i>
                                                Excluir
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    </div>

                    <div class="u-pagination">
                        {{ $companies->links() }}
                    </div>
                @endif

            </div>

        </div>

    </div>

</div>

@section('title', 'Empresas')

@push('modals')
    <livewire:panel.company.modal-data-to-company />
@endpush

{{-- plugins (select2/mask/cute-alert) carregam no layout, uma vez só (p/ wire:navigate) --}}


@push('component-styles')
@endpush

@script
    <script>
        $('.mask-cnpj_cpf').length > 11 ? $('.mask-cnpj_cpf').mask('00.000.000/0000-00',
            MASK_CNPJ_CPF) : $('.mask-cnpj_cpf').mask('000.000.000-00#', MASK_CNPJ_CPF);
        $('.mask-phone').mask(SPMaskBehavior, spOptions);

        Livewire.on('eventCuteConfirmDeleteCompany', (event) => {
            cuteAlert({
                'type': 'question',
                'title': "Confirmação!",
                'message': "Quer excluir permanentemente?",
                'confirmText': "Sim",
                'cancelText': "Não",
            }).then((e) => {

                if (e == "confirm") {
                    $wire.deleteCompany(event.id)
                }

            });

        });

        Livewire.hook('message.processed', (message, component) => {
            //
        });
    </script>
@endscript
