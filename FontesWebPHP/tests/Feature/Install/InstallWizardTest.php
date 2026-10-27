<?php

namespace Tests\Feature\Install;

use App\Http\Controllers\InstallController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wizard de instalação (/install). Cobre os caminhos seguros — mostrar o
 * formulário, travar quando já instalado e validar o POST — sem executar a
 * instalação real, que escreveria o .env e rodaria migrate.
 *
 * A tranca é `storage/installed`, e o estado dela é preservado no setUp.
 */
class InstallWizardTest extends TestCase
{
    use DatabaseTransactions;   // o "sistema zerado" apaga usuários; tem de voltar

    private bool $hadLock = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hadLock = InstallController::isInstalled();
    }

    protected function tearDown(): void
    {
        // restaura o estado original do lock
        if ($this->hadLock) {
            @file_put_contents(InstallController::lockPath(), "installed\n");
        } else {
            @unlink(InstallController::lockPath());
        }
        parent::tearDown();
    }

    /**
     * "Não instalado" de verdade: sem o arquivo de tranca E sem usuário. A
     * tranca em arquivo se perde, então apagá-la sozinha não representa mais um
     * sistema novo.
     */
    private function unlock(): void
    {
        @unlink(InstallController::lockPath());

        DB::table('user_company')->delete();
        DB::table('users')->delete();
    }

    private function lock(): void
    {
        @file_put_contents(InstallController::lockPath(), "installed\n");
    }

    public function test_wizard_aparece_quando_nao_instalado(): void
    {
        $this->unlock();

        $this->get('/install')
            ->assertOk()
            ->assertSee('Assistente de instalação')
            ->assertSee('Banco de dados')
            ->assertSee('Administrador inicial');
    }

    public function test_raiz_redireciona_para_o_wizard_quando_nao_instalado(): void
    {
        $this->unlock();

        $this->get('/')->assertRedirect(route('install.show'));
    }

    /**
     * Com usuário no banco, o wizard recusa mesmo sem o arquivo de tranca: é o
     * caso do portal instalado pelo dump ou que perdeu o `.env`.
     */
    public function test_wizard_recusa_quando_ha_usuario_mesmo_sem_o_arquivo(): void
    {
        @unlink(InstallController::lockPath());   // só o arquivo; os usuários ficam

        $this->assertTrue(User::query()->exists(), 'pré-condição: o dump tem usuários');
        $this->assertTrue(InstallController::isInstalled());

        $this->get('/install')->assertRedirect(route('auth.login'));

        $this->post('/install', [
            'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_database' => 'x',
            'db_username' => 'x', 'db_password' => 'x',
            'name' => 'Invasor', 'email' => 'invasor@x.com', 'password' => 'senha12345',
        ])->assertForbidden();

        $this->assertNull(User::where('email', 'invasor@x.com')->first(), 'admin criado por anônimo');
    }

    public function test_install_redireciona_para_login_quando_ja_instalado(): void
    {
        $this->lock();

        $this->get('/install')->assertRedirect(route('auth.login'));
        $this->get('/')->assertRedirect(route('auth.login'));
    }

    public function test_post_bloqueado_quando_ja_instalado(): void
    {
        $this->lock();

        $this->post('/install', [
            'db_host' => 'x', 'db_port' => '3306', 'db_database' => 'x',
            'db_username' => 'x', 'app_url' => 'http://x.test',
            'admin_name' => 'x', 'admin_email' => 'x@x.test', 'admin_password' => 'senha12345',
        ])->assertForbidden();
    }

    public function test_post_valida_campos_obrigatorios(): void
    {
        $this->unlock();

        $this->post('/install', [])
            ->assertSessionHasErrors(['db_host', 'db_database', 'db_username', 'app_url', 'admin_email', 'admin_password']);

        // validação falhou antes de instalar → lock NÃO deve ter sido criado
        $this->assertFalse(InstallController::isInstalled(), 'A validação não pode criar o lock.');
    }

    public function test_defaults_de_fabrica_viram_sugestoes_amigaveis(): void
    {
        // Valor de fábrica do .env.example não é configuração real: o host
        // sugere localhost e banco/usuário ficam em branco.
        $amigavel = ['db_host' => 'localhost', 'db_database' => '', 'db_username' => ''];

        $this->assertSame($amigavel, InstallController::suggestedDbDefaults('127.0.0.1', 'laravel', 'root'));
        $this->assertSame($amigavel, InstallController::suggestedDbDefaults(null, null, null));
        $this->assertSame($amigavel, InstallController::suggestedDbDefaults('', '', ''));

        // valores personalizados (instalação reconfigurada) são preservados
        $this->assertSame(
            ['db_host' => 'db.interno', 'db_database' => 'portal_x', 'db_username' => 'user_x'],
            InstallController::suggestedDbDefaults('db.interno', 'portal_x', 'user_x')
        );
    }

    public function test_wizard_mostra_placeholders_nos_campos(): void
    {
        $this->unlock();

        $resp = $this->get('/install')->assertOk();

        $resp->assertSee('placeholder="localhost"', false);
        $resp->assertSee('placeholder="ex.: usuario_portal"', false);
        $resp->assertSee('placeholder="https://cliente.seudominio.com.br"', false);
        $resp->assertSee('placeholder="mínimo 8 caracteres"', false);
    }

    public function test_post_valida_senha_curta_do_admin(): void
    {
        $this->unlock();

        $this->post('/install', [
            'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_database' => 'x',
            'db_username' => 'x', 'app_url' => 'http://x.test',
            'admin_name' => 'x', 'admin_email' => 'x@x.test', 'admin_password' => '123',
        ])->assertSessionHasErrors(['admin_password']);
    }
}
