<?php

namespace Tests\Feature\Infra;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * O `.env` de cliente antigo fica defasado e o /install não conserta (ele se
 * tranca quando o banco já tem usuário), então quem corrige é o `deploy.sh`.
 *
 * Tudo que dá para deduzir é corrigido sozinho; o `MAIL_HOST` é a caixa de
 * e-mail daquele cliente e vira aviso.
 */
class AutoReparoDoEnvTest extends TestCase
{
    private function deploy(): string
    {
        return File::get(base_path('deploy.sh'));
    }

    public static function reparos(): array
    {
        return [
            'chave do agente'    => ['SYSTEM_ACCESS_KEY', 'sem ela o agente leva 403 e para de enviar'],
            'cookie só https'    => ['SESSION_SECURE_COOKIE', 'cookie de sessão em claro'],
            'rotação de log'     => ['LOG_CHANNEL', 'arquivo único que enche o disco'],
            'retenção de log'    => ['LOG_DAILY_DAYS', ''],
            'ambiente'           => ['APP_ENV', 'ficava local, default do Laravel 8'],
            'nível de log'       => ['LOG_LEVEL', 'debug grava chave de acesso em texto plano'],
        ];
    }

    /** @dataProvider reparos */
    public function test_deploy_repara(string $chave, string $porque): void
    {
        $this->assertStringContainsString(
            $chave,
            $this->deploy(),
            "o deploy deixou de tratar {$chave}" . ($porque ? " — {$porque}" : '')
        );
    }

    /**
     * `APP_ENV` e `SESSION_SECURE_COOKIE` só mudam quando o APP_URL daquele
     * portal já é https: o cookie seguro num portal sem TLS derruba o login.
     */
    public function test_reparos_arriscados_dependem_de_https(): void
    {
        $deploy = $this->deploy();

        // Duas guardas: uma antes do SESSION_SECURE_COOKIE, outra antes do APP_ENV.
        $this->assertSame(
            2,
            substr_count($deploy, "APP_URL[[:space:]]*=[[:space:]]*"),
            'APP_ENV e SESSION_SECURE_COOKIE precisam checar https antes de mexer'
        );
    }

    /** `MAIL_HOST` é a caixa do cliente: continua sendo aviso, nunca troca automática. */
    public function test_mail_host_e_aviso_nao_reparo(): void
    {
        $deploy = $this->deploy();

        $this->assertDoesNotMatchRegularExpression(
            '/sed -i.*MAIL_HOST/',
            $deploy,
            'MAIL_HOST não pode ser trocado sozinho — cada cliente tem o seu SMTP'
        );
        $this->assertStringContainsString('MAIL_HOST', $deploy);
    }

    /** Canal de log personalizado (papertrail, etc.) não pode ser atropelado. */
    public function test_nao_atropela_canal_personalizado(): void
    {
        $this->assertMatchesRegularExpression(
            '/stack\|single\)/',
            $this->deploy(),
            'o reparo do LOG_CHANNEL precisa listar os canais que troca, não trocar qualquer um'
        );
    }
}
