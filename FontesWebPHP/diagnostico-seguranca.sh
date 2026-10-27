#!/usr/bin/env bash
# =============================================================================
# Diagnóstico de segurança do servidor — SOMENTE LEITURA.
#
# Não altera nada, não instala nada, não envia nada para lugar nenhum. Só olha e
# imprime. Rode no servidor, dentro da pasta do portal:
#
#     bash diagnostico-seguranca.sh
#
# A auditoria do CÓDIGO foi feita no repositório; o que este script cobre é o que
# só existe NA MÁQUINA: permissões reais, o que está exposto na web, versão de
# PHP em uso, e o estado do .env daquele cliente.
#
# Cole a saída inteira para análise. Ela NÃO contém senha nem chave — os valores
# sensíveis saem mascarados de propósito.
# =============================================================================
set -uo pipefail

ok()    { printf '  \033[32m✓\033[0m %s\n' "$1"; }
alerta(){ printf '  \033[33m⚠\033[0m %s\n' "$1"; }
falha() { printf '  \033[31m✗\033[0m %s\n' "$1"; }
titulo(){ printf '\n\033[1m== %s\033[0m\n' "$1"; }

APP="."
[ -f artisan ] || { [ -f FontesWebPHP/artisan ] && APP="FontesWebPHP"; }
cd "$APP" 2>/dev/null || { echo "Rode dentro da pasta do portal."; exit 1; }

titulo "1. Identificação"
echo "  pasta ............ $(pwd)"
echo "  PHP (CLI) ........ $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo '?')"
echo "  commit ........... $(git rev-parse --short HEAD 2>/dev/null || echo '?')"

titulo "2. Versão de PHP que o SITE usa (pode diferir do CLI)"
if grep -q 'AddHandler application/x-httpd-ea-php' public/.htaccess 2>/dev/null; then
  _v="$(grep -o 'ea-php[0-9]*' public/.htaccess | head -1)"
  echo "  handler no .htaccess: $_v"
  case "$_v" in
    ea-php8[2-9]) ok "site em PHP 8.2+ (o projeto exige ^8.2)" ;;
    *) falha "site em PHP ABAIXO de 8.2 — sem patch de segurança e incompatível" ;;
  esac
else
  alerta "sem AddHandler: a versão vem do painel (MultiPHP/PHP-FPM). Confirme lá que é 8.2+"
fi

titulo "3. Segredos e configuração do .env"
if [ -f .env ]; then
  # O que importa e o ULTIMO digito (others), nao o numero inteiro: `-le 640`
  # reprovava o 660 que o proprio deploy.sh grava de proposito, e aprovava 604,
  # que e legivel por todo mundo. O risco real sao as OUTRAS contas do servidor.
  _perm="$(stat -c '%a' .env)"
  case "$_perm" in
    *0) ok ".env com permissão $_perm (fechado para outras contas do servidor)" ;;
    *)  alerta ".env com permissão $_perm — outras contas do servidor podem ler. Rode o deploy.sh." ;;
  esac
  grep -qE '^APP_DEBUG=false' .env && ok "APP_DEBUG=false" || falha "APP_DEBUG NÃO está false — telas de erro expõem código e credenciais"
  grep -qE '^APP_ENV=production' .env && ok "APP_ENV=production" || alerta "APP_ENV não é production"
  grep -qE '^SESSION_SECURE_COOKIE=true' .env && ok "cookie de sessão só por HTTPS" || alerta "SESSION_SECURE_COOKIE não é true (cookie pode trafegar em HTTP)"
  # `APP_KEY="base64:..."` e valido — o dotenv tira as aspas. O grep ingenuo
  # (^APP_KEY=base64:) nao ve a aspa e ACUSA CHAVE VAZIA num portal sadio. O
  # deploy.sh ja tinha caido nisso em 2026-07-15; repeti aqui. Visto num servidor
  # real em 2026-07-31: o diagnostico gritava enquanto o deploy, com o grep
  # certo, nem chamava o key:generate.
  if grep -qE '^[[:space:]]*APP_KEY[[:space:]]*=[[:space:]]*["'"'"']?base64:' .env; then
    ok "APP_KEY definida"
  else
    falha "APP_KEY vazia — sessão e assinatura do Livewire ficam previsíveis"
  fi
  if grep -qE '^SYSTEM_LEGACY_KEY_ENABLED=false' .env; then
    ok "chave legada DESLIGADA (este cliente usa token por instalação)"
  else
    alerta "chave legada LIGADA — a palavra 'Sistema' é pública e grava na base fiscal. Migrar para token."
  fi
  _mh="$(grep -E '^MAIL_HOST=' .env | cut -d= -f2- | tr -d '\"')"
  case "${_mh:-}" in
    ''|null)              falha "MAIL_HOST vazio — \"Esqueci minha senha\" não funciona" ;;
    mailhog|localhost|127.0.0.1) falha "MAIL_HOST=${_mh} é o servidor de e-mail de DESENVOLVIMENTO — não existe aqui" ;;
    *)                    ok "SMTP apontado para ${_mh}" ;;
  esac
  grep -qE '^MAIL_FROM_ADDRESS=.+' .env && ok "remetente definido" || alerta "MAIL_FROM_ADDRESS vazio (SPF recusa endereço de outro domínio)"
  _lvl="$(grep -E '^LOG_LEVEL=' .env | cut -d= -f2)"
  [ "${_lvl:-}" = "error" ] && ok "LOG_LEVEL=error (não grava chave de acesso no log)" || alerta "LOG_LEVEL=${_lvl:-<vazio>} — info/warning gravam chave de acesso em claro"
