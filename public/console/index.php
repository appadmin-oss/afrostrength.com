<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/console-shell.php';

$staff = require_staff();

$pending = (int)(db_one("SELECT COUNT(*) AS n FROM contractors WHERE state = 'pending'")['n'] ?? 0);
$listed  = (int)(db_one("SELECT COUNT(*) AS n FROM contractors WHERE state = 'verified'")['n'] ?? 0);
$openJobs = (int)(db_one("SELECT COUNT(*) AS n FROM jobs WHERE state = 'open'")['n'] ?? 0);
$totals = introduction_totals();
$first = explode(' ', trim((string)$staff['name']))[0];
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

console_head('Overview', $staff, 'index.php', ['counters' => ['Waiting to be checked' => $pending]]);
?>

<?php ui_header($greeting . ', ' . $first, ['variant' => 'h1',
    'description' => 'The contractor directory and job board.']); ?>

<div class="stack stack-m">

<?php if ($pending > 0): ?>
  <?php ui_alert('info', $pending . ' ' . ($pending === 1 ? 'listing is' : 'listings are') . ' waiting to be checked',
    'Somebody put themselves forward and is waiting to hear back.',
    ui_button('Check those', ['href' => 'contractors.php?state=pending', 'variant' => 'primary'])); ?>
<?php else: ?>
  <?php ui_alert('success', 'Nothing waiting to be checked', 'Every listing has been looked at.'); ?>
<?php endif; ?>

  <?php ui_container_open('Where things stand'); ?>
    <div class="cols cols-4">
      <?php foreach ([
          ['On the directory', (string)$listed, 'contractors.php?state=verified'],
          ['Waiting to be checked', (string)$pending, 'contractors.php?state=pending'],
          ['Jobs open', (string)$openJobs, 'jobs.php'],
          ['Fees outstanding', naira((int)$totals['made']['kobo'] + (int)$totals['invoiced']['kobo']), 'introductions.php'],
      ] as [$label, $value, $href]): ?>
        <a href="<?= e($href) ?>" style="text-decoration:none;color:inherit;display:block;padding:var(--s-4);
                 border:1px solid var(--border);border-radius:9px;background:var(--cream)">
          <span style="display:block;font-size:var(--t-small);color:var(--muted)"><?= e($label) ?></span>
          <span style="display:block;font:600 24px/1.2 var(--font-display);margin-top:2px"><?= e($value) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php ui_container_close(); ?>
</div>

<?php console_foot($staff, 'index.php'); ?>
