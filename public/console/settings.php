<?php
declare(strict_types=1);

/**
 * The introduction fee.
 *
 * Zero out of the migration, deliberately. The software must not invent what
 * Afrostrength charges, and a number nobody chose is exactly the sort that
 * ends up on an invoice.
 *
 * Changing it does NOT restate what is already owed. Every introduction
 * snapshots the fee at the moment it was made, because somebody who was told
 * ₦25,000 was told ₦25,000.
 */

require_once __DIR__ . '/../../lib/console-shell.php';

$me = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        flash('error', 'That form had expired', 'Nothing was changed. Try again.');
    } else {
        $amount = parse_naira((string)($_POST['amount'] ?? ''));
        if (!$amount['ok']) {
            flash('error', 'Not changed', (string)$amount['error']);
        } else {
            set_introduction_fee((int)$amount['kobo'], (string)$me['name']);
            flash('success', 'Fee set to ' . naira((int)$amount['kobo']),
                'It applies to introductions made from now on. Ones already made keep the figure they were made at.');
        }
    }
    header('Location: settings.php');
    exit;
}

$fee = introduction_fee_kobo();
$outstanding = introduction_totals();

console_head('Fee and settings', $me, 'settings.php', [
    'crumbs' => [['text' => 'Overview', 'href' => 'index.php'], ['text' => 'Fee and settings', 'href' => '#']],
]);
?>

<?php ui_header('Fee and settings', ['variant' => 'h1',
    'description' => 'What Afrostrength charges for putting a contractor in front of a client.']); ?>

<div class="stack stack-m">

  <?php ui_container_open('The introduction fee'); ?>
    <p style="margin:0 0 var(--s-4)">
      It is <strong><?= $fee > 0 ? e(naira($fee)) : 'not set' ?></strong>.
      <?php if ($fee === 0): ?>
        Until it is, introductions are recorded at zero — which is fine while you are finding
        the number, and a problem if you forget.
      <?php endif; ?>
    </p>

    <?php ui_alert('info', 'Changing it does not restate what is already owed',
      'Every introduction keeps the figure it was made at. Somebody told ₦25,000 was told ₦25,000, and a ledger that quietly rewrites that is not a ledger.'); ?>

    <form method="post" class="stack stack-s" style="margin-top:var(--s-4)">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <?php ui_field(['label' => 'Amount', 'name' => 'amount', 'required' => true,
          'value' => $fee > 0 ? number_format($fee / 100, 2, '.', '') : '',
          'inputmode' => 'decimal',
          'hint' => 'In naira, like 25000. Written down as whole kobo, so nothing is lost in rounding.']); ?>
      <?php ui_form_actions('Set it'); ?>
    </form>
  <?php ui_container_close(); ?>

  <?php ui_container_open('What that means today'); ?>
    <div class="cols cols-3">
      <?php foreach ([['Made, not yet invoiced', 'made'], ['Invoiced', 'invoiced'], ['Paid', 'paid']] as [$label, $k]): ?>
        <div style="padding:var(--s-4);border:1px solid var(--border);border-radius:9px;background:var(--cream)">
          <span style="display:block;font-size:var(--t-small);color:var(--muted)"><?= e($label) ?></span>
          <span style="display:block;font:600 22px/1.25 var(--font-display);margin-top:2px">
            <?= e(naira((int)$outstanding[$k]['kobo'])) ?></span>
          <span style="display:block;font-size:var(--t-small);color:var(--muted)">
            <?= (int)$outstanding[$k]['n'] ?> introduction<?= (int)$outstanding[$k]['n'] === 1 ? '' : 's' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php ui_container_close(); ?>
</div>

<?php console_foot($me, 'settings.php'); ?>
