<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalação · Portal do Contador</title>
    <style>
        :root{
            --blue:#343A3F; --black:#3c4043; --dark-gray:#6f7d97; --accent:#72a4e6;
            --bg:#2b3034; --card:#33393e; --line:#454b52; --text:#e7ebef; --muted:#9aa6b2;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0}
        body{
            font-family:-apple-system,"Segoe UI",Roboto,"Source Sans Pro",system-ui,sans-serif;
            background:radial-gradient(1200px 600px at 20% -10%,#3a4148,transparent),var(--bg);
            color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px;
        }
        .wrap{width:100%;max-width:680px}
        .brand{display:flex;align-items:center;gap:12px;margin-bottom:20px}
        .brand .logo{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#4d7fbf);
            display:flex;align-items:center;justify-content:center;font-weight:700;color:#0e1620;font-size:20px}
        .brand h1{font-size:19px;margin:0;font-weight:600;letter-spacing:.2px}
        .brand p{margin:2px 0 0;color:var(--muted);font-size:13px}
        .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:26px 26px 22px;
            box-shadow:0 24px 60px rgba(0,0,0,.35)}
        .sec{font-size:12px;text-transform:uppercase;letter-spacing:.14em;color:var(--muted);
            margin:18px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--line)}
        .sec:first-of-type{margin-top:0}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 14px}
        .grid .col-2{grid-column:1 / -1}
        label{display:block;font-size:13px;color:var(--muted);margin-bottom:5px}
        input{width:100%;padding:11px 12px;border-radius:10px;border:1px solid var(--line);
            background:#2c3236;color:var(--text);font-size:14px;outline:none;transition:border-color .15s,box-shadow .15s}
        input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(114,164,230,.18)}
        input::placeholder{color:#6f7a85;opacity:1}
        .hint{font-size:12px;color:var(--muted);margin-top:6px}
        .errors{background:rgba(214,89,89,.12);border:1px solid rgba(214,89,89,.4);color:#f0b6b6;
            border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:14px}
        .errors ul{margin:6px 0 0;padding-left:18px}
        .actions{margin-top:22px;display:flex;justify-content:flex-end}
        button{background:linear-gradient(135deg,var(--accent),#4d7fbf);color:#0e1620;font-weight:700;
            border:0;border-radius:10px;padding:12px 22px;font-size:15px;cursor:pointer;transition:filter .15s}
        button:hover{filter:brightness(1.06)}
        .foot{color:var(--muted);font-size:12px;text-align:center;margin-top:16px}
        @media (max-width:560px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <div class="logo">PC</div>
            <div>
                <h1>Portal do Contador</h1>
                <p>Assistente de instalação</p>
            </div>
        </div>

        <div class="card">
            @if ($errors->any())
                <div class="errors">
                    Não foi possível instalar:
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('install.store') }}">
                @csrf

                <div class="sec">Banco de dados</div>
                <div class="grid">
                    <div>
                        <label>Host</label>
                        <input name="db_host" value="{{ old('db_host', $defaults['db_host']) }}" placeholder="localhost" required>
                    </div>
                    <div>
                        <label>Porta</label>
                        <input name="db_port" value="{{ old('db_port', $defaults['db_port']) }}" placeholder="3306" required>
                    </div>
                    <div class="col-2">
                        <label>Nome do banco</label>
                        <input name="db_database" value="{{ old('db_database', $defaults['db_database']) }}" placeholder="ex.: usuario_portal" required>
                    </div>
                    <div>
                        <label>Usuário</label>
                        <input name="db_username" value="{{ old('db_username', $defaults['db_username']) }}" placeholder="ex.: usuario_portal" required>
                    </div>
                    <div>
                        <label>Senha</label>
                        <input name="db_password" type="password" value="{{ old('db_password') }}" placeholder="senha do usuário do banco">
                    </div>
                </div>
                <div class="hint">Se o banco ainda não existir, tento criá-lo (se o usuário tiver permissão). Caso contrário, crie-o no painel do servidor.</div>

                <div class="sec">Aplicação</div>
                <div class="grid">
                    <div class="col-2">
                        <label>URL do site (APP_URL)</label>
                        <input name="app_url" type="url" value="{{ old('app_url', $defaults['app_url']) }}" placeholder="https://cliente.seudominio.com.br" required>
                    </div>
                </div>

                <div class="sec">Administrador inicial</div>
                <div class="grid">
                    <div class="col-2">
                        <label>Nome</label>
                        <input name="admin_name" value="{{ old('admin_name') }}" placeholder="Seu nome completo" required>
                    </div>
                    <div>
                        <label>E-mail</label>
                        <input name="admin_email" type="email" value="{{ old('admin_email') }}" placeholder="voce@dominio.com.br" required>
                    </div>
                    <div>
                        <label>Senha (mín. 8)</label>
                        <input name="admin_password" type="password" placeholder="mínimo 8 caracteres" required>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit">Instalar ▶</button>
                </div>
            </form>
        </div>

        <div class="foot">Este assistente roda uma única vez e se desativa após a instalação.</div>
    </div>
</body>
</html>
