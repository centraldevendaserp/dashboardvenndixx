<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/panel/dashboard';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            // O agente importa em rajada e ignora o status HTTP (só lê msg):
            // um 429 vira falha silenciosa com retry eterno, e o fim da
            // varredura (eventos/inutilizações) nunca chega. Credencial válida
            // não tem limite; credencial errada o CheckSystem barra com 403.
            //
            // ⚠️ O throttle roda ANTES do CheckSystem, por isso as DUAS
            // credenciais são validadas aqui de novo.
            $key = (string) $request->input('key', '');

            // Token por instalação, com a habilidade agent:upload e não expirado.
            if (str_contains($key, '|')) {
                $token = PersonalAccessToken::findToken($key);
                if ($token && $token->can('agent:upload') && ! ($token->expires_at && $token->expires_at->isPast())) {
                    return Limit::none();
                }
            }

            // Chave legada, só enquanto a convivência estiver ligada.
            $systemKey = (string) config('app.system_access_key');
            if (config('app.legacy_key_enabled') && $systemKey !== '' && hash_equals($systemKey, $key)) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
