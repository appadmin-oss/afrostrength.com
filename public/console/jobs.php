<?php
declare(strict_types=1);

/**
 * Jobs, and making the introduction.
 *
 * This is the only place the client's details and a contractor's details sit
 * on the same screen. That is the product, and it is why it is behind a
 * sign-in.
 */

require_once __DIR__ . '/../../lib/console-shell.php';

$staff = require_staff();
$open = (string)($_GET['job'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $jobId = (string)($_POST['job_id'] ?? '');
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        flash('error', 'That form had expired', 'Nothing was saved. Try again.');
    } elseif ($action === 'introduce') {
        $r = introduction_make($jobId, (string)($_POST['contractor_id'] ?? ''), $staff);
        flash($r['ok'] ? 'success' : 'error',
              $r['ok'] ? 'Introduced ' . (string)$r['contractor'] : 'Not introduced',
              $r['ok'] ? 'Send the two of them each other\'s details. The fee is on the introductions page.'
                       : (string)$r['error']);
    } elseif ($action === 'state') {
        db_run("UPDATE jobs SET state = ?, closed_reason = ?, updated_at = ? WHERE id = ?",
            [(string)($_POST['state'] ?? 'open'), trim((string)($_POST['reason'] ?? '')) ?: null, now_sql(), $jobId]);
        flash('success', 'Job updated', '');
    }
    header('Location: jobs.php' . ($jobId !== '' ? '?job=' . urlencode($jobId) : ''));
    exit;
}

$job = $open !== '' ? job_get_full($open) : null;
$responses = $job !== null ? job_responses((string)$job['id']) : [];
$introduced = $job !== null
    ? array_column(db_all("SELECT contractor_id FROM introductions WHERE job_id = ?", [(string)$job['id']]), 'contractor_id')
    : [];

$jobs = db_all(
    "SELECT j.*, (SELECT COUNT(*) FROM job_responses r WHERE r.job_id = j.id) AS responses,
            (SELECT COUNT(*) FROM introductions i WHERE i.job_id = j.id) AS intros
       FROM jobs j ORDER BY FIELD(j.state,'open','introducing','filled','closed'), j.created_at DESC",
);
$openCount = count(array_filter($jobs, fn($j) => (string)$j['state'] === 'open'));

console_head('Jobs', $staff, 'jobs.php', [
    'crumbs' => [['text' => 'Overview', 'href' => 'index.php'], ['text' => 'Jobs', 'href' => '#']],
]);
?>

<?php ui_header('Jobs', [
    'variant' => 'h1',
    'counter' => '(' . $openCount . ' open)',
    'description' => 'What clients have posted. The client\'s details are here and nowhere a contractor can reach.',
]); ?>

<div class="stack stack-m">

