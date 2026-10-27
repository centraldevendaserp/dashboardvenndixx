<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\User\Index as UserIndex;
use App\Livewire\Panel\User\ModalDataToUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Autorização do modal de usuário e guardas do deleteUser. O componente é
 * endereçável por /livewire/update, então a guarda da página-índice não basta —
 * os testes batem direto nele.
 */
class UserModalAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_nao_admin_nao_edita_outro_usuario(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => 'N']);
        $other = User::factory()->create(['is_admin' => 'N']);

        $this->actingAs($nonAdmin);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $other->id)
            ->assertForbidden();
    }

    public function test_nao_admin_nao_faz_gestao_completa_no_submit(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => 'N']);

        $this->actingAs($nonAdmin);

        Livewire::test(ModalDataToUser::class)
            ->set('name', 'Invasor')
            ->set('email', 'invasor@test.com')
            ->set('is_admin', 'S')
            ->call('submit')
            ->assertForbidden();
    }

    public function test_nao_admin_pode_abrir_a_propria_conta(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => 'N']);

        $this->actingAs($nonAdmin);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $nonAdmin->id)
            ->assertOk();
    }

    public function test_admin_pode_editar_outro_usuario(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $other = User::factory()->create(['is_admin' => 'N']);

        $this->actingAs($admin);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $other->id)
            ->assertOk();
    }

    public function test_admin_nao_exclui_a_propria_conta(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);

        $this->actingAs($admin);

        Livewire::test(UserIndex::class)->call('deleteUser', $admin->id);

        $this->assertNotNull(User::find($admin->id), 'Admin não deveria excluir a própria conta.');
    }

    public function test_admin_exclui_usuario_comum(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $target = User::factory()->create(['is_admin' => 'N']);

        $this->actingAs($admin);

        Livewire::test(UserIndex::class)->call('deleteUser', $target->id);

        $this->assertNull(User::find($target->id), 'Usuário comum deveria ter sido excluído.');
    }

    public function test_nao_admin_atualiza_proprio_nome_e_email(): void
    {
        $user = User::factory()->create(['is_admin' => 'N', 'name' => 'Antigo', 'email' => 'antigo@test.com']);

        $this->actingAs($user);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $user->id, null, 'profile')
            ->assertSet('mode', 'profile')
            ->set('name', 'Novo Nome')
            ->set('email', 'novo@test.com')
            ->call('submit')
            ->assertOk();

        $fresh = User::find($user->id);
        $this->assertSame('Novo Nome', $fresh->name);
        $this->assertSame('novo@test.com', $fresh->email);
        $this->assertSame('N', $fresh->is_admin, 'O perfil não pode alterar o nível de acesso.');
    }

    public function test_nao_admin_nao_escala_para_admin_no_perfil(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);

        $this->actingAs($user);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $user->id, null, 'profile')
            ->set('name', 'Tentando')
            ->set('email', 'tentando@test.com')
            ->set('is_admin', 'S')
            ->call('submit')
            ->assertOk();

        $this->assertSame('N', User::find($user->id)->is_admin, 'Não-admin não pode virar admin pelo perfil.');
    }

    public function test_alterar_senha_exige_confirmacao(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);

        $this->actingAs($user);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $user->id, 'password')
            ->assertSet('mode', 'password')
            ->set('password', 'novaSenha123')
            ->set('password_confirmation', 'diferente')
            ->call('submit')
            ->assertHasErrors(['password' => 'confirmed']);
    }

    public function test_alterar_senha_com_confirmacao_correta_atualiza(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $original = $user->password;

        $this->actingAs($user);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $user->id, 'password')
            ->set('password', 'novaSenha123')
            ->set('password_confirmation', 'novaSenha123')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertNotSame($original, User::find($user->id)->password, 'A senha deveria ter mudado.');
    }

    public function test_admin_edita_outro_mantem_gestao_completa(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $other = User::factory()->create(['is_admin' => 'N', 'name' => 'Old']);

        $this->actingAs($admin);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $other->id)
            ->assertSet('mode', 'manage')
            ->set('name', 'Atualizado')
            ->set('email', $other->email)
            ->set('is_admin', 'S')
            ->call('submit')
            ->assertOk();

        $fresh = User::find($other->id);
        $this->assertSame('Atualizado', $fresh->name);
        $this->assertSame('S', $fresh->is_admin, 'Admin deveria promover outro usuário (modo manage).');
    }

    public function test_modal_perfil_esconde_senha_e_admin_e_mostra_empresas(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);

        $this->actingAs($admin);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $admin->id, null, 'profile')
            ->assertSet('mode', 'profile')
            ->assertSee('Minhas configurações')
            ->assertSee('Empresas vinculadas')
            // o CAMPO admin (select #is_admin) não pode aparecer no perfil; a
            // PALAVRA "Administrador" pode (hint das empresas p/ usuário admin)
            ->assertDontSee('id="is_admin"', false)
            ->assertDontSee('Confirmar senha');
    }

    public function test_modal_alterar_senha_mostra_confirmar_senha(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);

        $this->actingAs($user);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $user->id, 'password')
            ->assertSet('mode', 'password')
            ->assertSee('Alterar senha')
            ->assertSee('Confirmar senha');
    }

    public function test_modal_self_locked_oculta_salvar(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);

        $this->actingAs($admin);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $admin->id)
            ->assertSet('mode', 'self_locked')
            ->assertSee('Edite seus dados pelo menu do perfil')
            ->assertDontSee('Salvar');
    }

    /**
     * A lista pagina de 10 em 10 e ordena por admin e nome: com nomes gerados,
     * os dois usuários caíam ora na página 1, ora na 2, e o teste falhava
     * sozinho. A busca por um marcador comum isola exatamente estes dois.
     */
    public function test_lista_oculta_excluir_da_propria_linha(): void
    {
        $marcador = 'ZZ-EXCLUIR-' . __FUNCTION__;
        $admin = User::factory()->create(['is_admin' => 'S', 'name' => $marcador . ' ADMIN']);
        $other = User::factory()->create(['is_admin' => 'N', 'name' => $marcador . ' COMUM']);

        $this->actingAs($admin);

        Livewire::test(UserIndex::class)
            ->set('search', $marcador)
            ->assertSeeHtml("eventCuteConfirmDeleteUser', { 'id': {$other->id} }")
            ->assertDontSeeHtml("eventCuteConfirmDeleteUser', { 'id': {$admin->id} }");
    }
}
