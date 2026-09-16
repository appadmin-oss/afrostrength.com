<?php
declare(strict_types=1);

require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/directory.php';

/** The public chrome. Afrostrength's, not the academy's. */
function page_head(string $title, string $current = '', ?string $description = null): void
{
    ?>
<!DOCTYPE html>
<html lang="en-NG">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#2D2620">
<title><?= e($title) ?> — Afrostrength contractors</title>
<?php if ($description !== null): ?>
<meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<link rel="preload" href="<?= e(asset('assets/fonts/archivo.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('assets/fonts/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/site.css')) ?>">
</head>
<body>
<a href="#main" class="skipLink">Skip to content</a>

<header class="masthead">
  <div class="mastheadRow">
    <a class="mastheadBrand" href="<?= e(app_url('')) ?>">
      <span class="mark" aria-hidden="true">AS</span>
      <span>
        <span class="wordmark">Afrostrength</span>
        <span class="parent">Contractors</span>
      </span>
    </a>
    <nav class="mastheadNav" aria-label="Afrostrength contractors">
      <?php foreach ([
          '' => 'Find someone',
          'post/' => 'Post a job',
          'work/' => 'Find work',
          'join/' => 'Join',
      ] as $href => $label):
        $on = $current === $href; ?>
        <a class="mastheadLink<?= $on ? ' mastheadOn' : '' ?>" href="<?= e(app_url($href)) ?>"
           <?= $on ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<main id="main" class="wrap">
<?php }

function page_foot(): void
{
    $phone = (string)cfg('site.phone', '+234 810 019 1456');
    $phoneHref = (string)cfg('site.phone_href', 'tel:+2348100191456');
    ?>
</main>

<footer class="siteFoot">
  <p>
    Afrostrength Limited ·
    <a href="<?= e($phoneHref) ?>"><?= e($phone) ?></a> ·
    <a href="<?= e(app_url('privacy.php')) ?>">How we handle your data</a>
  </p>
  <p class="fine">
    Afrostrength checks every listing before it appears, and charges a fee when it introduces a
    contractor to a client. It does not hold anyone's money: a client pays their contractor
    directly.
  </p>
</footer>
</body>
</html>
<?php }
