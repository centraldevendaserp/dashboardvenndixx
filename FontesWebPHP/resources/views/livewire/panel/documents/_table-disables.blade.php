{{-- Listagem de inutilizações (disable_documents): abrange modelos, mantém
     a coluna "Modelo". Sem "Imprimir pdf" (não há DANFE de inutilização).
     Aceita paginator (tabela principal) OU Collection (tabela secundária). --}}
@php($ehPaginado = $rows instanceof \Illuminate\Contracts\Pagination\Paginator)
<div class="table-wrap {{ !$ehPaginado || $rows->lastPage() == 1 ? 'mb-30' : '' }}">
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 60px;"></th>
                <th class="text-center">CNPJ</th>
                <th class="text-center">Série</th>
                <th class="text-center">N.º Protocolo</th>
                <th class="text-center">N.º Inicial</th>
                <th class="text-center">N.º Final</th>
                <th class="text-center">Modelo</th>
                <th class="text-center">Data/Emissão</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $disable)
                <tr wire:key="disable-{{ $disable->id }}">
                    <td class="text-center">
                        <div class="dropdown">
                            <a href="#" class="text-dark-gray"><i class="fas fa-ellipsis-v"></i></a>
                            <div class="dropdown-menu left text-left">
                                <a class="dropdown-item" href="#"
                                    wire:click.prevent="$dispatchTo('panel.dashboard.detail-doc', 'eventDetailDoc', { id: {{ $disable->id }}, type: 'disable' })">
                                    <i class="fas fa-eye"></i>
                                    Ver detalhes
                                </a>
                                <a class="dropdown-item" href="#"
                                    wire:click.prevent="downloadDocById({{ $disable->id }}, 'xml')">
                                    <i class="fas fa-download"></i>
                                    Download xml
                                </a>
                            </div>
                        </div>
                    </td>

                    @if (strlen($disable->cnpj) > 11)
                        <td class="text-center">{{ App\Helpers\Mask::run($disable->cnpj, '##.###.###/####-##') }}</td>
                    @else
                        <td class="text-center">{{ App\Helpers\Mask::run($disable->cnpj, '###.###.###-##') }}</td>
                    @endif

                    <td class="text-center">{{ $disable->series }}</td>
                    <td class="text-center">
                        {{ $disable->protocol_number }}
                        @include('livewire.panel.documents._selo-homolog', ['ambiente' => $disable->environment_type])
                    </td>
                    <td class="text-center">{{ $disable->number_start }}</td>
                    <td class="text-center">{{ $disable->number_end }}</td>
                    <td class="text-center">
                        @include('livewire.panel.dashboard.partials.model-badge', ['model' => $disable->model])
                    </td>
                    <td class="text-center">{{ date('d/m/Y', strtotime($disable->event_dh)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
