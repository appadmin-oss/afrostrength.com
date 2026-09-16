<?php
declare(strict_types=1);

require_once __DIR__ . '/staff.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/marks.php';
require_once __DIR__ . '/cloudflare.php';
require_once __DIR__ . '/directory.php';

/** The console's chrome. Same grammar as the academy's, different product. */
function console_nav(array $staff): array
{
    $items = [
        ['type' => 'link', 'href' => 'index.php', 'text' => 'Overview',
         'glyph' => 'M4 13h6V4H4zM14 20h6v-9h-6zM4 20h6v-4H4zM14 8h6V4h-6z'],
        ['type' => 'section', 'text' => 'The directory', 'items' => [
            ['type' => 'link', 'href' => 'contractors.php', 'text' => 'Contractors',
             'glyph' => 'M4 20a6 6 0 0 1 12 0M10 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8'],
            ['type' => 'link', 'href' => 'contractors.php?state=pending', 'text' => 'Waiting to be checked',
             'match' => 'contractors.php?state=pending'],
        ]],
        ['type' => 'section', 'text' => 'Work', 'items' => [
            ['type' => 'link', 'href' => 'jobs.php', 'text' => 'Jobs',
             'glyph' => 'M4 7h16v13H4z M9 7V4h6v3'],
            ['type' => 'link', 'href' => 'introductions.php', 'text' => 'Introductions',
             'glyph' => 'M7 12h10M13 8l4 4-4 4'],
        ]],
    ];

    if (is_admin($staff)) {
        $items[] = ['type' => 'divider'];
        $items[] = ['type' => 'section', 'text' => 'Administration', 'items' => [
            ['type' => 'link', 'href' => 'staff.php', 'text' => 'Staff accounts',
             'glyph' => 'M4 20a6 6 0 0 1 12 0M10 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8'],
            ['type' => 'link', 'href' => 'settings.php', 'text' => 'Fee and settings',
             'glyph' => 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6'],
            ['type' => 'link', 'href' => app_url('_ops.php'), 'text' => 'System checks', 'external' => true],
        ]];
    }
    return $items;
}

function console_tabs(array $staff): array
{
    return [
        ['href' => 'index.php', 'text' => 'Overview', 'glyph' => 'M4 13h6V4H4zM14 20h6v-9h-6zM4 20h6v-4H4zM14 8h6V4h-6z'],
        ['href' => 'contractors.php', 'text' => 'Contractors', 'glyph' => 'M4 20a6 6 0 0 1 12 0M10 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8'],
        ['href' => 'jobs.php', 'text' => 'Jobs', 'glyph' => 'M4 7h16v13H4z M9 7V4h6v3'],
        ['href' => 'introductions.php', 'text' => 'Intros', 'glyph' => 'M7 12h10M13 8l4 4-4 4'],
    ];
}

function console_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr((string)end($parts), 0, 1) : ''));
}

function console_role_label(string $role): string
{
    return $role === 'admin' ? 'Administrator' : 'Agent';
}

