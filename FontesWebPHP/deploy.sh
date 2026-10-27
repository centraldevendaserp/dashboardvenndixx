#!/usr/bin/env bash
#
# Deploy do Portal do Contador — rode NO SERVIDOR (PHP 8.3 + Composer + git),
# a partir da raiz do repositório clonado.
#
# Uso:
#   bash deploy.sh                     -> deploy normal (atualização)
#   bash deploy.sh https://meu.dominio -> 1a vez: grava esse APP_URL no .env novo
#
# Instala o que o git NÃO traz (gitignore): vendor/ (composer), public/storage
# (symlink) e o .env/APP_KEY. Os assets (CSS/JS) já vêm versionados em
# public/assets — este projeto NÃO usa npm build nem Chromium (PDF é via DomPDF).
#
# Detecta o cenário automaticamente pelo lock storage/installed:
#   - INSTALAÇÃO NOVA  (sem lock): cria um .env LIMPO do .env.example com
#     sessão/cache em ARQUIVO (pra a tela /install abrir sem banco), gera o
#     APP_KEY e NÃO roda migrations -> você conclui no /install (banco + admin).
#   - INSTALAÇÃO EXISTENTE (com lock): NÃO toca no .env. Atualiza o código,
#     reinstala as deps e roda SÓ as migrations novas (migrate --force).
#
set -euo pipefail

cd "$(dirname "$0")"
APP_URL_ARG="${1:-}"

# ── Localiza o app Laravel (raiz do repo ou a subpasta de fontes) ─────────────
if   [ -f artisan ]; then APP="."
elif [ -f "FontesWebPHP/artisan" ]; then APP="FontesWebPHP"
else echo "ERRO: não achei o 'artisan' (nem aqui nem em 'FontesWebPHP/')." >&2; exit 1
fi

# ── Sanidade: ferramentas ────────────────────────────────────────────────────
for bin in php composer git; do
  command -v "$bin" >/dev/null 2>&1 || { echo "ERRO: '$bin' não está instalado no servidor." >&2; exit 1; }
done
echo "==> PHP $(php -r 'echo PHP_VERSION;') | $(composer --version 2>/dev/null | head -n1) | app em: $APP"

# ── 1) Atualiza o código ─────────────────────────────────────────────────────
echo "==> [1/6] git pull"
# Auto-reexec: se o próprio deploy.sh mudar no pull, o bash continuaria rodando a
# versão ANTIGA (já em memória). Detectamos e reexecutamos a versão NOVA uma vez
# (guarda DEPLOY_REEXEC evita loop; no reexec o pull já foi feito).
if [ "${DEPLOY_REEXEC:-0}" = "1" ]; then
  echo "    (reexec após auto-update do deploy.sh — pull já feito nesta execução)"
