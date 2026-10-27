<div class="pnl-grid pnl-grid--kpi">

    {{-- Os dois primeiros são de CADASTRO (todo o portal) e os dois seguintes
         seguem o filtro do topo — daí "totais" no rótulo.

         ⚠️ O link só vale para admin: /panel/users e /panel/companies dão 403
         para os demais. O card de empresas aparece para todo mundo, então para
         o contador comum ele continua card, sem link. --}}

    @if ($user->is_admin == 'S')
        <a href="{{ route('panel.users.index') }}" wire:navigate class="pnl-kpi pnl-kpi--link">
            <span class="pnl-kpi__icon is-blue"><i class="fas fa-users"></i></span>
            <span class="pnl-kpi__meta">
                <span class="pnl-kpi__num">{{ number_format($users_count, 0, ',', '.') }}</span>
                <span class="pnl-kpi__label">Usuários totais</span>
            </span>
            <i class="fas fa-chevron-right pnl-kpi__go" aria-hidden="true"></i>
        </a>

        <a href="{{ route('panel.companies.index') }}" wire:navigate class="pnl-kpi pnl-kpi--link">
            <span class="pnl-kpi__icon is-slate"><i class="far fa-building"></i></span>
            <span class="pnl-kpi__meta">
                <span class="pnl-kpi__num">{{ number_format($companies_count, 0, ',', '.') }}</span>
                <span class="pnl-kpi__label">Empresas totais</span>
            </span>
            <i class="fas fa-chevron-right pnl-kpi__go" aria-hidden="true"></i>
        </a>
    @else
        <div class="pnl-kpi">
            <span class="pnl-kpi__icon is-slate"><i class="far fa-building"></i></span>
            <span class="pnl-kpi__meta">
                <span class="pnl-kpi__num">{{ number_format($companies_count, 0, ',', '.') }}</span>
                <span class="pnl-kpi__label">Empresas totais</span>
            </span>
        </div>
    @endif

    {{-- "Filtrado no mês atual" antes de "Total filtrado", que fecha a linha. --}}
    <div class="pnl-kpi">
        <span class="pnl-kpi__icon is-ocean"><i class="fas fa-calendar-day"></i></span>
        <span class="pnl-kpi__meta">
            <span class="pnl-kpi__num">{{ number_format($invoices_month_count, 0, ',', '.') }}</span>
            <span class="pnl-kpi__label">Filtrado no mês atual</span>
        </span>
    </div>

    <div class="pnl-kpi">
        <span class="pnl-kpi__icon is-green"><i class="fas fa-file-invoice"></i></span>
        <span class="pnl-kpi__meta">
            <span class="pnl-kpi__num">{{ number_format($invoices_count, 0, ',', '.') }}</span>
            <span class="pnl-kpi__label">Total filtrado</span>
        </span>
    </div>

</div>
