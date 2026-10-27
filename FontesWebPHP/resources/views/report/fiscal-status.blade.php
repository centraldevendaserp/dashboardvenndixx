@extends('report.app')

@section('title', 'Relatório — Status SEFAZ')

@section('content')

    <h2>Relatório — Status SEFAZ</h2>
    <small>Período: {!! App\Helpers\Format::periodHtml($searchArgsToDocReport) !!}</small>

    <hr>

    @if (!empty($capNotice))
        <p class="alert">{{ $capNotice }}</p>
    @endif

    <h4>Notas rejeitadas ou emitidas em contingência</h4>

    @php($tipos = [55 => 'NF-e', 65 => 'NFC-e', 57 => 'CT-e', 58 => 'MDF-e', 67 => 'CT-e OS'])

    <table>
        <tr>
            <th>Data</th>
            <th>Tipo</th>
            <th>N.º / Série</th>
            <th>Chave</th>
            <th>Situação</th>
            <th>Motivo</th>
            <th>Ambiente</th>
        </tr>

        @foreach ($statuses as $st)
            <tr>
                <td>{{ $st->dh_recbto?->format('d/m/Y H:i') ?? '—' }}</td>
                <td>{{ $tipos[(int) $st->model] ?? $st->model }}</td>
                <td>{{ $st->number }} / {{ $st->series }}</td>
                <td>{{ $st->key }}</td>
                <td>
                    {{-- Uma nota tem UMA situação: o 217 numa nota offline é
                         contingência, não rejeição (a SEFAZ nunca a recebeu). --}}
                    @php($catEfetiva = $st->categoriaEfetiva())
                    {{ $catEfetiva === \App\Models\FiscalStatus::CATEGORY_CONTINGENCIA
                        ? 'Contingência'
                        : (\App\Models\FiscalStatus::CATEGORIES[$catEfetiva] ?? $catEfetiva) }}{{ is_null($st->cstat) ? '' : ' ('.$st->cstat.')' }}
                    @if ($st->estaEmContingencia() && ! is_null($st->contingenciaLabel()))
                        <br><small>Contingência: {{ $st->contingenciaLabel() }}</small>
                    @endif
                </td>
                <td>{{ $st->x_motivo ?? '—' }}</td>
                <td>{{ $st->environment_type === '2' ? 'Homolog.' : ($st->environment_type === '1' ? 'Produção' : '—') }}</td>
            </tr>
        @endforeach

    </table>

@endsection
