<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('assets/images/favicon.png') }}" />
    <title>{{ env('APP_EMPRESA') }} - @yield('title')</title>

    <!-- fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- estilo dedicado da tela de auth (autônomo, não usa o bundle do painel) -->
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}">

    @stack('plugins-styles')
    @stack('component-styles')
    <livewire:styles />
</head>

<body class="auth-body">

    @php($heroFull = $heroFull ?? request()->routeIs('auth.login'))

    <main class="auth-shell">

        <!-- ===================== HERO ===================== -->
        <section class="auth-hero {{ $heroFull ? '' : 'auth-hero--lite' }}" aria-label="Portal do Contador">

            <div class="auth-hero__bg" aria-hidden="true">
                <svg viewBox="0 0 1000 1000" preserveAspectRatio="xMidYMid slice" role="img" aria-hidden="true">
                    <defs>
                        <pattern id="authDots" width="22" height="22" patternUnits="userSpaceOnUse">
                            <circle cx="2" cy="2" r="1.3" fill="#ffffff" opacity="0.07" />
                        </pattern>
                    </defs>
                    <rect width="1000" height="1000" fill="url(#authDots)" />

                    @if ($heroFull)
                        <!-- arcos (len ~ comprimento do path p/ a animação de traço) -->
                        <path class="auth-arc"    style="--len:760" d="M120,720 C 320,420 560,420 760,180" />
                        <path class="auth-arc a2" style="--len:680" d="M170,860 C 430,700 600,560 880,420" />
                        <path class="auth-arc a3" style="--len:620" d="M90,300 C 320,260 540,520 820,560" />
                        <path class="auth-arc a4" style="--len:540" d="M260,160 C 460,300 540,520 700,760" />

                        <!-- halos -->
                        <circle class="auth-node-glow"    cx="120" cy="720" r="14" />
                        <circle class="auth-node-glow g2" cx="760" cy="180" r="14" />
                        <circle class="auth-node-glow g3" cx="880" cy="420" r="14" />
                        <circle class="auth-node-glow g4" cx="700" cy="760" r="14" />
                        <circle class="auth-node-glow"    cx="90"  cy="300" r="13" />

                        <!-- nós -->
                        <circle class="auth-node"    cx="120" cy="720" r="4.5" />
                        <circle class="auth-node n2" cx="760" cy="180" r="4.5" />
                        <circle class="auth-node n3" cx="880" cy="420" r="4.5" />
                        <circle class="auth-node n4" cx="700" cy="760" r="4.5" />
                        <circle class="auth-node"    cx="90"  cy="300" r="4" />
                        <circle class="auth-node n2" cx="170" cy="860" r="4" />
                    @endif
                </svg>
            </div>

            <header class="auth-brand">
                <img src="{{ asset('assets/images/logo-fold2.png') }}" alt="Portal do Contador" width="198">
            </header>

            <div class="auth-hero__mid">
                <div class="auth-accent-line" aria-hidden="true"></div>

                @if ($heroFull)
                    <h2 class="auth-tagline">Gestão inteligente<br><span class="soft">de documentos fiscais</span></h2>
                    <p class="auth-lede">Centralize, consulte e baixe seus documentos fiscais com segurança e rastreabilidade — direto do seu escritório.</p>
                    <div class="auth-chips" aria-label="Tipos de documento suportados">
                        <span class="auth-chip">NF-e</span>
                        <span class="auth-chip">NFC-e</span>
                        <span class="auth-chip">CT-e</span>
                        <span class="auth-chip">MDF-e</span>
                        <span class="auth-chip">eventos</span>
                        <span class="auth-chip">inutilizações</span>
                    </div>
                @else
                    <h2 class="auth-tagline auth-tagline--sm">Gestão de<br><span class="soft">documentos fiscais</span></h2>
                    <p class="auth-lede auth-lede--sm">Acesse o portal para consultar e baixar seus documentos fiscais com segurança.</p>
                @endif
            </div>

            @if ($heroFull)
                <div class="auth-hero__foot">
                    <span class="auth-pulse" aria-hidden="true"></span>
                    <span>Sincronização contínua com o sistema desktop</span>
                </div>
            @endif
        </section>

        <!-- ===================== FORMULÁRIO ===================== -->
        <section class="auth-pane" aria-label="Acesso ao portal">
            <div class="auth-card">
                {{ $slot }}
            </div>
        </section>

    </main>

    @stack('modals')

    <script>
        document.addEventListener('livewire:init', () => {
            if (typeof Livewire.onPageExpired === 'function') {
                Livewire.onPageExpired((response, message) => {
                    window.location.reload();
                });
            }
        });
    </script>

    @stack('component-scripts')

    <livewire:scripts />
</body>

</html>