else
  # O cPanel REESCREVE public/.htaccess sozinho (bloco "cPanel-generated
  # handler") sempre que alguem mexe na versao do PHP no painel. Como o arquivo e
  # versionado, isso vira alteracao local e o `git pull` aborta com um erro que
  # nao diz o que fazer. Detecta antes e explica.
  # Servidor de deploy roda chmod (passo 5), e o git conta mudanca de PERMISSAO
  # como modificacao — o que faz o proprio `git pull` abortar. Desliga de vez
  # neste clone; e idempotente e so vale aqui.
  git config core.fileMode false 2>/dev/null || true

  # ---------------------------------------------------------------------------
  # public/.htaccess: o cPanel e DONO desse arquivo neste tipo de hospedagem.
  #
  # Ele reescreve o bloco "cPanel-generated handler" toda vez que alguem troca a
  # versao do PHP no painel — e e esse bloco que DEFINE a versao do site quando o
  # dominio nao esta em PHP-FPM. Como o arquivo tambem e versionado, a cada
  # deploy dava conflito, e a saida exigia quatro comandos na mao. Passo manual em
  # servidor de cliente e passo que nao vai ser feito, entao o deploy faz sozinho:
  #
  #   1. guarda o bloco do painel
  #   2. aceita a versao do repositorio (destrava o pull)
  #   3. depois do pull, devolve o bloco
  #   4. marca skip-worktree, para o git parar de brigar com o painel
  #
  # SO automatiza quando a UNICA diferenca e esse bloco. Se alguem editou regra de
  # rewrite na mao, para e avisa — ai nao e lixo do painel e nao da para adivinhar.
  # ---------------------------------------------------------------------------
  _HT="$APP/public/.htaccess"
  _BLOCO_CPANEL=""

  # Remove o bloco E as linhas em branco do fim: o cPanel acrescenta o bloco
  # precedido de uma linha vazia, que sobraria e faria a comparacao acusar
  # diferenca onde nao ha.
  _sem_bloco() {
    sed '/# php -- BEGIN cPanel-generated handler/,/# php -- END cPanel-generated handler/d' "$@" \
      | sed -e :a -e '/^[[:space:]]*$/{$d;N;ba' -e '}'
  }

  if [ -f "$_HT" ]; then
    # skip-worktree escondido impede o merge de atualizar o arquivo ("Entry not
    # uptodate"). Solta agora; remarca no fim.
    git update-index --no-skip-worktree "$_HT" 2>/dev/null || true

    if ! git diff --quiet -- "$_HT" 2>/dev/null; then
      _BLOCO_CPANEL="$(sed -n '/# php -- BEGIN cPanel-generated handler/,/# php -- END cPanel-generated handler/p' "$_HT")"

      if [ -n "$_BLOCO_CPANEL" ] \
         && diff -q <(git show "HEAD:$(git ls-files --full-name "$_HT")" | _sem_bloco) \
                    <(_sem_bloco "$_HT") >/dev/null 2>&1; then
        echo "    public/.htaccess: preservando o bloco do cPanel e aceitando a versao do repo"
        cp "$_HT" "$_HT.cpanel.bak"
        git checkout -- "$_HT"
      else
        echo
        echo "  ⚠️  public/.htaccess tem alteracao ALEM do bloco do cPanel."
        echo "     Isso o deploy nao decide sozinho. Veja:  git diff -- $_HT"
        echo
        exit 1
      fi
    fi
  fi

  # core.fileMode=false: o proprio deploy roda chmod em storage/ e bootstrap/cache,
  # e o git conta mudanca de PERMISSAO como modificacao. Sem isto o guard acusava
  # dez .gitignore e travava o deploy sozinho — falso positivo que impedia o
  # trabalho em vez de proteger.
  _sujos="$(git -c core.fileMode=false status --porcelain --untracked-files=no | awk '{print $2}')"
  if [ -n "$_sujos" ]; then
    echo
    echo "  ⚠️  ALTERACAO LOCAL neste servidor impede o git pull:"
    for _f in $_sujos; do echo "        $_f"; done
    echo
    echo "     Veja o que mudou:      git diff -- <arquivo>"
    echo "     Guarde uma copia:      cp <arquivo> ~/\$(basename <arquivo>).bak"
    echo "     Aceite a do repo:      git checkout -- <arquivo>"
    echo
    echo "     Se for public/.htaccess com o bloco do cPanel: descartar e o certo,"
    echo "     MAS confirme antes, no MultiPHP Manager, que o dominio esta em PHP 8.3+."
    echo "     A versao vem do painel, nao do .htaccess (AddHandler quebra PHP-FPM)."
    echo
    exit 1
  fi

  _before="$(git rev-parse HEAD 2>/dev/null || echo none)"
  git pull --ff-only
  _after="$(git rev-parse HEAD 2>/dev/null || echo none)"

  # Devolve o bloco do painel (o repo nao o traz, de proposito) e tira o arquivo
  # do radar do git neste clone.
  if [ -f "$_HT" ]; then
    if [ -n "$_BLOCO_CPANEL" ] && ! grep -q "cPanel-generated handler" "$_HT"; then
      printf '\n%s\n' "$_BLOCO_CPANEL" >> "$_HT"
      echo "    public/.htaccess: bloco do cPanel devolvido ($(printf '%s' "$_BLOCO_CPANEL" | grep -o 'ea-php[0-9]*' | head -1))"
      rm -f "$_HT.cpanel.bak"
    fi
    git update-index --skip-worktree "$_HT" 2>/dev/null || true
  fi
  if [ "$_before" != "$_after" ] && ! git diff --quiet "$_before" "$_after" -- deploy.sh 2>/dev/null; then
    echo "    deploy.sh foi atualizado no pull -> reexecutando a versão nova"
    DEPLOY_REEXEC=1 exec bash "$0" "$@"
  fi
