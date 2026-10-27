{{-- Linhas SEM NOTA no portal (ledger fiscal_status): recusa não gera XML,
     então não há ações — só os dados do retorno da SEFAZ. Aceita paginator
     (tabela principal) ou Collection (secundária).

     Lista rejeição E duplicidade, então o rótulo do badge sai da CATEGORIA. --}}
@php($rotulos = \App\Models\FiscalStatus::CATEGORIES)
@php($cores = ['duplicidade' => 'amber'])
@php($ehPaginado = $rows instanceof \Illuminate\Contracts\Pagination\Paginator)
<div class="table-wrap fs-table {{ !$ehPaginado || $rows->lastPage() == 1 ? 'mb-30' : '' }}">
    <table>
        <thead>
            <tr>
                <th class="text-center">Data</th>
                <th class="text-center">N.º / Série</th>
                <th class="text-center">Chave</th>
                <th class="text-center">Status</th>
                <th class="text-left">Motivo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $rejeicao)
                <tr wire:key="rejeicao-{{ $rejeicao->id }}">
                    <td class="text-center">{{ $rejeicao->dataHoraLegivel() }}</td>
                    <td class="text-center fs-mono">
                        {{ $rejeicao->number }} / {{ $rejeicao->series }}
                        @include('livewire.panel.documents._selo-homolog', ['ambiente' => $rejeicao->environment_type])
                    </td>
                    <td class="text-center fs-mono"><span title="{{ $rejeicao->key }}">…{{ substr($rejeicao->key, -10) }}</span></td>
                    <td class="text-center">
                        {{-- ⚠️ cStat em TERNÁRIO, não @if: o Livewire envolve @if de
                             conteúdo em comentários de morph, que entrariam entre o
                             rótulo e o "(cstat)". Linha do ERP vem sem cStat. --}}
                        <span class="badge badge-{{ $cores[$rejeicao->category] ?? 'red' }} badge-round"
                            title="{{ $rejeicao->x_motivo }}">{{ $rotulos[$rejeicao->category] ?? 'Rejeitada' }}{{ $rejeicao->cstat ? ' (' . $rejeicao->cstat . ')' : '' }}</span>
                    </td>
                    <td class="text-left fs-motivo">{{ $rejeicao->x_motivo ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
