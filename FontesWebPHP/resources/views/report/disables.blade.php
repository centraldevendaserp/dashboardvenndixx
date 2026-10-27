@extends('report.app')

@section('title', 'Relatório de inutilizadas')

@section('content')

    <h2>Relatório de inutilizadas</h2>
    <small>Período: {!! App\Helpers\Format::periodHtml($searchArgsToDocReport) !!}</small>

    <hr>

    @if (!empty($capNotice))
        <p class="alert">{{ $capNotice }}</p>
    @endif

    <h4>Lista de inutilizadas</h4>

    <table>
        <tr>
            <th>CNPJ</th>
            <th>Ano</th>
            <th>Modelo</th>
            <th>Série</th>
            <th>N.º Inicial</th>
            <th>N.º Final</th>
            <th>Protocolo</th>
            <th>Data Emissão</th>
        </tr>

        @foreach ($disables as $disable)
            <tr>
                @if (strlen($disable->cnpj) > 11)
                    <td>{{ App\Helpers\Mask::run($disable->cnpj, '##.###.###/####-##') }}</td>
                @else
                    <td>{{ App\Helpers\Mask::run($disable->cnpj, '###.###.###-##') }}</td>
                @endif

                <td>{{ $disable->year }}</td>

                <td>
                    @if ($disable->model == 55)
                        <span class="badge badge-blue">NF-e</span>
                    @elseif ($disable->model == 57)
                        <span class="badge badge-blue">CT-e</span>
                    @elseif ($disable->model == 58)
                        <span class="badge badge-blue">MDF-e</span>
                    @elseif ($disable->model == 59)
                        <span class="badge badge-blue">CF-e Sat</span>
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

@endsection
