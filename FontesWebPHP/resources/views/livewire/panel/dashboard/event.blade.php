<div>

    @if ($events->total() == 0)
        <div class="row">
            <div class="col-100 mb-30">
                <div class="alert alert-default">
                    <p>Nenhum documento</p>
                </div>
            </div>
        </div>
    @else
        <!-- table -->
        <div class="table-wrap {{ $events->lastPage() == 1 ? 'mb-30' : '' }}">

            <table>

                <thead>
                    <tr>
                        <th class="text-center" style="width: 60px;"></th>
                        <th class="text-center">CNPJ</th>
                        <th class="text-center">N.º evento</th>
                        <th class="text-center">N.º</th>
                        <th class="text-center">N.º protocolo</th>
                        <th class="text-center">Modelo</th>
                        <th class="text-center">Evento</th>
                        <th class="text-center">Data/Emissão</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach ($events as $event)
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
                                        @switch($event->model)
                                            @case(55)
                                                <a class="dropdown-item" target="_blank"
                                                    href="{{ route('panel.docs.print.event.nfenfce', ['id' => $event->id]) }}">
                                                    <i class="fas fa-print"></i>
                                                    Imprimir pdf
                                                </a>
                                            @break

                                            @case(65)
                                                <a class="dropdown-item" target="_blank"
                                                    href="{{ route('panel.docs.print.event.nfenfce', ['id' => $event->id]) }}">
                                                    <i class="fas fa-print"></i>
                                                    Imprimir pdf
                                                </a>
                                            @break

                                            @case(57)
                                                <a class="dropdown-item" target="_blank"
                                                    href="{{ route('panel.docs.print.event.cte', ['id' => $event->id]) }}">
                                                    <i class="fas fa-print"></i>
                                                    Imprimir pdf
                                                </a>
                                            @break
                                        @endswitch
                                    </div>
                                </div>
                            </td>

                            @if (strlen($event->cnpj) > 11)
                                <td class="text-center">{{ App\Helpers\Mask::run($event->cnpj, '##.###.###/####-##') }}</td>
                            @else
                                <td class="text-center">{{ App\Helpers\Mask::run($event->cnpj, '###.###.###-##') }}</td>
                            @endif

                            <td class="text-center">{{ $event->event_number }}</td>
                            {{-- N.º da nota: extraído da chave (nNF, 9 díg. a partir da 26ª) --}}
                            <td class="text-center">{{ strlen($event->nfe_key ?? '') >= 34 ? ltrim(substr($event->nfe_key, 25, 9), '0') : '—' }}</td>
                            <td class="text-center">{{ $event->protocol_number }}</td>
                            <td class="text-center">
                                @include('livewire.panel.dashboard.partials.model-badge', ['model' => $event->model])
                            </td>
                            <td class="text-center">
                                @php
                                    $ev = \Illuminate\Support\Str::lower($event->event_desc ?? '');
                                    $evClass = \Illuminate\Support\Str::contains($ev, 'cancel') ? 'badge-red'
                                        : (\Illuminate\Support\Str::contains($ev, ['corre', 'cce', 'carta']) ? 'badge-amber' : 'badge-default');
                                @endphp
                                <span class="badge {{ $evClass }} badge-round">{{ $event->event_desc }}</span>
                            </td>
                            <td class="text-center">{{ date('d/m/Y', strtotime($event->event_dh)) }}</td>
                        </tr>
                    @endforeach

                </tbody>

            </table>

        </div>
        <!-- table-wrap -->

        {{ $events->links() }}

    @endif

</div>
