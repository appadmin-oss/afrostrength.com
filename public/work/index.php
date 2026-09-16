<?php
declare(strict_types=1);

/**
 * The job board, as a contractor sees it.
 *
 * The client is not on this page, and not in the query that built it. That is
 * the product: Afrostrength is paid for the introduction, and a board that
 * gave away the phone number would be paid for nothing.
 */

require_once __DIR__ . '/../../lib/shell.php';
require_once __DIR__ . '/../../lib/cloudflare.php';

cache_public(60);

$trade = (string)($_GET['trade'] ?? '');
$city = trim((string)($_GET['city'] ?? ''));
$jobs = jobs_open_for_contractors([
    'trade' => in_array($trade, TRADES, true) ? $trade : '',
    'city' => $city,
]);

page_head('Find work', 'work/',
  'Open jobs across Lagos. Free to see. Afrostrength introduces you when a client wants to talk.');
?>

<h1>Find work</h1>
<p class="lede">
  Jobs clients have posted. Free to look, free to put yourself forward.
</p>

<p class="notice">
  You have to be <a href="<?= e(app_url('join/')) ?>">on the directory</a> and checked before you
  can respond. That is what a client is paying us for.
</p>

<form class="filterBar" method="get">
  <div class="field">
    <label for="trade">Trade</label>
    <select id="trade" name="trade">
      <option value="">Any trade</option>
      <?php foreach (TRADES as $t): ?>
        <option value="<?= e($t) ?>"<?= $trade === $t ? ' selected' : '' ?>><?= e($t) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="city">Where</label>
    <input type="text" id="city" name="city" value="<?= e($city) ?>" placeholder="Ikeja, Lekki…">
  </div>
  <button class="btn btnPrimary" type="submit">Filter</button>
</form>

<?php if ($jobs === []): ?>
  <div class="panel" style="margin-top:var(--s-6)">
    <div style="padding:var(--s-8) var(--s-5);text-align:center">
      <p style="margin:0;font:600 var(--t-lead)/1.4 var(--font-display)">Nothing open right now</p>
      <p style="margin:var(--s-2) 0 0;color:var(--muted)">
        Nothing is invented here — this is what clients have actually posted.
        <a href="<?= e(app_url('join/')) ?>">Get listed</a> and we will come to you when something fits.
      </p>
    </div>
  </div>
<?php else: ?>
  <ul class="jobList">
    <?php foreach ($jobs as $j): ?>
      <li class="job">
        <h2 class="jobTitle"><?= e((string)$j['title']) ?></h2>
        <p class="jobMeta">
          <?= e((string)$j['ref']) ?> · <?= e((string)$j['trade']) ?> · <?= e((string)$j['city']) ?>
          <?php if ($j['budget_band']): ?> · <?= e((string)$j['budget_band']) ?><?php endif; ?>
          · <?= (int)$j['responses'] ?> <?= (int)$j['responses'] === 1 ? 'response' : 'responses' ?>
        </p>
        <p class="jobBody"><?= e((string)$j['description']) ?></p>
        <p class="jobPrivate">
          The client's details are with Afrostrength. Call the office quoting
          <strong><?= e((string)$j['ref']) ?></strong> and we will put you forward if it fits.
        </p>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php page_foot(); ?>
