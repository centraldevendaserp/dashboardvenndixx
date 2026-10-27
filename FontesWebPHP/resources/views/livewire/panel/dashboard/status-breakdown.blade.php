<div>

    @if (empty($rows))
        <div class="pnl-empty">
            <i class="far fa-folder-open"></i>
            <p>Sem documentos no período</p>
        </div>
    @else
        {{-- wire:ignore: o canvas é do ApexCharts, o morph não tem o que fazer aqui --}}
        <div id="pnl-chart-status" class="pnl-chart pnl-chart--sm" wire:ignore></div>
    @endif

</div>

@script
    <script>
        // o componente popula $rows no render() e emite eventInitStatusChart
        Livewire.on('eventInitStatusChart', (event) => {
            const payload = Array.isArray(event) ? event[0] : event;
            const rows = (payload && payload.rows) ? payload.rows : [];

            setTimeout(() => {
                const el = document.querySelector('#pnl-chart-status');
                if (typeof ApexCharts === 'undefined') return;

                // Instância órfã (o div saiu do DOM): descarta antes de qualquer coisa.
                if (window.PNL_STATUS && window.PNL_STATUS.el !== el) {
                    try { window.PNL_STATUS.destroy(); } catch (e) {}
                    window.PNL_STATUS = null;
                }

                if (!el) return;

                if (!rows.length) {
                    if (window.PNL_STATUS) {
                        try { window.PNL_STATUS.destroy(); } catch (e) {}
                        window.PNL_STATUS = null;
                    }
                    return;
                }

                const labels = rows.map(r => r.label);
                const series = rows.map(r => r.qty);
                const statusColors = window.PNL_STATUS_COLORS || {};
                const colors = rows.map(r => statusColors[r.code] || '#5b8fd6');

                const opcoes = {
                    chart: { type: 'donut', height: 280, fontFamily: 'Source Sans Pro, sans-serif' },
                    series: series,
                    labels: labels,
                    colors: colors,
                    legend: { position: 'bottom' },
                    dataLabels: { enabled: false },
                    stroke: { width: 2, colors: ['#fff'] },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '66%',
                                labels: {
                                    show: true,
                                    total: {
                                        show: true, label: 'Notas',
                                        formatter: () => series.reduce((a, b) => a + b, 0).toLocaleString('pt-BR')
                                    }
                                }
                            }
                        }
                    },
                    tooltip: { y: { formatter: (v) => v.toLocaleString('pt-BR') + ' doc' } },
                };

                // Atualiza a instância viva: destruir e recriar deixava uma
                // instância órfã que ressuscitava no resize (ver o comentário
                // longo em invoice-qty-total.blade.php).
                if (window.PNL_STATUS) {
                    window.PNL_STATUS.updateOptions(opcoes, false, false);
                    return;
                }

                window.PNL_STATUS = new ApexCharts(el, opcoes);
                window.PNL_STATUS.render();
            }, 10);
        });
    </script>
@endscript
