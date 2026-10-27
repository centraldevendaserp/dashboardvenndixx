<div>

    @if (empty($invoices))
        <div class="pnl-empty">
            <i class="far fa-chart-bar"></i>
            <p>Dados insuficientes para o período</p>
        </div>
    @else
        {{-- wire:ignore: o canvas é do ApexCharts, o morph não tem o que fazer aqui --}}
        <div id="pnl-chart-permonth" class="pnl-chart" wire:ignore></div>
    @endif

</div>

@script
    <script>
        // o componente popula $invoices no render() e emite eventInitChartQtyPerMonth
        Livewire.on('eventInitChartQtyPerMonth', (event) => {
            const payload = Array.isArray(event) ? event[0] : event;
            const invoices = (payload && payload.invoices) ? payload.invoices : [];

            setTimeout(() => {
                const el = document.querySelector('#pnl-chart-permonth');
                if (typeof ApexCharts === 'undefined') return;

                // Instância órfã (o div saiu do DOM): descarta antes de qualquer coisa.
                if (window.PNL_PERMONTH && window.PNL_PERMONTH.el !== el) {
                    try { window.PNL_PERMONTH.destroy(); } catch (e) {}
                    window.PNL_PERMONTH = null;
                }

                if (!el) return;

                if (!invoices.length) {
                    if (window.PNL_PERMONTH) {
                        try { window.PNL_PERMONTH.destroy(); } catch (e) {}
                        window.PNL_PERMONTH = null;
                    }
                    return;
                }

                const months = invoices.map(d => d.month);
                const mk = (k) => invoices.map(d => parseInt(d[k]) || 0);
                const models = window.PNL_MODELS || [];

                const opcoes = {
                    chart: { type: 'area', height: 320, stacked: true, toolbar: { show: false },
                        fontFamily: 'Source Sans Pro, sans-serif' },
                    series: models.map(m => ({ name: m.label, data: mk(m.code) })),
                    colors: models.map(m => m.color),
                    xaxis: { categories: months, axisBorder: { show: false }, axisTicks: { show: false } },
                    yaxis: { labels: { formatter: (v) => Math.round(v).toLocaleString('pt-BR') } },
                    stroke: { curve: 'smooth', width: 2 },
                    fill: { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0.05 } },
                    dataLabels: { enabled: false },
                    legend: { position: 'top', horizontalAlign: 'left', markers: { radius: 4 } },
                    grid: { borderColor: '#eef1f5', strokeDashArray: 4 },
                    tooltip: { shared: true, intersect: false },
                };

                // Atualiza a instância viva: destruir e recriar deixava uma
                // instância órfã que ressuscitava no resize (ver o comentário
                // longo em invoice-qty-total.blade.php).
                if (window.PNL_PERMONTH) {
                    window.PNL_PERMONTH.updateOptions(opcoes, false, false);
                    return;
                }

                window.PNL_PERMONTH = new ApexCharts(el, opcoes);
                window.PNL_PERMONTH.render();
            }, 10);
        });
    </script>
@endscript
