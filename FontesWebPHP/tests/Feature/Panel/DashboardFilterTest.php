<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\QuickFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O filtro do topo é o único do dashboard: aplicar ou limpar move a tabela e o
 * "Status das notas" (eventDocsSearch) e também os dois gráficos
 * (eventDocsPerPeriodSearch).
 */
class DashboardFilterTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    public function test_aplicar_filtro_dispara_os_dois_eventos_de_periodo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(QuickFilter::class, ['user' => $admin])
            ->set('environment_type', '1')
            ->set('first_date', '01/01/2024')
            ->set('last_date', '31/12/2024')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertDispatched('eventDocsSearch')
            ->assertDispatched('eventDocsPerPeriodSearch');
    }

    public function test_limpar_filtro_dispara_os_dois_eventos(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(QuickFilter::class, ['user' => $admin])
            ->call('resetSearch')
            ->assertDispatched('eventDocsSearch')
            ->assertDispatched('eventDocsPerPeriodSearch');
    }
}
