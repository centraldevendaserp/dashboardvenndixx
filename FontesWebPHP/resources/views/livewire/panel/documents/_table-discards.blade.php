{{-- Notas descartadas no sistema de vendas: a venda existiu, ganhou número e
     foi jogada fora antes de virar documento. Tem valor e não tem protocolo — o
     oposto da aba "Inutilizações gerais". Sem download: não há XML. --}}
<div class="table-wrap {{ $rows->lastPage() == 1 ? 'mb-30' : '' }}">
    <table>
        <thead>
            <tr>
                <th class="text-center">CNPJ/CPF</th>
                <th class="text-center">Modelo</th>
                <th class="text-center">Série</th>
                <th class="text-center">N.º</th>
                <th class="text-center">Valor</th>
                <th class="text-center">Data Emissão</th>
                <th class="text-center">Ambiente</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $doc)
                <tr wire:key="discard-{{ $doc->id }}">
                    @if (strlen($doc->cnpj_cpf) > 11)
                        <td class="text-center">{{ App\Helpers\Mask::run($doc->cnpj_cpf, '##.###.###/####-##') }}</td>
                    @else
                        <td class="text-center">{{ App\Helpers\Mask::run($doc->cnpj_cpf, '###.###.###-##') }}</td>
                    @endif

                    <td class="text-center">
                        @include('livewire.panel.dashboard.partials.model-badge', ['model' => $doc->model])
                    </td>
                    <td class="text-center">{{ $doc->series ?: '—' }}</td>
                    <td class="text-center">
                        {{ $doc->number }}
                        @include('livewire.panel.documents._selo-homolog', ['ambiente' => $doc->environment_type])
                    </td>
                    <td class="text-center">R$ {{ number_format($doc->value, 2, ',', '.') }}</td>
                    <td class="text-center">{{ $doc->issue_dh ? date('d/m/Y', strtotime($doc->issue_dh)) : '—' }}</td>
                    {{-- O ERP não guarda tpAmb e o descarte nunca foi à SEFAZ: não há
                         ambiente a informar. "Produção" aqui seria invenção. --}}
                    <td class="text-center">
                        @if ($doc->environment_type === '2')
                            @include('livewire.panel.documents._selo-homolog', ['ambiente' => $doc->environment_type])
                        @elseif ($doc->environment_type === '1')
                            Produção
                        @else
                            <span title="A nota nunca foi transmitida à SEFAZ e o ERP não guarda o ambiente das notas de NF-e/NFC-e — não há o que informar.">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
