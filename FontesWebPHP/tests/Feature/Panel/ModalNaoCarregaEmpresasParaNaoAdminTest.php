<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\User\ModalDataToUser;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O modal de usuário é montado em TODA página do painel, inclusive para usuário
 * comum, e a consulta de empresas é crua (fura o global scope `linked_user`).
 * Ela tem de acompanhar a mesma condição da tela, não ficar a um @if de vazar.
 */
class ModalNaoCarregaEmpresasParaNaoAdminTest extends TestCase
{
    use DatabaseTransactions;

    private function empresaDeOutro(): Company
    {
        return Company::create([
            'cnpj_cpf' => '55667788000199',
            'corporate_name' => 'EMPRESA SIGILOSA DE OUTRO CLIENTE LTDA',
        ]);
    }

    public function test_nao_admin_nao_recebe_a_lista_de_empresas(): void
    {
        $alheia = $this->empresaDeOutro();
        $comum = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($comum);

        $componente = Livewire::test(ModalDataToUser::class, ['user' => $comum]);

        $this->assertCount(0, $componente->viewData('companies'), 'a lista foi carregada mesmo sem ser exibida');
        $componente->assertDontSee($alheia->corporate_name);
    }

    /** Nem no próprio perfil, que é o modo em que o não-admin abre o modal. */
    public function test_nem_no_proprio_perfil(): void
    {
        $alheia = $this->empresaDeOutro();
        $comum = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($comum);

        Livewire::test(ModalDataToUser::class, ['user' => $comum])
            ->call('eventAction', 'edit', $comum->id, null, 'profile')
            ->assertDontSee($alheia->corporate_name);
    }

    /** O admin continua recebendo — é quem edita os vínculos. */
    public function test_admin_continua_recebendo_a_lista(): void
    {
        $alheia = $this->empresaDeOutro();
        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);

        $componente = Livewire::test(ModalDataToUser::class, ['user' => $admin])
            ->call('eventAction', 'store');

        $this->assertGreaterThan(0, $componente->viewData('companies')->count());
        $componente->assertSee($alheia->corporate_name);
    }
}
