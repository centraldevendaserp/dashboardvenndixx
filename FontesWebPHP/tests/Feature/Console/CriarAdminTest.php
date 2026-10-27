<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Sem este comando, quem instala pelo DUMP fica trancado do lado de fora: o
 * /install se tranca quando o banco já tem usuário, e as senhas do dump ninguém
 * conhece. Esta é a saída suportada — sem colar hash gerado em site de terceiros.
 */
class CriarAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cria_admin_novo(): void
    {
        $email = 'novo-' . uniqid() . '@teste.local';

        $this->artisan('portal:admin', [
            '--email'    => $email,
            '--name'     => 'Contador Teste',
            '--password' => 'senha-forte-123',
        ])->assertExitCode(0);

        $u = User::where('email', $email)->firstOrFail();

        $this->assertSame('Contador Teste', $u->name);
        $this->assertSame('S', $u->is_admin);
        $this->assertTrue(Hash::check('senha-forte-123', $u->password));
    }

    /** E-mail que já existe: troca a senha e promove — é o caso do suporte. */
    public function test_redefine_senha_de_quem_ja_existe(): void
    {
        $u = User::factory()->create(['is_admin' => 'N']);

        $this->artisan('portal:admin', [
            '--email'    => $u->email,
            '--password' => 'outra-senha-456',
        ])->assertExitCode(0);

        $u->refresh();

        $this->assertSame('S', $u->is_admin, 'tem de promover — senão a pessoa entra e não administra');
        $this->assertTrue(Hash::check('outra-senha-456', $u->password));
    }

    /**
     * Sem --password, gera uma senha forte e mostra uma vez. O --generate existe
     * para o caso sem terminal (cron da hospedagem), onde um prompt travaria —
     * mas mesmo sem a flag o comando gera sozinho ao ler EOF.
     */
    public function test_gera_senha_quando_nao_informada(): void
    {
        $email = 'gerada-' . uniqid() . '@teste.local';

        $this->artisan('portal:admin', ['--email' => $email, '--name' => 'X', '--generate' => true])
            ->expectsOutputToContain('Senha')
            ->assertExitCode(0);

        $u = User::where('email', $email)->firstOrFail();

        $this->assertSame('S', $u->is_admin);
        $this->assertNotEmpty($u->password);
    }

    /** Mesma régua do wizard e do painel: mínimo 8. */
    public function test_recusa_senha_curta(): void
    {
        $email = 'curta-' . uniqid() . '@teste.local';

        $this->artisan('portal:admin', [
            '--email'    => $email,
            '--name'     => 'X',
            '--password' => 'abc',
        ])->assertExitCode(1);

        $this->assertNull(User::where('email', $email)->first());
    }

    public function test_recusa_email_invalido(): void
    {
        $this->artisan('portal:admin', [
            '--email'    => 'nao-e-email',
            '--name'     => 'X',
            '--password' => 'senha-forte-123',
        ])->assertExitCode(1);
    }

    /** A senha nunca é gravada em claro. */
    public function test_senha_e_gravada_com_o_hasher_do_app(): void
    {
        $email = 'hash-' . uniqid() . '@teste.local';

        $this->artisan('portal:admin', [
            '--email'    => $email,
            '--name'     => 'X',
            '--password' => 'senha-forte-123',
        ]);

        $u = User::where('email', $email)->firstOrFail();

        $this->assertNotSame('senha-forte-123', $u->password);
        $this->assertStringStartsWith('$2y$', $u->password);
    }
}
