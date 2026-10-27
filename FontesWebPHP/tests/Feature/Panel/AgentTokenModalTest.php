<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\User\ModalDataToUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class AgentTokenModalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_gera_token_para_outro_usuario(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $target = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($admin);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $target->id)
            ->call('enableEdit')   // manage abre somente-leitura: e o que o admin faz
            ->set('tokenInstallationName', 'PC Recepcao')
            ->call('generateAgentToken')
            ->assertSet('showTokenPanel', true)
            ->assertSet('generatedTokenName', 'PC Recepcao');

        $token = $target->tokens()->first();
        $this->assertNotNull($token, 'Deveria ter criado um token para o alvo.');
        $this->assertSame('PC Recepcao', $token->name);
        $this->assertTrue($token->can('agent:upload'), 'Token deve ter a habilidade agent:upload.');
    }

    public function test_admin_revoga_token(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $target = User::factory()->create(['is_admin' => 'N']);
        $target->createToken('PC1', ['agent:upload']);
        $tokenId = $target->tokens()->first()->id;
        $this->actingAs($admin);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $target->id)
            ->call('enableEdit')   // manage abre somente-leitura; e o que o admin faz
            ->call('revokeAgentToken', $tokenId);

        $this->assertNull($target->tokens()->first(), 'O token deveria ter sido revogado.');
    }

    public function test_nao_admin_nao_gera_token(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($user);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $user->id, null, 'profile')
            ->call('generateAgentToken')
            ->assertForbidden();
    }

    public function test_cadastro_gera_primeira_chave_e_mostra_painel(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);
        $email = 'novo-' . uniqid() . '@test.com';

        $component = Livewire::test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'store'])
            ->set('name', 'Contador Novo')
            ->set('email', $email)
            ->set('is_admin', 'N')
            ->set('password', 'senha12345')
            ->set('tokenInstallationName', 'Instalacao principal')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('showTokenPanel', true);

        $this->assertNotNull($component->get('generatedToken'), 'O token em texto puro deve ser exposto uma vez.');

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user, 'O usuário deveria ter sido criado.');
        $this->assertNotNull($user->tokens()->first(), 'A 1ª chave da instalação deveria ter sido criada.');
    }

    public function test_secao_token_aparece_para_admin_no_cadastro(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'store'])
            ->assertSee('Chave de acesso do agente')
            ->assertSee('Nome da chave');
    }

    public function test_secao_token_nao_aparece_no_perfil_de_nao_admin(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($user);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $user->id, null, 'profile')
            ->assertDontSee('Chave de acesso do agente');
    }

    public function test_painel_mostra_token_em_texto_puro_apos_gerar(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $target = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($admin);

        $component = Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $target->id)
            ->call('enableEdit')   // manage abre somente-leitura: e o que o admin faz
            ->set('tokenInstallationName', 'PC1')
            ->call('generateAgentToken');

        $plain = $component->get('generatedToken');
        $component->assertSee($plain);      // o texto puro é renderizado uma vez
        $component->assertSee('Concluir');
    }

    public function test_revogar_nao_apaga_token_de_outro_usuario(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $target = User::factory()->create(['is_admin' => 'N']);
        $outro = User::factory()->create(['is_admin' => 'N']);
        $outro->createToken('PC-outro', ['agent:upload']);
        $tokenId = $outro->tokens()->first()->id;
        $this->actingAs($admin);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $target->id)   // alvo = target, NÃO outro
            ->call('enableEdit')   // manage abre somente-leitura; e o que o admin faz
            ->call('revokeAgentToken', $tokenId);          // tenta revogar token do outro

        $this->assertNotNull($outro->tokens()->first(),
            'Revogar no alvo não pode apagar token de outro usuário.');
    }

    public function test_reabrir_modal_limpa_o_painel_do_token(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $target = User::factory()->create(['is_admin' => 'N']);
        $outro = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($admin);

        $c = Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $target->id)
            ->call('enableEdit')   // manage abre somente-leitura: e o que o admin faz
            ->set('tokenInstallationName', 'PC1')
            ->call('generateAgentToken')
            ->assertSet('showTokenPanel', true);

        // reabrir o modal para outro usuário SEM passar pelo "Concluir" (simula fechar pelo X)
        $c->call('eventAction', 'edit', $outro->id)
        ->call('enableEdit')   // manage abre somente-leitura: e o que o admin faz
            ->assertSet('showTokenPanel', false)
            ->assertSet('generatedToken', null);
    }

    public function test_excluir_usuario_revoga_tokens_do_agente(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $target = User::factory()->create(['is_admin' => 'N']);
        $target->createToken('PC1', ['agent:upload']);
        $tokenId = $target->tokens()->first()->id;
        $this->actingAs($admin);

        Livewire::test(\App\Livewire\Panel\User\Index::class)->call('deleteUser', $target->id);

        $this->assertNull(\Laravel\Sanctum\PersonalAccessToken::find($tokenId),
            'Excluir o usuário deveria revogar (apagar) os tokens do agente dele.');
    }

    public function test_painel_de_cadastro_avisa_que_usuario_foi_salvo(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);
        $email = 'novo-' . uniqid() . '@test.com';

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'store'])
            ->set('name', 'Contador Novo')
            ->set('email', $email)
            ->set('is_admin', 'N')
            ->set('password', 'senha12345')
            ->call('submit')
            ->assertSet('showTokenPanel', true)
            ->assertSet('userJustCreated', true)
            ->assertSee('cadastrado com sucesso');
    }

    public function test_gerar_chave_no_manage_nao_mostra_banner_de_cadastro(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $target = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($admin);

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', 'edit', $target->id)
            ->call('enableEdit')   // manage abre somente-leitura: e o que o admin faz
            ->set('tokenInstallationName', 'PC1')
            ->call('generateAgentToken')
            ->assertSet('showTokenPanel', true)
            ->assertSet('userJustCreated', false)
            ->assertDontSee('cadastrado com sucesso');
    }

    /**
     * O campo Administrador é obrigatório: vazio, a validação falha e o modal
     * fica "carregando sem sair do lugar". O default 'Não' evita isso.
     */
    public function test_cadastro_sem_escolher_admin_usa_nao_e_salva(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);
        $email = 'default-' . uniqid() . '@x.com';

        Livewire::test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'store'])
            ->set('name', 'Sem Escolher Admin')
            ->set('email', $email)
            ->set('password', 'senha12345')
            // NÃO seta is_admin — deve usar o default 'N' e salvar sem erro
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('showTokenPanel', true);

        $u = User::where('email', $email)->first();
        $this->assertNotNull($u, 'O usuário deveria ter sido criado.');
        $this->assertSame('N', $u->is_admin, 'Sem escolher admin, o usuário nasce não-admin.');
    }
}
