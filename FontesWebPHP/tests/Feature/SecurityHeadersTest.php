<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Cabeçalhos de segurança do portal. Os dois que mais importam:
 *  - `frame-ancestors`/`X-Frame-Options`: o painel tem ações destrutivas a um
 *    clique, e enquadrado num site alheio o contador clicaria sem ver;
 *  - `nosniff`: o portal serve XML e ZIP enviados por terceiros, que o navegador
 *    poderia tratar como HTML e executar na origem do portal.
 */
class SecurityHeadersTest extends TestCase
{
    public static function paginas(): array
    {
        return [
            'login' => ['/auth/login'],
            'raiz'  => ['/'],
        ];
    }

    /** @dataProvider paginas */
    public function test_cabecalhos_presentes(string $url): void
    {
        $r = $this->get($url);

        $r->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", $r->headers->get('Content-Security-Policy'));
    }

    public function test_painel_autenticado_tambem(): void
    {
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail())
            ->get('/panel/dashboard')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** A API do agente também: ela devolve JSON, mas o nosniff vale igual. */
    public function test_api_do_agente_tambem(): void
    {
        $this->postJson('/api/docs/status-erp/upload', ['key' => 'errada'])
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * HSTS só em HTTPS: anunciar em HTTP não tem efeito, e num ambiente sem TLS
     * prenderia o navegador em HTTPS deixando o portal inacessível.
     */
    public function test_hsts_so_em_https(): void
    {
        $this->get('/auth/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/auth/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=15552000');
    }
}
