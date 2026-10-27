/* ============================================================
   Portal do Contador — Painel: controle da sidebar
   - Desktop: alterna expandida <-> rail (preferência salva)
   - Mobile (<=992px): off-canvas com backdrop
   - Sombra da topbar ao rolar
   Não depende do toggle do bundle (usa #pnl-toggle / classes .is-*).
   ============================================================ */

/* ------------------------------------------------------------
   Paleta única: modelo de documento -> rótulo + cor. Fonte da verdade dos
   gráficos do dashboard.
   ⚠️ É um ARRAY porque chave numérica de objeto seria reordenada pelo JS,
   bagunçando a ordem das séries e da legenda.
   ------------------------------------------------------------ */
window.PNL_MODELS = [
  { code: '55', label: 'NF-e',    color: '#2fa37a' },
  { code: '65', label: 'NFC-e',   color: '#2f6fb3' },
  { code: '57', label: 'CT-e',    color: '#6f7d97' },
  { code: '58', label: 'MDF-e',   color: '#c79019' },
  { code: '59', label: 'Entrada', color: '#2ca2e2' },
];
window.PNL_MODEL_BY_CODE = window.PNL_MODELS.reduce(function (m, x) { m[x.code] = x; return m; }, {});
window.PNL_MODEL_COLOR_BY_LABEL = window.PNL_MODELS.reduce(function (m, x) { m[x.label] = x.color; return m; }, {});
window.PNL_STATUS_COLORS = { '100': '#2fa37a', '101': '#cb6468', '110': '#6f7d97' };

(function () {
  if (window.__pnlInit) return;
  window.__pnlInit = true;

  var KEY = 'pnl-sidebar';        // 'rail' | 'exp'
  var MOBILE = 992;

  function box() { return document.querySelector('.box-general'); }
  function isMobile() { return window.innerWidth <= MOBILE; }
  function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function syncAria() {
    var b = box(), btn = document.querySelector('#pnl-toggle');
    if (btn && b) btn.setAttribute('aria-expanded', b.classList.contains('is-mobile-open') ? 'true' : 'false');
  }

  function applySaved() {
    var b = box();
    if (!b) return;
    if (!isMobile() && lsGet(KEY) === 'rail') {
      b.classList.add('is-rail');
    } else {
      b.classList.remove('is-rail');
    }
  }

  function toggle() {
    var b = box();
    if (!b) return;
    if (isMobile()) {
      b.classList.toggle('is-mobile-open');
      syncAria();
    } else {
      var rail = b.classList.toggle('is-rail');
      lsSet(KEY, rail ? 'rail' : 'exp');
    }
  }

  function closeMobile() {
    var b = box();
    if (b) b.classList.remove('is-mobile-open');
    syncAria();
  }

  function bind() {
    applySaved();
    syncAria();

    document.addEventListener('click', function (e) {
      if (e.target.closest('#pnl-toggle')) { e.preventDefault(); toggle(); return; }
      if (e.target.closest('#pnl-backdrop')) { closeMobile(); return; }
      // ao navegar pelo menu no mobile, fecha o off-canvas
      if (isMobile() && e.target.closest('.sidebar .pnl-nav a')) { closeMobile(); }
    });

    // sombra da topbar ao rolar
    var topbar = document.querySelector('.box-general > .header .topbar');
    if (topbar) {
      var onScroll = function () { topbar.classList.toggle('is-scrolled', window.scrollY > 4); };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }

    // ajustar ao cruzar o breakpoint
    var wasMobile = isMobile();
    window.addEventListener('resize', function () {
      var nowMobile = isMobile();
      if (nowMobile !== wasMobile) {
        wasMobile = nowMobile;
        closeMobile();
        applySaved();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();

/* ------------------------------------------------------------
   Fecha o modal ao clicar no X. O handler do bundle exige o wrapper .dialog,
   que os modais de filtro não têm — então o X deles não fechava. Este cobre
   qualquer .close dentro de .modal-main, e é aditivo.
   ------------------------------------------------------------ */
(function () {
  document.addEventListener('click', function (e) {
    var closeBtn = e.target.closest('.modal-main a.close');
    if (!closeBtn) return;
    var modal = closeBtn.closest('.modal-main');
    if (!modal) return;
    e.preventDefault();
    modal.classList.remove('active');
    document.body.style.overflow = 'initial';
  });
})();

/* ------------------------------------------------------------
   Chips de vínculo ("Empresas/Usuários vinculados").
   O bundle cancela o clique dentro de .modal-main, o que mata o toggle nativo
   do checkbox. Este guard impede que o clique no chip chegue lá, sem afetar o
   backdrop. Reanexa após navegação AJAX, senão o modal recriado fica sem ele.
   ------------------------------------------------------------ */
(function () {
  function attachChipGuard() {
    document.querySelectorAll('.modal-main').forEach(function (m) {
      if (m.__linkChipGuard) return;
      m.__linkChipGuard = true;
      m.addEventListener('click', function (e) {
        if (e.target.closest('.link-chip')) e.stopPropagation();
      });
    });
  }
  document.addEventListener('livewire:navigated', attachChipGuard);
  document.addEventListener('livewire:initialized', attachChipGuard);
  attachChipGuard();
})();

/* ------------------------------------------------------------
   Accordion da sidebar ("Documentos"): abre/fecha o submenu dos tipos. Já vem
   aberto pelo servidor quando a rota atual é uma tela de documentos.
   ------------------------------------------------------------ */
(function () {
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-acc-toggle]');
    if (!toggle) return;
    e.preventDefault();
    var acc = toggle.closest('.pnl-acc');
    if (!acc) return;
    var open = acc.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();

/* ------------------------------------------------------------
   Navegação dos links que NÃO são de documentos. Eles são <a href> puros para
   não disputar o clique com o wire:navigate: assim dá para fechar o accordion
   com a transição e só depois navegar, ainda por AJAX.
   ------------------------------------------------------------ */
(function () {
  document.addEventListener('click', function (e) {
    // cliques com modificador (nova aba etc.) seguem o padrão do navegador
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var link = e.target.closest('.sidebar a[href], .box-general > .header .logo a[href]');
    if (!link) return;
    var href = link.getAttribute('href') || '';
    if (href.indexOf('/panel/') === -1 || href.indexOf('/panel/documents/') !== -1) return;
    if (!(window.Livewire && Livewire.navigate)) return; // fallback: navegação normal do <a>

    e.preventDefault();
    var go = function () { Livewire.navigate(href); };

    var acc = document.querySelector('.pnl-acc.is-open');
    if (!acc) { go(); return; } // accordion já fechado: navega direto

    // fecha o accordion com a MESMA transição do toggle manual, depois navega
    acc.classList.remove('is-open');
    var toggle = acc.querySelector('[data-acc-toggle]');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');

    var sub = acc.querySelector('.pnl-acc__sub');
    var navigated = false;
    var fin = function () {
      if (navigated) return;
      navigated = true;
      go();
    };
    if (sub) sub.addEventListener('transitionend', fin, { once: true });
    setTimeout(fin, 520); // fallback se o transitionend não disparar
  });
})();

/* ------------------------------------------------------------
   Paginação (Empresas/Usuários): ao trocar de página, rola até os controles.
   Sem isto, a lista muda de altura e eles saem do campo de visão.
   ------------------------------------------------------------ */
(function () {
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.u-pagination a')) return;
    setTimeout(function () {
      var pag = document.querySelector('.u-pagination');
      if (pag) pag.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, 380);
  });
})();
