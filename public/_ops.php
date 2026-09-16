<?php
declare(strict_types=1);

/**
 * The operations page. Behind ops_token; 404 without one.
 *
 * Same idea as the academy's: there is no shell on a shared host, so the
 * checks come to you. Nothing here prints a password or a token.
 */

require_once __DIR__ . '/../lib/directory.php';
require_once __DIR__ . '/../lib/staff.php';
require_once __DIR__ . '/../lib/migrate.php';
require_once __DIR__ . '/../lib/guards.php';
require_once __DIR__ . '/../lib/assets.php';

no_store();
header('X-Robots-Tag: noindex, nofollow, noarchive');

$token = (string)cfg('ops_token', '');
$given = (string)($_GET['token'] ?? $_POST['token'] ?? '');
if ($token === '' || strlen($given) !== strlen($token) || !hash_equals($token, $given)) {
    http_response_code(404);
    exit;
}

$notice = null; $ok = true; $newPassword = null;
$csrf = csrf_token();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_ok($_POST['csrf'] ?? null)) {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'migrate') {
        $r = migrations_run('ops');
        $ok = $r['failed'] === null;
        $notice = $ok
            ? ($r['applied'] ? 'Applied: ' . implode(', ', $r['applied']) : 'Nothing to do.')
            : "Migration {$r['failed']['name']} failed: {$r['failed']['error']}";
    } elseif ($action === 'first_admin') {
        if (staff_count() > 0) {
            $ok = false;
            $notice = 'There is already a staff account. Add colleagues from inside the console.';
        } else {
            $password = staff_random_password();
            $r = staff_create((string)($_POST['admin_email'] ?? ''), (string)($_POST['admin_name'] ?? ''), $password, 'admin');
            $ok = $r['ok'];
            if ($ok) { $newPassword = $password; $notice = 'Administrator created. The password is below and is shown once.'; }
            else $notice = (string)$r['error'];
        }
    }
}

$checks = [];
$checks['PHP version'] = [PHP_VERSION_ID >= 80100, PHP_VERSION];
$checks['Database'] = [db_ready(), db_ready() ? 'connected' : 'cannot connect — check lib/config.php'];
if (db_ready()) {
    foreach (['contractors','contractor_skills','jobs','job_responses','introductions','staff','settings'] as $t) {
        $there = db_one("SELECT COUNT(*) AS n FROM information_schema.tables
                          WHERE table_schema = DATABASE() AND table_name = ?", [$t]);
        $checks["Table: $t"] = [(int)($there['n'] ?? 0) === 1, (int)($there['n'] ?? 0) === 1 ? 'there' : 'MISSING — run the migrations'];
    }
    $checks['Staff accounts'] = [staff_count() > 0, staff_count() . ' active'];
}
$checks['var/ is writable'] = [is_writable(dirname(__DIR__) . '/var'), dirname(__DIR__) . '/var'];
$checks['Debug is off'] = [!cfg('debug', false), cfg('debug', false) ? 'ON — turn it off in production' : 'off'];

$failures = count(array_filter($checks, fn($c) => !$c[0]));
$self = htmlspecialchars((string)($_SERVER['PHP_SELF'] ?? '_ops.php'), ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en-NG"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>System checks — Afrostrength contractors</title>
<link rel="stylesheet" href="<?= e(asset('assets/base.css')) ?>">
</head><body><main class="wrap">
<h1>System checks</h1>
<p class="lede"><?= $failures === 0 ? 'Everything below passed.' : $failures . ' thing(s) need attention.' ?></p>

<?php if ($notice !== null): ?>
  <div class="<?= $ok ? 'notice' : 'errorSummary' ?>"><p style="margin:0"><?= e($notice) ?></p></div>
<?php endif; ?>

<?php if ($newPassword !== null): ?>
  <div class="notice">
    <p style="margin:0 0 8px"><strong>Write this down now.</strong> It is shown once and cannot be recovered.</p>
    <p style="margin:0;font:700 20px/1.5 ui-monospace,monospace;letter-spacing:.04em;user-select:all"><?= e($newPassword) ?></p>
    <p style="margin:8px 0 0">Sign in at <a href="console/signin.php">console/signin.php</a>.</p>
  </div>
<?php endif; ?>

<?php if (db_ready() && staff_count() === 0): ?>
  <h2>There is no way into the console yet</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="token" value="<?= e($given) ?>">
    <input type="hidden" name="action" value="first_admin">
    <div class="field"><label for="an">Full name</label><input type="text" id="an" name="admin_name" required></div>
    <div class="field"><label for="ae">Work email</label><input type="email" id="ae" name="admin_email" required></div>
    <button class="btn btnPrimary" type="submit">Create administrator</button>
  </form>
<?php endif; ?>

<h2>Things you would otherwise need a shell for</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="token" value="<?= e($given) ?>">
  <input type="hidden" name="action" value="migrate">
  <button class="btn btnSecondary" type="submit">Run the migrations</button>
</form>

<h2>Checks</h2>
<ul>
  <?php foreach ($checks as $name => [$pass, $detail]): ?>
    <li><strong><?= $pass ? 'PASS' : 'FAIL' ?></strong> — <?= e($name) ?>: <?= e((string)$detail) ?></li>
  <?php endforeach; ?>
</ul>
</main></body></html>
