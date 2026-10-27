<?php
/**
 * Diagnostico de ambiente ANTES do Laravel subir: troca o "500 em branco" da
 * instalacao por uma pagina dizendo o que falta. As causas comuns em hospedagem
 * compartilhada (PHP velho, vendor/ ausente, extensao faltando, storage sem
 * escrita) acontecem antes de o Laravel existir, e com APP_DEBUG=false ele nao
 * tem como contar. Ambiente sadio: sai calado.
 *
 * ⚠️ ESTE ARQUIVO RODA EM PHP ANTIGO — ele e carregado num servidor em PHP 7.4,
 * que e um dos casos que ele diagnostica. Sintaxe de PHP 8 (match, ?->, tipos de
 * propriedade) faria o proprio diagnostico virar erro. Mantenha compativel com
 * PHP 5.6.
 */

$raiz = dirname(__DIR__);
$problemas = array();

// --- 1. Versao do PHP -------------------------------------------------------
// Em PHP velho o vendor/ nem carrega, entao esta e a primeira checagem.
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $problemas[] = array(
        'o' => 'O site esta rodando em PHP ' . PHP_VERSION . ', e o portal exige 8.2 ou mais novo.',
        'f' => 'No painel da hospedagem, procure "Selecionar versao do PHP" (cPanel: MultiPHP Manager) '
             . 'e escolha 8.2 ou 8.3 para este dominio. Nao use AddHandler no .htaccess: em servidor '
             . 'com PHP-FPM isso derruba o dominio.',
    );
}

// --- 2. vendor/ -------------------------------------------------------------
// O git nao versiona vendor/: quem manda os arquivos por FTP cai aqui.
if (!file_exists($raiz . '/vendor/autoload.php')) {
    $problemas[] = array(
        'o' => 'A pasta "vendor" nao existe (ou esta incompleta). Ela guarda as bibliotecas do portal '
             . 'e NAO vem no download do codigo.',
        'f' => 'Rode "composer install --no-dev --optimize-autoloader" na pasta do portal. '
             . 'Sem terminal no servidor: rode o composer no seu computador e envie a pasta "vendor" '
             . 'inteira por FTP — ela e so codigo PHP, funciona igual.',
    );
}

// --- 3. Extensoes que o portal USA em execucao ------------------------------
// Sem qualquer uma destas o Laravel nao roda. No painel da hospedagem elas sao
// marcaveis uma a uma, entao vale listar quais faltam.
$faltando = array();
foreach (array('pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo') as $ext) {
    if (!extension_loaded($ext)) {
        $faltando[] = $ext;
    }
}
if ($faltando) {
    $problemas[] = array(
        'o' => 'Faltam extensoes do PHP: ' . implode(', ', $faltando) . '.',
        'f' => 'No painel da hospedagem, na tela de extensoes do PHP (cPanel: "Select PHP Version" -> '
             . 'aba Extensions; Hostinger: Avancado -> Configuracao PHP -> Extensoes PHP), marque as '
             . 'que faltam e salve.',
    );
}

// --- 3b. soap: exigencia do COMPOSER, nao da execucao -----------------------
// O `composer install` aborta sem ela, mas o portal so gera DANFE e nunca fala
// com a SEFAZ — em execucao ele funciona sem soap. Por isso nao e bloqueio
// quando o vendor/ ja existe: barrar deixaria de fora quem monta o vendor/ na
// propria maquina e sobe o zip, que e como se instala sem terminal.
if (!extension_loaded('soap') && !file_exists($raiz . '/vendor/autoload.php')) {
    $problemas[] = array(
        'o' => 'Falta a extensao "soap" do PHP. Ela e exigida pelo "composer install"; '
             . 'o portal em si nao usa SOAP.',
        'f' => 'Ative no painel (cPanel: "Select PHP Version" -> aba Extensions -> marque "soap"; '
             . 'Hostinger: Avancado -> Configuracao PHP -> Extensoes PHP). Se nao achar a opcao, '
             . 'rode o "composer install" no SEU computador e envie a pasta "vendor" por FTP — '
             . 'ai a soap nao e mais necessaria neste servidor.',
    );
}

// --- 4. .env ----------------------------------------------------------------
if (!file_exists($raiz . '/.env')) {
    $problemas[] = array(
        'o' => 'O arquivo ".env" nao existe. E nele que ficam o endereco do site e os dados do banco.',
        'f' => 'Rode "bash deploy.sh https://seu.dominio.com.br" na pasta do portal — ele cria o .env. '
             . 'Sem terminal: copie o ".env.example" para ".env" pelo gerenciador de arquivos e '
             . 'preencha APP_URL e os dados do banco.',
    );
}

