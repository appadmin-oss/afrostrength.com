<?php
declare(strict_types=1);

require_once __DIR__ . '/assets.php';

/**
 * The studio site's chrome — header, drawer, footer.
 *
 * Built to the design handoff (`Afrostrength Homepage v10`), which specifies
 * every measurement here. Where this file names a number, the handoff named it
 * first; where it names a colour, the colour is a token in base.css and the
 * handoff is the reason that token exists.
 *
 * ── Why <details> and not a JavaScript menu ──────────────────────────────
 *
 * The handoff asks for a Platform dropdown that opens on hover AND click, and
 * a bottom-sheet drawer under 1000px. Both are built on <details>, because a
 * <details> opens when you click its summary with no script running at all.
 *
 * That matters more here than it usually does. This page is read on Nigerian
 * mobile connections where a 3G request for a script times out often enough
 * to plan for, and a navigation that is a dead button when that happens is a
 * site with no navigation. site.js then adds what <details> cannot do by
 * itself — hover-open, Escape, closing on the scrim, and the focus trap the
 * handoff asks for — and everything it adds is an upgrade, never a
 * prerequisite.
 *
 * ── The 1000px threshold ─────────────────────────────────────────────────
 *
 * The handoff drives it from matchMedia and says to prefer CSS in production
 * if the codebase can express it. CSS can, so it does: one media query swaps
 * the desktop nav for the Menu button. No script decides what the page looks
 * like, which also means no flash of the wrong nav before the script lands.
 */

/**
 * Where the academy lives.
 *
 * The handoff's markup links to academy.afrostrength.com and its own notes
 * flag that the academy's flier says afrotech.afrostrength.com, asking for the
 * canonical one to be confirmed before launch. It is config rather than a
 * literal so that confirmation is a one-line change and not a search across
 * the site. The default matches what the academy is actually deployed as.
 */
function academy_url(): string
{
    return (string)cfg('site.academy_url', 'https://afrotech.afrostrength.com');
}

/** The one gradient square the brand is drawn with, at any size. */
function brand_mark(int $px, int $radius = 8): string
{
    return '<span aria-hidden="true" class="brandMark" style="width:' . $px . 'px;height:' . $px
         . 'px;border-radius:' . $radius . 'px"></span>';
}

