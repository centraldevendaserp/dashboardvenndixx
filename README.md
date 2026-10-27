# Portal do Contador — Repositório do produto

Sistema de **gestão de documentos fiscais** (NF-e / NFC-e / NFS-e / CT-e / MDF-e /
eventos / inutilizações). Este repositório reúne as **três partes** do produto.

## Como as três partes se conectam

```
  [ máquina do cliente / contabilidade ]                 [ servidor / hospedagem ]
 ┌────────────────────────────────────┐                 ┌──────────────────────┐
 │  Agente Desktop (Windows)          │   HTTPS POST    │  Portal Web          │
 │  lê os XMLs nas pastas do cliente  │ ──────────────► │  (Laravel 11)        │
 │  e envia para a API, a cada 30 s   │  key=<token>    │  recebe, guarda      │
 │                                    │  + arquivo XML  │  e exibe os docs     │
 └────────────────────────────────────┘                 └──────────────────────┘
            ▲                                                      ▲
            │ é compilado a partir de                              │ o contador
     ┌──────────────┐                                              │ acessa aqui
     │ FontesDelphi │  (código-fonte do agente)
     └──────────────┘
```

O **agente** roda em segundo plano no computador do cliente, varre as pastas onde
o ERP salva os XMLs e envia para a API do portal. O **portal** recebe, guarda e
exibe para o contador.

Além dos XMLs, o agente também lê o **banco do ERP (Firebird)** para trazer o que
não existe em arquivo — nota rejeitada, por exemplo, não gera XML.

## As pastas deste repositório

| Pasta | O que é | Papel |
|---|---|---|
| **`FontesWebPHP/`** | O **portal web** — Laravel 11 + Livewire 3. Inclui a infra: `deploy.sh` e `docker/`. | A fonte da verdade da aplicação. **Toda mudança de código nasce aqui.** Tem o próprio [`README.md`](FontesWebPHP/README.md) com instalação, atualização e migração. |
| **`FontesDelphi/`** | Código-fonte **Delphi 12** (`.pas`, `.dfm`, `.dproj`) do agente. | Onde se **edita e compila** o agente. É a fonte da verdade do contrato da API. |
| **`Portal Contador/`** ⚠️ *com espaço no nome* | O que se **instala no cliente**: `PortalContador.exe`, `PortalContador.ini` (modelo), `banco.sqlite` (**vazio** — é o controle do que já foi enviado) e as DLLs do SQLite. | A pasta que se distribui, pronta. |

> `sistema/` e `sql/` na raiz são locais (gitignored), não fazem parte da entrega.

## Por onde começar

- **Instalar ou atualizar o portal num cliente** → **[`FontesWebPHP/README.md`](FontesWebPHP/README.md)**.
  Ele cobre **três caminhos**: servidor com terminal, servidor **sem** terminal
  (você monta o `vendor/` na sua máquina e sobe o zip) e **migração** de cliente
  que já usa uma versão anterior.
- **Mexer no código do portal** → `FontesWebPHP/`. O
  [`README.md`](FontesWebPHP/README.md) de lá cobre instalação, atualização,
  migração e resolução de problemas; o resto se aprende pelos comentários do
  próprio código, que explicam o porquê de cada decisão.
- **Mexer no agente** → `FontesDelphi/`. Precisa de **Delphi 12 + DevExpress**
  para compilar.
- **Instalar o agente num cliente** → distribua a pasta `Portal Contador/` **com
  o `.exe` compilado** e preencha, na tela do agente: as pastas dos XMLs, a URL
  do portal e a **Chave do Contador**.

---

## ⚠️ A chave da API mudou

O README anterior dizia que *"a chave é `Sistema`, hardcoded no agente"*. **Não é
mais assim.**

Hoje cada instalação tem a **sua própria chave**, gerada no painel:

1. No portal: **Usuários** → o usuário daquele cliente → **Chave de acesso do
   agente** → dê um nome que identifique a máquina ("PC da recepção") e gere.
2. No agente: cole no campo **"Chave do Contador"** e clique em **Gravar Config.**

Enquanto a chave estiver vazia o agente **não envia nada** — e avisa isso na tela,
em vez de falhar em silêncio.

A chave antiga `Sistema` ainda funciona em instalação existente, para não quebrar
ninguém, mas **é pública** (está neste repositório e na documentação). Migre cada
cliente para a chave própria e depois desligue o legado com
`SYSTEM_LEGACY_KEY_ENABLED=false` no `.env` daquele portal.

> A chave também amarra o que o agente pode gravar: ela só aceita CNPJs das
> empresas vinculadas ao usuário dono dela. Cadastre e vincule a empresa **antes**
> de o agente começar a enviar — sem vínculo o portal recusa com uma mensagem
> dizendo o que fazer, e o agente re-tenta sozinho quando o vínculo existir.

---

## Release: portal e agente sobem separados

O contrato da API é estável, então os dois são independentes:

| mudou | o que fazer |
|---|---|
| Só o portal (telas, correções, migrations) | `bash deploy.sh` no servidor. O agente instalado **continua funcionando sem tocar em nada**. É a maioria dos deploys. |
| O agente (`FontesDelphi/`) | **Recompilar** e redistribuir só o `PortalContador.exe`. |

⚠️ Ao atualizar o agente de um cliente, **troque só o `.exe`**. Não substitua o
`banco.sqlite` dele: é o controle do que já foi enviado, e o `banco.sqlite` desta
pasta é **vazio** — sobrescrever o dele faria milhares de XMLs subirem de novo. O
`.ini` também é preservado, e se atualiza sozinho no que dá.

> O `banco.sqlite` daqui é o **molde**, para instalação NOVA. Ele traz o schema
> completo, incluindo a tabela `arquivos` — a única que o agente **não** cria
> sozinho, ou seja, sem ela o agente não roda.
