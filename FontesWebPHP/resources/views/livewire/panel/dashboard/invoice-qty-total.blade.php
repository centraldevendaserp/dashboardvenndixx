<div>

    @if (empty($invoices))
        <div class="pnl-empty">
            <i class="fas fa-chart-pie"></i>
            <p>Dados insuficientes para o período</p>
        </div>
    @else
        {{-- wire:ignore: o canvas é do ApexCharts, o morph não tem o que fazer aqui --}}
        <div id="pnl-chart-qtytotal" class="pnl-chart pnl-chart--sm" wire:ignore></div>
    @endif

</div>

@script
    <script>
        // o componente popula $invoices no render() e emite eventInitChartQtyTotal
        Livewire.on('eventInitChartQtyTotal', (event) => {
            const payload = Array.isArray(event) ? event[0] : event;
            const invoices = (payload && payload.invoices) ? payload.invoices : [];

            setTimeout(() => {
                const el = document.querySelector('#pnl-chart-qtytotal');
                if (typeof ApexCharts === 'undefined') return;

                // Instância órfã (o div saiu do DOM): descarta antes de qualquer coisa.
                if (window.PNL_QTYTOTAL && window.PNL_QTYTOTAL.el !== el) {
                    try { window.PNL_QTYTOTAL.destroy(); } catch (e) {}
                    window.PNL_QTYTOTAL = null;
                }

                if (!el) return;

                if (!invoices.length) {
                    if (window.PNL_QTYTOTAL) {
                        try { window.PNL_QTYTOTAL.destroy(); } catch (e) {}
                        window.PNL_QTYTOTAL = null;
                    }
                    return;
                }

                const labels = invoices.map(d => d.model);
                const qtys = invoices.map(d => parseInt(d.qty) || 0);
                const totals = invoices.map(d => parseFloat(d.total) || 0);
                const colorByLabel = window.PNL_MODEL_COLOR_BY_LABEL || {};
                const colors = labels.map(l => colorByLabel[l] || '#5b8fd6');
                const fmtBRL = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

                const opcoes = {
                    chart: { type: 'donut', height: 300, fontFamily: 'Source Sans Pro, sans-serif' },
                    series: qtys,
                    labels: labels,
                    colors: colors,
                    legend: { position: 'bottom' },
                    dataLabels: { enabled: false },
                    stroke: { width: 2, colors: ['#fff'] },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '68%',
                                labels: {
                                    show: true,
                                    total: {
                                        show: true, label: 'Documentos',
                                        formatter: () => qtys.reduce((a, b) => a + b, 0).toLocaleString('pt-BR')
                                    }
                                }
                            }
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: (val, opts) => {
                                const i = opts.seriesIndex;
                                return val.toLocaleString('pt-BR') + ' doc · ' + fmtBRL(totals[i]);
                            }
                        }
                    },
                };

                // ⚠️ Atualiza a instância viva em vez de destruir e recriar: o
                // debounce de resize do ApexCharts sobrevive ao destroy(), e a
                // instância descartada ressuscita por cima com os dados antigos.
                if (window.PNL_QTYTOTAL) {
                    window.PNL_QTYTOTAL.updateOptions(opcoes, false, false);
                    return;
                }

                window.PNL_QTYTOTAL = new ApexCharts(el, opcoes);
                window.PNL_QTYTOTAL.render();
            }, 10);
        });
    </script>
@endscript