function icon(string $name, int $size = 24, float $stroke = 1.9): string
{
    $paths = [
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-ne'    => '<path d="M7 17 17 7M9 7h8v8"/>',
        'chevron'     => '<path d="m6 9 6 6 6-6"/>',
        'menu'        => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'       => '<path d="M6 6l12 12M18 6 6 18"/>',
        'tick'        => '<path d="M4 12.5 9 17.5 20 6.5"/>',
        'lock'        => '<path d="M7 10V7a5 5 0 0 1 10 0v3M5 10h14v10H5z"/>',
        'instagram'   => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/>',
        'phone'       => '<path d="M6 3h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 4 5a2 2 0 0 1 2-2z"/>',
        'mail'        => '<path d="M3 6h18v12H3z"/><path d="m3 7 9 6 9-6"/>',
        'cap'         => '<path d="m3 8 9-4 9 4-9 4z"/><path d="M7 11v4c0 1.5 2.2 2.8 5 2.8s5-1.3 5-2.8v-4"/>',
    ];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none"'
         . ' stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round"'
         . ' stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/**
 * @param string $title    the <title>, before the site name
 * @param string $current  a nav key, so the current page can say so
 */
function site_head(string $title, string $current = '', ?string $description = null): void
{
    $academy = academy_url();
    ?>
<!DOCTYPE html>
<html lang="en-NG">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php /* The one colour that cannot be a token: a meta tag has no access to
          CSS custom properties. It is --ink, and it has to be kept in step
          with it by hand. */ ?>
<meta name="theme-color" content="#2B231E">
<title><?= e($title) ?></title>
<?php if ($description !== null): ?>
<meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<?php /* Archivo carries the hero, which is the first thing painted. */ ?>
<link rel="preload" href="<?= e(asset('assets/fonts/archivo.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('assets/fonts/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/site.css')) ?>">
</head>
<body>
<a href="#main" class="skipLink">Skip to content</a>

<header class="siteHead">
  <div class="siteHeadRow">
    <a class="brand" href="<?= e(app_url('')) ?>">
      <?= brand_mark(27) ?>
      <span class="brandName">Afrostrength</span>
    </a>

    <?php /* The desktop nav. Hidden by CSS under 1000px, never by script. */ ?>
    <nav class="navWide" aria-label="Primary">
      <details class="dropdown" data-hover>
        <summary class="navLink dropdownSummary">Platform<?= icon('chevron', 12, 2.2) ?></summary>
        <div class="dropdownPanel">
          <?php foreach ([
            ['brand',    'Brand system',     'Strategy, identity, guidelines'],
            ['digital',  'Digital &amp; growth', 'Web, social, campaign systems'],
            ['software', 'Software',         'Custom applications and integrations'],
          ] as [$tab, $name, $desc]): ?>
            <a class="dropdownRow" href="<?= e(app_url('')) ?>?tab=<?= e($tab) ?>#capabilities">
              <span class="dropdownTitle"><?= $name ?></span>
              <span class="dropdownDesc"><?= e($desc) ?></span>
            </a>
          <?php endforeach; ?>
          <a class="dropdownRow dropdownAll" href="<?= e(app_url('')) ?>#services">All five services →</a>
        </div>
      </details>

      <a class="navLink<?= $current === 'work' ? ' navOn' : '' ?>" href="<?= e(app_url('')) ?>#work">Work</a>
      <a class="navLink<?= $current === 'pricing' ? ' navOn' : '' ?>" href="<?= e(app_url('')) ?>#pricing">Pricing</a>
      <a class="navLink<?= $current === 'directory' ? ' navOn' : '' ?>" href="<?= e(app_url('directory/')) ?>">Directory</a>
      <a class="navLink navExternal" href="<?= e($academy) ?>">Academy<?= icon('arrow-ne', 13, 1.9) ?></a>
      <a class="btnInk" href="<?= e(app_url('')) ?>#contact">Start a project</a>
    </nav>

    <?php /* Under 1000px. A <details> so the sheet opens with no script. */ ?>
    <details class="drawer" id="drawer">
      <summary class="btnMenu" aria-label="Open menu"><?= icon('menu', 18, 1.9) ?>Menu</summary>
      <div class="drawerScrim" data-close-drawer>
        <div class="drawerSheet" role="dialog" aria-modal="true" aria-label="Menu">
          <div class="drawerTop">
            <span class="drawerTitle">Menu</span>
            <button type="button" class="drawerClose" data-close-drawer aria-label="Close menu">
              <?= icon('close', 18, 1.9) ?>
            </button>
          </div>
          <nav class="drawerNav" aria-label="Primary">
            <a href="<?= e(app_url('')) ?>#capabilities">Platform</a>
            <a href="<?= e(app_url('')) ?>#work">Work</a>
            <a href="<?= e(app_url('')) ?>#services">Services</a>
            <a href="<?= e(app_url('')) ?>#pricing">Pricing</a>
            <a href="<?= e(app_url('directory/')) ?>">Contractor directory</a>
            <a href="<?= e($academy) ?>">Academy</a>
          </nav>
          <a class="btnInk btnFull" href="<?= e(app_url('')) ?>#contact">Start a project</a>
        </div>
      </div>
    </details>
  </div>
</header>

<main id="main">
<?php }

function site_foot(): void
{
    $phone = (string)cfg('site.phone', '+234 810 019 1456');
    $phoneHref = (string)cfg('site.phone_href', 'tel:+2348100191456');
    $office = (string)cfg('site.office_email', 'reachus@afrostrength.com');
    $academy = academy_url();

    /*
     * Six columns, as the handoff lists them. Most are routes the rest of the
     * design set covers and this page does not; they point at the sections
     * that exist today rather than at files that do not, so the footer has no
     * dead links on the day it ships.
     */
    $columns = [
      'Services' => [
        ['Brand strategy', '#capabilities'], ['Brand identity', '#capabilities'],
        ['Digital and marketing', '#capabilities'], ['Rebranding and refresh', '#capabilities'],
        ['Software development', '#capabilities'], ['Support and maintenance', '#services'],
        ['Pricing', '#pricing'],
      ],
      'Engagements' => [
        ['Project', '#pricing'], ['Retainer', '#pricing'], ['Consulting', '#pricing'],
        ['How we work', '#process'], ['Discovery', '#process'], ['Handover', '#process'],
      ],
      'Sectors' => [
        ['Consumer goods and retail', '#sectors'], ['Financial services', '#sectors'],
        ['Health and education', '#sectors'], ['Logistics', '#sectors'],
        ['Nonprofits', '#sectors'], ['Events and culture', '#sectors'],
        ['Technology teams', '#sectors'], ['Hospitality', '#sectors'],
      ],
      'Resources' => [
        ['Customer stories', '#case'], ['AfroTech Academy', $academy],
        ['Contractor directory', app_url('directory/')],
        ['Post a job', app_url('post/')], ['Find work', app_url('work/')],
      ],
      'Company' => [
        ['About', '#why'], ['Work', '#work'], ['Contact', '#contact'],
        ['Join the directory', app_url('join/')],
      ],
      'Terms and policies' => [
        ['Privacy notice', app_url('privacy.php')],
      ],
    ];
    ?>
</main>

<footer class="siteFoot">
  <div class="footLinks">
    <div class="footBrandCol">
      <a class="brand brandOnDark" href="<?= e(app_url('')) ?>">
        <?= brand_mark(26) ?>
        <span class="brandName">Afrostrength</span>
      </a>
      <a class="footTeaser" href="<?= e(app_url('')) ?>#brief">
        <span>What do you want built?</span>
        <span class="footTeaserMark" aria-hidden="true"><?= icon('arrow-right', 15, 2.2) ?></span>
      </a>
    </div>
    <div class="footGrid">
      <?php foreach ($columns as $heading => $links): ?>
        <div class="footCol">
          <h2 class="footHead"><?= e($heading) ?></h2>
          <ul class="footList">
            <?php foreach ($links as [$label, $href]): ?>
              <li><a href="<?= e($href) ?>"><?= e($label) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php /* Decorative, and told so: it is the wordmark as texture, not a heading. */ ?>
  <div class="footWordmarkBand"><span class="footWordmark" aria-hidden="true">Afrostrength</span></div>

  <div class="footLegal">
    <ul class="footIcons">
      <li><a href="https://instagram.com/afrostrength" aria-label="Afrostrength on Instagram"><?= icon('instagram', 20, 1.8) ?></a></li>
      <li><a href="<?= e($phoneHref) ?>" aria-label="Call <?= e($phone) ?>"><?= icon('phone', 20, 1.8) ?></a></li>
      <li><a href="mailto:<?= e($office) ?>" aria-label="Email <?= e($office) ?>"><?= icon('mail', 20, 1.8) ?></a></li>
      <li><a href="<?= e($academy) ?>" aria-label="AfroTech Academy"><?= icon('cap', 20, 1.8) ?></a></li>
    </ul>
    <p class="footMeta">
      <span class="footAvailable"><span class="footDot" aria-hidden="true"></span><?= e((string)cfg('site.availability', 'Taking projects for Q4 2026')) ?></span>
      <span class="footCopy">© <?= date('Y') ?> Afrostrength Limited</span>
    </p>
  </div>
</footer>

<script src="<?= e(asset('assets/site.js')) ?>" defer></script>
</body>
</html>
<?php }
