<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ForgotPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Anti-enumeração: e-mail existente e inexistente devem produzir a MESMA
 * resposta genérica (sem revelar se a conta existe).
 */
class ForgotPasswordTest extends TestCase
{
    use DatabaseTransactions;

    private string $genericMessage = 'Se o e-mail existir, enviamos as instruções de redefinição.';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']); // throttle isolado
    }

    public function test_email_existente_retorna_mensagem_generica(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('submit')
            ->assertDispatched('eventCuteToast', fn ($event, $params) => ($params['msg'] ?? null) === $this->genericMessage);
    }

    public function test_email_inexistente_retorna_a_mesma_mensagem(): void
    {
        Livewire::test(ForgotPassword::class)
            ->set('email', 'nao-existe-' . uniqid() . '@inexistente.test')
            ->call('submit')
            // Mesma mensagem genérica do caso existente -> impossível enumerar contas.
            ->assertDispatched('eventCuteToast', fn ($event, $params) => ($params['msg'] ?? null) === $this->genericMessage);
    }

    /** SMTP inalcançável — o caso de instalação nova, que nasce sem MAIL_HOST. */
    private function comSmtpQuebrado(): void
    {
        config([
            'mail.default'            => 'smtp',
            'mail.mailers.smtp.host'  => 'smtp-que-nao-existe.invalido',
            'mail.mailers.smtp.port'  => 1025,
        ]);
        app('mail.manager')->forgetMailers();
    }

    /**
     * `Password::sendResetLink()` ESTOURA quando o transporte falha, em vez de
     * devolver status: sem o try/catch, o cliente clica em "Recuperar senha" e
     * leva erro sem mensagem nenhuma. SMTP mal configurado é o caso comum.
     */
    public function test_falha_de_smtp_nao_estoura(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $this->comSmtpQuebrado();

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('submit')
            ->assertOk();
    }

    /**
     * E a resposta é a mesma de sempre: com o transporte quebrado, conta
     * existente estouraria e conta inexistente sairia antes de tentar — mensagens
     * diferentes viram oráculo de enumeração. O erro real vai para o log.
     */
    public function test_falha_de_smtp_responde_igual_e_nao_enumera(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $this->comSmtpQuebrado();

        $mesmaMensagem = fn ($event, $params) => ($params['msg'] ?? null) === $this->genericMessage;

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('submit')
            ->assertDispatched('eventCuteToast', $mesmaMensagem);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'nao-existe-' . uniqid() . '@inexistente.test')
            ->call('submit')
            ->assertDispatched('eventCuteToast', $mesmaMensagem);
    }
}