fi

# A partir daqui trabalhamos DENTRO do app (git já foi puxado na raiz do repo).
cd "$APP"

# Lê o valor de uma chave do .env (tira aspas externas). Usada no backup do banco.
_env() { grep -E "^$1=" .env 2>/dev/null | head -n1 | cut -d= -f2- | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/" || true; }

# Instalação JÁ EXISTENTE que dependia do default 'Sistema' do config/app.php.
# Esse default foi REMOVIDO em 2026-07-30 (a chave está publicada no repo, então
# valer de fábrica deixava qualquer um gravar na base fiscal de um portal novo).
# Quem não tem a linha no .env passaria a recusar o agente com 403 — o cliente
# para de enviar em silêncio, e só o log do agente conta. Avisa e conserta aqui.
if [ -f .env ] && ! grep -qE '^SYSTEM_ACCESS_KEY=.' .env; then
  echo
  echo "  ⚠️  Este .env NÃO define SYSTEM_ACCESS_KEY."
  echo "     Até 2026-07-30 o valor 'Sistema' vinha por default do código; agora não vem mais."
  echo "     Sem a linha, o agente deste cliente passa a levar 403 e PARA DE ENVIAR."
  echo
  echo "     Mantendo o comportamento atual (chave legada), acrescentando ao .env:"
  printf 'SYSTEM_ACCESS_KEY=Sistema\n' >> .env
  grep -qE '^SYSTEM_LEGACY_KEY_ENABLED=' .env || printf 'SYSTEM_LEGACY_KEY_ENABLED=true\n' >> .env
  echo "       SYSTEM_ACCESS_KEY=Sistema"
  echo
  echo "     Quando migrar este cliente para chave por instalação (token gerado no"
  echo "     painel, colado no Key= do .ini), troque para SYSTEM_LEGACY_KEY_ENABLED=false."
  echo
fi

# ── 2) .env (criado ANTES do composer: scripts pós-install bootam a app) ──────
if [ ! -f .env ]; then
  echo "==> [2/6] Instalação NOVA: criando .env limpo a partir do .env.example"
  # Auto-cura: sem .env, garante que não há lock preso (o clone não deveria
  # trazê-lo — é gitignored — mas se vier, o passo [6] migraria em vez de mandar
  # pro /install).
  rm -f storage/installed
  cp .env.example .env
  # Produção já na 1a instalação; sessão/cache em ARQUIVO para o /install abrir
  # SEM banco configurado (o wizard é quem grava as credenciais depois).
  sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
  sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
  sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' .env
  sed -i 's/^CACHE_DRIVER=.*/CACHE_DRIVER=file/' .env
  sed -i 's/^CACHE_STORE=.*/CACHE_STORE=file/' .env
  sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/' .env
  if [ -n "$APP_URL_ARG" ]; then
    sed -i "s#^APP_URL=.*#APP_URL=${APP_URL_ARG}#" .env
    echo "    APP_URL=${APP_URL_ARG}"
  else
    echo "    (!) APP_URL não informado. Edite no .env depois, ou rode: bash deploy.sh https://seu.dominio"
  fi
else
  echo "==> [2/6] Instalação EXISTENTE: .env preservado (não será tocado)"
fi

# ── 3) Dependências PHP (produção) ───────────────────────────────────────────
echo "==> [3/6] composer install (--no-dev --optimize-autoloader)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ── 4) APP_KEY (só se vazia/ausente) ─────────────────────────────────────────
# O valor pode vir COM ASPAS e/ou espaços — `APP_KEY="base64:..."` é válido e o
# dotenv do Laravel remove as aspas. O grep antigo (^APP_KEY=base64:) não via a
# aspa e disparava o key:generate à toa em instalação sadia; o key:generate então
# falhava com "No APP_KEY variable was found" (a regex dele também não casa com a
# aspa) — erro assustador e inofensivo, visto em produção 2026-07-15.
# Aceita: APP_KEY=base64:x | APP_KEY="base64:x" | APP_KEY='base64:x' | com espaços.
if ! grep -qE '^[[:space:]]*APP_KEY[[:space:]]*=[[:space:]]*["'"'"']?base64:' .env; then
  echo "==> [4/6] Gerando APP_KEY"
  php artisan key:generate --force
fi

# ── 5) Permissões + symlink ──────────────────────────────────────────────────
echo "==> [5/6] permissões + storage:link"
# u+rwX,g+rwX (X MAIUSCULO): execucao so em DIRETORIO. O `-R 775` punha +x em
# todo arquivo, inclusive nos .gitignore versionados — e o git enxerga o bit de
# execucao, entao eles apareciam como modificados a cada deploy.
chmod -R u+rwX,g+rwX storage bootstrap/cache

# `others` FORA do storage. Isto só mexia em u e g, e o Laravel não fecha sozinho:
# FileSessionHandler grava com file_put_contents sem modo, ou seja 0666 & ~umask
# = 0644. Medido em 2026-07-30: storage/framework/sessions/ listável e os arquivos
# de sessão do contador legíveis, mais 1,1 GB de XML fiscal em 664 e o
# laravel.log. Em hospedagem COMPARTILHADA isso é outra conta do mesmo servidor
# lendo sessão (sequestro sem tocar na rede) e o acervo fiscal inteiro.
chmod -R o-rwx storage bootstrap/cache

# Exceção: storage/app/public É publicado na web pelo storage:link, e em cPanel
# quem serve o arquivo estático é o Apache (nobody), não o PHP do dono. Sem isto,
# tudo que o storage:link publica passa a dar 403.
#   o+x (sem r) nos pais: permite ATRAVESSAR até lá, não LISTAR o storage.
chmod o+x storage storage/app 2>/dev/null || true
[ -d storage/app/public ] && chmod -R o+rX storage/app/public || true

[ -e public/storage ] || php artisan storage:link

# O wizard web escreve o .env e cria o lock storage/installed — precisa ser
# gravável pelo usuário do PHP (no cPanel/suPHP roda como o dono dos arquivos).
# 660, não 664: o 4 final deixava QUALQUER conta do servidor ler DB_PASSWORD,
# SYSTEM_ACCESS_KEY e a APP_KEY — que decifra e assina o cookie de sessão. O bit
# de GRUPO fica porque nem toda hospedagem roda o PHP como o dono do arquivo;
# 600 é mais apertado e vale onde se sabe que roda (o risco real é `others`,
# que é onde moram as outras contas).
[ -f .env ] && chmod 660 .env || true

# Cookie de sessão só por HTTPS. `SESSION_SECURE_COOKIE=true` entrou no
# .env.example em 2026-07-15, e instalação EXISTENTE tem .env próprio, que o
# deploy não toca — ou seja, quem instalou antes disso nunca vai receber a linha,
# e o cookie continua viajando em claro se alguém abrir o portal por http://.
# Só liga quando o APP_URL do PRÓPRIO cliente já é https: num portal sem TLS,
# `true` derruba o login (o navegador nunca devolve o cookie).
if [ -f .env ] && ! grep -qE '^[[:space:]]*SESSION_SECURE_COOKIE=' .env; then
  if grep -qiE '^[[:space:]]*APP_URL[[:space:]]*=[[:space:]]*["'"'"']?https://' .env; then
    printf 'SESSION_SECURE_COOKIE=true\n' >> .env
    echo "    + SESSION_SECURE_COOKIE=true (APP_URL é https)"
  else
    echo "    (!) SESSION_SECURE_COOKIE ausente e APP_URL não é https — cookie de sessão"
    echo "        trafega em claro. Ao publicar o portal em HTTPS, acrescente a linha."
  fi
fi

# SMTP. O portal manda o e-mail de "Esqueci minha senha" NA HORA do clique
# (QUEUE_CONNECTION=sync, notificação sem ShouldQueue) — SMTP errado significa
# que o cliente simplesmente não consegue mais entrar, e ninguém descobre até
# alguém esquecer a senha. O deploy não preenche isto sozinho (é a caixa de
# e-mail DAQUELE cliente), mas não deixa passar em silêncio.
if [ -f .env ]; then
  _mh="$(_env MAIL_HOST)"
  case "$_mh" in
    ''|null)
      echo
      echo "  ⚠️  MAIL_HOST vazio no .env — \"Esqueci minha senha\" NÃO vai funcionar."
      echo "     Preencha MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION e MAIL_FROM_ADDRESS"
      echo "     com os dados da caixa de e-mail deste cliente (painel da hospedagem ->"
      echo "     Contas de E-mail -> Configurar cliente de e-mail, servidor de SAÍDA)."
      echo "     Depois: php artisan config:clear"
      echo
      ;;
    mailhog|localhost|127.0.0.1)
      echo
      echo "  ⚠️  MAIL_HOST=$_mh — isso é o servidor de e-mail de DESENVOLVIMENTO."
      echo "     Neste servidor ele não existe: \"Esqueci minha senha\" vai falhar."
      echo "     Troque pelo SMTP real do cliente e rode: php artisan config:clear"
      echo
      ;;
  esac
  case "$(_env MAIL_FROM_ADDRESS)" in
    ''|null)
      echo "  ⚠️  MAIL_FROM_ADDRESS vazio — preencha com um endereço DO DOMÍNIO do cliente"
      echo "     (endereço de outro domínio é recusado ou cai em spam, por SPF)."
      ;;
  esac
