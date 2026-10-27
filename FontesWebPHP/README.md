# Portal do Contador — instalação e atualização

Sistema de gestão de documentos fiscais (NF-e / NFC-e / NFS-e / CT-e / MDF-e /
eventos / inutilizações). **Laravel 11 + Livewire 3 + MySQL**, com DANFE via
DomPDF (`barryvdh/laravel-dompdf` + `nfephp-org/sped-da`). Quem alimenta o portal
é o **agente Delphi** instalado no cliente: ele lê os XMLs nas pastas do emissor
e envia para a API (`/api/docs/*`).

Este arquivo é o passo a passo de **instalação**, **atualização** e **migração**
em hospedagem compartilhada (cPanel e afins).

> O deploy é **sem Docker no servidor**. O `docker-compose` do projeto é só o
> ambiente de desenvolvimento local (última seção).
>
> ⚠️ O app Laravel fica na subpasta **`FontesWebPHP/`** do repositório. O
> `deploy.sh` detecta isso sozinho; o **Document Root** do domínio aponta para
> `.../FontesWebPHP/public`.

---

## Requisitos do servidor

| | |
|---|---|
| **PHP** | **8.2 ou mais novo** (8.3 recomendado) |
| **Extensões** | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `gd`, `bcmath`, `curl`, `zip`, `intl`, `exif` — e `soap` **só se você for rodar o `composer install` no servidor** (veja abaixo) |
| **Banco** | MySQL 8 — um banco vazio por cliente |
| **Outros** | Composer 2 e git — **se** você tiver terminal (veja o Caminho B se não tiver) |

### A extensão `soap` — quando ela importa (e quando não)

`nfephp-org/sped-common` declara `ext-soap` no `composer.json`, então **o
`composer install` rodado no servidor aborta sem ela**. Mas o portal **não usa
SOAP em execução**:

- o pacote que o portal usa é o `sped-da`, que gera o DANFE em PDF;
- `grep -rn Soap vendor/nfephp-org/sped-da/` → **nada**;
- gerando um DANFE de verdade, **zero** classes SOAP são carregadas.

O SOAP do `sped-common` serve para **falar com a SEFAZ** — quem faz isso é o
emissor do cliente, não o portal. O portal só recebe o XML já autorizado.

**Na prática:**

| situação | precisa de `soap`? |
|---|---|
| Caminho A (`composer install` no servidor) | **Sim** — senão o composer aborta |
| Caminho B (`vendor/` montado na sua máquina) | **Não** — nem precisa procurar no painel |

**Onde ativar, se precisar:**

| painel | caminho |
|---|---|
| cPanel | *Software* → **Select PHP Version** → aba **Extensions** → marque `soap` |
| cPanel (novo) | *Software* → **MultiPHP INI Editor** → não tem lá; use o Select PHP Version |
| Hostinger (hPanel) | *Avançado* → **Configuração PHP** → aba **Extensões PHP** → `soap` |
| WHM (servidor próprio) | EasyApache 4 → `ea-php83-php-soap` |

> **Não achou a opção?** Vários planos compartilhados simplesmente não oferecem.
> Use o **Caminho B**: rode o `composer install` no seu computador e envie a
> pasta `vendor/` pronta. O portal funciona igual.

**Não** usa Node/npm: os assets já vêm prontos em `public/assets/`. O PDF é
DomPDF, então também não precisa de Chromium.

### Se algo faltar, o portal avisa

Você **não** vai receber um "500 em branco". Quando o ambiente está incompleto, o
portal responde com uma página listando o que falta e o que fazer — PHP velho,
`vendor/` ausente, extensão faltando, `.env` ausente, `storage/` sem escrita. Ela
some sozinha quando estiver tudo certo.

---

## Escolha o seu caminho

