<?php
declare(strict_types=1);

/** Minutes-ago wording, without pulling in the academy's working-day maths. */
function relative_time(?string $sql): string
{
    if ($sql === null || $sql === '') return '';
    try { $then = new DateTimeImmutable($sql); } catch (Throwable $e) { return ''; }
    $secs = time() - $then->getTimestamp();
    if ($secs < 90) return 'just now';
    if ($secs < 3600) return intdiv($secs, 60) . ' min ago';
    if ($secs < 86400) { $h = intdiv($secs, 3600); return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago'; }
    $d = intdiv($secs, 86400);
    if ($d < 31) return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    $m = intdiv($d, 30);
    return $m < 12 ? $m . ' month' . ($m === 1 ? '' : 's') . ' ago' : intdiv($d, 365) . ' years ago';
}

function human_date(?string $sql): string
{
    if ($sql === null || $sql === '') return '';
    try { return (new DateTimeImmutable($sql))->format('j M Y'); } catch (Throwable $e) { return $sql; }
}


/**
 * Staff accounts. Administrators only.
 *
 * Accounts live in a table rather than in config.php because adding a
 * colleague should not mean editing a PHP file over FTP — and because a
 * password in a config file is a password in every backup of that file.
 *
 * An account is deactivated, never deleted. Their notes, decisions and marks
 * carry their name, and deleting the row would turn a signed record into an
 * anonymous one.
 */

require_once __DIR__ . '/../../lib/console-shell.php';


$me = require_admin();
$newPassword = null;
$newFor = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_ok($_POST['csrf'] ?? null)) {
        flash('error', 'That form had expired', 'Nothing was saved. Try again.');
        header('Location: staff.php');
        exit;
    }

    if ($action === 'create') {
        $password = staff_random_password();
        $r = staff_create(
            (string)($_POST['email'] ?? ''),
            (string)($_POST['name'] ?? ''),
            $password,
            (string)($_POST['role'] ?? ''),
        );
        if ($r['ok']) {
            // Shown on THIS response and never again — it is not in the
            // session, not in the URL and not in the log.
            $newPassword = $password;
            $newFor = trim((string)($_POST['name'] ?? ''));
        } else {
            flash('error', 'The account was not created', (string)$r['error']);
            header('Location: staff.php');
            exit;
        }
    } elseif ($action === 'reset') {
        $id = (string)($_POST['id'] ?? '');
        if ($id === (string)$me['id']) {
            flash('error', 'Use Change password for your own account',
                'Resetting your own would sign you out holding a password you have not read yet.');
        } else {
            $r = staff_reset_password($id);
            if ($r['ok']) { $newPassword = $r['password']; $newFor = (string)($_POST['name'] ?? 'that account'); }
            else flash('error', 'Could not reset it', (string)$r['error']);
        }
        if ($newPassword === null) { header('Location: staff.php'); exit; }
    } elseif ($action === 'active') {
        $id = (string)($_POST['id'] ?? '');
        $want = ($_POST['want'] ?? '') === 'on';
        if ($id === (string)$me['id'] && !$want) {
            // The last administrator locking themselves out would leave the
            // console unreachable and the applications unworked.
            flash('error', 'You cannot deactivate your own account',
                'Ask another administrator to do it, so somebody is always able to get in.');
        } else {
            staff_set_active($id, $want);
            flash('success', $want ? 'Account reactivated' : 'Account deactivated',
                $want ? 'They can sign in again.'
                      : 'They cannot sign in. Everything they did keeps their name on it.');
        }
        header('Location: staff.php');
        exit;
    }
}

$rows = staff_all();
$active = array_filter($rows, fn($r) => (bool)$r['is_active']);

console_head('Staff accounts', $me, 'staff.php', [
    'crumbs' => [['text' => 'Overview', 'href' => 'index.php'], ['text' => 'Staff accounts', 'href' => '#']],
]);
?>

<?php ui_header('Staff accounts', [
    'variant' => 'h1',
    'counter' => '(' . count($active) . ' active)',
    'description' => 'Who can sign in, and what each of them can reach.',
    'info' => 'accounts',
]); ?>

<div class="stack stack-m">

