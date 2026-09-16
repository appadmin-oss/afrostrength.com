<?php
declare(strict_types=1);

/**
 * The academy's small marks.
 *
 * Inline SVG rather than a Lottie player — see the note at the top of
 * motion.css for why. Each is drawn in the same hand: a 1.8px stroke, round
 * caps, no fills, sized in a 24 or 40 unit box. They are one accent colour or
 * currentColor, never a palette.
 *
 * Every one is decorative. The meaning is always in the text beside it, so
 * they are `aria-hidden` and a reader who never sees them loses nothing.
 */

/**
 * A tick that draws itself.
 *
 * Used once per page, on a confirmation, after a real write. A tick that
 * draws says "this just happened"; a tick that is simply present says "this
 * was always true", and on a submission confirmation that difference is the
 * whole reassurance.
 */
function mark_check(int $size = 40): string
{
    return <<<SVG
<svg class="markDraw" width="{$size}" height="{$size}" viewBox="0 0 40 40" fill="none"
     aria-hidden="true" focusable="false">
  <circle cx="20" cy="20" r="17" stroke="currentColor" stroke-width="1.8"
          opacity="0.28" style="--len:107"/>
  <polyline class="delay1" points="12.5,20.5 17.8,25.8 27.5,14.5" stroke="currentColor"
            stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="--len:29"/>
</svg>
SVG;
}

/**
 * Work in somebody's hands.
 *
 * A page with a slow pulse, not a spinner. Nothing is loading — a person has
 * to read it, and that takes days. A spinner would promise seconds.
 */
function mark_waiting(int $size = 40): string
{
    return <<<SVG
<svg class="pending" width="{$size}" height="{$size}" viewBox="0 0 40 40" fill="none"
     aria-hidden="true" focusable="false">
  <path d="M13 8h10l6 6v18a2 2 0 0 1-2 2H13a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2z"
        stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
  <path d="M23 8v6h6" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
  <path d="M16 23h8M16 27h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
</svg>
SVG;
}

/**
 * An empty list.
 *
 * Deliberately not a sad face or an open box — an empty state is not a
 * failure, and drawing it as one teaches people to distrust the screen. This
 * is a clean sheet: the thing that is true, which is that nothing is here yet.
 */
function mark_empty(int $size = 40): string
{
    return <<<SVG
<svg width="{$size}" height="{$size}" viewBox="0 0 40 40" fill="none"
     aria-hidden="true" focusable="false" opacity="0.55">
  <rect x="9" y="7" width="22" height="26" rx="2.5" stroke="currentColor" stroke-width="1.8"/>
  <path d="M15 15h10M15 20h10M15 25h6" stroke="currentColor" stroke-width="1.8"
        stroke-linecap="round" opacity="0.45"/>
</svg>
SVG;
}

/** A module finished. Same tick, smaller, no draw — it is a state, not news. */
function mark_done(int $size = 20): string
{
    return <<<SVG
<svg width="{$size}" height="{$size}" viewBox="0 0 20 20" fill="none"
     aria-hidden="true" focusable="false">
  <polyline points="5,10.5 8.5,14 15,6.5" stroke="currentColor" stroke-width="2.2"
            stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;
}

/** A module not open yet. */
function mark_locked(int $size = 20): string
{
    return <<<SVG
<svg width="{$size}" height="{$size}" viewBox="0 0 20 20" fill="none"
     aria-hidden="true" focusable="false">
  <rect x="4.5" y="9" width="11" height="7.5" rx="1.6" stroke="currentColor" stroke-width="1.6"/>
  <path d="M7.2 9V6.8a2.8 2.8 0 0 1 5.6 0V9" stroke="currentColor" stroke-width="1.6"
        stroke-linecap="round"/>
</svg>
SVG;
}

/**
 * A progress ring.
 *
 * The number sits inside it and is the actual signal; the ring agrees. Drawn
 * with a dash offset rather than an arc path so the geometry is one line of
 * arithmetic instead of trigonometry nobody will want to edit later.
 */
function mark_ring(int $percent, int $size = 72): string
{
    $percent = max(0, min(100, $percent));
    $r = 32;
    $c = 2 * M_PI * $r;
    $offset = $c * (1 - $percent / 100);
    $cf = number_format($c, 2, '.', '');
    $of = number_format($offset, 2, '.', '');

    return <<<SVG
<svg class="ring" width="{$size}" height="{$size}" viewBox="0 0 72 72" fill="none"
     aria-hidden="true" focusable="false">
  <circle cx="36" cy="36" r="{$r}" stroke="var(--hair)" stroke-width="6"/>
  <circle cx="36" cy="36" r="{$r}" stroke="var(--red-deep)" stroke-width="6"
          stroke-linecap="round" transform="rotate(-90 36 36)"
          stroke-dasharray="{$cf}" stroke-dashoffset="{$of}"
          style="--to:{$of};--c:{$cf}"/>
</svg>
SVG;
}

/** A search that found nothing. Not an error — a question with no answer yet. */
function mark_no_results(int $size = 40): string
{
    return <<<SVG
<svg width="{$size}" height="{$size}" viewBox="0 0 40 40" fill="none"
     aria-hidden="true" focusable="false" opacity="0.55">
  <circle cx="18" cy="18" r="10" stroke="currentColor" stroke-width="1.8"/>
  <path d="M25.5 25.5 33 33" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
</svg>
SVG;
}

/** An envelope, for "we have emailed you". Drawn, because it just happened. */
function mark_sent(int $size = 40): string
{
    return <<<SVG
<svg class="markDraw" width="{$size}" height="{$size}" viewBox="0 0 40 40" fill="none"
     aria-hidden="true" focusable="false">
  <rect x="6" y="11" width="28" height="19" rx="2.5" stroke="currentColor"
        stroke-width="1.8" style="--len:94"/>
  <path class="delay1" d="M6.5 13 20 22.5 33.5 13" stroke="currentColor" stroke-width="1.8"
        stroke-linecap="round" stroke-linejoin="round" style="--len:34"/>
</svg>
SVG;
}
