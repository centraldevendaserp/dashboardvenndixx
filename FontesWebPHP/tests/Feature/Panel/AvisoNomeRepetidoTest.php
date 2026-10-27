<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\User\ModalDataToUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nome de usuário repetido AVISA, não bloqueia: nome igual é legítimo, e quem
 * identifica é o e-mail. O aviso pega o cadastro feito duas vezes por engano.
 *
 * Contraste deliberado com o NOME DA CHAVE, que bloqueia: lá o nome é rótulo de
 * máquina, e repetido não dá para saber qual revogar.
 */
class AvisoNomeRepetidoTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_avisa_quando_o_nome_ja_existe(): void
    {
        User::factory()->create(['name' => 'Victor', 'email' => 'victor@gmail.com']);
        $this->admin();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'store')
            ->set('name', 'Victor')
            ->assertSee('victor@gmail.com')
            ->assertSee('pode salvar');
    }

    /** E deixa salvar mesmo assim — é aviso, não trava. */
    public function test_salva_mesmo_com_o_nome_repetido(): void
    {
        User::factory()->create(['name' => 'Victor', 'email' => 'victor@gmail.com']);
        $this->admin();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'store')
            ->set('name', 'Victor')
            ->set('email', 'victor2@gmail.com')
            ->set('password', 'senha12345')
            ->set('is_admin', 'N')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(2, User::where('name', 'Victor')->count());
    }

    public function test_nome_novo_nao_avisa(): void
    {
        User::factory()->create(['name' => 'Victor', 'email' => 'victor@gmail.com']);
        $this->admin();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'store')
            ->set('name', 'Wagner')
            ->assertDontSee('pode salvar');
    }

    /** Editando o próprio registro, o nome dele mesmo não conta como repetido. */
    public function test_editar_sem_mudar_o_nome_nao_avisa(): void
    {
        $alvo = User::factory()->create(['name' => 'Victor', 'email' => 'victor@gmail.com']);
        $this->admin();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $alvo->id)
            ->assertDontSee('pode salvar');
    }
}
