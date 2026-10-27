@extends('report.app')

@section('title', 'Relatório de descartes do sistema')

@section('content')

    <h2>Descartes do sistema</h2>
    <small>Período: {!! App\Helpers\Format::periodHtml($searchArgsToDocReport) !!}</small>

    <hr>

    @if (!empty($capNotice))
        <p class="alert">{{ $capNotice }}</p>
    @endif

    {{-- Diz o que a lista é: sem isto, "descarte" se confunde com a inutilização
         de faixa homologada pela SEFAZ, que tem protocolo e não tem valor. --}}
    <p class="alert">
        Vendas que ganharam número no sistema e foram descartadas <strong>antes</strong> de virar
        documento fiscal. Não foram transmitidas à SEFAZ e não têm protocolo — por isso não
        aparecem em “Inutilizações gerais”, que lista o evento fiscal de inutilização de faixa.
    </p>

    <h4>Lista de descartes</h4>

    <table>
        <tr>
            <th>CNPJ/CPF</th>
            <th>Modelo</th>
            <th>Série</th>
            <th>N.º</th>
            <th>Valor</th>
            <th>Data Emissão</th>
        </tr>

        @foreach ($discards as $doc)
            <tr>
                @if (strlen($doc->cnpj_cpf) > 11)
                    <td>{{ App\Helpers\Mask::run($doc->cnpj_cpf, '##.###.###/####-##') }}</td>
                @else
                    <td>{{ App\Helpers\Mask::run($doc->cnpj_cpf, '###.###.###-##') }}</td>
                @endif

                <td>{{ (int) $doc->model === 55 ? 'NF-e' : ((int) $doc->model === 65 ? 'NFC-e' : $doc->model) }}</td>
                <td>{{ $doc->series ?: '—' }}</td>
                <td>{{ $doc->number }}</td>
                <td>R$ {{ number_format($doc->value, 2, ',', '.') }}</td>
                <td>{{ $doc->issue_dh ? date('d/m/Y', strtotime($doc->issue_dh)) : '—' }}</td>
            </tr>
        @endforeach

        <tr>
            <th colspan="4" style="text-align: right;">Total</th>
            <th>R$ {{ number_format($discards->sum('value'), 2, ',', '.') }}</th>
            <th></th>
        </tr>
    </table>

@endsection
