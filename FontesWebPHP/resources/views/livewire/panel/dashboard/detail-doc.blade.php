<div>

    {{-- Estilo deste modal: public/assets/css/custom.css (escopo #modal-detail-doc).
         Fica lá porque @push de componente Livewire aninhado não chega ao @stack do layout. --}}

    <!-- modal form -->
    <div wire:ignore.self class="modal-main" id="modal-detail-doc">

        <div class="dialog">

            <div class="content">

                <a href="#" class="close"><i class="fas fa-times"></i></a>

                <div class="header">
                    <div class="modal-head">
                        <span class="modal-head__icon"><i class="fas fa-file-invoice"></i></span>
                        <div class="modal-head__txt">
                            <p>Detalhes do documento</p>
                            <small>Informações da nota (somente leitura).</small>
                        </div>
                    </div>
                </div>

                <div class="body">

                    <dl class="doc-detail">

                        @if (isset($details['cnpj_cpf']))
                            <div class="dd-row">
                                <dt>CNPJ/CPF</dt>
                                <dd>
                                    @if (strlen($details['cnpj_cpf']) > 11)
                                        {{ App\Helpers\Mask::run($details['cnpj_cpf'], '##.###.###/####-##') }}
                                    @else
                                        {{ App\Helpers\Mask::run($details['cnpj_cpf'], '###.###.###-##') }}
                                    @endif
                                </dd>
                            </div>
                        @endif

                        @if (isset($details['cnpj']))
                            <div class="dd-row">
                                <dt>CNPJ</dt>
                                <dd>{{ App\Helpers\Mask::run($details['cnpj'], '##.###.###/####-##') }}</dd>
                            </div>
                        @endif

                        @if (isset($details['ie']))
                            <div class="dd-row"><dt>IE</dt><dd>{{ $details['ie'] }}</dd></div>
                        @endif

                        @if (isset($details['model']))
                            <div class="dd-row">
                                <dt>Modelo</dt>
                                <dd>
                                    @if ($details['model'] == 55)
                                        <span class="badge badge-blue">NF-e</span>
                                    @elseif ($details['model'] == 57)
                                        <span class="badge badge-blue">CT-e</span>
                                    @elseif ($details['model'] == 58)
                                        <span class="badge badge-blue">MDF-e</span>
                                    @elseif ($details['model'] == 59)
                                        <span class="badge badge-blue">Entrada</span>
                                    @elseif ($details['model'] == 65)
                                        <span class="badge badge-blue">NFC-e</span>
                                    @endif
                                </dd>
                            </div>
                        @endif

                        @if (isset($details['series']))
                            <div class="dd-row"><dt>Série</dt><dd>{{ $details['series'] }}</dd></div>
                        @endif

                        @if (isset($details['number']))
                            <div class="dd-row"><dt>Número</dt><dd>{{ $details['number'] }}</dd></div>
                        @endif

                        {{-- O evento não guarda série nem número da nota, só a chave — e os
                             dois estão dentro dela (série = dígitos 23-25, número = 26-34),
                             então dá para mostrar sem consultar mais nada. --}}
                        @if (!isset($details['number']) && isset($details['nfe_key']) && strlen($details['nfe_key']) === 44)
                            <div class="dd-row"><dt>Série da nota</dt><dd>{{ (int) substr($details['nfe_key'], 22, 3) }}</dd></div>
                            <div class="dd-row"><dt>Número da nota</dt><dd>{{ (int) substr($details['nfe_key'], 25, 9) }}</dd></div>
                        @endif

                        @if (isset($details['protocol']))
                            {{-- Protocolo de AUTORIZAÇÃO da nota. --}}
                            <div class="dd-row"><dt>Protocolo de autorização</dt><dd>{{ $details['protocol'] }}</dd></div>
                        @endif

                        @if (isset($details['protocol_number']))
                            {{-- Protocolo do EVENTO ou da INUTILIZAÇÃO, nunca o da
                                 autorização da nota: são operações diferentes na SEFAZ, e o
                                 rótulo igual fazia parecer divergência. --}}
                            <div class="dd-row">
                                <dt>{{ isset($details['event_type']) ? 'Protocolo do evento' : 'Protocolo da inutilização' }}</dt>
                                <dd>{{ $details['protocol_number'] }}</dd>
                            </div>
                        @endif

                        @if (isset($details['event_number']))
                            <div class="dd-row"><dt>Número evento</dt><dd>{{ $details['event_number'] }}</dd></div>
                        @endif

                        @if (isset($details['number_start']))
                            <div class="dd-row"><dt>Número começo</dt><dd>{{ $details['number_start'] }}</dd></div>
                        @endif

                        @if (isset($details['number_end']))
                            <div class="dd-row"><dt>Número final</dt><dd>{{ $details['number_end'] }}</dd></div>
                        @endif

                        @if (isset($details['key']))
                            <div class="dd-row"><dt>Chave</dt><dd class="dd-value--key">{{ $details['key'] }}</dd></div>
                        @endif

                        @if (isset($details['nfe_key']))
                            <div class="dd-row"><dt>Chave</dt><dd class="dd-value--key">{{ $details['nfe_key'] }}</dd></div>
                        @endif

                        @if (isset($details['month_year']))
                            <div class="dd-row"><dt>Mês/Ano</dt><dd>{{ $details['month_year'] }}</dd></div>
                        @endif

                        @if (isset($details['issue_dh']))
                            <div class="dd-row"><dt>Data emissão</dt><dd>{{ date('d/m/Y', strtotime($details['issue_dh'])) }}</dd></div>
                        @endif

                        @if (isset($details['event_dh']))
                            <div class="dd-row"><dt>Data emissão</dt><dd>{{ date('d/m/Y', strtotime($details['event_dh'])) }}</dd></div>
                        @endif

                        @if (isset($details['environment_type']))
                            <div class="dd-row">
                                <dt>Tipo ambiente</dt>
                                <dd>
                                    @if ($details['environment_type'] == 1)
                                        Produção
                                    @elseif ($details['environment_type'] == 2)
                                        Homologação
                                    @endif
                                </dd>
                            </div>
                        @endif

                        @if (isset($details['event_type']))
                            <div class="dd-row"><dt>Tipo de evento</dt><dd>{{ $details['event_type'] }}</dd></div>
                        @endif

                        @if (isset($details['status_xml']))
                            <div class="dd-row">
                                <dt>Status</dt>
                                <dd>
                                    {{-- Mapa cStat central (o mesmo dos chips e das listagens):
                                         ver Documents\Index. --}}
                                    @php($st = \App\Livewire\Panel\Documents\Index::groupForCode($details['status_xml']))
                                    @if ($st)
                                        <span class="badge badge-{{ $st['color'] }} badge-round">{{ $st['label'] }}</span>
                                    @else
                                        <span class="badge badge-default badge-round">{{ $details['status_xml'] }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endif

                        @if (isset($details['event_status']))
                            <div class="dd-row">
                                <dt>Status</dt>
                                <dd>
                                    @if ($details['event_status'] == 102)
                                        <span class="badge badge-red badge-round">Inutilização de número homologado</span>
                                    @elseif ($details['event_status'] == 135)
                                        <span class="badge badge-red badge-round">Evento registrado</span>
                                    @endif
                                </dd>
                            </div>
                        @endif

                        @if (isset($details['event_desc']))
                            <div class="dd-row"><dt>Descrição</dt><dd>{{ empty($details['event_desc']) ? '...' : $details['event_desc'] }}</dd></div>
                        @endif

                        @if (isset($details['justification']))
                            <div class="dd-row"><dt>Justificativa</dt><dd>{{ empty($details['justification']) ? '...' : $details['justification'] }}</dd></div>
                        @endif

                        @if (isset($details['correction']))
                            <div class="dd-row"><dt>Correção</dt><dd>{{ empty($details['correction']) ? '...' : $details['correction'] }}</dd></div>
                        @endif

                        @if (isset($details['vNF']))
                            <div class="dd-row"><dt>Valor</dt><dd class="dd-value--money">R$ {{ number_format($details['vNF'], 2, ',', '.') }}</dd></div>
                        @endif

                    </dl>

                </div>

            </div>

        </div>

    </div>

</div>

@push('component-scripts')
    <script>
        document.addEventListener('livewire:init', function() {
            (function($) {
                Livewire.hook('message.processed', (message, component) => {});
            })(jQuery);
        });
    </script>
@endpush
