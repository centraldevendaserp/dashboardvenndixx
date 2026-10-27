<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Company\Index as CompanyIndex;
use App\Livewire\Panel\Dashboard\CardInfo;
use App\Livewire\Panel\Dashboard\CardInfoDocument;
use App\Livewire\Panel\Dashboard\Disable as DisableTab;
use App\Livewire\Panel\Dashboard\Event as EventTab;
use App\Livewire\Panel\Dashboard\Index as DashboardIndex;
use App\Livewire\Panel\Dashboard\Invoice as InvoiceTab;
use App\Livewire\Panel\Dashboard\InvoicePerMonth;
use App\Livewire\Panel\Dashboard\InvoiceQtyTotal;
use App\Livewire\Panel\Dashboard\RecentDocuments;
use App\Livewire\Panel\Dashboard\StatusBreakdown;
use App\Livewire\Panel\User\Index as UserIndex;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/** Smoke test de renderização: as páginas do painel abrem sem erro de Blade/runtime. */
class PanelRendersTest extends TestCase
{
    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    public function test_dashboard_renderiza_para_admin(): void
    {
        $this->actingAs($this->admin());

        // As widgets são lazy: o render inicial traz os esqueletos (pnl-skel),
        // não o conteúdo (que carrega em background).
        Livewire::test(DashboardIndex::class)
            ->assertOk()
            ->assertSee('pnl-skel');
    }

    public function test_dashboard_http_entrega_esqueletos_lazy(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('panel.dashboard.index'))
            ->assertOk()
            ->assertSee('pnl-skel');
    }

    /**
     * As widgets do dashboard têm lazy-load: aqui cada uma é renderizada de
     * verdade, não só o placeholder.
     */
    public function test_widgets_lazy_do_dashboard_renderizam_para_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $widgets = [
            CardInfo::class,
            CardInfoDocument::class,
            InvoicePerMonth::class,
            InvoiceQtyTotal::class,
            StatusBreakdown::class,
            RecentDocuments::class,
        ];

        foreach ($widgets as $widget) {
            Livewire::test($widget, ['user' => $admin])->assertOk();
        }
    }

    /**
     * As três abas de listagem renderizam a tabela sem erro — pega Blade quebrado
     * (coluna removida deixando th/td torto, por exemplo).
     */
    public function test_aba_documentos_fiscais_renderiza_para_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(InvoiceTab::class, ['user' => $admin])->assertOk();
    }

    public function test_aba_de_eventos_renderiza_para_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(EventTab::class, ['user' => $admin])->assertOk();
    }

    public function test_aba_de_inutilizacoes_renderiza_para_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(DisableTab::class, ['user' => $admin])->assertOk();
    }

    public function test_pagina_de_usuarios_renderiza_para_admin(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(UserIndex::class)->assertOk();
    }

    public function test_pagina_de_empresas_renderiza_para_admin(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CompanyIndex::class)->assertOk();
    }

    public function test_painel_exige_autenticacao(): void
    {
        $this->get(route('panel.dashboard.index'))
            ->assertRedirect(route('auth.login'));
    }
}
