<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Company\ModalDataToCompany;
use App\Livewire\Panel\User\ModalDataToUser;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vínculo bidirecional pela grade de chips: no modal de usuário vinculam-se
 * empresas, no de empresa vinculam-se usuários, e o que já está vinculado abre
 * marcado.
 */
class ModalLinkingTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    public function test_admin_vincula_empresas_a_um_usuario(): void
    {
        $this->actingAs($this->admin());

        $user = User::where('is_admin', '!=', 'S')->where('id', '!=', Auth::id())->firstOrFail();
        $companyIds = Company::take(3)->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'edit', 'user_id' => $user->id])
            ->set('related_companies', $companyIds)
            ->call('submit');

        $this->assertEqualsCanonicalizing(
            $companyIds,
            $user->fresh()->companies()->pluck('companies.id')->map(fn ($id) => (int) $id)->toArray()
        );
    }

    public function test_modal_de_usuario_carrega_empresas_ja_vinculadas(): void
    {
        $this->actingAs($this->admin());

        $user = User::where('is_admin', '!=', 'S')->where('id', '!=', Auth::id())->firstOrFail();
        $companyIds = Company::take(2)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        $user->companies()->sync($companyIds);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'edit', 'user_id' => $user->id])
            ->assertSet('related_companies', $companyIds);
    }

    public function test_admin_vincula_usuarios_a_uma_empresa(): void
    {
        $this->actingAs($this->admin());

        $company = Company::firstOrFail();
        $userIds = User::take(3)->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        Livewire::test(ModalDataToCompany::class)
            ->call('eventAction', 'edit', $company->id)
            ->set('related_users', $userIds)
            ->call('submit');

        $this->assertEqualsCanonicalizing(
            $userIds,
            $company->fresh()->users()->pluck('users.id')->map(fn ($id) => (int) $id)->toArray()
        );
    }

    public function test_modal_de_empresa_carrega_usuarios_ja_vinculados(): void
    {
        $this->actingAs($this->admin());

        $company = Company::firstOrFail();
        $userIds = User::take(2)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        $company->users()->sync($userIds);

        Livewire::test(ModalDataToCompany::class)
            ->call('eventAction', 'edit', $company->id)
            ->assertSet('related_users', $userIds);
    }

    /* -------- "Habilitar edição": editar abre travado; criar já liberado -------- */

    public function test_edicao_de_empresa_abre_travada_e_habilita(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ModalDataToCompany::class)
            ->call('eventAction', 'edit', Company::firstOrFail()->id)
            ->assertSet('editEnabled', false)
            ->call('enableEdit')
            ->assertSet('editEnabled', true);
    }

    public function test_cadastro_de_empresa_abre_liberado(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ModalDataToCompany::class)
            ->call('eventAction', 'store')
            ->assertSet('editEnabled', true);
    }

    public function test_gestao_de_usuario_abre_travada_e_habilita(): void
    {
        $this->actingAs($this->admin());

        $user = User::where('is_admin', '!=', 'S')->where('id', '!=', Auth::id())->firstOrFail();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'edit', 'user_id' => $user->id])
            ->assertSet('mode', 'manage')
            ->assertSet('editEnabled', false)
            ->call('enableEdit')
            ->assertSet('editEnabled', true);
    }

    public function test_novo_usuario_abre_liberado(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'store'])
            ->assertSet('editEnabled', true);
    }

    /* -------- Empresas no PRÓPRIO perfil (Minhas configurações) -------- */

    public function test_admin_no_proprio_perfil_fica_com_todas_as_empresas(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $allIds = Company::pluck('id')->map(fn ($id) => (int) $id)->toArray();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'edit', 'user_id' => $admin->id, 'mode' => 'profile'])
            ->assertSet('mode', 'profile')
            ->set('related_companies', [$allIds[0]]) // seleção parcial é ignorada p/ admin
            ->call('submit');

        // Admin enxerga TODAS as empresas -> fica vinculado a todas.
        $this->assertEqualsCanonicalizing(
            $allIds,
            $admin->fresh()->companies()->pluck('companies.id')->map(fn ($id) => (int) $id)->toArray()
        );
    }

    public function test_usuario_marcado_como_admin_recebe_todas_as_empresas(): void
    {
        $this->actingAs($this->admin());

        $user = User::where('is_admin', '!=', 'S')->where('id', '!=', Auth::id())->firstOrFail();
        $allIds = Company::pluck('id')->map(fn ($id) => (int) $id)->toArray();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'edit', 'user_id' => $user->id])
            ->set('is_admin', 'S')
            ->set('related_companies', [$allIds[0]]) // parcial, mas admin -> todas
            ->call('submit');

        $this->assertEqualsCanonicalizing(
            $allIds,
            $user->fresh()->companies()->pluck('companies.id')->map(fn ($id) => (int) $id)->toArray()
        );
    }

    public function test_nao_admin_nao_pode_autovincular_empresas_no_perfil(): void
    {
        $user = User::where('is_admin', '!=', 'S')->where('id', '!=', Auth::id())->firstOrFail();
        $user->companies()->sync([]); // começa sem empresas
        $this->actingAs($user);

        $companyIds = Company::take(2)->pluck('id')->toArray();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'edit', 'user_id' => $user->id, 'mode' => 'profile'])
            ->assertSet('mode', 'profile')
            ->set('related_companies', $companyIds) // injeta pelo payload
            ->call('submit');

        // Invariante de segurança: não-admin NÃO se auto-vincula (continua sem empresas).
        $this->assertCount(0, $user->fresh()->companies);
    }
}