fi

# ── APP_ENV ──────────────────────────────────────────────────────────────────
# `.env` de cliente antigo costuma ter APP_ENV=local (era o default do Laravel 8
# e ninguém trocou). Só corrige quando o APP_URL daquele portal já é https — é a
# mesma evidência usada para o cookie seguro, e evita mexer numa cópia de teste
# que alguém mantém de propósito em `local`.
if [ -f .env ]; then
  _ae="$(_env APP_ENV)"
  if [ "$_ae" != "production" ] && grep -qiE '^[[:space:]]*APP_URL[[:space:]]*=[[:space:]]*["'"'"']?https://' .env; then
    sed -i 's/^[[:space:]]*APP_ENV[[:space:]]*=.*/APP_ENV=production/' .env
    echo "    + APP_ENV: ${_ae:-<vazio>} -> production (o APP_URL deste portal e https)"
  fi
fi

# ── LOG_LEVEL ────────────────────────────────────────────────────────────────
# `debug` grava TUDO, inclusive `Log::info(... 'chave' => $chave)` — chave de
# acesso de 44 digitos, que embute o CNPJ do emitente, em arquivo de texto. Era
# o default do Laravel 8, então todo `.env` antigo está assim.
if [ -f .env ]; then
  _ll="$(_env LOG_LEVEL)"
  case "$_ll" in
    debug)
      sed -i 's/^[[:space:]]*LOG_LEVEL[[:space:]]*=.*/LOG_LEVEL=error/' .env
      echo "    + LOG_LEVEL: debug -> error (o 'debug' gravava chave de acesso em texto plano)"
      ;;
    info|notice)
      # Não troca sozinho: pode ser diagnóstico em andamento. Mas avisa, porque
      # `info` também alcança as linhas que carregam chave.
      echo "    (!) LOG_LEVEL=${_ll} — este nivel ainda grava chave de acesso no log. Use 'error'."
      ;;
  esac
