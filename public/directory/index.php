<?php
declare(strict_types=1);

/**
 * The directory.
 *
 * Verified listings only, enforced in the query rather than in this template.
 * A page that forgets to filter must not be able to show an unchecked one.
 */

require_once __DIR__ . '/../../lib/shell.php';
require_once __DIR__ . '/../../lib/cloudflare.php';

cache_public(120);

$q = trim((string)($_GET['q'] ?? ''));
$trade = (string)($_GET['trade'] ?? '');
$city = trim((string)($_GET['city'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;

$filter = ['q' => $q, 'trade' => in_array($trade, TRADES, true) ? $trade : '', 'city' => $city];
$total = directory_count($filter);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$rows = directory_search($filter, $perPage, ($page - 1) * $perPage);

page_head('Find a contractor', '',
  'Checked electricians, plumbers, carpenters and more across Lagos. Afrostrength verifies every listing before it appears.');
?>

<h1>Find someone who can do it</h1>
<p class="lede">
  Every listing here has been checked by somebody at Afrostrength. That is the point of it —
  an unchecked directory is a phone book.
</p>

<form class="filterBar" method="get" role="search">
  <div class="field">
    <label for="q">Search</label>
    <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="A name, a trade, a skill">
  </div>
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
  <button class="btn btnPrimary" type="submit">Search</button>
  <?php if ($q !== '' || $trade !== '' || $city !== ''): ?>
    <a class="btn btnSecondary" href="<?= e(app_url('directory/')) ?>">Clear</a>
  <?php endif; ?>
</form>

<?php if ($rows === []): ?>
  <div class="panel" style="margin-top:var(--s-6)">
    <div style="padding:var(--s-8) var(--s-5);text-align:center">
      <p style="margin:0;font:600 var(--t-lead)/1.4 var(--font-display)">
        <?= $q !== '' || $trade !== '' || $city !== '' ? 'Nothing matches that' : 'Nobody listed yet' ?>
      </p>
      <p style="margin:var(--s-2) 0 0;color:var(--muted)">
        <?php if ($q !== '' || $trade !== '' || $city !== ''): ?>
          Try a wider search — or <a href="<?= e(app_url('post/')) ?>">post the job</a> and let
          the right person come to you.
        <?php else: ?>
          Listings appear here once they have been checked. If you do this work,
          <a href="<?= e(app_url('join/')) ?>">put yourself forward</a>.
        <?php endif; ?>
      </p>
    </div>
  </div>
<?php else: ?>
  <p class="screenNote" style="margin-top:var(--s-6)">
    <?= number_format($total) ?> checked listing<?= $total === 1 ? '' : 's' ?>.
  </p>
  <ul class="cardGrid">
    <?php foreach ($rows as $c): ?>
      <li class="card">
        <span class="cardTrade"><?= e((string)$c['trade']) ?></span>
        <h2 class="cardName">
          <a href="<?= e(app_url('directory/contractor.php?c=' . urlencode((string)$c['slug']))) ?>">
            <?= e((string)$c['full_name']) ?>
          </a>
        </h2>
        <p class="cardHeadline"><?= e((string)$c['headline']) ?></p>
        <p class="cardMeta">
          <?= e((string)$c['city']) ?><?php
            if ($c['years'] !== null) echo ' · ' . (int)$c['years'] . ' year' . ((int)$c['years'] === 1 ? '' : 's');
          ?><br>
          <span class="checkedMark">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M3 8.5 6.5 12 13 4.5"/>
            </svg>
            Checked by Afrostrength
          </span>
        </p>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pages" style="margin-top:var(--s-6)">
      <p class="pagerCount">Page <?= $page ?> of <?= $pages ?></p>
      <ul class="pagerList">
        <?php for ($i = 1; $i <= $pages; $i++):
          $qs = http_build_query(array_filter(['q' => $q, 'trade' => $trade, 'city' => $city, 'page' => $i])); ?>
          <li><?= $i === $page
              ? '<span class="pagerLink pagerOn" aria-current="page">' . $i . '</span>'
              : '<a class="pagerLink" href="?' . e($qs) . '">' . $i . '</a>' ?></li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<div class="panel" style="margin-top:var(--s-8)">
  <div style="padding:var(--s-5)">
    <h2 style="margin:0 0 var(--s-2);font:600 var(--t-h3)/1.3 var(--font-display)">Not sure who you need?</h2>
    <p style="margin:0 0 var(--s-4)">
      Describe the job instead. We read every one and put the right people in front of you —
      usually the same day.
    </p>
    <a class="btn btnPrimary" href="<?= e(app_url('post/')) ?>">Post a job</a>
  </div>
</div>

<?php page_foot(); ?>
