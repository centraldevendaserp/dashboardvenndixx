<?php

namespace Tests\Feature\Infra;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Log enchendo o disco do cliente: `LOG_CHANNEL=stack` cai no canal `single`,
 * que é um arquivo só, para sempre. O `daily` corta por dia e apaga os antigos.
 *
 * ⚠️ Duas armadilhas travadas aqui:
 *  1. a retenção tem de ser de 30 dias, não os 14 padrão;
 *  2. trocar o canal NÃO encolhe o `laravel.log` que já existe — ele vira órfão,
 *     ninguém escreve nele e ninguém o rotaciona.
 */
class RotacaoDeLogTest extends TestCase
{
    public function test_retencao_e_de_30_dias(): void
    {
        $this->assertSame(30, config('logging.channels.daily.days'));
    }

    /** Configurável por `.env`, para um cliente com pouco disco poder baixar. */
    public function test_retencao_vem_do_env(): void
    {
        $this->assertStringContainsString(
            "env('LOG_DAILY_DAYS'",
            File::get(config_path('logging.php')),
            'a retenção precisa vir do .env — cliente com pouco disco não pode depender de deploy'
        );
    }

    /** Instalação nova já nasce rotacionando. */
    public function test_env_example_nasce_com_daily_e_30(): void
    {
        $env = File::get(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^LOG_CHANNEL=daily$/m', $env);
        $this->assertMatchesRegularExpression('/^LOG_DAILY_DAYS=30$/m', $env);
    }

    /**
     * O caso comum é o `.env` antigo COM a chave presente e o valor errado:
     * agir só quando ela está ausente pula exatamente quem tem o problema.
     */
    public function test_deploy_troca_stack_e_single_por_daily(): void
    {
        $deploy = File::get(base_path('deploy.sh'));

        $this->assertMatchesRegularExpression(
            '/LOG_CHANNEL=(stack|single)/',
            $deploy,
            'o deploy precisa reconhecer o canal ANTIGO, não só a ausência da chave'
        );
        $this->assertStringContainsString('LOG_DAILY_DAYS', $deploy);
    }

    /** E precisa lidar com o arquivo órfão — senão o disco não volta. */
    public function test_deploy_encolhe_o_laravel_log_orfao(): void
    {
        $deploy = File::get(base_path('deploy.sh'));

        $this->assertStringContainsString('logs/laravel.log', $deploy);
        $this->assertStringContainsString(
            'SKIP_LOG_TRIM',
            $deploy,
            'mexer no log de um cliente precisa ter escape, como SKIP_DB_BACKUP'
        );
    }
}
