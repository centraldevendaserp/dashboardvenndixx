<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * O agente importa em massa e ignora o status HTTP (só lê msg=100): com o
 * throttle padrão, o fim da varredura levava 429 e nunca chegava. Credencial
 * válida não tem limite; todo o resto mantém os 60/min.
 */
class UploadRateLimitTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_requisicao_com_chave_do_sistema_nao_tem_rate_limit(): void
    {
        $limiter = RateLimiter::limiter('api');

        $request = Request::create('/api/docs/eventos/nfenfce/upload', 'POST', [
            'key' => config('app.system_access_key'),
        ]);

        $this->assertInstanceOf(Unlimited::class, $limiter($request));
    }

    public function test_requisicao_sem_chave_valida_mantem_60_por_minuto(): void
    {
        $limiter = RateLimiter::limiter('api');

        foreach (['', 'chave-errada'] as $key) {
            $request = Request::create('/api/docs/eventos/nfenfce/upload', 'POST', [
                'key' => $key,
            ]);

            $limit = $limiter($request);

            $this->assertNotInstanceOf(Unlimited::class, $limit);
            $this->assertSame(60, $limit->maxAttempts);
        }
    }

    public function test_61_uploads_seguidos_com_chave_valida_nao_recebem_429(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.61.61.61']);

        for ($i = 1; $i <= 61; $i++) {
            $response = $this->post('/api/docs/eventos/nfenfce/upload', [
                'key' => config('app.system_access_key'),
            ]);

            $this->assertNotSame(429, $response->getStatusCode(),
                "Upload {$i} com chave valida recebeu 429 (throttle)");
        }
    }

    public function test_sem_chave_valida_o_61o_request_recebe_429(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.62.62.62']);

        for ($i = 1; $i <= 60; $i++) {
            $this->post('/api/docs/eventos/nfenfce/upload', ['key' => 'chave-errada'])
                ->assertStatus(403);
        }

        $this->post('/api/docs/eventos/nfenfce/upload', ['key' => 'chave-errada'])
            ->assertStatus(429);
    }

    public function test_requisicao_com_token_valido_nao_tem_rate_limit(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('PC Teste', ['agent:upload'])->plainTextToken;

        $limiter = RateLimiter::limiter('api');
        $request = Request::create('/api/docs/eventos/nfenfce/upload', 'POST', ['key' => $plain]);

        $this->assertInstanceOf(Unlimited::class, $limiter($request));
    }

    public function test_61_uploads_seguidos_com_token_nao_recebem_429(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.63.63.63']);
        $user = User::factory()->create();
        $plain = $user->createToken('PC Teste', ['agent:upload'])->plainTextToken;

        for ($i = 1; $i <= 61; $i++) {
            $response = $this->post('/api/docs/eventos/nfenfce/upload', ['key' => $plain]);
            $this->assertNotSame(429, $response->getStatusCode(),
                "Upload {$i} com token valido recebeu 429 (throttle)");
        }
    }

    public function test_token_revogado_mantem_60_por_minuto(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('PC Teste', ['agent:upload'])->plainTextToken;
        $user->tokens()->delete();

        $limiter = RateLimiter::limiter('api');
        $request = Request::create('/api/docs/eventos/nfenfce/upload', 'POST', ['key' => $plain]);

        $limit = $limiter($request);
        $this->assertNotInstanceOf(Unlimited::class, $limit);
        $this->assertSame(60, $limit->maxAttempts);
    }
}
