<?php
declare(strict_types=1);

/**
 * Staff sign-in.
 *
 * Deliberately plain. No navigation, no brand essay, nothing to read — the
 * person here has done this four hundred times and wants two fields and a
 * button. The one thing it does say is what the console is for, because a
 * sign-in page with no context is where phishing reports come from.
 */

require_once __DIR__ . '/../../lib/staff.php';
require_once __DIR__ . '/../../lib/ui.php';
require_once __DIR__ . '/../../lib/assets.php';
require_once __DIR__ . '/../../lib/cloudflare.php';

no_store();
header('X-Robots-Tag: noindex, nofollow, noarchive');

// Already signed in? Then this page is a dead end. Send them on.
if (current_staff() !== null) {
    header('Location: index.php');
    exit;
}

$error = null;
$email = '';
$noAccounts = db_ready() ? staff_count() === 0 : false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!csrf_ok($_POST['csrf'] ?? null)) {
        // Not "invalid token". The person did nothing wrong and the fix is
        // to try again, so that is what it says.
        $error = 'This page had been open a while and the form expired. Try again.';
    } else {
        $result = staff_signin($email, $password);
        if ($result['ok']) {
            // Where they were going before the guard sent them here.
            $next = (string)($_SESSION['after_signin'] ?? 'index.php');
            unset($_SESSION['after_signin']);
            // Only a path inside the console — an open redirect on a sign-in
            // page is how a convincing phishing link gets built.
            if (!preg_match('#^[a-z0-9_\-]+\.php(\?[^\s]*)?$#i', $next)) $next = 'index.php';
            header('Location: ' . $next);
            exit;
        }
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en-NG">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#2D2620">
<meta name="robots" content="noindex, nofollow">
<title>Sign in — Afrostrength console</title>
<link rel="preload" href="<?= e(asset('assets/fonts/archivo.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(asset('assets/fonts/instrument-sans.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('assets/fonts/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/console.css')) ?>">
<style>
  .signinPage {
    min-height: 100vh; display: grid; place-items: center;
    padding: var(--s-6) var(--s-4); background: var(--tint);
  }
  .signinCard {
    width: 100%; max-width: 420px;
    background: var(--page); border: 1px solid var(--border); border-radius: 13px;
    padding: var(--s-8) var(--s-6);
    box-shadow: 0 1px 2px rgba(45,38,32,.05);
  }
  .signinBrand { display: flex; align-items: center; gap: var(--s-3); margin-bottom: var(--s-6); }
  /* Ink on cream, not white on red: the mark at 32px in brand red on white
     measures 3.31:1 and fails. */
  .signinBrand .mark {
    display: grid; place-items: center; width: 40px; height: 40px; border-radius: 9px;
    background: var(--ink); color: #fff;
    font: 700 15px/1 var(--font-display); letter-spacing: .04em;
  }
  .signinName { font: 600 var(--t-lead)/1.2 var(--font-display); letter-spacing: -.015em; }
  .signinSub { font-size: var(--t-small); color: var(--muted); }
  .signinTitle { margin: 0 0 var(--s-2); font: 600 var(--t-h2)/1.25 var(--font-display); letter-spacing: -.02em; }
  .signinLead { margin: 0 0 var(--s-6); color: var(--muted); font-size: var(--t-body); }
  .signinFields { display: grid; gap: var(--s-4); }
  .signinFoot {
    margin: var(--s-6) 0 0; padding-top: var(--s-5);
    border-top: 1px solid var(--hair); font-size: var(--t-small); color: var(--muted);
  }
  .signinFoot a { color: var(--focus); }
  .signinAway { display: block; text-align: center; margin-top: var(--s-4); font-size: var(--t-small); }
</style>
</head>
<body class="signinPage">
<main class="signinCard">
  <div class="signinBrand">
    <span class="mark" aria-hidden="true">AS</span>
    <span>
      <span class="signinName">Afrostrength</span><br>
      <span class="signinSub">Staff console</span>
    </span>
  </div>

  <h1 class="signinTitle">Sign in</h1>
  <p class="signinLead">For Afrostrength staff who work the contractor directory.</p>

  <?php if ($noAccounts): ?>
    <?php ui_alert('warning', 'No staff accounts exist yet',
        'The first administrator is created from the system checks page, which needs the ops token from lib/config.php.'); ?>
  <?php endif; ?>

  <?php if ($error !== null): ?>
    <?php ui_alert('error', 'Could not sign you in', $error); ?>
  <?php endif; ?>

  <form method="post" class="signinFields" action="signin.php" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?php
    ui_field([
        'label' => 'Work email', 'name' => 'email', 'type' => 'email',
        'value' => $email, 'required' => true,
        'autocomplete' => 'username', 'inputmode' => 'email',
    ]);
    ui_field([
        'label' => 'Password', 'name' => 'password', 'type' => 'password',
        'required' => true, 'autocomplete' => 'current-password',
    ]);
    echo ui_button('Sign in', ['variant' => 'primary', 'full' => true]);
    ?>
  </form>

  <p class="signinFoot">
    Forgotten it? An administrator can set you a new one — there is no reset
    link, because a reset link in an inbox is a way into every learner's
    record. Ask in the office.
  </p>
  <a class="signinAway" href="<?= e(base_path() === '' ? '/' : base_path() . '/') ?>">Back to the directory</a>
</main>
</body>
</html>
