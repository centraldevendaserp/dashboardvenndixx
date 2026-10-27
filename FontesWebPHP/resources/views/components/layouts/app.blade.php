<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('assets/images/favicon.png') }}" />
    <title>Portal do Contador</title>

    <!-- fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Source+Sans+Pro:wght@400;600;700&display=swap"
        rel="stylesheet">

    <!-- plugins (uma vez no layout: com wire:navigate o morph os mantem,
         sem re-injetar/redeclarar como acontecia com o push por pagina) -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/plugins/cute-alert/style.css') }}">
    @stack('plugins-styles')

    <!-- bundle -->
    <link rel="stylesheet" href="{{ asset('assets/bundle/app.css') }}">

    <!-- custom css (cache-busting por mtime: mudanças de CSS chegam sem refresh forçado) -->
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}?v={{ filemtime(public_path('assets/css/custom.css')) }}">

    <!-- painel (redesign) -->
    <link rel="stylesheet" href="{{ asset('assets/css/panel.css') }}?v={{ filemtime(public_path('assets/css/panel.css')) }}">

    @stack('component-styles')
    <livewire:styles />
</head>

<body>

    <div class="box-general">

        <div class="header">

            <div class="logo">
                <a href="{{ route('panel.dashboard.index') }}" title="Portal do Contador">
                    <img class="pnl-logo--full" src="{{ asset('assets/images/logo-fold2.png') }}"
                        alt="Portal do Contador">
                    <img class="pnl-logo--mark" src="{{ asset('assets/images/favicon.png') }}" alt="">
                </a>
            </div>

            <div class="topbar">

                <div class="pnl-topbar__left">
                    <button id="pnl-toggle" class="pnl-burger" type="button" aria-label="Alternar menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    @php($pnlName = trim((string) (auth('web')->user()->name ?? '')))
                    <div class="pnl-greet">
                        <b>Olá, {{ \Illuminate\Support\Str::of($pnlName)->explode(' ')->first() ?: 'bem-vindo' }}</b>
                        <span>@yield('title', 'Painel')</span>
                    </div>
                </div>

                <div class="pnl-topbar__right">
                    {{-- <livewire:general.notification/> --}}
                    <livewire:general.profile-dropdown />
                </div>

            </div>

        </div>

        <livewire:general.menu-sidebar />

        <div id="pnl-backdrop" class="pnl-backdrop"></div>

        <div class="container">
            {{ $slot }}
        </div>

    </div>
    <!-- box-general -->

    <script>
        function afterModalShown(modalQuery, callback) {
            const modal = document.querySelector(modalQuery);

            if (!modal) return;

            let alreadyInitialized = false;

            const observer = new MutationObserver(() => {
                if (modal.classList.contains('active')) {
                    if (!alreadyInitialized) {
                        alreadyInitialized = true;
                        setTimeout(() => callback(), 50);
                    }
                } else {
                    alreadyInitialized = false;
                }
            });

            observer.observe(modal, {
                attributes: true,
                attributeFilter: ['class']
            });
        }

        /* --------------------------------------------------------------------
           Modal de cadastro nao mostra dado VELHO enquanto o certo nao chega.

           O JS do tema abre o modal NO CLIQUE, sincronamente, enquanto o
           wire:click so traz o dado um round-trip depois — nesse intervalo o
           modal exibe o registro ANTERIOR, e da para comecar a digitar achando
           que e o certo.

           O sinal de "chegou" nao e um timer: sao os eventos que o servidor ja
           dispara no fim do eventAction.
           -------------------------------------------------------------------- */
        (function () {
            if (document.__modalEsperaDado) return;   // o layout re-executa em wire:navigate
            document.__modalEsperaDado = true;

            var MODAIS = ['#modal-data-to-user', '#modal-data-to-company'];

            // Captura (3o argumento true): roda ANTES do handler do tema, entao
            // a classe ja esta la quando o modal recebe o .active.
            document.addEventListener('click', function (e) {
                var gatilho = e.target.closest('[data-trigger="modal"]');
                if (!gatilho) return;

                var alvo = gatilho.getAttribute('data-modal');
                if (MODAIS.indexOf(alvo) === -1) return;

                var modal = document.querySelector(alvo);
                if (!modal) return;

                modal.classList.add('is-esperando-dado');

                // Requisicao que nunca volta nao pode deixar o modal em branco
                // para sempre: conteudo velho e melhor que nada.
                clearTimeout(modal.__esperaDado);
                modal.__esperaDado = setTimeout(function () {
                    modal.classList.remove('is-esperando-dado');
                }, 5000);
            }, true);

            function revelar(seletor) {
                var modal = document.querySelector(seletor);
                if (!modal) return;
                clearTimeout(modal.__esperaDado);
                modal.classList.remove('is-esperando-dado');
            }

            function registrar() {
                // Estes dois eventos sao a ultima coisa que o eventAction de
                // cada modal dispara: "o dado esta no DOM".
                Livewire.on('configureUserModal', function () { revelar('#modal-data-to-user'); });
                Livewire.on('syncCompanyModalFields', function () { revelar('#modal-data-to-company'); });
            }

            if (window.Livewire) registrar();
            else document.addEventListener('livewire:init', registrar);
        })();
    </script>

    @stack('modals')

    {{-- Scripts do APP: rodam UMA VEZ (data-navigate-once). Sem isso o
         wire:navigate os re-executaria a cada troca de tela, recarregando o
         jQuery (o que apaga o select2) e redeclarando o cute-alert. --}}

    <!-- bundle -->
    <script src="{{ asset('assets/bundle/app.js') }}" data-navigate-once></script>

    <!-- plugins (depois do bundle/jQuery e antes do custom/panel) -->
    <script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}" data-navigate-once></script>
    <script src="{{ asset('assets/plugins/select2/js/i18n/pt-BR.js') }}" data-navigate-once></script>
    <script src="{{ asset('assets/plugins/jquery-mask/jquery.mask.min.js') }}" data-navigate-once></script>
    <script src="{{ asset('assets/plugins/apexcharts/apexcharts.min.js') }}" data-navigate-once></script>
    <script src="{{ asset('assets/plugins/cute-alert/cute-alert.js') }}" data-navigate-once></script>
    @stack('plugins-scripts')

    <!-- custom js -->
    <script src="{{ asset('assets/js/custom.js') }}" data-navigate-once></script>

    <!-- painel (redesign): controle da sidebar -->
    <script src="{{ asset('assets/js/panel.js') }}?v={{ filemtime(public_path('assets/js/panel.js')) }}" data-navigate-once></script>

    <!-- fix error -->
    <script src="{{ asset('assets/js/fix-error.js') }}" data-navigate-once></script>

    <script>
        document.addEventListener('livewire:init', () => {
            if (typeof Livewire.onPageExpired === 'function') {
                Livewire.onPageExpired((response, message) => {
                    window.location.reload();
                });
            }
        });

        // Navegação SPA (wire:navigate): dispara na 1ª carga E a cada troca de tela.
        document.addEventListener('livewire:navigated', () => {
            // Preserva o estado recolhido da sidebar: o morph a re-renderiza a
            // partir do HTML do servidor, sem a classe is-rail.
            const box = document.querySelector('.box-general');
            if (box && window.innerWidth > 992) {
                try {
                    box.classList.toggle('is-rail', localStorage.getItem('pnl-sidebar') === 'rail');
                } catch (e) {}
            }
            // Accordion "Documentos": abre na rota de documentos e fecha nas
            // outras. O <li.pnl-acc> tem wire:ignore, então persiste entre
            // navegações e a transição roda.
            const acc = document.querySelector('.pnl-acc');
            if (acc) {
                const onDocs = location.pathname.indexOf('/panel/documents/') === 0;
                acc.classList.toggle('is-open', onDocs);
                const accToggle = acc.querySelector('[data-acc-toggle]');
                if (accToggle) accToggle.setAttribute('aria-expanded', onDocs ? 'true' : 'false');
                acc.querySelectorAll('.pnl-acc__sub li').forEach((li) => {
                    const a = li.querySelector('a');
                    li.classList.toggle('is-active', !!(a && new URL(a.href).pathname === location.pathname));
                });
            }
            // Re-aplica a máscara de data: o livewire:init não redispara ao
            // navegar via AJAX, e os campos perderiam a máscara.
            if (window.jQuery && jQuery.fn.mask) {
                jQuery('.mask-date').mask('00/00/0000');
            }
            // Transição de entrada do conteúdo (fade + subida leve).
            const c = document.querySelector('.box-general > .container');
            if (c) {
                c.classList.remove('pnl-navd');
                void c.offsetWidth; // reinicia a animação
                c.classList.add('pnl-navd');
            }
        });
    </script>

    <!-- component-scriptss -->
    @stack('component-scripts')

    <livewire:scripts />
</body>

</html>
