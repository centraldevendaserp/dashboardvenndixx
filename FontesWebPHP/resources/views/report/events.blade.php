@extends('report.app')

@section('title', 'Relatório de eventos')

@section('content')

    <h2>Relatório de eventos</h2>
    <small>Período: {!! App\Helpers\Format::periodHtml($searchArgsToDocReport) !!}</small>

    <hr>

    @if (!empty($capNotice))
        <p class="alert">{{ $capNotice }}</p>
    @endif

    <h4>Lista de eventos</h4>

    <table>
        <tr>
            <th>CNPJ</th>
            <th>Modelo</th>
            <th>N.º evento</th>
            <th>Chave</th>
            <th>Descrição</th>
            <th>Protocolo</th>
            <th>Data Emissão</th>
        </tr>

        @foreach ($events as $event)
            <tr>
                @if (strlen($event->cnpj) > 11)
                    <td>{{ App\Helpers\Mask::run($event->cnpj, '##.###.###/####-##') }}</td>
                @else
                    <td>{{ App\Helpers\Mask::run($event->cnpj, '###.###.###-##') }}</td>
                @endif

                <td>
                    @if ($event->model == 55)
                        <span class="badge badge-blue">NF-e</span>
                    @elseif ($event->model == 57)
                        <span class="badge badge-blue">CT-e</span>
                    @elseif ($event->model == 58)
                        <span class="badge badge-blue">MDF-e</span>
                    @elseif ($event->model == 59)
                        <span class="badge badge-blue">CF-e Sat</span>
                    @elseif ($event->model == 65)
                        <span class="badge badge-blue">NFC-e</span>
                    @endif
                </td>

                <td>{{ $event->event_number }}</td>
                <td>{{ $event->nfe_key }}</td>
                <td>{{ $event->event_desc }}</td>
                <td>{{ $event->protocol_number }}</td>

                <td>{{ date('d/m/Y', strtotime($event->event_dh)) }}</td>
            </tr>

        @endforeach

    </table>

@endsection
