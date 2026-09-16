<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/console-shell.php';

$staff = require_staff();
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $error = 'That form had expired. Nothing was changed — try again.';
    } elseif ((string)($_POST['next'] ?? '') !== (string)($_POST['again'] ?? '')) {
        // Checked here rather than only in the browser: the two-box check is
        // about typos, and a typo is just as possible with scripting off.
        $error = 'The two new passwords are not the same.';
    } else {
        $r = staff_change_password((string)$staff['id'],
            (string)($_POST['current'] ?? ''), (string)($_POST['next'] ?? ''));
        if ($r['ok']) {
            flash('success', 'Your password is changed', 'Use the new one next time you sign in.');
            header('Location: index.php');
            exit;
        }
        $error = (string)$r['error'];
    }
}

console_head('Change password', $staff, 'index.php', [
    'crumbs' => [['text' => 'Overview', 'href' => 'index.php'], ['text' => 'Change password', 'href' => '#']],
]);
?>

<?php ui_header('Change your password', ['variant' => 'h1',
    'description' => 'You need the one you have now. There is no reset link — an administrator sets you a new one if it is lost.']); ?>

<div style="max-width:460px;margin-top:var(--s-5)">
<?php ui_container_open(); ?>
  <?php if ($error !== null) ui_alert('error', 'Not changed', $error); ?>
  <form method="post" class="stack stack-s" style="<?= $error ? 'margin-top:var(--s-4)' : '' ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?php
    ui_field(['label' => 'Your current password', 'name' => 'current', 'type' => 'password',
        'required' => true, 'autocomplete' => 'current-password']);
    ui_field(['label' => 'New password', 'name' => 'next', 'type' => 'password',
        'required' => true, 'autocomplete' => 'new-password',
        'hint' => 'At least 12 characters. Three or four unrelated words is easier to remember and harder to guess than a short one with symbols in it.']);
    ui_field(['label' => 'New password again', 'name' => 'again', 'type' => 'password',
        'required' => true, 'autocomplete' => 'new-password']);
    ui_form_actions('Change it', 'index.php');
    ?>
  </form>
<?php ui_container_close(); ?>
</div>

<?php console_foot($staff, 'index.php'); ?>
