<?php
declare(strict_types=1);

/**
 * One contractor's listing.
 *
 * Their contact details ARE shown — they are advertising, and a directory
 * that hides the phone number is not a directory. The fee is charged on the
 * job board, where the client's details are the thing being withheld.
 */

require_once __DIR__ . '/../lib/shell.php';
require_once __DIR__ . '/../lib/cloudflare.php';

cache_public(300);

$c = contractor_by_slug((string)($_GET['c'] ?? ''));
if ($c === null) {
    http_response_code(404);
    page_head('Not found');
    echo '<h1>That listing is not here</h1>';
    echo '<p class="lede">It may have been withdrawn, or the link may be wrong.</p>';
    echo '<p><a class="btn btnPrimary" href="' . e(app_url('')) . '">Back to the directory</a></p>';
    page_foot();
    exit;
}

$skills = contractor_skills((string)$c['id']);

page_head($c['full_name'] . ' — ' . $c['trade'], '',
  $c['headline'] . ' ' . $c['city'] . '. Checked by Afrostrength.');
?>

<p class="screenNote"><a href="<?= e(app_url('')) ?>">← All contractors</a></p>

<h1><?= e((string)$c['full_name']) ?></h1>
<p class="lede"><?= e((string)$c['headline']) ?></p>

<p>
  <span class="checkedMark">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M3 8.5 6.5 12 13 4.5"/>
    </svg>
    Checked by Afrostrength<?php
      if ($c['verified_at']) echo ' · ' . e((new DateTimeImmutable((string)$c['verified_at']))->format('M Y')); ?>
  </span>
</p>

<div class="panel">
  <ul class="moduleList">
    <li class="module"><span class="moduleBody">
      <span class="moduleState">Trade</span><span class="moduleName"><?= e((string)$c['trade']) ?></span>
    </span></li>
    <li class="module"><span class="moduleBody">
      <span class="moduleState">Works in</span><span class="moduleName"><?= e((string)$c['city']) ?></span>
    </span></li>
    <?php if ($c['years'] !== null): ?>
      <li class="module"><span class="moduleBody">
        <span class="moduleState">Doing this for</span>
        <span class="moduleName"><?= (int)$c['years'] ?> year<?= (int)$c['years'] === 1 ? '' : 's' ?></span>
      </span></li>
    <?php endif; ?>
  </ul>
</div>

<?php if ($skills !== []): ?>
  <h2>What they do</h2>
  <p><?= e(implode(' · ', $skills)) ?></p>
<?php endif; ?>

<?php if ($c['about']): ?>
  <h2>In their words</h2>
  <p style="white-space:pre-wrap"><?= e((string)$c['about']) ?></p>
<?php endif; ?>

<h2>Get in touch</h2>
<div class="actions">
  <a class="btn btnPrimary" href="mailto:<?= e((string)$c['email']) ?>">Email <?= e(explode(' ', (string)$c['full_name'])[0]) ?></a>
  <?php if ($c['phone']): ?>
    <a class="btn btnSecondary" href="tel:<?= e((string)$c['phone']) ?>">Call <?= e((string)$c['phone']) ?></a>
  <?php endif; ?>
</div>

<p class="fine">
  Afrostrength checked who this person is and that they do this work. We are not a party to
  anything you agree with them, we do not hold your money, and we would still like to hear
  about it either way — good or bad, it is how the directory stays worth something.
</p>

<?php page_foot(); ?>
