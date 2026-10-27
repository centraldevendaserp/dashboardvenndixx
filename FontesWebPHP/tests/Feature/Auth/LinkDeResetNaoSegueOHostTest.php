<?php

namespace Tests\Feature\Auth;

use App\Mail\ForgotPassword;
use App\Models\User;
use Illuminate\Http\Request;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * O link de redefinição de senha não pode sair de `route()`, que monta a URL a
 * partir do cabeçalho Host: com o TrustHosts fora do stack, qualquer Host passa,
 * e o e-mail sai na mesma requisição já com o endereço forjado — entregando o
 * token a quem o forjou. O link tem de ancorar no APP_URL.
 */
class LinkDeResetNaoSegueOHostTest extends TestCase
{
    /** Simula o que o Host forjado faz: a raiz das URLs vira o domínio do atacante. */
    private function comHostForjado(string $host): void
    {
        $req = Request::create("{$host}/auth/forgot-password", 'POST');
        $this->app->instance('request', $req);
        URL::setRequest($req);
    }

    public function test_link_usa_o_app_url_e_nao_o_host_da_requisicao(): void
    {
        config(['app.url' => 'https://portal.exemplo.com.br']);
        $this->comHostForjado('http://evil.example');

        $url = (new ForgotPassword('TOKEN123'))->build()->viewData['url'];

        $this->assertStringStartsWith('https://portal.exemplo.com.br/', $url);
        $this->assertStringNotContainsString('evil.example', $url);
        $this->assertStringContainsString('TOKEN123', $url);
    }

    /** O link continua funcionando no caminho normal — não pode virar 404. */
    public function test_link_aponta_para_a_rota_de_reset(): void
    {
        config(['app.url' => 'https://portal.exemplo.com.br']);

        $url = (new ForgotPassword('TOKEN123'))->build()->viewData['url'];

        $this->assertSame(
            'https://portal.exemplo.com.br' . route('auth.password.reset', ['token' => 'TOKEN123'], false),
            $url
        );
    }

    /** APP_URL vazio: sem domínio para ancorar, mantém o comportamento de antes. */
    public function test_sem_app_url_nao_quebra(): void
    {
        config(['app.url' => '']);

        $url = (new ForgotPassword('TOKEN123')) ->build()->viewData['url'];

        $this->assertStringContainsString('TOKEN123', $url);
    }

    /**
     * A cadeia real, do usuário ao corpo do e-mail:
     * User::sendPasswordResetNotification -> ResetPasswordNotification ->
     * App\Mail\ForgotPassword. Não há rota POST: o formulário é Livewire.
     */
    public function test_cadeia_real_da_notificacao(): void
    {
        config(['app.url' => 'https://portal.exemplo.com.br']);
        $this->comHostForjado('http://evil.example');

        $user = User::query()->firstOrFail();

        Notification::fake();
        $user->sendPasswordResetNotification('TOKEN123');
        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $url = (new ResetPasswordNotification('TOKEN123'))->toMail($user)->build()->viewData['url'];

        $this->assertStringNotContainsString('evil.example', $url);
        $this->assertStringStartsWith('https://portal.exemplo.com.br/', $url);
    }
}
