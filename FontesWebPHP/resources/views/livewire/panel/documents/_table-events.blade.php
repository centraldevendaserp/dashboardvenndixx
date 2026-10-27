{{-- Listagem de cancelamentos/CC-e (event_documents): abrange modelos, então
     mantém a coluna "Modelo". Colunas centralizadas. --}}
<div class="table-wrap {{ $rows->lastPage() == 1 ? 'mb-30' : '' }}">
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 60px;"></th>
                <th class="text-center">CNPJ</th>
                {{-- N.º da nota e Chave permitem casar o cancelamento com a nota
                     que ele cancela. "Protocolo do evento" nao e o protocolo de
                     autorizacao: sao operacoes diferentes na SEFAZ. --}}
                <th class="text-center">N.º nota</th>
                <th class="text-center">Chave</th>
                <th class="text-center">Protocolo do evento</th>
                <th class="text-center">N.º evento</th>
                <th class="text-center">Evento</th>
                <th class="text-center">Modelo</th>
                <th class="text-center">Data/Emissão</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $event)
                <tr wire:key="event-{{ $event->id }}">
                    <td class="text-center">
                        <div class="dropdown">
                            <a href="#" class="text-dark-gray"><i class="fas fa-ellipsis-v"></i></a>
                            <div class="dropdown-menu left text-left">
                                <a class="dropdown-item" href="#"
                                    wire:click.prevent="$dispatchTo('panel.dashboard.detail-doc', 'eventDetailDoc', { id: {{ $event->id }}, type: 'event' })">
                                    <i class="fas fa-eye"></i>
                                    Ver detalhes
                                </a>
                                <a class="dropdown-item" href="#"
                                    wire:click.prevent="downloadDocById({{ $event->id }}, 'xml')">
                                    <i class="fas fa-download"></i>
                                    Download xml
                                </a>
                                @if (in_array($event->model, [55, 65]))
                                    <a class="dropdown-item" target="_blank"
                                        href="{{ route('panel.docs.print.event.nfenfce', ['id' => $event->id]) }}">
                                        <i class="fas fa-print"></i>
                                        Imprimir pdf
                                    </a>
                                @elseif ($event->model == 57)
                                    <a class="dropdown-item" target="_blank"
                                        href="{{ route('panel.docs.print.event.cte', ['id' => $event->id]) }}">
                                        <i class="fas fa-print"></i>
                                        Imprimir pdf
                                    </a>
                                @endif
                            </div>
                        </div>
                    </td>

                    @if (strlen($event->cnpj) > 11)
                        <td class="text-center">{{ App\Helpers\Mask::run($event->cnpj, '##.###.###/####-##') }}</td>
                    @else
                        <td class="text-center">{{ App\Helpers\Mask::run($event->cnpj, '###.###.###-##') }}</td>
                    @endif

                    {{-- Serie/numero vivem DENTRO da chave (digitos 23-25 e 26-34);
                         event_documents nao guarda os dois em coluna propria. --}}
                    <td class="text-center">
                        @if (strlen((string) $event->nfe_key) === 44)
                            {{ (int) substr($event->nfe_key, 25, 9) }} / {{ (int) substr($event->nfe_key, 22, 3) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-center fs-mono">
                        @if (filled($event->nfe_key))
                            <span title="{{ $event->nfe_key }}">…{{ substr($event->nfe_key, -10) }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-center">{{ $event->protocol_number }}</td>
                    <td class="text-center">
                        {{ $event->event_number }}
                        @include('livewire.panel.documents._selo-homolog', ['ambiente' => $event->environment_type])
                    </td>
                    <td class="text-center">{{ $event->event_desc }}</td>
                    <td class="text-center">
                        @include('livewire.panel.dashboard.partials.model-badge', ['model' => $event->model])
                    </td>
                    <td class="text-center">{{ date('d/m/Y', strtotime($event->event_dh)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
