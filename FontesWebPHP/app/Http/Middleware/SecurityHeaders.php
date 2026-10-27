<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Cabeçalhos de segurança HTTP (middleware global).
 *
 * ⚠️ /assets e /storage não passam pelo PHP: quem serve é o Apache. Os mesmos
 * cabeçalhos estão repetidos no public/.htaccess, e lá com `Header setifempty`
 * — com `set`, as respostas do Laravel saem com tudo duplicado.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Alguns retornos (stream de ZIP/PDF) não expõem headers do mesmo jeito.
        if (! method_exists($response, 'header')) {
            return $response;
        }

        // Clickjacking: o painel tem ações destrutivas a um clique.
        // X-Frame-Options fica só para navegador antigo.
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // O portal serve XML e ZIP enviados por terceiros: sem `nosniff`, o
        // navegador pode tratá-los como HTML e executar na origem do portal.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // A URL do painel carrega id de documento; não vaza para fora.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Recursos que o portal não usa: nega de saída.
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');

        // O X-Powered-By entrega a versao exata do PHP. `header_remove`
        // funciona com mod_php e PHP-FPM; `php_flag` no .htaccess nao.
        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        // HSTS só sobre HTTPS: num portal sem TLS, prender o navegador em
        // HTTPS o deixaria inacessível.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