| | quando usar |
|---|---|
| **[A — com terminal](#caminho-a--servidor-com-terminal)** | você consegue rodar comandos por SSH |
| **[B — sem terminal](#caminho-b--servidor-sem-terminal)** | só tem FTP e gerenciador de arquivos |
| **[C — migração](#caminho-c--cliente-que-já-tem-o-portal-antigo)** | o cliente já usa uma versão anterior |

---

## Caminho A — servidor com terminal

### 1. Colocar o código na pasta do domínio

```bash
cd ~/
git clone <url-do-repo> portal
```

### 2. Apontar o Document Root para `public/`

cPanel → **Domains** → o domínio → *Document Root* →
`/home/USUARIO/portal/FontesWebPHP/public`

> Este é o erro nº 1 de instalação. Apontando para a raiz do projeto, o site
> lista arquivos ou serve código em vez do app.

### 3. Definir o PHP do domínio

cPanel → **MultiPHP Manager** → escolha **8.2+** para o domínio.

> ⚠️ Não use `AddHandler` no `.htaccess`. Em servidor com PHP-FPM isso **derruba
> o domínio**. Esse bloco é gerenciado pelo `deploy.sh`.

### 4. Criar um banco MySQL vazio

cPanel → **MySQL Databases**: crie o banco, o usuário, e dê **todos os
privilégios** do usuário ao banco. **Não importe nada** — as tabelas são criadas
no passo 6. Anote host (`localhost`), banco, usuário e senha.

### 5. Rodar o deploy

```bash
cd portal/FontesWebPHP
bash deploy.sh https://cliente.seudominio.com.br
```

Cria o `.env`, instala as dependências, gera a `APP_KEY` e ajusta as permissões.
**Não** roda migrations — isso é o passo 6.

### 6. Concluir em `/install`

Acesse o domínio: cai no **`/install`**. Preencha banco, URL e o
**administrador** (nome, e-mail, senha).

Ao finalizar, ele testa a conexão, grava o `.env`, roda as **migrations**, cria o
admin e tranca o instalador.

> **Não existe usuário nem senha padrão.** Quem instala escolhe os dois aqui. Se
> alguém pedir "a senha do portal", a resposta é essa: não há uma.

### 7. Forçar HTTPS

cPanel → **Force HTTPS Redirect** no domínio.

### 8. Gerar a chave do agente

No painel: **Usuários** → o usuário daquele cliente → **Chave de acesso do
agente** → dê um nome que identifique a instalação ("PC da recepção") e gere.

Copie o valor e cole no agente, no campo **"Chave do Contador"**, e clique em
**Gravar Config.** Enquanto a chave estiver vazia o agente **não envia nada** — e
diz isso na tela.

---

## Caminho B — servidor sem terminal

Funciona: o que o `deploy.sh` faria, você faz na sua máquina e sobe pronto.

### 1. Na SUA máquina

```bash
cd FontesWebPHP
composer install --no-dev --optimize-autoloader
```

Isso cria a pasta `vendor/`, que **não vem no download do código**. É só PHP —
funciona igual no servidor.

### 2. Preparar o `.env`

Copie `.env.example` para `.env` e preencha `APP_URL` e os dados do banco.
**Deixe a `APP_KEY` vazia** — o portal gera sozinho na primeira visita.

### 3. Subir

Zipe o projeto (**com** a pasta `vendor/` e **com** o `.env`) e envie por FTP ou
pelo gerenciador de arquivos. Descompacte na pasta do domínio.

### 4. Ajustar no painel

- **Document Root** → `.../FontesWebPHP/public`
- **PHP 8.2+** e as extensões (**não** precisa da `soap` neste caminho)
- Permissão de escrita em `storage/`, `bootstrap/cache` e no **`.env`**

### 5. Acessar o domínio

Cai no `/install`. Daí em diante é igual ao Caminho A, passo 6.

> Se aparecer a página "O portal ainda não está pronto para abrir", ela diz
> exatamente o que falta. Corrija e atualize a página.

---

## Caminho C — cliente que já tem o portal antigo

O banco e os XMLs dele **continuam valendo**. As migrations são idempotentes:
aplicam só o que falta, sem tocar nos dados.

Testado sobre um banco de produção do Laravel 8 com 1653 notas: 11 migrations
aplicadas, 10 tabelas → 13, **nenhum dado perdido**. E o **login antigo continua
valendo**, porque os usuários são os dele.

### Mesmo servidor (o caso comum)

1. Faça backup (o `deploy.sh` já faz o do banco automaticamente)
2. Coloque o código novo por cima
3. **Mantenha o `.env` E o `storage/` dele** — o deploy não toca em nenhum dos
   dois. O `.env` é gitignored; o `storage/app/docs` é onde estão todos os XMLs
   que ele já recebeu, e os caminhos continuam sendo montados pelo mesmo código.
4. `cd FontesWebPHP && bash deploy.sh`

> Na prática você sobrescreve **só o código**. `.env`, `storage/` e o banco são
> do cliente e ficam onde estão.

### Servidor novo

1. Dump do banco por **phpMyAdmin** ou **Backup Wizard** do cPanel
2. `storage/app/docs` por FTP
3. Código novo + `.env` apontando para o banco restaurado
4. `bash deploy.sh`

> Não tente importar o dump pelo `/install`. Um arquivo desses esbarra em
> `upload_max_filesize` e `max_execution_time` justamente na hospedagem fraca,
> e as ferramentas do painel já fazem isso melhor.

---

## O que o `deploy.sh` conserta sozinho

`.env` de instalação antiga fica defasado. O deploy detecta e corrige, dizendo na
tela o que mudou:

| ponto | o que acontecia sem o conserto |
|---|---|
| `SYSTEM_ACCESS_KEY` ausente | 🔴 o agente leva 403 e **para de enviar em silêncio** |
| `LOG_CHANNEL=stack` | 🔴 um `laravel.log` que cresce **para sempre** — já esgotou 100 GB de hospedagem |
| `LOG_LEVEL=debug` | grava chave de acesso de 44 dígitos (que embute o CNPJ do emitente) em texto plano |
| `APP_ENV=local` | ambiente errado (era o default do Laravel 8) |
| `SESSION_SECURE_COOKIE` ausente | cookie de sessão trafega em HTTP |
| `LOG_DAILY_DAYS` ausente | retenção do log |
| `laravel.log` gigante | encolhe acima de 50 MB, guardando as últimas 2000 linhas |
| bloco do cPanel no `public/.htaccess` | conflito em todo `git pull` |

> `APP_ENV` e `SESSION_SECURE_COOKIE` só mudam quando o `APP_URL` daquele portal
> **já é https** — assim uma cópia de teste em `local`/http não é alterada. Canal
> de log personalizado (`papertrail` etc.) não é atropelado.

## O que você precisa ajustar na mão

Só um — e o deploy avisa quando falta.

**`MAIL_*` — o SMTP do cliente.** Impossível adivinhar: é a caixa de e-mail dele.
Sem isso, **"Esqueci minha senha" não funciona** (o portal manda o e-mail na hora
do clique, sem fila).

```bash
MAIL_MAILER=smtp
MAIL_HOST=mail.dominiodocliente.com.br   # painel da hospedagem -> Contas de E-mail
MAIL_PORT=587
MAIL_USERNAME=nao-responda@dominiodocliente.com.br
MAIL_PASSWORD=a-senha-da-caixa
MAIL_ENCRYPTION=tls                       # porta 465 -> ssl
MAIL_FROM_ADDRESS=nao-responda@dominiodocliente.com.br
MAIL_FROM_NAME="Portal do Contador"
```

Depois: `php artisan config:clear`

> ⚠️ **`MAIL_FROM_ADDRESS` tem de ser do domínio do cliente.** Endereço de outro
> domínio é recusado ou cai em spam (SPF), porque quem envia é o SMTP dele.
>
> ⚠️ **`APP_URL` precisa estar correto**: o link do e-mail de recuperação sai
> dele, não do endereço que o navegador mandou.

Testar sem esperar cliente reclamar:

```bash
php artisan tinker --execute='Illuminate\Support\Facades\Password::sendResetLink(["email"=>"seu@email.com"]);'
```

Sem exceção = SMTP OK.

---

## Atualizar uma instância no ar

```bash
cd ~/portal/FontesWebPHP
bash deploy.sh
```

Faz `git pull`, **backup do banco**, `composer install`, `migrate` e limpa os
caches. Um portal atrasado pode ter várias migrations pendentes — rodam em ordem.

> **Atualização = `deploy.sh` na MESMA pasta.** Nunca reclone para atualizar: um
> clone novo vem sem `.env` nem `storage/installed`, e o deploy trata como
> instalação nova.

**No agente:** troque só o `PortalContador.exe`. **Não** substitua o
`banco.sqlite` do cliente — ele guarda o controle do que já foi enviado, e um
arquivo vazio no lugar faria milhares de XMLs subirem de novo. O `.ini` é
preservado e se atualiza sozinho no que dá.

---

## Não consigo entrar / não sei a senha

Acontece com quem **importou o dump** em vez de usar o wizard: o banco já vem com
usuários cujas senhas ninguém conhece, e o `/install` se considera concluído (é a
proteção que impede o wizard de ficar aberto na internet com o banco cheio).

```bash
php artisan portal:admin --email=voce@dominio.com.br --name="Seu Nome" --generate
```

Cria o administrador — ou, se o e-mail já existir, **redefine a senha e promove a
admin** — e imprime a senha gerada uma vez.

**Sem SSH?** Rode pelo **Cron Jobs** do cPanel, uma vez, e depois apague o
agendamento:

```
cd /home/USUARIO/portal/FontesWebPHP && /usr/local/bin/ea-php83 artisan portal:admin --email=voce@dominio.com.br --name="Seu Nome" --generate >> /home/USUARIO/senha.txt 2>&1
```

A senha sai no `senha.txt`. **Apague o arquivo depois de anotar.**

> ⚠️ **Nunca gere um hash em site de terceiros para colar na coluna `password`.**
> Circulou como dica e funciona por acaso, mas manda a senha do seu portal para
> um site que você não controla, quebra se o algoritmo do app mudar, e não
> promove a admin — a pessoa entra e não consegue administrar nada.

---

## Se aparecer erro

### Página "O portal ainda não está pronto para abrir"

É o diagnóstico do próprio portal, no lugar do 500 em branco. Ele lista o que
falta:

| o que ele diz | onde resolver |
|---|---|
| PHP abaixo de 8.2 | cPanel → MultiPHP Manager |
| `vendor` não existe | `composer install`, ou envie a pasta por FTP (Caminho B) |
| faltam extensões | cPanel → Select PHP Version → Extensions |
| falta a `soap` | só aparece se o `vendor/` **não** existir — monte o `vendor/` na sua máquina (Caminho B) e o aviso some |
| `.env` não existe | copie o `.env.example` e preencha |
| pasta sem escrita | `chmod -R u+rwX,g+rwX storage bootstrap/cache` |
| não consegui gravar a `APP_KEY` | dê escrita ao `.env` (`chmod 660 .env`) |

### Outros sintomas

| sintoma | causa | fix |
|---|---|---|
| Site mostra **"Index of /"** ou código-fonte | Document Root na raiz do projeto | aponte para `.../FontesWebPHP/public` |
| **404** em `index.php` que existe | `AddHandler` de PHP quebrando o PHP-FPM | tire o `AddHandler`; defina a versão no MultiPHP Manager |
| **400 Bad Request** | host real ≠ `APP_URL` | ajuste o `APP_URL` e `php artisan optimize:clear` |
| Disco da hospedagem lotado | `laravel.log` sem rotação | rode o `deploy.sh` novo — ele corrige e encolhe o arquivo |
| Agente não envia nada | chave vazia, URL incompleta, ou empresa não vinculada ao dono da chave | o log do agente e a tela dele dizem qual dos três |
| "Esqueci minha senha" dá erro | SMTP não configurado | seção "O que você precisa ajustar na mão" |
| `git pull` aborta com *"local changes"* | editaram um arquivo versionado no servidor | `git checkout -- <arquivo>` e rode o deploy |
| `/install` vai direto pro login | já existe usuário no banco | é o esperado; use `portal:admin` |

Log do app: `storage/logs/laravel-AAAA-MM-DD.log`.

### Diagnóstico completo do servidor

```bash
bash diagnostico-seguranca.sh
```

Somente leitura, não altera nada, e **mascara senhas**. Reporta versão de PHP,
permissões, estado do log, chaves do agente em uso e o que está exposto na web.

---

## Regras de ouro

1. **Nunca** troque `SYSTEM_ACCESS_KEY` sem alinhar o `.ini` dos clientes — os
   uploads param na hora.
2. **Nunca** substitua o `banco.sqlite` de um cliente ao atualizar o agente.
3. **Não** versione `.env`, dump de banco ou qualquer arquivo com credencial. A
   `APP_KEY` assina e decifra o cookie de sessão: quem a tiver forja login sem
   saber senha nenhuma.
4. **Não** use `AddHandler` no `.htaccess` — o `deploy.sh` cuida disso.
5. **No servidor, não edite arquivo versionado à mão.** O único que você edita
   direto lá é o **`.env`** (é gitignored).
6. Faça o backup **antes** do deploy (o script já faz o do banco).

---

## Desenvolvimento local (Docker)

```bash
cd FontesWebPHP
docker compose build lojadev
docker compose up -d
```

- Portal: http://localhost:8080 — **`admin@gmail.com` / `password`**
- MailHog (e-mails de teste): http://localhost:8025
- MySQL: `localhost:3307` (`portal`/`portal`)

Criar as tabelas:

```bash
docker compose exec -u www-data lojadev php artisan migrate
```

Isso basta para o portal subir e para **produção** — as migrations criam o schema
completo.

> 📦 **Dump de exemplo (só para desenvolvimento).** O dump com dados já não vem
> no repositório: ele é de um cliente real (CNPJ, razão social e 1.653 notas), e
> o repositório é compartilhado. Ele fica **fora do Git**, na pasta `sql/` da
> raiz do projeto. Quem tiver o arquivo carrega assim:
>
> ```bash
> docker compose exec -T db mysql -uroot -proot -e "DROP DATABASE IF EXISTS portal_lojadev; CREATE DATABASE portal_lojadev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
> cat "../sql/Script Banco Portal_Contador.sql" | docker compose exec -T db mysql -uroot -proot portal_lojadev
> ```
>
> ⚠️ **Sem ele, 18 dos 87 arquivos de teste falham** — eles procuram o
> `admin@gmail.com`, que vem do dump. Os outros 70 criam o próprio dado e passam
> num banco recém-migrado.

Testes:

```bash
docker compose exec -u www-data lojadev php artisan test
```

> 🚨 **Sempre com `-u www-data`.** Rodar artisan como root deixa arquivos em
> `storage/` com dono errado e o painel passa a dar 500. Já derrubou o ambiente
> duas vezes. Se cair:
> `docker compose exec lojadev chown -R www-data:www-data storage bootstrap/cache`

> **O contrato com o agente Delphi é o que não pode quebrar:** ele manda `key` +
> o XML no campo `file`, e decide o sucesso **só pelo JSON `{"msg":"100"}`** — o
> status HTTP é ignorado. Resposta diferente disso faz o agente re-tentar o mesmo
> arquivo a cada 30 s, para sempre. Antes de mexer em `DocsController`,
> `EventsController`, `CheckSystem` ou `routes/api.php`, rode
> `php artisan test --filter=DelphiContractTest`, que trava esse contrato.
