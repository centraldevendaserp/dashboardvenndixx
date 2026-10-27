@extends('report.app')

@section('title', 'Relatório de NFS-e')

@section('content')

    <h2>Relatório de NFS-e</h2>
    <small>Período: {!! App\Helpers\Format::periodHtml($searchArgsToDocReport) !!}</small>

    <hr>

    @if (!empty($capNotice))
        <p class="alert">{{ $capNotice }}</p>
    @endif

    <h4>Lista de NFS-e</h4>

    <table>
        <tr>
            <th>Prestador (CNPJ/CPF)</th>
            <th>Número</th>
            <th>Município</th>
            <th>Valor</th>
            <th>Situação</th>
            <th>Data Emissão</th>
        </tr>

        @foreach ($nfses as $nfse)
            <tr>
                @if (strlen($nfse->cnpj_prestador) > 11)
                    <td>{{ App\Helpers\Mask::run($nfse->cnpj_prestador, '##.###.###/####-##') }}</td>
                @else
                    <td>{{ App\Helpers\Mask::run($nfse->cnpj_prestador, '###.###.###-##') }}</td>
                @endif

                {{-- Homologação não gasta número: sem a marca, o relatório
                     impresso mostraria o mesmo número repetido sem explicação. --}}
                <td>{{ $nfse->numero }}@if ($nfse->environment_type === '2') <small>(homologação)</small>@endif</td>
                <td>{{ $nfse->municipio ?: '—' }}</td>
                <td>R$ {{ number_format($nfse->valor, 2, ',', '.') }}</td>
                <td>{{ $nfse->situacao ?: '—' }}</td>
                <td>{{ $nfse->issue_dh ? date('d/m/Y', strtotime($nfse->issue_dh)) : '—' }}</td>
            </tr>
        @endforeach

    </table>

@endsection
