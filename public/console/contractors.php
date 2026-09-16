<?php
declare(strict_types=1);

/**
 * Checking listings.
 *
 * The queue that makes the directory worth being on. Pending first, because
 * somebody is waiting to be let in and nothing else here is time-sensitive.
 */

require_once __DIR__ . '/../../lib/console-shell.php';

$staff = require_staff();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        flash('error', 'That form had expired', 'Nothing was saved. Try again.');
    } elseif (($_POST['action'] ?? '') === 'prefs') {
        ui_prefs_save((string)($_POST['density'] ?? ''), (int)($_POST['page_size'] ?? 25));
    } else {
        $r = contractor_set_state((string)($_POST['id'] ?? ''), (string)($_POST['state'] ?? ''),
            (string)($_POST['note'] ?? ''), $staff);
        flash($r['ok'] ? 'success' : 'error',
              $r['ok'] ? 'Listing updated' : 'Not updated',
              $r['ok'] ? (($_POST['state'] ?? '') === 'verified' ? 'It is live on the directory now.' : 'It is off the directory.')
                       : (string)$r['error']);
    }
    header('Location: ' . ui_query([]));
    exit;
}

$prefs = ui_prefs();
$q = trim((string)($_GET['q'] ?? ''));
$state = (string)($_GET['state'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = $prefs['page_size'];

$where = ['1=1'];
$args = [];
if (in_array($state, CONTRACTOR_STATES, true)) { $where[] = 'state = ?'; $args[] = $state; }
if ($q !== '') {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR trade LIKE ? OR city LIKE ?)';
    $like = '%' . $q . '%';
    array_push($args, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$total = (int)(db_one("SELECT COUNT(*) AS n FROM contractors WHERE $whereSql", $args)['n'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$rows = db_all(
    "SELECT * FROM contractors WHERE $whereSql
      ORDER BY FIELD(state,'pending','verified','suspended','withdrawn'), created_at DESC
      LIMIT " . (int)$perPage . " OFFSET " . (int)(($page - 1) * $perPage),
    $args,
);
$pending = (int)(db_one("SELECT COUNT(*) AS n FROM contractors WHERE state = 'pending'")['n'] ?? 0);

$current = $state === 'pending' ? 'contractors.php?state=pending' : 'contractors.php';
console_head('Contractors', $staff, $current, [
    'counters' => ['Waiting to be checked' => $pending],
    'crumbs' => [['text' => 'Overview', 'href' => 'index.php'], ['text' => 'Contractors', 'href' => '#']],
]);
?>

<?php ui_header('Contractors', [
    'variant' => 'h1',
    'counter' => '(' . number_format($total) . ')',
    'description' => 'Nothing is on the directory until somebody here has checked it. That is what a client is paying for.',
]); ?>

<div class="stack stack-m">

<?php if ($pending > 0 && $state !== 'pending'): ?>
  <?php ui_alert('info', $pending . ' ' . ($pending === 1 ? 'listing is' : 'listings are') . ' waiting to be checked',
    'Somebody put themselves forward and is waiting to hear.',
    ui_button('Check those', ['href' => 'contractors.php?state=pending', 'variant' => 'primary'])); ?>
<?php endif; ?>

<section class="box boxFlush">
  <div class="tableTools">
    <?php ui_text_filter($q, 'Find by name, email, trade or city'); ?>
    <?php
    $opts = ['' => 'Any state'];
    foreach (CONTRACTOR_STATES as $s) $opts[$s] = ucfirst($s);
    ui_select_filter('state', 'State', $opts, $state);
    ui_preferences_control($prefs);
    ?>
  </div>

  <?php if ($rows === []): ?>
    <?php if ($q !== '' || $state !== '') { ui_no_match('contractors.php'); }
    else { ui_empty('Nobody has put themselves forward yet',
      'When somebody fills in the join form they appear here to be checked.'); } ?>
  <?php else: ?>
    <div class="tableWrap">
      <table class="dataTable">
        <caption class="srOnly">Contractor listings, unchecked ones first</caption>
        <thead><tr>
          <th scope="col">Name</th><th scope="col">Trade</th><th scope="col">Where</th>
          <th scope="col">Contact</th><th scope="col">State</th><th scope="col">Action</th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $c): ?>
            <tr>
              <td>
                <span class="rowName"><?= e((string)$c['full_name']) ?></span>
                <span class="rowSub"><?= e((string)$c['headline']) ?></span>
              </td>
              <td><?= e((string)$c['trade']) ?>
                <?php if ($c['years'] !== null): ?><span class="rowSub"><?= (int)$c['years'] ?> yrs</span><?php endif; ?></td>
              <td><?= e((string)$c['city']) ?></td>
              <td>
                <a href="mailto:<?= e((string)$c['email']) ?>"><?= e((string)$c['email']) ?></a>
                <?php if ($c['phone']): ?><span class="rowSub"><?= e((string)$c['phone']) ?></span><?php endif; ?>
              </td>
              <td>
                <?= match ((string)$c['state']) {
                  'pending'   => ui_status('pending', 'Waiting'),
                  'verified'  => ui_status('success', 'On the directory'),
                  'suspended' => ui_status('error', 'Suspended'),
                  default     => ui_status('stopped', 'Withdrawn'),
                } ?>
                <?php if ($c['state_note']): ?><span class="rowSub"><?= e((string)$c['state_note']) ?></span><?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <?php if ($c['state'] === 'verified'): ?>
                    <a class="btn btn-link" href="<?= e(app_url('contractor.php?c=' . urlencode((string)$c['slug']))) ?>">View</a>
                  <?php endif; ?>
                  <details class="prefs">
                    <summary class="prefsButton">Set state</summary>
                    <form class="prefsPanel" method="post" style="width:280px">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="id" value="<?= e((string)$c['id']) ?>">
                      <?php ui_field(['label' => 'State', 'name' => 'state', 'type' => 'select',
                          'value' => (string)$c['state'],
                          'options' => ['verified' => 'Put it on the directory', 'pending' => 'Back to waiting',
                                        'suspended' => 'Suspend it', 'withdrawn' => 'Withdraw it']]); ?>
                      <?php ui_field(['label' => 'Why', 'name' => 'note', 'type' => 'textarea', 'rows' => 2,
                          'hint' => 'Required to suspend or withdraw. Write it as if they will read it, because they may.']); ?>
                      <?php ui_form_actions('Set it'); ?>
                    </form>
                  </details>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php ui_pagination($page, $pages, $total, 'listing'); ?>
  <?php endif; ?>
</section>
</div>

<?php console_foot($staff, $current); ?>
