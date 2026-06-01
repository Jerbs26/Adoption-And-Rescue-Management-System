(function () {
  'use strict';

  /* ── helpers ─────────────────────────────────────────────── */
  function $id(id)  { return document.getElementById(id); }
  function $qs(sel) { return document.querySelector(sel); }

  /* ── Public-site hamburger (mobile nav sidebar drawer) ───── */
  var hamburger = $id('navHamburger');
  var mobileNav = $id('mobileNav');

  if (hamburger && mobileNav) {

    /* Move drawer to <body> so it's not clipped by header stacking context */
    if (mobileNav.parentElement !== document.body) {
      document.body.appendChild(mobileNav);
    }

    /* Inject overlay if not present */
    var mobileNavOverlay = $id('mobileNavOverlay');
    if (!mobileNavOverlay) {
      mobileNavOverlay = document.createElement('div');
      mobileNavOverlay.id        = 'mobileNavOverlay';
      mobileNavOverlay.className = 'mobile-nav-overlay';
      document.body.appendChild(mobileNavOverlay);
    }

    /* Inject close button inside drawer if not present */
    if (!mobileNav.querySelector('.mobile-nav__close-btn')) {
      var closeBtn = document.createElement('button');
      closeBtn.className = 'mobile-nav__close-btn';
      closeBtn.setAttribute('aria-label', 'Close menu');
      closeBtn.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
      mobileNav.insertBefore(closeBtn, mobileNav.firstChild);
    }

    function openMobileNav() {
      mobileNav.classList.add('open');
      mobileNavOverlay.classList.add('open');
      hamburger.setAttribute('aria-expanded', 'true');
      mobileNav.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function closeMobileNav() {
      mobileNav.classList.remove('open');
      mobileNavOverlay.classList.remove('open');
      hamburger.setAttribute('aria-expanded', 'false');
      mobileNav.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    hamburger.addEventListener('click', function () {
      mobileNav.classList.contains('open') ? closeMobileNav() : openMobileNav();
    });

    mobileNav.addEventListener('click', function (e) {
      if (e.target.closest('.mobile-nav__close-btn')) closeMobileNav();
    });

    mobileNavOverlay.addEventListener('click', closeMobileNav);

    mobileNav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMobileNav);
    });
  }

  /* ── User dropdown ───────────────────────────────────────── */
  var userBtn  = $id('userMenuBtn');
  var userDrop = $id('userMenuDropdown');
  if (userBtn && userDrop) {
    userBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = userDrop.classList.toggle('open');
      userBtn.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', function (e) {
      if (!userDrop.contains(e.target) && e.target !== userBtn) {
        userDrop.classList.remove('open');
        userBtn.setAttribute('aria-expanded', 'false');
      }
    });
    userDrop.addEventListener('click', function (e) { e.stopPropagation(); });
  }

  /* ── Escape key closes everything ───────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var openModal = $qs('.modal.open');
    if (openModal) { openModal.classList.remove('open'); document.body.style.overflow = ''; return; }
    var sb = $id('appSidebar');
    if (sb && sb.classList.contains('open')) { doCloseSidebar(); return; }
    if (userDrop && userDrop.classList.contains('open')) {
      userDrop.classList.remove('open');
      if (userBtn) userBtn.setAttribute('aria-expanded', 'false');
      return;
    }
    if (mobileNav && mobileNav.classList.contains('open')) {
      mobileNav.classList.remove('open');
      var _ov = $id('mobileNavOverlay');
      if (_ov) _ov.classList.remove('open');
      document.body.style.overflow = '';
      if (hamburger) {
        hamburger.setAttribute('aria-expanded', 'false');
        mobileNav.setAttribute('aria-hidden', 'true');
      }
    }
  });

  /* ── Modal open/close ────────────────────────────────────── */
  document.addEventListener('click', function (e) {
    var openTarget = e.target.closest('[data-modal-open]');
    if (openTarget) {
      var m = $id(openTarget.dataset.modalOpen);
      if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
    }
    var closeTarget = e.target.closest('[data-close-modal]');
    if (closeTarget) {
      var mc = closeTarget.closest('.modal');
      if (mc) { mc.classList.remove('open'); document.body.style.overflow = ''; }
    }
    if (e.target.classList.contains('modal')) {
      e.target.classList.remove('open');
      document.body.style.overflow = '';
    }
  });

  /* ── Auto-dismiss alerts ─────────────────────────────────── */
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
    var delay = parseInt(el.dataset.autoDismiss, 10) || 4000;
    setTimeout(function () {
      el.style.transition = 'opacity .4s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, delay);
  });

  /* ── Image upload preview ────────────────────────────────── */
  document.querySelectorAll('.img-upload-input').forEach(function (inp) {
    var wrap = $id(inp.dataset.preview);
    if (!wrap) return;
    inp.addEventListener('change', function () {
      var file = inp.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function (ev) {
        var img = wrap.querySelector('img') || document.createElement('img');
        img.src = ev.target.result;
        img.alt = 'Preview';
        if (!wrap.querySelector('img')) wrap.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
    wrap.addEventListener('click', function () { inp.click(); });
  });

  /* ── Status pills ────────────────────────────────────────── */
  document.querySelectorAll('.status-pills').forEach(function (group) {
    var input = $id(group.dataset.input);
    group.querySelectorAll('.status-pill').forEach(function (btn) {
      btn.addEventListener('click', function () {
        group.querySelectorAll('.status-pill').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        if (input) input.value = btn.dataset.status;
      });
    });
  });

  /* ── Confirm delete ──────────────────────────────────────── */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
  });

  /* ── Accessibility: keyboard nav class ───────────────────── */
  document.addEventListener('keydown',   function (e) { if (e.key === 'Tab') document.body.classList.add('kb-nav'); });
  document.addEventListener('mousedown', function ()  { document.body.classList.remove('kb-nav'); });



  /* ══════════════════════════════════════════════════════════
   * Dashboard sidebar — single unified init
   * All sidebar open/close logic lives here, nowhere else.
   * ══════════════════════════════════════════════════════════ */

  /* ── Inject guaranteed sidebar CSS once ────────────────────
   * This ensures .sidebar and .sidebar.open always have the
   * correct behaviour regardless of which stylesheet loads,
   * overriding any !important inline styles from PHP scripts. */
  (function injectSidebarCSS() {
    if ($id('_sidebarFixCSS')) return; // already injected
    var style = document.createElement('style');
    style.id = '_sidebarFixCSS';
    style.textContent = [
      /* Hidden state — off-screen left */
      '@media (max-width:900px){',
      '  #appSidebar:not(.open){',
      '    position:fixed!important;',
      '    top:0!important;left:0!important;',
      '    height:100%!important;',
      '    width:min(260px,85vw)!important;',
      '    min-width:0!important;max-width:none!important;',
      '    transform:translateX(-110%)!important;',
      '    z-index:300!important;',
      '    overflow-y:auto!important;',
      '    transition:transform .25s ease!important;',
      '    flex:none!important;',
      '  }',
      /* Open state — slide in */
      '  #appSidebar.open{',
      '    position:fixed!important;',
      '    top:0!important;left:0!important;',
      '    height:100%!important;',
      '    width:min(260px,85vw)!important;',
      '    min-width:0!important;max-width:none!important;',
      '    transform:translateX(0)!important;',
      '    z-index:300!important;',
      '    overflow-y:auto!important;',
      '    transition:transform .25s ease!important;',
      '    flex:none!important;',
      '  }',
      /* Overlay */
      '  #sidebarOverlay.open{',
      '    display:block!important;',
      '    position:fixed!important;',
      '    inset:0!important;',
      '    background:rgba(0,0,0,.45)!important;',
      '    z-index:299!important;',
      '  }',
      '  #sidebarOverlay:not(.open){display:none!important;}',
      '}'
    ].join('\n');
    document.head.appendChild(style);
  })();

  /* Module-level open/close so Escape handler above can call them */
  function doOpenSidebar(sb, ov, toggleBtn) {
    /* Strip any conflicting inline styles before applying .open */
    ['flex','min-width','max-width','width','overflow',
     'padding','transform','position'].forEach(function(p){
      sb.style.removeProperty(p);
    });
    sb.classList.add('open');
    if (ov) { ov.classList.add('open'); ov.removeAttribute('aria-hidden'); }
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    var closeBtn = $id('sidebarClose');
    if (closeBtn) closeBtn.focus();
  }
  function doCloseSidebar(toggleBtn) {
    var sb = $id('appSidebar');
    var ov = $id('sidebarOverlay');
    if (!sb) return;
    sb.classList.remove('open');
    if (ov) { ov.classList.remove('open'); ov.setAttribute('aria-hidden', 'true'); }
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  function initSidebar() {
    var sb = $id('appSidebar');
    if (!sb) return; // not a dashboard page

    /* ── Ensure overlay exists ──────────────────────────────── */
    var ov = $id('sidebarOverlay');
    if (!ov) {
      ov = document.createElement('div');
      ov.id        = 'sidebarOverlay';
      ov.className = 'sidebar-overlay';
      ov.setAttribute('aria-hidden', 'true');
      document.body.appendChild(ov);
    }

    /* ── Wire sidebar close button ─────────────────────────── */
    var closeBtn = $id('sidebarClose');
    if (closeBtn) {
      closeBtn.addEventListener('click', function () { doCloseSidebar(); });
    }

    /* ── Wire overlay ──────────────────────────────────────── */
    ov.addEventListener('click', function () { doCloseSidebar(); });

    /* ── Close on resize to desktop ────────────────────────── */
    window.addEventListener('resize', function () {
      if (window.innerWidth > 900) doCloseSidebar();
    });

    /* ── Find or create the toggle button ──────────────────── */
    var toggleBtn = $id('sidebarToggle');

    if (!toggleBtn) {
      /* Build the hamburger button */
      toggleBtn = document.createElement('button');
      toggleBtn.type      = 'button';
      toggleBtn.id        = 'sidebarToggle';
      toggleBtn.className = 'sidebar-toggle';
      toggleBtn.setAttribute('aria-label',    'Open navigation');
      toggleBtn.setAttribute('aria-expanded', 'false');
      toggleBtn.setAttribute('aria-controls', 'appSidebar');
      toggleBtn.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';

      /* Find a real topbar to inject into */
      var realTopbar = $qs('.main-topbar')
                    || $qs('.dash-topbar')
                    || $qs('[class*="topbar"]:not(.main-body)');

      if (realTopbar) {
        realTopbar.insertBefore(toggleBtn, realTopbar.firstChild);
      } else {
        /* No topbar at all — create one before .main-body */
        var mainContent = $qs('.main-content');
        var mainBody    = $qs('.main-body');
        if (!mainContent || !mainBody) return;
        var bar = document.createElement('div');
        bar.className = 'main-topbar injected-topbar';
        bar.appendChild(toggleBtn);
        mainContent.insertBefore(bar, mainBody);
      }
    }

    /* ── Wire the toggle (whether found or created) ────────── */
    toggleBtn.addEventListener('click', function () {
      if (sb.classList.contains('open')) {
        doCloseSidebar(toggleBtn);
      } else {
        doOpenSidebar(sb, ov, toggleBtn);
      }
    });
  }

  /* Wait for DOM — sidebar/topbar HTML is rendered by PHP includes
   * that appear AFTER the <script src="main.js"> tag, so we must
   * defer until DOMContentLoaded or the elements won't exist yet. */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebar);
  } else {
    initSidebar();
  }

})();