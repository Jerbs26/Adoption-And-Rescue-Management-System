(function () {
  'use strict';

  /* ── helpers ─────────────────────────────────────────────── */
  function $id(id)  { return document.getElementById(id); }
  function $qs(sel) { return document.querySelector(sel); }

  /* ── Public-site hamburger (mobile nav) ──────────────────── */
  var hamburger = $id('navHamburger');
  var mobileNav = $id('mobileNav');
  if (hamburger && mobileNav) {
    hamburger.addEventListener('click', function () {
      var open = mobileNav.classList.toggle('open');
      hamburger.setAttribute('aria-expanded', String(open));
      mobileNav.setAttribute('aria-hidden',  String(!open));
    });
    mobileNav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        mobileNav.classList.remove('open');
        hamburger.setAttribute('aria-expanded', 'false');
        mobileNav.setAttribute('aria-hidden', 'true');
      });
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

  /* ── Dashboard sidebar ───────────────────────────────────── */
  var sidebar        = $id('appSidebar');
  var sidebarOverlay = $id('sidebarOverlay');
  var sidebarToggle  = $id('sidebarToggle');

  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('open');
    if (sidebarOverlay) sidebarOverlay.classList.add('open');
    if (sidebarToggle)  sidebarToggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('open');
    if (sidebarOverlay) sidebarOverlay.classList.remove('open');
    if (sidebarToggle)  sidebarToggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () {
      sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
  }
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebar);
  }
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) closeSidebar();
  });

  /* ── Escape key closes everything ───────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var openModal = $qs('.modal.open');
    if (openModal) { openModal.classList.remove('open'); document.body.style.overflow = ''; return; }
    if (sidebar && sidebar.classList.contains('open')) { closeSidebar(); return; }
    if (userDrop && userDrop.classList.contains('open')) {
      userDrop.classList.remove('open');
      if (userBtn) userBtn.setAttribute('aria-expanded', 'false');
      return;
    }
    if (mobileNav && mobileNav.classList.contains('open')) {
      mobileNav.classList.remove('open');
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

  /* ── Table scroll hints on mobile ───────────────────────── */
  if (window.innerWidth <= 640) {
    document.querySelectorAll('.card table').forEach(function (table) {
      var card = table.closest('.card');
      if (!card) return;
      var hint = document.createElement('div');
      hint.className = 'table-scroll-hint';
      hint.innerHTML = '<i class="fa-solid fa-arrows-left-right" style="margin-right:.3rem"></i>Scroll to see more';
      card.parentNode.insertBefore(hint, card.nextSibling);
      card.addEventListener('scroll', function () { hint.style.display = 'none'; }, { once: true });
    });
  }

  /* ── Guaranteed sidebar hamburger for admin pages ────────── *
   *
   * Problem: the admin topbar.php may not render #sidebarToggle,
   * so we inject one into the topbar if it's missing.
   *
   * Strategy:
   *   1. Wait for DOM ready (we're in <body> so it's already ready,
   *      but use DOMContentLoaded for safety if deferred).
   *   2. Find the topbar element — try multiple selectors.
   *   3. If no #sidebarToggle exists in the DOM, create one and
   *      prepend it to the topbar.
   *   4. Also ensure the overlay exists.
   * ──────────────────────────────────────────────────────────── */
  function initHamburger() {
    var sb = $id('appSidebar');
    if (!sb) return; // not a dashboard page

    // ── Ensure overlay exists ──────────────────────────────────
    var ov = $id('sidebarOverlay');
    if (!ov) {
      ov = document.createElement('div');
      ov.id        = 'sidebarOverlay';
      ov.className = 'sidebar-overlay';
      document.body.appendChild(ov);
    }

    // ── Open / close helpers ───────────────────────────────────
    function doOpen() {
      sb.classList.add('open');
      ov.classList.add('open');
      document.body.style.overflow = 'hidden';
    }
    function doClose() {
      sb.classList.remove('open');
      ov.classList.remove('open');
      document.body.style.overflow = '';
    }
    ov.addEventListener('click', doClose);

    // ── Wire existing #sidebarToggle if present ────────────────
    var existing = $id('sidebarToggle');
    if (existing) {
      var clone = existing.cloneNode(true);
      existing.parentNode.replaceChild(clone, existing);
      clone.addEventListener('click', function () {
        sb.classList.contains('open') ? doClose() : doOpen();
      });
      return;
    }

    // ── Build the hamburger button ─────────────────────────────
    var btn = document.createElement('button');
    btn.type      = 'button';
    btn.id        = 'sidebarToggle';
    btn.className = 'sidebar-toggle';
    btn.setAttribute('aria-label',    'Open navigation');
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-controls', 'appSidebar');
    btn.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';

    btn.addEventListener('click', function () {
      if (sb.classList.contains('open')) {
        doClose();
        btn.setAttribute('aria-expanded', 'false');
        btn.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
      } else {
        doOpen();
        btn.setAttribute('aria-expanded', 'true');
        btn.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
      }
    });

    // ── Find a REAL topbar (has content beyond the toggle) ─────
    // A real topbar will have class containing "topbar" but NOT be .main-body.
    var realTopbar = $qs('.main-topbar')
                  || $qs('.dash-topbar')
                  || $qs('[class*="topbar"]:not(.main-body)');

    if (realTopbar) {
      // Prepend into existing topbar
      realTopbar.insertBefore(btn, realTopbar.firstChild);
      return;
    }

    // ── No real topbar found: create one and insert before .main-body ──
    var mainContent = $qs('.main-content');
    var mainBody    = $qs('.main-body');

    if (!mainContent || !mainBody) return;

    var bar = document.createElement('div');
    bar.className = 'main-topbar injected-topbar';
    bar.appendChild(btn);

    // Insert the new topbar bar before .main-body
    mainContent.insertBefore(bar, mainBody);
  }

  // Run immediately (script loads at end of body)
  initHamburger();

})();