<div>

    @if ($invoices->total() == 0)
        <div class="row">
            <div class="col-100 mb-30">
                <div class="alert alert-default">
                    <p>Nenhum documento</p>
                </div>
            </div>
        </div>
    @else
        <!-- table -->
        <div class="table-wrap {{ $invoices->lastPage() == 1 ? 'mb-30' : '' }}">

            <table>

                <thead>
                    <tr>
                        <th class="text-center" style="width: 60px;"></th>
                        <th class="text-center">CNPJ/CPF</th>
                        <th class="text-center">Série</th>
                        <th class="text-center">N.º</th>
                        <th class="text-center">Valor</th>
                        <th class="text-center">Modelo</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Data Emissão</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach ($invoices as $invoice)
                        <tr wire:key="invoice-{{ $invoice->id }}">
                            <td class="text-center">
                                <div class="dropdown">
                                    <a href="#" class="text-dark-gray"><i class="fas fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu left text-left">
                                        <a class="dropdown-item" href="#"
                                            wire:click.prevent="$dispatchTo('panel.dashboard.detail-doc', 'eventDetailDoc', { id: {{ $invoice->id }}, type: 'invoice' })">
                                            <i class="fas fa-eye"></i>
                                            Ver detalhes
                                        </a>
                                        <a class="dropdown-item" href="#"
                                            wire:click.prevent="downloadDocById({{ $invoice->id }}, 'xml')">
                                            <i class="fas fa-download"></i>
                                            Download xml
                                        </a>

                                        <a class="dropdown-item" target="_blank"
                                            href="{{ route('panel.docs.print.invoice', ['id' => $invoice->id]) }}">
                                            <i class="fas fa-print"></i>
                                            Imprimir pdf
                                        </a>
                                    </div>
                                </div>
                            </td>

                            @if (strlen($invoice->cnpj_cpf) > 11)
                                <td class="text-center">{{ App\Helpers\Mask::run($invoice->cnpj_cpf, '##.###.###/####-##') }}</td>
                            @else
                                <td class="text-center">{{ App\Helpers\Mask::run($invoice->cnpj_cpf, '###.###.###-##') }}</td>
                            @endif

                            <td class="text-center">{{ $invoice->series }}</td>
                            <td class="text-center">{{ $invoice->number }}</td>
                            <td class="text-center">R$ {{ number_format($invoice->vNF, 2, ',', '.') }}</td>
                            <td class="text-center">
                                @include('livewire.panel.dashboard.partials.model-badge', ['model' => $invoice->model])
                            </td>
                            <td class="text-center">
                                {{-- Rótulo/cor pelo helper único (o mesmo da tela por tipo):
                                     código fora dos conhecidos vira "Rejeitada" em vez de
                                     célula em branco. --}}
                                @php ($grupo = App\Livewire\Panel\Documents\Index::groupForCode($invoice->status_xml))
                                @if ($grupo)
                                    <span class="badge badge-{{ $grupo['color'] }} badge-round">{{ $grupo['label'] }}</span>
                                @else
                                    <span class="badge badge-default badge-round">Status {{ $invoice->status_xml }}</span>
                                @endif
                            </td>
                            <td class="text-center">{{ date('d/m/Y', strtotime($invoice->issue_dh)) }}
                            </td>
                        </tr>
                    @endforeach

                </tbody>

            </table>

        </div>
        <!-- table-wrap -->

        {{ $invoices->links() }}

    @endif

</div>