<?php if ($newPassword !== null): ?>
  <div class="alert alert-success" role="alert" tabindex="-1" id="pw">
    <span class="alertIcon"><?= ui_status('success', '') ?></span>
    <div class="alertBody">
      <p class="alertHead">Password for <?= e((string)$newFor) ?> — write it down now</p>
      <p class="alertText">It is shown once and cannot be recovered. It was not emailed. Pass it
        on in person or by phone, and ask them to change it after they sign in.</p>
      <p style="margin:var(--s-3) 0 0;font:700 20px/1.5 var(--font-mono);letter-spacing:.04em;
                word-break:break-all;user-select:all"><?= e($newPassword) ?></p>
    </div>
  </div>
  <script>document.getElementById('pw')?.focus();</script>
<?php endif; ?>

  <?php ui_container_open('Add a colleague', ['info' => 'accounts']); ?>
    <form method="post" class="stack stack-s">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="cols cols-2">
        <?php ui_field(['label' => 'Full name', 'name' => 'name', 'required' => true,
            'hint' => 'It is what a contractor sees when somebody here writes to them.',
            'autocomplete' => 'off']); ?>
        <?php ui_field(['label' => 'Work email', 'name' => 'email', 'type' => 'email',
            'required' => true, 'autocomplete' => 'off', 'inputmode' => 'email']); ?>
      </div>
      <div class="cols cols-2">
        <?php ui_field(['label' => 'Role', 'name' => 'role', 'type' => 'select', 'required' => true,
            'options' => ['agent' => 'Agent — checks listings and makes introductions',
                          'admin' => 'Administrator — everything, plus staff and settings'],
            'hint' => 'Both roles work the directory. Only an administrator can add colleagues or change the fee.']); ?>
      </div>
      <?php ui_form_actions('Create account'); ?>
      <p style="margin:0;font-size:var(--t-small);color:var(--muted)">
        The password is generated here and shown once. Nothing is emailed — a password in an
        inbox is a password in whatever else can read that inbox.
      </p>
    </form>
  <?php ui_container_close(); ?>

  <?php ui_container_open('Everyone', ['counter' => '(' . count($rows) . ')', 'class' => 'boxFlush']); ?>
    <div class="tableWrap">
      <table class="dataTable">
        <caption class="srOnly">Staff accounts, active first</caption>
        <thead><tr>
          <th scope="col">Name</th><th scope="col">Role</th>
          <th scope="col">Last signed in</th><th scope="col">State</th><th scope="col">Actions</th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r): $isMe = (string)$r['id'] === (string)$me['id']; ?>
            <tr>
              <td>
                <span class="rowName"><?= e((string)$r['name']) ?><?= $isMe ? ' ' . ui_badge('you', 'grey') : '' ?></span>
                <span class="rowSub"><?= e((string)$r['email']) ?></span>
              </td>
              <td><?= e(console_role_label((string)$r['role'])) ?></td>
              <td style="white-space:nowrap"><?= $r['last_seen_at']
                  ? e(relative_time((string)$r['last_seen_at']))
                  : '<span class="kvDash">never</span>' ?></td>
              <td><?= $r['is_active'] ? ui_status('success', 'Active') : ui_status('stopped', 'Deactivated') ?></td>
              <td>
                <div style="display:flex;gap:var(--s-2);flex-wrap:wrap">
                  <?php if (!$isMe): ?>
                    <form method="post" style="margin:0">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="reset">
                      <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                      <input type="hidden" name="name" value="<?= e((string)$r['name']) ?>">
                      <button class="btn btn-normal" type="submit">Reset password</button>
                    </form>
                    <form method="post" style="margin:0">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="active">
                      <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                      <input type="hidden" name="want" value="<?= $r['is_active'] ? 'off' : 'on' ?>">
                      <button class="btn <?= $r['is_active'] ? 'btn-danger' : 'btn-normal' ?>" type="submit">
                        <?= $r['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                      </button>
                    </form>
                  <?php else: ?>
                    <a class="btn btn-link" href="password.php">Change your password</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php ui_container_close(); ?>

</div>

<?php console_foot($me, 'staff.php'); ?>
