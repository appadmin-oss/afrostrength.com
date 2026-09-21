/*
 * afrostrength.com — the small amount of behaviour the markup cannot carry.
 *
 * Everything here is an UPGRADE. The Platform dropdown and the drawer are
 * <details> elements: they open and close on their own, and this file only
 * adds what <details> has no way to express — opening on hover, closing on
 * Escape or on a click outside, trapping focus inside the drawer, and closing
 * the drawer when a link inside it is followed.
 *
 * If this file never arrives — and on a 3G connection in Lagos that happens —
 * the navigation still works. That is the whole reason it is written this way.
 */
(function () {
  "use strict";

  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ── The Platform dropdown ───────────────────────────────────────────── */
  document.querySelectorAll("details[data-hover]").forEach(function (d) {
    var shut;
    var summary = d.querySelector("summary");
    var openedByHover = false;

    // <details> exposes its state to assistive tech natively, but the handoff
    // names aria-expanded and some older screen-reader pairings still read it,
    // so it is mirrored rather than assumed.
    function sync() { if (summary) summary.setAttribute("aria-expanded", String(d.open)); }
    sync();
    d.addEventListener("toggle", sync);

    // Hover opens it; a click then has to KEEP it open rather than toggle it
    // shut. Without this a mouse user who clicks instead of waiting sees the
    // panel flash open and close, because the pointer had already opened it
    // and <summary> toggles whatever state it finds.
    if (summary) {
      summary.addEventListener("click", function (e) {
        if (openedByHover && d.open) {
          e.preventDefault();
          openedByHover = false;
        }
      });
    }

    // Hover opens it, as the handoff asks. A short delay on leaving, because
    // the pointer has to cross a gap between the summary and the panel and
    // closing the instant it leaves makes the menu feel like it is dodging.
    d.addEventListener("mouseenter", function () {
      window.clearTimeout(shut);
      if (!window.matchMedia("(hover: hover)").matches) return;
      if (!d.open) { d.open = true; openedByHover = true; }
    });
    d.addEventListener("mouseleave", function () {
      if (!window.matchMedia("(hover: hover)").matches) return;
      shut = window.setTimeout(function () {
        d.open = false;
        openedByHover = false;
      }, reduced ? 0 : 140);
    });

    // A click anywhere else closes it. Without this the panel stays open
    // behind whatever the reader went on to do.
    document.addEventListener("click", function (e) {
      if (d.open && !d.contains(e.target)) d.open = false;
    });
    d.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && d.open) {
        d.open = false;
        var s = d.querySelector("summary");
        if (s) s.focus();
      }
    });
  });

  /* ── The drawer ──────────────────────────────────────────────────────── */
  var drawer = document.getElementById("drawer");
  if (!drawer) return;

  var FOCUSABLE =
    'a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])';

  function close() {
    drawer.open = false;
    var s = drawer.querySelector("summary");
    if (s) s.focus();
  }

  // The scrim and the close button both carry data-close-drawer. The scrim
  // must only answer to a click on ITSELF — a click that lands on the sheet
  // bubbles up to it, and closing then would mean the menu shuts whenever
  // somebody touches it.
  drawer.addEventListener("click", function (e) {
    var hit = e.target.closest("[data-close-drawer]");
    if (!hit) return;
    if (hit.classList.contains("drawerScrim") && e.target !== hit) return;
    e.preventDefault();
    close();
  });

  // Following a link closes it, so coming back does not land behind a sheet.
  drawer.querySelectorAll(".drawerNav a, .drawerSheet .btnInk").forEach(function (a) {
    a.addEventListener("click", function () { drawer.open = false; });
  });

  drawer.addEventListener("keydown", function (e) {
    if (e.key === "Escape") { e.preventDefault(); close(); return; }
    if (e.key !== "Tab" || !drawer.open) return;

    // The focus trap the handoff asks for: the sheet is aria-modal, so Tab
    // must not walk out of it into the page behind.
    var sheet = drawer.querySelector(".drawerSheet");
    if (!sheet) return;
    var items = Array.prototype.filter.call(
      sheet.querySelectorAll(FOCUSABLE),
      function (el) { return el.offsetParent !== null; }
    );
    if (!items.length) return;
    var first = items[0], last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  // Opening moves focus into the sheet, and locks the page behind it so the
  // background does not scroll under the reader's thumb.
  drawer.addEventListener("toggle", function () {
    document.body.style.overflow = drawer.open ? "hidden" : "";
    if (!drawer.open) return;
    var target = drawer.querySelector(".drawerNav a");
    if (target) target.focus();
  });
})();
