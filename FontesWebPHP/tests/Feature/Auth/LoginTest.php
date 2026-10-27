<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Login: credenciais válidas entram; inválidas não; rate-limit após 5 falhas.
 */
class LoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Rate-limiter isolado por processo (não vaza estado entre execuções).
        config(['cache.default' => 'array']);

        // O submit() chama session()->regenerate() e o Livewire recria o
        // request ao despachar: religa a MESMA sessão em qualquer request
        // rebindado no container.
        $store = $this->app['session']->driver();
        $store->start();
        $this->app->rebinding('request', function ($app, $request) use ($store) {
            $request->setLaravelSession($store);
        });
        $this->app['request']->setLaravelSession($store);
    }

    public function test_credenciais_validas_autenticam_e_redirecionam(): void
    {
        // A senha padrão do UserFactory é "password".
        $user = User::factory()->create(['is_admin' => 'N']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('submit')
            ->assertRedirect(route('panel.dashboard.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_credenciais_invalidas_nao_autenticam(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'senha-errada')
            ->call('submit')
            ->assertNoRedirect()
            ->assertDispatched('auth-finished');

        $this->assertGuest();
    }

    public function test_login_e_limitado_apos_cinco_falhas(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)
                ->set('email', $user->email)
                ->set('password', 'errada')
                ->call('submit');
        }

        // A 6ª tentativa deve cair no throttle, mesmo com a senha CORRETA.
        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('submit')
            ->assertNoRedirect()
            ->assertDispatched('auth-finished');

        // 6ª tentativa (mesmo com a senha CORRETA) não autentica = throttle atuou.
        $this->assertGuest();
    }
}
