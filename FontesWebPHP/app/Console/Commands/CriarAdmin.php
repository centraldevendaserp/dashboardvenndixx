<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Cria (ou redefine a senha de) um administrador do portal — a saída para quem
 * instalou pelo dump e ficou sem senha conhecida, já que o /install se tranca
 * quando o banco tem usuário.
 *
 *     php artisan portal:admin
 *     php artisan portal:admin --email=voce@dominio.com.br
 *
 * ⚠️ `--password` na linha de comando fica no histórico do shell e no `ps`. Sem
 * a opção, o comando pergunta (entrada oculta) ou gera uma senha forte.
 */
class CriarAdmin extends Command
{
    protected $signature = 'portal:admin
        {--email= : E-mail do administrador (existindo, a senha é redefinida)}
        {--name= : Nome exibido no painel}
        {--password= : Senha em texto; prefira omitir e deixar o comando perguntar}
        {--generate : Gera uma senha forte e mostra (para rodar sem terminal, via cron)}';

    protected $description = 'Cria um administrador do portal, ou redefine a senha de um existente';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('E-mail do administrador');
        $email = trim((string) $email);

        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('E-mail invalido: ' . $email);

            return self::FAILURE;
        }

        $usuario = User::where('email', $email)->first();
        $existia = $usuario !== null;

        // Nome só é obrigatório para usuário novo.
        $nome = $this->option('name');
        if (! $existia && ! $nome) {
            $nome = $this->ask('Nome do administrador');
        }

        $senha = $this->option('password');
        $gerada = false;

        // Boa parte da hospedagem não tem SSH e isto roda por cron, sem
        // terminal: nenhum caminho pode ficar pendurado num prompt.
        //   --generate -> gera direto     -n -> não-interativo, gera direto
        //   < /dev/null -> secret() lê EOF, volta vazio e o bloco abaixo gera
        $semTerminal = ! $this->input->isInteractive();

        if (! $senha && ($this->option('generate') || $semTerminal)) {
            $senha = Str::password(20, symbols: false);
            $gerada = true;
        }

        if (! $senha) {
            // secret() não ecoa; vazio gera uma senha forte.
            $senha = $this->secret('Senha (deixe em branco para gerar uma forte)');

            if (! $senha) {
                $senha = Str::password(20, symbols: false);
                $gerada = true;
            }
        }

        // Mesma régua do wizard e do painel: os três não podem discordar.
        if (mb_strlen($senha) < 8) {
            $this->error('A senha precisa de pelo menos 8 caracteres.');

            return self::FAILURE;
        }

        if (! $existia) {
            $usuario = new User();
            $usuario->email = $email;
        }

        if ($nome) {
            $usuario->name = $nome;
        }

        $usuario->password = Hash::make($senha);
        // is_admin fica fora do $fillable: setado na instância.
        $usuario->is_admin = 'S';
        $usuario->save();

        $this->newLine();
        $this->info($existia
            ? "Administrador atualizado: {$email}"
            : "Administrador criado: {$email}");

        if ($gerada) {
            $this->newLine();
            $this->warn('Senha gerada (anote agora — ela nao e mostrada de novo):');
            $this->line('    ' . $senha);
        }

        if ($existia && $usuario->wasChanged('is_admin')) {
            $this->line('Este usuario foi PROMOVIDO a administrador.');
        }

        $this->newLine();
        $this->line('Entre em ' . rtrim((string) config('app.url'), '/') . '/auth/login');

        return self::SUCCESS;
    }
}
