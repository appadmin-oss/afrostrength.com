<?php
declare(strict_types=1);

/**
 * Putting yourself on the directory.
 *
 * Nothing appears until somebody checks it, and the form says so before they
 * start rather than after they submit. Being told at the end is how you make
 * somebody feel tricked.
 */

require_once __DIR__ . '/../../lib/shell.php';
require_once __DIR__ . '/../../lib/guards.php';
require_once __DIR__ . '/../../lib/cloudflare.php';

no_store();

$values = ['full_name' => '', 'trade' => '', 'headline' => '', 'about' => '', 'email' => '',
           'phone' => '', 'city' => '', 'years' => '', 'skills' => '', 'consent' => false];
$errors = [];
$done = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (array_keys($values) as $k) {
        $values[$k] = $k === 'consent' ? !empty($_POST[$k]) : trim((string)($_POST[$k] ?? ''));
    }
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $errors['_form'] = 'This page was open a while and the form expired. Nothing was lost — press Send again.';
    } else {
        $limit = rate_limit('join:' . real_client_ip(), 4, 900);
        if (!$limit['allowed']) {
            $errors['_form'] = 'That is several in a short time. Wait a few minutes.';
        } else {
            $r = contractor_create($values);
            if ($r['ok']) $done = true;
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

page_head($done ? 'We have your listing' : 'Join the directory', 'join/',
  'Put yourself in front of clients across Lagos. Afrostrength checks every listing before it appears.');
?>

<?php if ($done): ?>

  <h1>We have it</h1>
  <p class="lede">Somebody will check it and be in touch. Usually within two working days.</p>

  <p>
    Your listing is not live yet, and that is the point — every one on the directory has been
    checked, which is why a client trusts it. We may ring you to confirm a few things.
  </p>

  <div class="actions">
    <a class="btn btnPrimary" href="<?= e(app_url('directory/')) ?>">See the directory</a>
    <a class="btn btnSecondary" href="<?= e(app_url('work/')) ?>">See what work is about</a>
  </div>

<?php else: ?>

  <h1>Join the directory</h1>
  <p class="lede">
    Free to list. We take a fee only when we put you in front of a client who then hires you.
  </p>

  <p class="notice">
    <strong>Nothing goes live until we have checked it.</strong> That is what makes the
    directory worth being on — and it means we may call you before your listing appears.
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
      <legend>You</legend>
      <div class="field" id="full_name">
        <label for="fn">Your name</label>
        <input type="text" id="fn" name="full_name" autocomplete="name"
               value="<?= e((string)$values['full_name']) ?>"<?= aria($errors, 'full_name') ?>>
        <?= err($errors, 'full_name') ?>
      </div>
      <div class="field" id="email">
        <label for="em">Email</label>
        <input type="email" id="em" name="email" inputmode="email" autocomplete="email"
               value="<?= e((string)$values['email']) ?>"<?= aria($errors, 'email') ?>>
        <?= err($errors, 'email') ?>
      </div>
      <div class="field">
        <label for="ph">Phone</label>
        <span class="hint">Shown on your listing, so clients can call you.</span>
        <input type="tel" id="ph" name="phone" inputmode="tel" autocomplete="tel"
               value="<?= e((string)$values['phone']) ?>">
      </div>
      <div class="field" id="city">
        <label for="ci">Where you work</label>
        <span class="hint">The areas you will actually travel to.</span>
        <input type="text" id="ci" name="city" value="<?= e((string)$values['city']) ?>"<?= aria($errors, 'city') ?>>
        <?= err($errors, 'city') ?>
      </div>
    </fieldset>

    <fieldset>
      <legend>Your work</legend>
      <div class="field" id="trade">
        <label for="tr">Your main trade</label>
        <select id="tr" name="trade"<?= aria($errors, 'trade') ?>>
          <option value="">Choose one</option>
          <?php foreach (TRADES as $t): ?>
            <option value="<?= e($t) ?>"<?= $values['trade'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
        <?= err($errors, 'trade') ?>
      </div>
      <div class="field" id="headline">
        <label for="hl">One line about what you do</label>
        <span class="hint" id="hl-hint">This is what a client reads first. "Rewiring and fault-finding for homes and small offices" beats "Electrician".</span>
        <input type="text" id="hl" name="headline" maxlength="200" aria-describedby="hl-hint"
               value="<?= e((string)$values['headline']) ?>"<?= aria($errors, 'headline') ?>>
        <?= err($errors, 'headline') ?>
      </div>
      <div class="field">
        <label for="sk">What you can do</label>
        <span class="hint">Separated by commas. Up to twelve.</span>
        <input type="text" id="sk" name="skills" value="<?= e((string)$values['skills']) ?>"
               placeholder="Rewiring, distribution boards, fault-finding, inverter installs">
      </div>
      <div class="field" id="years">
        <label for="yr">Years doing this</label>
        <input type="number" id="yr" name="years" inputmode="numeric" min="0" max="70"
               value="<?= e((string)$values['years']) ?>"<?= aria($errors, 'years') ?>>
        <?= err($errors, 'years') ?>
      </div>
      <div class="field">
        <label for="ab">Anything else</label>
        <span class="hint">Jobs you are proud of, who you have worked for, how you charge.</span>
        <textarea id="ab" name="about" rows="4"><?= e((string)$values['about']) ?></textarea>
      </div>
    </fieldset>

    <div class="field" id="consent">
      <label class="consent">
        <input type="checkbox" name="consent" value="1"<?= $values['consent'] ? ' checked' : '' ?><?= aria($errors, 'consent') ?>>
        <span>I agree to Afrostrength listing these details publicly so clients can find and
              contact me. I can ask for them to be changed or taken down at any time.</span>
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
