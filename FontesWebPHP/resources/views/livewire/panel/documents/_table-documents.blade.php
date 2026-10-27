{{-- Listagem dos 5 modelos de nota (documents), filtrada por 1 modelo:
     sem coluna "Modelo" (redundante). Colunas centralizadas. --}}
<div class="table-wrap {{ $rows->lastPage() == 1 ? 'mb-30' : '' }}">
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 60px;"></th>
                <th class="text-center">CNPJ/CPF</th>
                <th class="text-center">Série</th>
                <th class="text-center">N.º</th>
                <th class="text-center">Valor</th>
                <th class="text-center">Status</th>
                <th class="text-center">Data Emissão</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $doc)
                <tr wire:key="doc-{{ $doc->id }}">
                    <td class="text-center">
                        <div class="dropdown">
                            <a href="#" class="text-dark-gray"><i class="fas fa-ellipsis-v"></i></a>
                            <div class="dropdown-menu left text-left">
                                <a class="dropdown-item" href="#"
                                    wire:click.prevent="$dispatchTo('panel.dashboard.detail-doc', 'eventDetailDoc', { id: {{ $doc->id }}, type: 'invoice' })">
                                    <i class="fas fa-eye"></i>
                                    Ver detalhes
                                </a>
                                <a class="dropdown-item" href="#"
                                    wire:click.prevent="downloadDocById({{ $doc->id }}, 'xml')">
                                    <i class="fas fa-download"></i>
                                    Download xml
                                </a>
                                <a class="dropdown-item" target="_blank"
                                    href="{{ route('panel.docs.print.invoice', ['id' => $doc->id]) }}">
                                    <i class="fas fa-print"></i>
                                    Imprimir pdf
                                </a>
                            </div>
                        </div>
                    </td>

                    @if (strlen($doc->cnpj_cpf) > 11)
                        <td class="text-center">{{ App\Helpers\Mask::run($doc->cnpj_cpf, '##.###.###/####-##') }}</td>
                    @else
                        <td class="text-center">{{ App\Helpers\Mask::run($doc->cnpj_cpf, '###.###.###-##') }}</td>
                    @endif

                    <td class="text-center">{{ $doc->series }}</td>
                    <td class="text-center">
                        {{ $doc->number }}
                        @include('livewire.panel.documents._selo-homolog', ['ambiente' => $doc->environment_type])
                    </td>
                    <td class="text-center">R$ {{ number_format($doc->vNF, 2, ',', '.') }}</td>
                    <td class="text-center">
                        @php($st = \App\Livewire\Panel\Documents\Index::groupForCode($doc->status_xml))
                        @if ($st)
                            <span class="badge badge-{{ $st['color'] }} badge-round">{{ $st['label'] }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ date('d/m/Y', strtotime($doc->issue_dh)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
