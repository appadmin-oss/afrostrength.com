<?php
declare(strict_types=1);

/**
 * The privacy notice.
 *
 * Linked from every page's footer, and it was a dead link. A notice that
 * does not exist is worse than no link at all: the footer was promising
 * something the site could not produce, which is the one thing a privacy
 * notice must never do.
 */

require_once __DIR__ . '/../lib/shell.php';
require_once __DIR__ . '/../lib/cloudflare.php';

cache_public(3600);

$office = (string)cfg('site.office_email', 'reachus@afrostrength.com');
$phone  = (string)cfg('site.phone', '+234 810 019 1456');

page_head('How we handle your data', '',
  'What Afrostrength holds about contractors and clients, why, and how to get a copy or have it deleted.');
?>

<h1>How we handle your data</h1>
<p class="lede">
  Written under the Nigeria Data Protection Act 2023. Short, because there is not much to say.
</p>

<h2>If you are a contractor</h2>
<p>
  We hold what you put on your listing: your name, trade, city, how long you have been doing
  this, your email and phone, and anything you wrote about yourself. <strong>All of it is
  published</strong> — that is the point of a directory, and you agreed to it when you joined.
</p>
<p>
  We also hold whether somebody here has checked your listing and, if we suspended it, why.
</p>

<h2>If you are a client</h2>
<p>
  We hold your name, email, phone and the job you described. <strong>Your contact details are
  not published and contractors do not see them</strong> — not until you agree to an
  introduction, and then only to that one contractor.
</p>
<p>
  The job description itself is shown on the board, so write it as something a stranger will
  read.
</p>

<h2>Why we are allowed to hold it</h2>
<p>
  Because you gave it to us and agreed we could, for one purpose: putting contractors and
  clients in front of each other. We do not use it for anything else, and we do not sell it to
  anybody.
</p>

<h2>Who else sees it</h2>
<p>
  Afrostrength staff working the directory, and nobody else. It is a separate system from
  Afrotech Academy with separate accounts — somebody who checks contractor listings cannot see
  a learner's record, and vice versa.
</p>

<h2>How long we keep it</h2>
<p>
  A listing, for as long as you want it up. A job, for two years after it closes — long enough
  to answer a question about an introduction we charged for.
</p>

<h2>What you can ask for</h2>
<ul>
  <li>A copy of everything we hold about you.</li>
  <li>A correction, if something is wrong.</li>
  <li>Deletion. We will do it, except where we have to keep a record of a fee we charged.</li>
  <li>To be taken off the directory, at any time, without giving a reason.</li>
</ul>
<p>
  Email <a href="mailto:<?= e($office) ?>"><?= e($office) ?></a> or call
  <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a>.
  We answer within 30 days, which is what the Act allows us, and usually much sooner.
</p>

<h2>Cookies</h2>
<p>
  One, and only when you are signing in to the staff console — it is what keeps you signed in.
  There is no advertising cookie, no analytics cookie and no tracking pixel anywhere on this
  site.
</p>

<?php page_foot(); ?>