fi

# ── Rotação de log ───────────────────────────────────────────────────────────
# Caso real de 14/07/2026: cliente esgotou 100 GB de hospedagem em 6 meses. A
# pasta `docs` tinha 55 MB e o banco 4,8 MB — o espaço todo estava em UM
# `storage/logs/laravel.log`, que nunca é apagado.
#
# `LOG_CHANNEL=stack` cai no canal `single`: um arquivo só, para sempre. O
# `daily` corta por dia e apaga os antigos (LOG_DAILY_DAYS, 30 por padrão) —
# mesmo conteúdo, tamanho limitado. Importa também porque o log carrega chave de
# acesso de 44 dígitos, que embute o CNPJ do emitente: arquivo eterno é dado
# fiscal acumulado sem prazo.
if [ -f .env ]; then
  _lc="$(_env LOG_CHANNEL)"
  case "$_lc" in
    daily) : ;;
    ''|null)
      printf 'LOG_CHANNEL=daily\n' >> .env
      echo "    + LOG_CHANNEL=daily (sem a linha, o padrão era 'stack' -> arquivo único sem limite)"
      ;;
    stack|single)
      # O bloco anterior só agia quando a chave estava AUSENTE — e pulava
      # justamente quem tinha o problema, que é ter a linha com o valor antigo.
      sed -i 's/^[[:space:]]*LOG_CHANNEL[[:space:]]*=.*/LOG_CHANNEL=daily/' .env
      echo "    + LOG_CHANNEL: ${_lc} -> daily (o '${_lc}' escrevia num arquivo único, sem limite)"
      ;;
    *)
      echo "    (i) LOG_CHANNEL=${_lc} (personalizado — não foi alterado)"
      ;;
  esac
  grep -qE '^[[:space:]]*LOG_DAILY_DAYS=' .env || {
    printf 'LOG_DAILY_DAYS=30\n' >> .env
    echo "    + LOG_DAILY_DAYS=30"
  }
