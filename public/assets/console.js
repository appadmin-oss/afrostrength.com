/*
 * Afrotech Academy — console enhancements.
 *
 * Everything below is an improvement on a page that already works. The
 * navigation drawer has a real toggle before this file loads (the rail is in
 * the document); the help panel is a real URL; a flash message can be left on
 * screen; the filter selects have a real submit button.
 *
 * So there is no framework and no build step, and if this file fails to load
 * the console loses polish and nothing else.
 */
(function () {
  'use strict';

  // Tells the stylesheet it may hide the fallback submit buttons.
  document.documentElement.classList.add('js');

  var body = document.body;

  /* ── Navigation drawer ───────────────────────────────────────────── */
  var toggle = document.querySelector('[data-nav-toggle]');
  var nav = document.getElementById('nav');

  function setNav(open) {
    body.classList.toggle('navOpen', open);
    if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open && nav) {
      var first = nav.querySelector('a');
      if (first) first.focus();
    }
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      setNav(!body.classList.contains('navOpen'));
    });
  }

  // Escape closes whatever is open, innermost first, and returns focus to
  // the control that opened it. Without this the drawer is a trap.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;

    var openDetails = document.querySelector('details[open] > summary');
    if (openDetails) {
      openDetails.parentNode.removeAttribute('open');
      openDetails.focus();
      return;
    }
    if (body.classList.contains('navOpen')) {
      setNav(false);
      if (toggle) toggle.focus();
    }
  });

  // A click on the scrim closes the drawer. The scrim is a pseudo-element,
  // so the test is "outside the rail and not the toggle".
  document.addEventListener('click', function (e) {
    if (!body.classList.contains('navOpen')) return;
    if (nav && nav.contains(e.target)) return;
    if (toggle && toggle.contains(e.target)) return;
    setNav(false);
  });

  /* ── Only one <details> open at a time ───────────────────────────── */
  document.addEventListener('toggle', function (e) {
    var d = e.target;
    if (d.tagName !== 'DETAILS' || !d.open) return;
    Array.prototype.forEach.call(document.querySelectorAll('details[open]'), function (other) {
      if (other !== d && !other.contains(d)) other.removeAttribute('open');
    });
  }, true);

  document.addEventListener('click', function (e) {
    Array.prototype.forEach.call(document.querySelectorAll('details[open]'), function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });

  /* ── Filters that submit themselves ──────────────────────────────── */
  Array.prototype.forEach.call(document.querySelectorAll('[data-autosubmit]'), function (sel) {
    sel.addEventListener('change', function () {
      if (sel.form) sel.form.submit();
    });
  });

  /* ── Dismissing a flash ──────────────────────────────────────────── */
  Array.prototype.forEach.call(document.querySelectorAll('[data-dismiss]'), function (btn) {
    btn.addEventListener('click', function () {
      var flash = btn.closest('.flash');
      if (!flash) return;
      // Focus moves before the element leaves, or it lands on <body> and a
      // screen-reader user loses their place entirely.
      var next = flash.nextElementSibling || document.getElementById('main');
      flash.remove();
      if (next && next.focus) {
        next.setAttribute('tabindex', '-1');
        next.focus();
      }
    });
  });

  /* ── Help panel, opened without a round trip ─────────────────────── */
  var panel = document.getElementById('helpPanel');

  function closeHelp() {
    if (!panel) return;
    panel.remove();
    panel = null;
    body.classList.remove('helpOpen');
    var url = new URL(window.location.href);
    url.searchParams.delete('help');
    history.replaceState({}, '', url);
  }

  document.addEventListener('click', function (e) {
    var close = e.target.closest('[data-help-close]');
    if (close && panel) {
      e.preventDefault();
      closeHelp();
      var back = document.querySelector('.infoLink');
      if (back) back.focus();
      return;
    }

    var open = e.target.closest('[data-help]');
    if (!open) return;
    // Nothing is cached here — let the link navigate and the server render
    // the panel. Fetching it would mean duplicating the panel's markup in
    // JavaScript, and two copies of anything drift.
  });

  /* ── The table is scrollable; say so to a keyboard user ──────────── */
  Array.prototype.forEach.call(document.querySelectorAll('.tableWrap'), function (wrap) {
    if (wrap.scrollWidth > wrap.clientWidth) {
      wrap.setAttribute('tabindex', '0');
      wrap.setAttribute('role', 'region');
      wrap.setAttribute('aria-label', 'Table, scrolls sideways');
    }
  });
}());
