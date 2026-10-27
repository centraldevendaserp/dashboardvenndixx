<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\QuickFilter;
use App\Models\User;
use App\Support\DashboardStatusScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtrar por "Inutilizada" no dashboard tem de abrir a aba Inutilização.
 *
 * Trava as duas metades verificáveis aqui: o contrato do dispatch do servidor e
 * o fato de o script ter caminho alternativo quando o Livewire já está de pé —
 * chegando pela sidebar, o 'livewire:init' já passou e o listener não registra.
 */
class DashboardAbaPorStatusTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@gmail.com')->firstOrFail();
        $this->actingAs($this->admin);
    }

    /** @return array<string,string> status marcado => aba esperada */
    public static function mapaDeAbas(): array
    {
        return [
            'inutilizada' => ['inutilizada', 'disable'],
            'cancelada'   => ['cancelada', 'event'],
            'autorizada'  => ['autorizada', 'authorized'],
            // Rejeitada não tem aba própria: cai na lista geral.
            'rejeitada'   => ['rejeitada', 'invoice'],
        ];
    }

    /** @dataProvider mapaDeAbas */
    public function test_status_escolhe_a_aba(string $status, string $abaEsperada): void
    {
        $this->assertSame($abaEsperada, DashboardStatusScope::abaPara([$status]));

        Livewire::test(QuickFilter::class, ['user' => $this->admin])
            ->set('doc_status', [$status])
            ->set('first_date', '01/01/2000')
            ->set('last_date', date('d/m/Y'))
            ->call('submit')
            ->assertDispatched('pnlSelecionarAba', tipo: $abaEsperada, rolar: true);
    }

    /** Mais de um status marcado não tem aba única: volta para a lista geral. */
    public function test_dois_status_caem_na_lista_geral(): void
    {
        $this->assertSame('invoice', DashboardStatusScope::abaPara(['inutilizada', 'cancelada']));
    }

    /**
     * O listener não pode depender só de 'livewire:init'. É teste de FONTE, não
     * de navegador — é o que dá para verificar aqui.
     */
    public function test_script_registra_o_listener_mesmo_com_livewire_de_pe(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/panel/dashboard/index.blade.php'));

        $this->assertStringContainsString('if (window.Livewire) {', $blade);
        $this->assertStringContainsString("Livewire.on('pnlSelecionarAba', selecionarAba)", $blade);
        $this->assertStringNotContainsString(
            "document.addEventListener('livewire:init', function () {\n                Livewire.on('pnlSelecionarAba'",
            $blade,
            'o Livewire.on voltou a ficar preso dentro do livewire:init'
        );
    }
}
