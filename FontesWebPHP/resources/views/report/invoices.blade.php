@extends('report.app')

@section('title', 'Relatório de notas')

@section('content')

    <h2>Relatório de notas fiscais</h2>
    <small>Período: {!! App\Helpers\Format::periodHtml($searchArgsToDocReport) !!}</small>

    <hr>

    @if (!empty($capNotice))
        <p class="alert">{{ $capNotice }}</p>
    @endif

    <h4>Visão geral</h4>

    <table>
        <tr>
            <th>Modelo</th>
            <th>Status</th>
            <th>Quantidade</th>
            <th>Total</th>
        </tr>

        @foreach ($invoicesOverview as $value)
            <tr>
                <td>{{ $value['model'] }}</td>
                <td>{{ $value['status_xml'] }}</td>
                <td>{{ $value['qty'] }}</td>
                {{-- Inutilização não tem valor (o número foi queimado, nota
                     nenhuma existiu): "R$ 0,00" seria lido como venda zerada. --}}
                <td>
                    @if (is_null($value['total'] ?? null))
                        —
                    @else
                        R$ {{ number_format($value['total'], 2, ',', '.') }}
                    @endif
                </td>
            </tr>
        @endforeach

    </table>

    <h4>Lista de notas</h4>

    <table>
        <tr>
            <th>CNPJ/CPF</th>
            <th>Série</th>
            <th>N.º</th>
            <th>Chave</th>
            <th>Valor</th>
            <th>Modelo</th>
            <th>Status</th>
            <th>Data Emissão</th>
        </tr>

        @foreach ($invoices as $invoice)
            <tr>
                @if (strlen($invoice->cnpj_cpf) > 11)
                    <td>{{ App\Helpers\Mask::run($invoice->cnpj_cpf, '##.###.###/####-##') }}</td>
                @else
                    <td>{{ App\Helpers\Mask::run($invoice->cnpj_cpf, '###.###.###-##') }}</td>
                @endif

                <td>{{ $invoice->series }}</td>
                <td>{{ $invoice->number }}</td>
                <td>{{ $invoice->key }}</td>
                <td>R$ {{ number_format($invoice->vNF, 2, ',', '.') }}</td>
                <td class="text-center">
                    @if ($invoice->model == 55)
                        <span class="badge badge-blue">NF-e</span>
                    @elseif ($invoice->model == 57)
                        <span class="badge badge-blue">CT-e</span>
                    @elseif ($invoice->model == 58)
                        <span class="badge badge-blue">MDF-e</span>
                    @elseif ($invoice->model == 59)
                        <span class="badge badge-blue">Entrada</span>
                    @elseif ($invoice->model == 65)
                        <span class="badge badge-blue">NFC-e</span>
                    @endif
                </td>
                <td class="text-center">
                    {{-- Rótulo/cor pelo helper único, em vez de repetir os códigos
                         aqui: fora dos conhecidos vira "Rejeitada". --}}
                    @php ($grupo = App\Livewire\Panel\Documents\Index::groupForCode($invoice->status_xml))
                    @if ($grupo)
                        <span class="badge badge-{{ $grupo['color'] }} badge-round">{{ $grupo['label'] }}</span>
                    @else
                        <span class="badge badge-default badge-round">Status {{ $invoice->status_xml }}</span>
                    @endif
                </td>
                <td class="text-center">{{ date('d/m/Y', strtotime($invoice->issue_dh)) }}</td>
            </tr>

        @endforeach

    </table>

    @if (($extraDisables ?? null) && count($extraDisables))
        <h4>Inutilizações</h4>

        <table>
            <tr>
                <th>CNPJ</th>
                <th>Modelo</th>
                <th>Série</th>
                <th>N.º Inicial</th>
                <th>N.º Final</th>
                <th>Protocolo</th>
                <th>Data Emissão</th>
            </tr>

            @foreach ($extraDisables as $disable)
                <tr>
                    @if (strlen($disable->cnpj) > 11)
                        <td>{{ App\Helpers\Mask::run($disable->cnpj, '##.###.###/####-##') }}</td>
                    @else
                        <td>{{ App\Helpers\Mask::run($disable->cnpj, '###.###.###-##') }}</td>
                    @endif

                    <td>
                        @if ($disable->model == 55)
                            <span class="badge badge-blue">NF-e</span>
                        @elseif ($disable->model == 57)
                            <span class="badge badge-blue">CT-e</span>
                        @elseif ($disable->model == 58)
                            <span class="badge badge-blue">MDF-e</span>
                        @elseif ($disable->model == 65)
                            <span class="badge badge-blue">NFC-e</span>
                        @endif
                    </td>

                    <td>{{ $disable->series }}</td>
                    <td>{{ $disable->number_start }}</td>
                    <td>{{ $disable->number_end }}</td>
                    <td>{{ $disable->protocol_number }}</td>
                    <td>{{ date('d/m/Y', strtotime($disable->event_dh)) }}</td>
                </tr>
            @endforeach

        </table>
    @endif

@endsection
