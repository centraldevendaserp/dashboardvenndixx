@extends('report.app')

@section('title', 'Relatório de rejeições')

@section('content')

    <h2>Relatório de rejeições</h2>
    <small>Período: {!! App\Helpers\Format::periodHtml($searchArgsToDocReport) !!}</small>

    <hr>

    @if (!empty($capNotice))
        <p class="alert">{{ $capNotice }}</p>
    @endif

    {{-- O ledger lista rejeição E duplicidade: título e rótulo saem da CATEGORIA
         das linhas, senão o PDF diz "Rejeições" numa página de duplicidades. --}}
    @php($cats = collect($rejeicoes)->pluck('category')->unique()->values()->all())
    @php($plural = ['rejeitada' => 'Rejeições', 'duplicidade' => 'Duplicidades'])
    <h4>{{ implode(' e ', array_map(fn ($c) => $plural[$c] ?? 'Rejeições', $cats ?: ['rejeitada'])) }} (sem nota no portal)</h4>

    <table>
        <tr>
            <th>Data</th>
            <th>N.º / Série</th>
            <th>Chave</th>
            <th>Status</th>
            <th>Motivo</th>
        </tr>

        @foreach ($rejeicoes as $rejeicao)
            <tr>
                <td>{{ $rejeicao->dh_recbto?->format('d/m/Y H:i') ?? '—' }}</td>
                <td>{{ $rejeicao->number }} / {{ $rejeicao->series }}</td>
                <td>{{ $rejeicao->key }}</td>
                <td>{{ \App\Models\FiscalStatus::CATEGORIES[$rejeicao->category] ?? 'Rejeitada' }}{{ $rejeicao->cstat ? ' (' . $rejeicao->cstat . ')' : '' }}</td>
                <td>{{ $rejeicao->x_motivo ?? '—' }}</td>
            </tr>
        @endforeach

    </table>

@endsection
