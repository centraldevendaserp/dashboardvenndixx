<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Instalador web (tela /install). Enquanto não instalado, a raiz do site cai
 * aqui: o formulário pede banco, URL e admin inicial; ao enviar, testa a
 * conexão, escreve o .env, roda as migrations, cria o admin e grava o lock.
 *
 * ⚠️ A tela abre sem banco porque SESSION/CACHE são `file` no .env.example —
 * trocar para `database` quebra o instalador.
 */
class InstallController extends Controller
{
    /** Caminho do arquivo-lock que marca "já instalado". */
    public static function lockPath(): string
    {
        return storage_path('installed');
    }

    /**
     * Raiz do site: wizard enquanto não instalado, login depois.
     *
     * ⚠️ Método, não Closure na rota: `route:cache` não serializa Closure e o
     * portal ficaria sem cache de rotas.
     */
    public function raiz()
    {
        return redirect()->route(self::isInstalled() ? 'auth.login' : 'install.show');
    }

    public static function isInstalled(): bool
    {
        if (file_exists(self::lockPath())) {
            return true;
        }

        // A tranca é um arquivo, e arquivo se perde (o deploy apaga o lock
        // quando não acha o .env; instalação pelo dump nunca o cria). Se já
        // existe usuário, o sistema ESTÁ instalado. Banco inacessível cai no
        // catch e conta como "não instalado", que é o estado de quem começa.
        try {
            return \App\Models\User::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** GET /install — mostra o wizard (ou manda pro login se já instalado). */
    public function show()
    {
        if (self::isInstalled()) {
            return redirect()->route('auth.login');
        }

        // valores sugeridos: o que já estiver no .env atual (base) ou defaults
        return view('install.wizard', [
            'defaults' => [
                'app_url' => request()->getSchemeAndHttpHost(),
                'db_port' => env('DB_PORT', '3306'),
            ] + self::suggestedDbDefaults(env('DB_HOST'), env('DB_DATABASE'), env('DB_USERNAME')),
        ]);
    }

    /**
     * Sugestões do formulário: valor de fábrica do .env.example não é
     * configuração real, então vira localhost / campo em branco. Valor
     * personalizado de um .env já ajustado é preservado.
     */
    public static function suggestedDbDefaults(?string $host, ?string $database, ?string $username): array
    {
        return [
            'db_host'     => ($host === null || $host === '' || $host === '127.0.0.1') ? 'localhost' : $host,
            'db_database' => ($database === null || $database === 'laravel') ? '' : $database,
            'db_username' => ($username === null || $username === 'root') ? '' : $username,
        ];
    }

    /** POST /install — processa a instalação. */
    public function store(Request $request)
    {
        if (self::isInstalled()) {
            abort(403, 'O sistema já está instalado.');
        }

        $data = $request->validate([
            'db_host'        => ['required', 'string'],
            'db_port'        => ['required', 'numeric'],
            'db_database'    => ['required', 'string'],
            'db_username'    => ['required', 'string'],
            'db_password'    => ['nullable', 'string'],
            'app_url'        => ['required', 'url'],
            'admin_name'     => ['required', 'string', 'max:191'],
            'admin_email'    => ['required', 'email', 'max:191'],
            'admin_password' => ['required', 'string', 'min:8'],
        ], [], [
            'db_host' => 'host do banco', 'db_database' => 'nome do banco',
            'db_username' => 'usuário do banco', 'admin_email' => 'e-mail do admin',
            'admin_password' => 'senha do admin',
        ]);

        // 1. Testa a conexão (e cria o database se tiver privilégio) --------------
        $error = $this->checkDatabase($data);
        if ($error !== null) {
            return back()->withInput()->withErrors(['db_host' => $error]);
        }

        // 2. Escreve o .env (preserva/garante APP_KEY) ---------------------------
        $appKey = trim((string) config('app.key'));
        if ($appKey === '') {
            $appKey = 'base64:' . base64_encode(random_bytes(32));
        }

        $this->writeEnv([
            'APP_ENV'     => 'production',
            'APP_DEBUG'   => 'false',
            'APP_KEY'     => $appKey,
            'APP_URL'     => $data['app_url'],
            'DB_CONNECTION' => 'mysql',
            'DB_HOST'     => $data['db_host'],
            'DB_PORT'     => $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
        ]);

        // 3. Aplica a nova conexão em runtime (sem reiniciar o processo) ---------
        config([
            'app.key' => $appKey,
            'database.connections.mysql.host'     => $data['db_host'],
            'database.connections.mysql.port'     => $data['db_port'],
            'database.connections.mysql.database' => $data['db_database'],
            'database.connections.mysql.username' => $data['db_username'],
            'database.connections.mysql.password' => $data['db_password'] ?? '',
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        // 4. Migrations (idempotentes: cria do zero OU aplica o delta) -----------
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->withErrors([
                'db_host' => 'Falha ao rodar as migrations: ' . $e->getMessage(),
            ]);
        }

        // 5. Cria (ou atualiza) o admin -----------------------------------------
        // is_admin fica fora do $fillable, então tem de ser setado na instância.
        $admin = User::firstOrNew(['email' => $data['admin_email']]);
        $admin->name = $data['admin_name'];
        $admin->password = Hash::make($data['admin_password']);
        $admin->is_admin = 'S';
        $admin->save();

        // 6. Trava o instalador + limpa cache de config -------------------------
        file_put_contents(self::lockPath(), 'installed ' . date('c') . "\n");
        Artisan::call('config:clear');

        return redirect()->route('auth.login')
            ->with('message-green', 'Instalação concluída! Faça login com o admin que você criou.');
    }

    /**
     * Testa a conexão. Best-effort: conecta sem database, tenta criar o database
     * (se tiver privilégio), depois confirma a conexão com o database. Devolve
     * null em sucesso ou a mensagem de erro amigável.
     */
    protected function checkDatabase(array $d): ?string
    {
        $dsnBase = "mysql:host={$d['db_host']};port={$d['db_port']}";
        try {
            $pdo = new \PDO($dsnBase, $d['db_username'], $d['db_password'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (\PDOException $e) {
            return 'Não consegui conectar no servidor de banco (host/porta/usuário/senha). Detalhe: ' . $e->getMessage();
        }

        // tenta criar o database (ignora se não tiver privilégio — pode já existir)
        try {
            $db = str_replace('`', '', $d['db_database']);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\PDOException $e) {
            // sem privilégio de CREATE — ok se o banco já existir (checado abaixo)
        }

        try {
            new \PDO("{$dsnBase};dbname={$d['db_database']}", $d['db_username'], $d['db_password'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (\PDOException $e) {
            return "Conectei no servidor, mas não no banco '{$d['db_database']}'. Crie o banco no painel (cPanel/Hostinger) e tente de novo.";
        }

        return null;
    }

    /** Escreve/atualiza chaves no .env (cria a partir do .env.example se faltar). */
    protected function writeEnv(array $values): void
    {
        $path = base_path('.env');
        $content = is_file($path)
            ? file_get_contents($path)
            : (is_file(base_path('.env.example')) ? file_get_contents(base_path('.env.example')) : '');

        foreach ($values as $key => $value) {
            $line = $key . '=' . $this->escapeEnvValue((string) $value);
            if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $content)) {
                $content = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $content);
            } else {
                $content = rtrim($content, "\n") . "\n" . $line . "\n";
            }
        }

        file_put_contents($path, $content);
    }

    protected function escapeEnvValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s"\'#=]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }
        return $value;
    }
}
