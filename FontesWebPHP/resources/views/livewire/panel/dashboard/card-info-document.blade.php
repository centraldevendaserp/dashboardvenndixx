<div class="pnl-grid pnl-grid--model">
    {{-- Ordem: NFC-e · NF-e · NFS-e | MDF-e · CT-e · Entrada/Compras — a mesma
         da sidebar, da aba de Documentos e das pastas do agente. A quebra entre
         os grupos é a virada de linha do grid (repeat(3, 1fr), 6 cards). --}}

    <div class="pnl-modelcard mc-nfce">
        <div class="pnl-modelcard__top">
            <span class="pnl-modelcard__tag">NFC-e</span>
            <span class="pnl-modelcard__qty" title="Quantidade">{{ number_format($qty_nfce, 0, '.', '.') }} doc</span>
        </div>
        <div class="pnl-modelcard__val"><small>R$</small>{{ number_format($total_nfce, 2, ',', '.') }}</div>
        <div class="pnl-spark" wire:ignore data-model="65"></div>
    </div>

    <div class="pnl-modelcard mc-nfe">
        <div class="pnl-modelcard__top">
            <span class="pnl-modelcard__tag">NF-e</span>
            <span class="pnl-modelcard__qty" title="Quantidade">{{ number_format($qty_nfe, 0, '.', '.') }} doc</span>
        </div>
        <div class="pnl-modelcard__val"><small>R$</small>{{ number_format($total_nfe, 2, ',', '.') }}</div>
        <div class="pnl-spark" wire:ignore data-model="55"></div>
    </div>

    {{-- NFS-e: tabela própria (nfse_documents), sem sparkline — a série diária
         é montada a partir de `documents`, onde a NFS-e não entra. --}}
    <div class="pnl-modelcard mc-nfse">
        <div class="pnl-modelcard__top">
            <span class="pnl-modelcard__tag">NFS-e</span>
            <span class="pnl-modelcard__qty" title="Quantidade">{{ number_format($qty_nfse, 0, '.', '.') }} doc</span>
        </div>
        <div class="pnl-modelcard__val"><small>R$</small>{{ number_format($total_nfse, 2, ',', '.') }}</div>
    </div>

    <div class="pnl-modelcard mc-mdfe">
        <div class="pnl-modelcard__top">
            <span class="pnl-modelcard__tag">MDF-e</span>
            <span class="pnl-modelcard__qty" title="Quantidade">{{ number_format($qty_mdfe, 0, '.', '.') }} doc</span>
        </div>
        <div class="pnl-modelcard__val"><small>R$</small>{{ number_format($total_mdfe, 2, ',', '.') }}</div>
        <div class="pnl-spark" wire:ignore data-model="58"></div>
    </div>

    <div class="pnl-modelcard mc-cte">
        <div class="pnl-modelcard__top">
            <span class="pnl-modelcard__tag">CT-e</span>
            <span class="pnl-modelcard__qty" title="Quantidade">{{ number_format($qty_cte, 0, '.', '.') }} doc</span>
        </div>
        <div class="pnl-modelcard__val"><small>R$</small>{{ number_format($total_cte, 2, ',', '.') }}</div>
        <div class="pnl-spark" wire:ignore data-model="57"></div>
    </div>

    {{-- Modelo 59 = notas de ENTRADA (compras), recebidas dos fornecedores. Não
         é duplicata da NF-e (55), que são as de saída — é o fluxo oposto. --}}
    <div class="pnl-modelcard mc-entr">
        <div class="pnl-modelcard__top">
            <span class="pnl-modelcard__tag">Entrada/Compras</span>
            <span class="pnl-modelcard__qty" title="Quantidade">{{ number_format($qty_cfesat, 0, '.', '.') }} doc</span>
        </div>
        <div class="pnl-modelcard__val"><small>R$</small>{{ number_format($total_cfesat, 2, ',', '.') }}</div>
        <div class="pnl-spark" wire:ignore data-model="59"></div>
    </div>

</div>

@script
    <script>
        (function () {
            window.__pnlSparks = window.__pnlSparks || {};

            window.pnlDrawSparklines = function (spark) {
                if (typeof ApexCharts === 'undefined' || !window.PNL_MODELS) return;

                window.PNL_MODELS.forEach(function (model) {
                    const m = model.code;
                    const el = document.querySelector('.pnl-spark[data-model="' + m + '"]');
                    if (!el) return;

                    const data = (spark && spark[m]) ? spark[m] : [];
                    const series = data.length ? data : [0, 0];

                    // Instância órfã (o div saiu do DOM): descarta.
                    if (window.__pnlSparks[m] && window.__pnlSparks[m].el !== el) {
                        try { window.__pnlSparks[m].destroy(); } catch (e) {}
                        delete window.__pnlSparks[m];
                    }

                    const opcoes = {
                        chart: { type: 'area', height: 38, sparkline: { enabled: true }, animations: { enabled: false } },
                        series: [{ name: 'Qtd', data: series }],
                        stroke: { curve: 'smooth', width: 2 },
                        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
                        colors: [model.color],
                        tooltip: { enabled: false }
                    };

                    // Atualiza a instância viva: destruir e recriar deixa uma
                    // instância órfã que ressuscita no resize.
                    if (window.__pnlSparks[m]) {
                        window.__pnlSparks[m].updateOptions(opcoes, false, false);
                        return;
                    }

                    const chart = new ApexCharts(el, opcoes);
                    chart.render();
                    window.__pnlSparks[m] = chart;
                });
            };

            Livewire.on('eventInitSparklines', function (event) {
                const payload = Array.isArray(event) ? event[0] : event;
                window.pnlDrawSparklines(payload ? payload.spark : null);
            });
            // O componente emite eventInitSparklines a partir do render().
        })();
    </script>
@endscript
