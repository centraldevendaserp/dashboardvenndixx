<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\User\ModalDataToUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Duas regras da chave do agente:
 *  1. gerar e revogar exigem a edição habilitada — o bloco das chaves fica FORA
 *     do <fieldset disabled>, e revogar derruba o envio do cliente na hora;
 *  2. o nome da chave é único POR USUÁRIO: repetido, não dá para saber qual
 *     revogar. Entre usuários diferentes o mesmo nome é normal.
 */
class ChaveAgenteRegrasTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);

        return $admin;
    }

    /* ------------------- trava do "Habilitar edição" -------------------- */

    public function test_gerar_sem_habilitar_edicao_e_recusado(): void
    {
        $alvo = User::factory()->create(['is_admin' => 'N']);
        $this->admin();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $alvo->id)   // manage: somente-leitura
            ->set('tokenInstallationName', 'PC Recepcao')
            ->call('generateAgentToken')
            ->assertForbidden();

        $this->assertSame(0, $alvo->tokens()->count());
    }

    public function test_revogar_sem_habilitar_edicao_e_recusado(): void
    {
        $alvo = User::factory()->create(['is_admin' => 'N']);
        $tokenId = $alvo->createToken('PC Recepcao', ['agent:upload'])->accessToken->id;
        $this->admin();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $alvo->id)
            ->call('revokeAgentToken', $tokenId)
            ->assertForbidden();

        $this->assertSame(1, $alvo->tokens()->count(), 'a chave foi revogada com a tela travada');
    }

    public function test_com_a_edicao_habilitada_funciona(): void
    {
        $alvo = User::factory()->create(['is_admin' => 'N']);
        $this->admin();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $alvo->id)
            ->call('enableEdit')
            ->set('tokenInstallationName', 'PC Recepcao')
            ->call('generateAgentToken')
            ->assertSet('showTokenPanel', true);

        $this->assertSame(1, $alvo->tokens()->count());
    }

    /* ----------------------- nome único por usuário --------------------- */

    public function test_mesmo_nome_duas_vezes_no_mesmo_usuario_e_recusado(): void
    {
        $alvo = User::factory()->create(['is_admin' => 'N']);
        $alvo->createToken('Instalação principal', ['agent:upload']);
        $this->admin();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $alvo->id)
            ->call('enableEdit')
            ->set('tokenInstallationName', 'Instalação principal')
            ->call('generateAgentToken')
            ->assertHasErrors('tokenInstallationName')
            ->assertSet('showTokenPanel', false);

        $this->assertSame(1, $alvo->tokens()->count(), 'criou a segunda chave com o nome repetido');
    }

    /** O MESMO nome em usuários diferentes é normal — cada cliente tem a sua. */
    public function test_mesmo_nome_em_usuarios_diferentes_e_permitido(): void
    {
        $a = User::factory()->create(['is_admin' => 'N']);
        $b = User::factory()->create(['is_admin' => 'N']);
        $a->createToken('Instalação principal', ['agent:upload']);
        $this->admin();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $b->id)
            ->call('enableEdit')
            ->set('tokenInstallationName', 'Instalação principal')
            ->call('generateAgentToken')
            ->assertHasNoErrors()
            ->assertSet('showTokenPanel', true);

        $this->assertSame(1, $b->tokens()->count());
    }

    /** Nome diferente no mesmo usuário continua podendo — é o caso de 2 máquinas. */
    public function test_nomes_diferentes_no_mesmo_usuario_podem(): void
    {
        $alvo = User::factory()->create(['is_admin' => 'N']);
        $alvo->createToken('PC da recepcao', ['agent:upload']);
        $this->admin();

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $alvo->id)
            ->call('enableEdit')
            ->set('tokenInstallationName', 'Servidor do escritorio')
            ->call('generateAgentToken')
            ->assertHasNoErrors();

        $this->assertSame(2, $alvo->tokens()->count());
    }
}