else
  falha ".env não encontrado"
fi

titulo "4. O que está alcançável pela web"
_docroot_ok=0
if [ -f public/index.php ]; then
  ok "public/index.php existe"
fi
for f in .env .git/config composer.json storage/logs/laravel.log; do
  [ -e "$f" ] && echo "  presente no disco: $f"
done
echo "  --- teste pela URL (troque pelo domínio real):"
echo "      for p in .env .git/config storage/logs/laravel.log backup_banco_automatico/ ; do"
echo "        curl -s -o /dev/null -w \"%{http_code}  \$p\\n\" https://SEU-DOMINIO/\$p ; done"
echo "      Esperado: 403 ou 404 em TODOS. Qualquer 200 é vazamento."

titulo "5. Permissões"
for d in storage bootstrap/cache; do
  [ -d "$d" ] && echo "  $d: $(stat -c '%a %U:%G' "$d")"
done
if [ -d backup_banco_automatico ]; then
  _p="$(stat -c '%a' backup_banco_automatico)"
  [ "$_p" = "700" ] && ok "backups com permissão 700" || alerta "backups com permissão $_p (contêm o banco inteiro em claro)"
  echo "  backups guardados: $(ls -1 backup_banco_automatico 2>/dev/null | wc -l) arquivo(s), $(du -sh backup_banco_automatico 2>/dev/null | cut -f1)"
  alerta "backup fica NO MESMO servidor: se a máquina cair ou for invadida, vai junto"
fi
# `! -type l`: link simbolico e SEMPRE lrwxrwxrwx no Linux e isso nao quer dizer
# nada — quem manda e a permissao do ALVO. Sem isto, o `public/storage` criado
# pelo `storage:link` aparecia como "gravavel por qualquer um" em todo servidor.
_ww="$(find . -maxdepth 2 -perm -o+w ! -type l -not -path './vendor/*' -not -path './node_modules/*' -not -path './.git/*' 2>/dev/null | head -5)"
[ -z "$_ww" ] && ok "nada gravável por 'outros' na raiz" || { alerta "gravável por QUALQUER usuário do servidor:"; echo "$_ww" | sed 's/^/      /'; }

