<?php
declare(strict_types=1);

/**
 * Posting a job.
 *
 * The client's details are taken and never shown to contractors. The page
 * says so, because a client who thinks their number is about to go on a
 * public board will not post.
 */

require_once __DIR__ . '/../../lib/shell.php';
require_once __DIR__ . '/../../lib/guards.php';
require_once __DIR__ . '/../../lib/cloudflare.php';

no_store();

$values = ['client_name' => '', 'client_email' => '', 'client_phone' => '', 'title' => '',
           'description' => '', 'trade' => '', 'city' => '', 'budget_band' => '', 'consent' => false];
$errors = [];
$ref = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (array_keys($values) as $k) {
        $values[$k] = $k === 'consent' ? !empty($_POST[$k]) : trim((string)($_POST[$k] ?? ''));
    }
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $errors['_form'] = 'This page was open a while and the form expired. Nothing was lost — press Send again.';
    } else {
        $limit = rate_limit('post:' . real_client_ip(), 5, 900);
        if (!$limit['allowed']) {
            $errors['_form'] = 'That is several in a short time. Wait a few minutes.';
        } else {
            $r = job_create($values);
            if ($r['ok']) $ref = (string)$r['ref'];
            else $errors = $r['errors'];
        }
    }
}

function err(array $e, string $k): string {
    return isset($e[$k]) ? '<p class="fieldError" id="' . e($k) . '-error">'
        . '<span class="srOnly">Error: </span>' . e($e[$k]) . '</p>' : '';
}
function aria(array $e, string $k): string {
    return isset($e[$k]) ? ' aria-invalid="true" aria-describedby="' . e($k) . '-error"' : '';
}

page_head($ref !== null ? 'We have your job' : 'Post a job', 'post/',
  'Describe what needs doing. Afrostrength puts checked contractors in front of you, usually the same day.');
?>

<?php if ($ref !== null): ?>

  <h1>We have it</h1>
  <p class="lede">Your reference is <strong><?= e($ref) ?></strong>. Quote it if you call.</p>

  <p>
    Somebody reads every job. We will come back to you with people who actually do this kind of
    work — not a list of everybody on the directory.
  </p>

  <p><strong>Your name and number stay with us.</strong> Contractors see the job, not you, until
     we introduce somebody. That way your phone does not start ringing at seven in the morning.</p>

  <div class="actions">
    <a class="btn btnPrimary" href="<?= e(app_url('')) ?>">Browse the directory meanwhile</a>
  </div>

<?php else: ?>

  <h1>Post a job</h1>
  <p class="lede">Describe what needs doing. We put the right people in front of you.</p>

  <p class="notice">
    <strong>Your details are not published.</strong> Contractors see the job and where it is —
    never your name, email or phone — until we introduce one to you. Free to post.
  </p>

  <?php if (isset($errors['_form'])): ?>
    <div class="errorSummary" role="alert" tabindex="-1" id="summary">
      <h2>We could not save that</h2>
      <p style="margin:0"><?= e($errors['_form']) ?></p>
    </div>
  <?php elseif ($errors !== []): ?>
    <div class="errorSummary" role="alert" tabindex="-1" id="summary">
      <h2>There <?= count($errors) === 1 ? 'is one thing' : 'are ' . count($errors) . ' things' ?> to fix</h2>
      <ul>
        <?php foreach ($errors as $field => $msg): ?>
          <li><a href="#<?= e($field) ?>"><?= e($msg) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <fieldset>
      <legend>The job</legend>
      <div class="field" id="title">
        <label for="ti">What needs doing</label>
        <span class="hint">One line. "Rewire a three-bedroom flat in Ikeja".</span>
        <input type="text" id="ti" name="title" value="<?= e((string)$values['title']) ?>"<?= aria($errors, 'title') ?>>
        <?= err($errors, 'title') ?>
      </div>
      <div class="field" id="description">
        <label for="de">Tell us more</label>
        <span class="hint" id="de-hint">What the place is like, when you need it, anything already tried. A contractor deciding whether to take this on needs something to go on.</span>
        <textarea id="de" name="description" rows="5" aria-describedby="de-hint"<?= aria($errors, 'description') ?>><?= e((string)$values['description']) ?></textarea>
        <?= err($errors, 'description') ?>
      </div>
      <div class="field" id="trade">
        <label for="tr">What kind of work</label>
        <select id="tr" name="trade"<?= aria($errors, 'trade') ?>>
          <option value="">Choose one</option>
          <?php foreach (TRADES as $t): ?>
            <option value="<?= e($t) ?>"<?= $values['trade'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
        <?= err($errors, 'trade') ?>
      </div>
      <div class="field" id="city">
        <label for="ci">Where</label>
        <input type="text" id="ci" name="city" value="<?= e((string)$values['city']) ?>"<?= aria($errors, 'city') ?>>
        <?= err($errors, 'city') ?>
      </div>
      <div class="field">
        <label for="bb">Roughly what you have in mind</label>
        <span class="hint">A band is fine. It only helps a contractor decide whether to put themselves forward — nobody is held to it.</span>
        <select id="bb" name="budget_band">
          <option value="">Rather not say</option>
          <?php foreach (BUDGET_BANDS as $b): ?>
            <option value="<?= e($b) ?>"<?= $values['budget_band'] === $b ? ' selected' : '' ?>><?= e($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </fieldset>

    <fieldset>
      <legend>You</legend>
      <p class="hint">Not published. This is how we come back to you.</p>
      <div class="field" id="client_name">
        <label for="cn">Your name</label>
        <input type="text" id="cn" name="client_name" autocomplete="name"
               value="<?= e((string)$values['client_name']) ?>"<?= aria($errors, 'client_name') ?>>
        <?= err($errors, 'client_name') ?>
      </div>
      <div class="field" id="client_email">
        <label for="ce">Email</label>
        <input type="email" id="ce" name="client_email" inputmode="email" autocomplete="email"
               value="<?= e((string)$values['client_email']) ?>"<?= aria($errors, 'client_email') ?>>
        <?= err($errors, 'client_email') ?>
      </div>
      <div class="field">
        <label for="cp">Phone</label>
        <input type="tel" id="cp" name="client_phone" inputmode="tel" autocomplete="tel"
               value="<?= e((string)$values['client_phone']) ?>">
      </div>
    </fieldset>

    <div class="field" id="consent">
      <label class="consent">
        <input type="checkbox" name="consent" value="1"<?= $values['consent'] ? ' checked' : '' ?><?= aria($errors, 'consent') ?>>
        <span>I agree to Afrostrength holding these details to find me a contractor, and to
              passing them to one when we agree an introduction.</span>
      </label>
      <?= err($errors, 'consent') ?>
    </div>

    <div class="actions">
      <button class="btn btnPrimary" type="submit">Send it</button>
    </div>
  </form>

<?php endif; ?>

<?php if ($errors !== []): ?>
<script>document.getElementById('summary')?.focus();</script>
<?php endif; ?>

<?php page_foot(); ?>