<?php if ($job !== null): ?>
  <?php ui_container_open($job['title'], [
      'description' => (string)$job['ref'] . ' · ' . (string)$job['trade'] . ' · ' . (string)$job['city']
          . ((string)($job['budget_band'] ?? '') !== '' ? ' · ' . (string)$job['budget_band'] : ''),
      'actions' => ui_button('All jobs', ['href' => 'jobs.php', 'variant' => 'normal']),
  ]); ?>
    <?php ui_key_values([
        ['label' => 'Client', 'value' => e((string)$job['client_name'])],
        ['label' => 'Email', 'value' => '<a href="mailto:' . e((string)$job['client_email']) . '">' . e((string)$job['client_email']) . '</a>'],
        ['label' => 'Phone', 'value' => $job['client_phone'] ? '<a href="tel:' . e((string)$job['client_phone']) . '">' . e((string)$job['client_phone']) . '</a>' : ''],
    ], 3); ?>
    <p style="margin:var(--s-4) 0 0;white-space:pre-wrap"><?= e((string)$job['description']) ?></p>
  <?php ui_container_close(); ?>

  <?php ui_container_open('Who has put themselves forward', ['counter' => '(' . count($responses) . ')']); ?>
    <?php if ($responses === []): ?>
      <?php ui_empty('Nobody yet',
        'Contractors respond from the job board, or you put somebody forward from the directory.'); ?>
    <?php else: ?>
      <ol class="stack stack-s" style="list-style:none;margin:0;padding:0">
        <?php foreach ($responses as $r):
          $already = in_array((string)$r['contractor_id'], $introduced, true); ?>
          <li style="padding-top:var(--s-3);border-top:1px solid var(--hair)">
            <p style="margin:0;display:flex;gap:var(--s-3);align-items:baseline;flex-wrap:wrap">
              <strong><?= e((string)$r['full_name']) ?></strong>
              <span style="font-size:var(--t-small);color:var(--muted)">
                <?= e((string)$r['trade']) ?> · <?= e((string)$r['city']) ?>
              </span>
              <?= (string)$r['contractor_state'] === 'verified'
                  ? ui_status('success', 'Checked') : ui_status('warning', 'Not checked') ?>
            </p>
            <p style="margin:4px 0 0;white-space:pre-wrap"><?= e((string)$r['message']) ?></p>
            <div style="margin-top:var(--s-3)">
              <?php if ($already): ?>
                <?= ui_status('success', 'Already introduced') ?>
              <?php else: ?>
                <form method="post" style="margin:0">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="introduce">
                  <input type="hidden" name="job_id" value="<?= e((string)$job['id']) ?>">
                  <input type="hidden" name="contractor_id" value="<?= e((string)$r['contractor_id']) ?>">
                  <button class="btn btn-primary" type="submit">Introduce them</button>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  <?php ui_container_close(); ?>

  <?php ui_container_open('Close it off'); ?>
    <form method="post" style="display:flex;gap:var(--s-3);align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="state">
      <input type="hidden" name="job_id" value="<?= e((string)$job['id']) ?>">
      <div style="flex:1 1 200px">
        <?php ui_field(['label' => 'State', 'name' => 'state', 'type' => 'select', 'value' => (string)$job['state'],
            'options' => ['open' => 'Open', 'introducing' => 'Introducing', 'filled' => 'Filled', 'closed' => 'Closed']]); ?>
      </div>
      <div style="flex:2 1 280px">
        <?php ui_field(['label' => 'Note', 'name' => 'reason']); ?>
      </div>
      <?= ui_button('Set it', ['variant' => 'normal']) ?>
    </form>
  <?php ui_container_close(); ?>
<?php endif; ?>

  <?php ui_container_open('All jobs', ['counter' => '(' . count($jobs) . ')', 'class' => 'boxFlush']); ?>
    <?php if ($jobs === []): ?>
      <?php ui_empty('Nothing posted yet', 'Jobs appear here as clients post them.'); ?>
    <?php else: ?>
      <div class="tableWrap">
        <table class="dataTable">
          <caption class="srOnly">Jobs, open ones first</caption>
          <thead><tr>
            <th scope="col">Reference</th><th scope="col">What</th><th scope="col">Where</th>
            <th scope="col">Responses</th><th scope="col">Introduced</th><th scope="col">State</th>
          </tr></thead>
          <tbody>
            <?php foreach ($jobs as $j): ?>
              <tr>
                <td><a class="rowRef" href="jobs.php?job=<?= e((string)$j['id']) ?>"><?= e((string)$j['ref']) ?></a></td>
                <td><span class="rowName"><?= e((string)$j['title']) ?></span>
                    <span class="rowSub"><?= e((string)$j['trade']) ?> · <?= e((string)$j['client_name']) ?></span></td>
                <td><?= e((string)$j['city']) ?></td>
                <td><?= (int)$j['responses'] ?></td>
                <td><?= (int)$j['intros'] ?></td>
                <td><?= match ((string)$j['state']) {
                      'open'        => ui_status('pending', 'Open'),
                      'introducing' => ui_status('in-progress', 'Introducing'),
                      'filled'      => ui_status('success', 'Filled'),
                      default       => ui_status('stopped', 'Closed'),
                    } ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php ui_container_close(); ?>
</div>

<?php console_foot($staff, 'jobs.php'); ?>