fi

# O `laravel.log` que já existe vira ÓRFÃO ao trocar para `daily`: o novo canal
# grava em `laravel-AAAA-MM-DD.log` e ninguém mais escreve nem rotaciona o
# antigo. Num cliente com dezenas de GB acumulados, trocar o canal sozinho não
# devolve UM BYTE — o arquivo fica lá, parado, ocupando o disco.
#
# Por isso o deploy encolhe: guarda as últimas 2000 linhas (o que serve para
# diagnóstico) e descarta o resto. `cat > arquivo` trunca NO LUGAR, preservando
# dono e permissão — `mv` criaria um arquivo novo com o dono de quem rodou.
# Escape: SKIP_LOG_TRIM=1.
_log="storage/logs/laravel.log"
if [ "${SKIP_LOG_TRIM:-0}" != "1" ] && [ -f "$_log" ]; then
  _kb="$(du -k "$_log" 2>/dev/null | cut -f1)"
  if [ "${_kb:-0}" -gt 51200 ]; then     # 50 MB — log saudável tem poucos MB
    _antes="$(du -h "$_log" 2>/dev/null | cut -f1)"
    if tail -n 2000 "$_log" > "$_log.tmp" 2>/dev/null && cat "$_log.tmp" > "$_log"; then
      rm -f "$_log.tmp"
      echo "    + laravel.log encolhido: ${_antes} -> $(du -h "$_log" 2>/dev/null | cut -f1) (últimas 2000 linhas mantidas)"
    else
      rm -f "$_log.tmp"
      echo "    (!) AVISO: não consegui encolher o laravel.log (${_antes}). Comando:"
      echo "        tail -n 2000 $APP/$_log > /tmp/l && cat /tmp/l > $APP/$_log && rm /tmp/l"
    fi
  fi
fi

