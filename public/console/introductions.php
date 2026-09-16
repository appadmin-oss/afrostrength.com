<?php
declare(strict_types=1);

/**
 * The fee ledger.
 *
 * One row per introduction Afrostrength has made. Same rules as the academy's
 * charges ledger: raised, then settled, never edited, and a payment needs a
 * bank reference because otherwise "did they pay" is answered by trusting
 * whoever pressed the button.
 */

require_once __DIR__ . '/../../lib/console-shell.php';

$staff = require_staff();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        flash('error', 'That form had expired', 'Nothing was saved. Try again.');
    } else {
        $r = introduction_settle((string)($_POST['id'] ?? ''), (string)($_POST['state'] ?? ''), [
            'reference' => $_POST['reference'] ?? '', 'note' => $_POST['note'] ?? '',
        ], $staff);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Recorded' : 'Not recorded', (string)($r['error'] ?? ''));
    }
    header('Location: ' . ui_query([]));
    exit;
}

$state = (string)($_GET['state'] ?? '');
$rows = introductions_all(in_array($state, ['made','invoiced','paid','waived','void'], true) ? $state : null);
$totals = introduction_totals();

console_head('Introductions', $staff, 'introductions.php', [
    'crumbs' => [['text' => 'Overview', 'href' => 'index.php'], ['text' => 'Introductions', 'href' => '#']],
]);
?>

<?php ui_header('Introductions', [
    'variant' => 'h1',
    'counter' => '(' . count($rows) . ')',
    'description' => 'The fee is for putting a named contractor in front of a named client. No money moves through this system — the client pays their contractor directly.',
]); ?>

<div class="stack stack-m">

  <?php ui_container_open('Where things stand'); ?>
    <div class="cols cols-4">
      <?php foreach ([['Made', 'made'], ['Invoiced', 'invoiced'], ['Paid', 'paid'], ['Waived', 'waived']] as [$label, $k]): ?>
        <a href="<?= e(ui_query(['state' => $k])) ?>"
           style="text-decoration:none;color:inherit;display:block;padding:var(--s-4);
                  border:1px solid var(--border);border-radius:9px;background:var(--cream)">
          <span style="display:block;font-size:var(--t-small);color:var(--muted)"><?= e($label) ?></span>
          <span style="display:block;font:600 22px/1.25 var(--font-display);margin-top:2px"><?= e(naira((int)$totals[$k]['kobo'])) ?></span>
          <span style="display:block;font-size:var(--t-small);color:var(--muted)"><?= (int)$totals[$k]['n'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php ui_container_close(); ?>

  <?php ui_container_open('Every introduction', ['class' => 'boxFlush']); ?>
    <?php if ($rows === []): ?>
      <?php ui_empty('None made yet',
        'An introduction is recorded when you put a contractor in front of a client, from the job page.'); ?>
    <?php else: ?>
      <div class="tableWrap">
        <table class="dataTable">
          <caption class="srOnly">Introductions and their fees</caption>
          <thead><tr>
            <th scope="col">Job</th><th scope="col">Contractor</th><th scope="col">Client</th>
            <th scope="col">Fee</th><th scope="col">State</th><th scope="col">Made</th><th scope="col">Action</th>
          </tr></thead>
          <tbody>
            <?php foreach ($rows as $i): ?>
              <tr>
                <td><a class="rowRef" href="jobs.php?job=<?= e((string)$i['job_id']) ?>"><?= e((string)$i['ref']) ?></a>
                    <span class="rowSub"><?= e((string)$i['title']) ?></span></td>
                <td><?= e((string)$i['contractor_name']) ?></td>
                <td><?= e((string)$i['client_name']) ?></td>
                <td style="font-family:var(--font-mono);white-space:nowrap"><?= e(naira((int)$i['fee_kobo'])) ?></td>
                <td><?= match ((string)$i['state']) {
                      'made'     => ui_status('pending', 'Made'),
                      'invoiced' => ui_status('in-progress', 'Invoiced'),
                      'paid'     => ui_status('success', 'Paid'),
                      'waived'   => ui_status('info', 'Waived'),
                      default    => ui_status('stopped', 'Written off'),
                    } ?>
                    <?php if ($i['reference']): ?><span class="rowSub"><?= e((string)$i['reference']) ?></span><?php endif; ?></td>
                <td style="white-space:nowrap"><?= e((new DateTimeImmutable((string)$i['made_at']))->format('j M Y')) ?>
                    <span class="rowSub"><?= e((string)$i['made_by']) ?></span></td>
                <td>
                  <?php if (in_array((string)$i['state'], ['made', 'invoiced'], true)): ?>
                    <details class="prefs">
                      <summary class="prefsButton">Record</summary>
                      <form class="prefsPanel" method="post" style="width:300px">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= e((string)$i['id']) ?>">
                        <?php ui_field(['label' => 'What happened', 'name' => 'state', 'type' => 'select',
                            'options' => ['invoiced' => 'Invoiced them', 'paid' => 'They paid',
                                          'waived' => 'Waive it', 'void' => 'Write it off']]); ?>
                        <?php ui_field(['label' => 'Bank reference', 'name' => 'reference',
                            'hint' => 'Needed when they paid.']); ?>
                        <?php ui_field(['label' => 'Note', 'name' => 'note', 'type' => 'textarea', 'rows' => 2,
                            'hint' => 'Required for a waiver or a write-off.']); ?>
                        <?php ui_form_actions('Record it'); ?>
                      </form>
                    </details>
                  <?php else: ?><span class="kvDash">—</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php ui_container_close(); ?>
</div>

<?php console_foot($staff, 'introductions.php'); ?>
