{{-- Listagem de NFS-e (nfse_documents): prestador, número, município, valor e
     situação TEXTUAL. Sem "Imprimir pdf" (DANFSE não tem layout único) nem
     "Ver detalhes" (o detail-doc é dos documentos SEFAZ) — só download do XML. --}}
<div class="table-wrap {{ $rows->lastPage() == 1 ? 'mb-30' : '' }}">
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 60px;"></th>
                <th class="text-center">Prestador (CNPJ/CPF)</th>
                <th class="text-center">Número</th>
                <th class="text-center">Município</th>
                <th class="text-center">Valor</th>
                <th class="text-center">Situação</th>
                <th class="text-center">Data Emissão</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $doc)
                <tr wire:key="doc-{{ $doc->id }}">
                    <td class="text-center">
                        <div class="dropdown">
                            <a href="#" class="text-dark-gray"><i class="fas fa-ellipsis-v"></i></a>
                            <div class="dropdown-menu left text-left">
                                <a class="dropdown-item" href="#"
                                    wire:click.prevent="downloadDocById({{ $doc->id }}, 'xml')">
                                    <i class="fas fa-download"></i>
                                    Download xml
                                </a>
                            </div>
                        </div>
                    </td>

                    @if (strlen($doc->cnpj_prestador) > 11)
                        <td class="text-center">{{ App\Helpers\Mask::run($doc->cnpj_prestador, '##.###.###/####-##') }}</td>
                    @else
                        <td class="text-center">{{ App\Helpers\Mask::run($doc->cnpj_prestador, '###.###.###-##') }}</td>
                    @endif

                    {{-- Homologação não gasta número: várias emissões ficam com o MESMO
                         número. Sem o selo, parecem nota duplicada na listagem. --}}
                    <td class="text-center">
                        {{ $doc->numero }}
                        @include('livewire.panel.documents._selo-homolog', ['ambiente' => $doc->environment_type])
                    </td>
                    <td class="text-center">{{ $doc->municipio ?: '—' }}</td>
                    <td class="text-center">R$ {{ number_format($doc->valor, 2, ',', '.') }}</td>
                    <td class="text-center">
                        @php($cor = \App\Livewire\Panel\Documents\Index::corSituacaoNfse($doc->situacao))
                        <span class="badge badge-{{ $cor }} badge-round">{{ $doc->situacao ?: '—' }}</span>
                    </td>
                    <td class="text-center">{{ $doc->issue_dh ? date('d/m/Y', strtotime($doc->issue_dh)) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