# ── 6) Migrations + caches (só se JÁ instalado) ──────────────────────────────
if [ -f storage/installed ]; then
  echo "==> [6/6] Instância instalada: backup do banco + migrations + cache de produção"
  php artisan optimize:clear || true

  # Backup AUTOMÁTICO do banco ANTES do migrate. Só roda aqui (instância já
  # instalada) — na 1a instalação não há banco para salvar. Cada deploy gera um
  # arquivo novo em backup_banco_automatico/ (dentro do projeto, ACIMA do public/
  # — não acessível pela web), com data+hora no nome (não sobrescreve). É a rede
  # de segurança para rollback se uma migration nova falhar. MYSQL_PWD evita
  # expor a senha no `ps`. SKIP_DB_BACKUP=1 pula (não recomendado).
  if [ "${SKIP_DB_BACKUP:-0}" != "1" ] && command -v mysqldump >/dev/null 2>&1; then
    if [ "$(_env DB_CONNECTION)" = "mysql" ] || [ -z "$(_env DB_CONNECTION)" ]; then
      mkdir -p backup_banco_automatico && chmod 700 backup_banco_automatico || true
      _bk="backup_banco_automatico/backup_banco_$(date +%Y-%m-%d_%H%M%S).sql.gz"
      # Nasce 600. O `> "$_bk"` abaixo cria com o umask padrao (644) e o chmod so
      # roda DEPOIS do dump terminar — com um banco de dezenas de MB isso e uma
      # janela de minutos com o dump fiscal legivel. Truncar preserva o modo,
      # entao criar antes resolve. (A pasta ja e 700, o que na pratica bloqueia;
      # isto fecha o caso de o chmod da pasta ter falhado — ele tem `|| true`.)
      : > "$_bk" && chmod 600 "$_bk" || true
      _h="$(_env DB_HOST)"; _p="$(_env DB_PORT)"; _u="$(_env DB_USERNAME)"; _d="$(_env DB_DATABASE)"
      if MYSQL_PWD="$(_env DB_PASSWORD)" mysqldump --single-transaction --quick --no-tablespaces \
           -h "${_h:-127.0.0.1}" -P "${_p:-3306}" -u "$_u" "$_d" 2>/dev/null | gzip > "$_bk"; then
        chmod 600 "$_bk" || true
        echo "    backup do banco OK -> $APP/$_bk  ($(du -h "$_bk" 2>/dev/null | cut -f1))"
        # Retenção: mantém os backups dos últimos 90 dias; apaga os mais antigos.
        _antigos="$(find backup_banco_automatico -maxdepth 1 -name 'backup_banco_*.sql.gz' -type f -mtime +90 2>/dev/null | wc -l)"
        if [ "${_antigos:-0}" -gt 0 ]; then
          find backup_banco_automatico -maxdepth 1 -name 'backup_banco_*.sql.gz' -type f -mtime +90 -delete 2>/dev/null || true
          echo "    retenção: removidos ${_antigos} backup(s) com mais de 90 dias."
        fi
      else
        rm -f "$_bk"
        echo "    (!) AVISO: backup do banco falhou (host/creds/privilégio?) — seguindo SEM backup."
      fi
    fi
  else
    echo "    (!) AVISO: mysqldump ausente ou SKIP_DB_BACKUP=1 — migrate SEM backup."
  fi

  php artisan migrate --force
  php artisan config:cache
  # Sem `|| echo AVISO`: desde 2026-07-30 não há Closure em rota, então isto
  # PRECISA passar. Engolir a falha treinava a ignorar o aviso — e o portal
  # rodava sem cache de rotas em todo deploy, sem ninguém notar.
  php artisan route:cache
  php artisan view:cache 2>/dev/null || echo "    AVISO: view:cache pulado (inofensivo)."
  echo
  echo "✅ Deploy concluído — código + deps + migrations aplicados."
else
  php artisan optimize:clear || true
  _url="$(_env APP_URL)"
  echo
  echo "============================================================"
  echo " ✅ Dependências instaladas. Instalação NOVA — próximos passos:"
  echo "   1) Aponte o Document Root do domínio para:"
  echo "        $(pwd)/public"
  echo "      e em cPanel -> MultiPHP Manager selecione ea-php83 p/ o domínio"
  echo "      (NÃO use AddHandler no .htaccess: quebra domínios em PHP-FPM)."
  echo "   2) Crie um banco MySQL vazio no painel (banco + usuário)."
  echo "   3) Acesse ${_url:-o site} -> cai no /install (banco + empresa + admin)."
  echo "============================================================"
fi