// --- 4b. APP_KEY ------------------------------------------------------------
// Sem chave o Laravel nao sobe, e da 500 ate no /install — que e justamente
// quem geraria a chave. Um laco fechado para quem nao tem terminal.
//
// ⚠️ Gerar so e seguro com a chave VAZIA: nada foi cifrado com ela ainda.
// Sobrescrever chave existente mataria sessoes e todo dado cifrado.
if (file_exists($raiz . '/.env') && function_exists('random_bytes')) {
    $env = file_get_contents($raiz . '/.env');

    if (!preg_match('/^[ \t]*APP_KEY[ \t]*=[ \t]*["\']?base64:/m', $env)) {
        $nova = 'APP_KEY=base64:' . base64_encode(random_bytes(32));

        if (preg_match('/^[ \t]*APP_KEY[ \t]*=.*$/m', $env)) {
            $env = preg_replace('/^[ \t]*APP_KEY[ \t]*=.*$/m', $nova, $env, 1);
        } else {
            $env = rtrim($env, "\r\n") . "\n" . $nova . "\n";
        }

        if (is_writable($raiz . '/.env') && file_put_contents($raiz . '/.env', $env) !== false) {
            // O config em cache guardaria a chave vazia, e este boot precisa da
            // chave nova ja valendo.
            @unlink($raiz . '/bootstrap/cache/config.php');
        } else {
            $problemas[] = array(
                'o' => 'O arquivo ".env" nao tem chave de seguranca (APP_KEY) e nao consegui gravar uma.',
                'f' => 'De permissao de escrita ao arquivo ".env" (chmod 660) e atualize esta pagina — '
                     . 'a chave e gerada sozinha. Com terminal, o comando e "php artisan key:generate".',
            );
        }
    }
}

// --- 5. Escrita -------------------------------------------------------------
// Causa numero um do 500 quando o resto esta certo: o Laravel precisa gravar
// cache de view, sessao e log.
foreach (array('storage', 'storage/logs', 'storage/framework', 'bootstrap/cache') as $pasta) {
    $caminho = $raiz . '/' . $pasta;
    if (file_exists($caminho) && !is_writable($caminho)) {
        $problemas[] = array(
            'o' => 'A pasta "' . $pasta . '" nao tem permissao de escrita.',
            'f' => 'Rode "chmod -R u+rwX,g+rwX storage bootstrap/cache" na pasta do portal, ou marque '
                 . 'escrita para o dono no gerenciador de arquivos da hospedagem.',
        );
    }
}

if (!$problemas) {
    return;   // ambiente sadio: segue o fluxo normal do index.php
}

// --- Pagina ------------------------------------------------------------------
// Texto simples, sem depender de nada do projeto: o CSS do portal esta atras do
// Laravel, que e justamente o que nao subiu.
if (!headers_sent()) {
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
}

echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width,initial-scale=1">'
   . '<title>Portal do Contador - instalacao incompleta</title><style>'
   . 'body{margin:0;background:#eef1f5;color:#1c2430;font:16px/1.55 system-ui,Segoe UI,Arial,sans-serif}'
   . '.w{max-width:760px;margin:0 auto;padding:48px 22px 64px}'
   . 'h1{font-size:1.45rem;margin:0 0 6px}'
   . '.sub{color:#6b7480;margin:0 0 26px}'
   . '.i{background:#fff;border:1px solid #e0e6ee;border-left:4px solid #d08a1e;border-radius:8px;'
   . 'padding:16px 18px;margin:0 0 14px}'
   . '.i b{display:block;margin-bottom:6px}'
   . '.i span{color:#4a5560;font-size:.94rem}'
   . 'code{background:#eef1f5;border:1px solid #e0e6ee;border-radius:4px;padding:1px 5px;font-size:.9em}'
   . '.p{color:#8b96a4;font-size:.85rem;margin-top:26px}'
   . '</style></head><body><div class="w">'
   . '<h1>O portal ainda nao esta pronto para abrir</h1>'
   . '<p class="sub">Encontramos ' . count($problemas) . ' ponto(s) para resolver no servidor. '
   . 'Depois de corrigir, atualize esta pagina.</p>';

foreach ($problemas as $p) {
    echo '<div class="i"><b>' . htmlspecialchars($p['o'], ENT_QUOTES, 'UTF-8') . '</b>'
       . '<span>' . htmlspecialchars($p['f'], ENT_QUOTES, 'UTF-8') . '</span></div>';
}

echo '<p class="p">PHP ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8')
   . ' &middot; esta mensagem aparece no lugar do erro 500 em branco, e some sozinha '
   . 'quando o ambiente estiver correto.</p></div></body></html>';

exit;