titulo "6. Permissões de 'outros' (hospedagem compartilhada)"
# O Laravel grava sessão/log com file_put_contents sem modo -> 0644. Em servidor
# compartilhado isso é OUTRA CONTA lendo a sessão do contador e o acervo fiscal.
for d in storage/framework/sessions storage/logs storage/app/docs; do
  [ -d "$d" ] || continue
  _n="$(find "$d" -perm -o+r -type f 2>/dev/null | wc -l)"
  if [ "${_n:-0}" -gt 0 ]; then
    alerta "$d: ${_n} arquivo(s) legíveis por QUALQUER conta do servidor — rode o deploy.sh novo"
  else
    ok "$d fechado para 'outros'"
  fi
done
if [ -d storage/app/public ]; then
  find storage/app/public -type f ! -name '.gitignore' -perm -o+r >/dev/null 2>&1 \
    && ok "storage/app/public legível (correto: é o que o storage:link publica)" \
    || echo "  (storage/app/public vazio)"
fi

titulo "7. Lixo de download acumulado"
if [ -d storage/app/downloads ]; then
  _velhos="$(find storage/app/downloads -type f -mmin +60 2>/dev/null | wc -l)"
  echo "  pasta: $(du -sh storage/app/downloads 2>/dev/null | cut -f1) | com mais de 1h: ${_velhos:-0}"
  [ "${_velhos:-0}" -gt 0 ] \
    && alerta "sobrou lixo — some no próximo 'Baixar XML' (App\\Support\\DownloadCleanup)" \
    || ok "sem lixo antigo"
fi

titulo "8. Cabeçalhos de segurança (precisa do domínio)"
echo "  --- rode e confira que TODOS aparecem:"
echo "      curl -sI https://SEU-DOMINIO/auth/login | grep -iE 'x-frame|nosniff|referrer|content-security'"
echo "      curl -sI https://SEU-DOMINIO/assets/css/auth.css | grep -iE 'x-frame|nosniff'"
echo "      Cada um tem de aparecer UMA vez. Duplicado o navegador pode ignorar."

titulo "9. Tamanho dos logs"
# Caso de 14/07/2026: cliente esgotou 100 GB e o espaco todo estava aqui.
_lc="$(grep -E '^LOG_CHANNEL=' .env 2>/dev/null | cut -d= -f2- | tr -d '"')"
case "${_lc:-}" in
  daily) ok "LOG_CHANNEL=daily (corta por dia; guarda ${_ldd:-30} dias)" ;;
  ''|stack|single) falha "LOG_CHANNEL=${_lc:-<vazio>} — arquivo UNICO que cresce para sempre. Rode o deploy.sh novo." ;;
  *) echo "  LOG_CHANNEL=${_lc} (personalizado)" ;;
esac
if [ -f storage/logs/laravel.log ]; then
  echo "  laravel.log: $(du -h storage/logs/laravel.log | cut -f1)"
  _kb="$(du -k storage/logs/laravel.log | cut -f1)"
  [ "${_kb:-0}" -gt 51200 ] && alerta "acima de 50 MB — no canal 'daily' este arquivo e ORFAO (ninguem escreve nem rotaciona); o deploy.sh encolhe"
fi
ls -1 storage/logs/laravel-*.log >/dev/null 2>&1 && \
  echo "  arquivos rotacionados: $(ls -1 storage/logs/laravel-*.log | wc -l) | total: $(du -ch storage/logs/laravel-*.log 2>/dev/null | tail -1 | cut -f1)"
echo "  chaves de acesso em claro no log: $(grep -ac '"chave"' storage/logs/laravel.log 2>/dev/null || echo 0) linha(s)"

titulo "10. Chaves do agente em uso"
php artisan tinker --execute '
  $t = DB::table("personal_access_tokens")->count();
  echo "  tokens por instalacao: $t\n";
  foreach (DB::table("personal_access_tokens")->select("name","last_used_at")->get() as $r)
      echo "     - {$r->name} (ultimo uso: " . ($r->last_used_at ?: "nunca") . ")\n";
' 2>/dev/null || echo "  (não consegui consultar; rode: php artisan tinker)"

titulo "Fim"
echo "  Cole a saída acima para análise. Nenhum valor de senha ou chave foi impresso."