function console_head(string $title, array $staff, string $current, array $opts = []): void
{
    no_store();
    $nav = console_nav($staff);
    $prefs = ui_prefs();
    $counters = $opts['counters'] ?? [];
    $crumbs = $opts['crumbs'] ?? [];
    $helpTopic = $_GET['help'] ?? ($opts['help'] ?? null);
    ?>
<!DOCTYPE html>
<html lang="en-NG" data-density="<?= e($prefs['density']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#2D2620">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — Afrostrength console</title>
<link rel="preload" href="<?= e(asset('assets/fonts/archivo.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('assets/fonts/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/console.css')) ?>">
</head>
<body class="consoleBody<?= $helpTopic ? ' helpOpen' : '' ?>">
<a href="#main" class="skipLink">Skip to content</a>

<header class="topNav">
  <button type="button" class="navToggle" data-nav-toggle aria-expanded="false" aria-controls="nav">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    <span class="srOnly">Show navigation</span>
  </button>
  <a class="topBrand" href="index.php">
    <span class="mark" aria-hidden="true">AS</span>
    <span class="topBrandText">
      <span class="topBrandName">Afrostrength</span>
      <span class="topBrandSub">Contractors console</span>
    </span>
  </a>
  <div class="topUtils">
    <details class="userMenu">
      <summary class="userButton">
        <span class="avatar" aria-hidden="true"><?= e(console_initials((string)$staff['name'])) ?></span>
        <span class="userText">
          <span class="userName"><?= e((string)$staff['name']) ?></span>
          <span class="userRole"><?= e(console_role_label((string)$staff['role'])) ?></span>
        </span>
        <svg class="userChev" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6l4 4 4-4"/></svg>
      </summary>
      <div class="userPanel">
        <p class="userPanelHead"><?= e((string)$staff['email']) ?></p>
        <hr class="userRule">
        <a class="userLink" href="password.php">Change password</a>
        <form method="post" action="signout.php" class="userSignout">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <button type="submit" class="userLink userDanger">Sign out</button>
        </form>
      </div>
    </details>
  </div>
</header>

<div class="appLayout">
  <nav id="nav" class="sideNav" aria-label="Console sections">
    <div class="sideNavInner">
    <?php foreach ($nav as $item):
      if ($item['type'] === 'divider'): ?><hr class="navRule">
      <?php elseif ($item['type'] === 'section'): ?>
        <div class="navSection">
          <h2 class="navSectionTitle"><?= e($item['text']) ?></h2>
          <ul class="navList">
            <?php foreach ($item['items'] as $link) console_nav_link($link, $current, $counters); ?>
          </ul>
        </div>
      <?php else: ?>
        <ul class="navList navTop"><?php console_nav_link($item, $current, $counters); ?></ul>
      <?php endif;
    endforeach; ?>
    </div>
  </nav>

  <div class="appMain<?= !empty($opts['wide']) ? ' appWide' : '' ?>">
    <?php if ($crumbs !== []): ?>
      <nav class="crumbs" aria-label="Breadcrumbs">
        <ol class="crumbList">
          <?php $last = count($crumbs) - 1;
          foreach ($crumbs as $i => $c): ?>
            <li class="crumb">
              <?php if ($i === $last): ?><span aria-current="page"><?= e($c['text']) ?></span>
              <?php else: ?>
                <a href="<?= e($c['href']) ?>"><?= e($c['text']) ?></a>
                <svg class="crumbSep" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3l5 5-5 5"/></svg>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>
    <?php ui_flashbar(); ?>
    <main id="main" class="appContent">
<?php }

function console_nav_link(array $link, string $current, array $counters = []): void
{
    $key = $link['match'] ?? $link['href'];
    $on = $key === $current;
    $count = $counters[$link['text']] ?? null;
    ?>
    <li>
      <a href="<?= e($link['href']) ?>" class="navLink<?= $on ? ' navOn' : '' ?>" <?= $on ? 'aria-current="page"' : '' ?>>
        <?php if (!empty($link['glyph'])): ?>
          <svg class="navIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="<?= e($link['glyph']) ?>"/>
          </svg>
        <?php else: ?><span class="navIcon navDot" aria-hidden="true"></span><?php endif; ?>
        <span class="navText"><?= e($link['text']) ?></span>
        <?php if ($count): ?><span class="navCount"><?= (int)$count ?></span><?php endif; ?>
      </a>
    </li>
    <?php
}

function console_foot(array $staff, string $current): void
{
    $tabs = console_tabs($staff);
    ?>
    </main>
  </div>
</div>

<nav class="tabBar" aria-label="Console">
  <?php foreach ($tabs as $t):
    $on = $t['href'] === $current; ?>
    <a href="<?= e($t['href']) ?>" class="tabItem<?= $on ? ' tabOn' : '' ?>" <?= $on ? 'aria-current="page"' : '' ?>>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="<?= e($t['glyph']) ?>"/>
      </svg>
      <span><?= e($t['text']) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<script src="<?= e(asset('assets/console.js')) ?>" defer></script>
</body>
</html>
<?php }
