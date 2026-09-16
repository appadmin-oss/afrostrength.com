<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/money.php';

/**
 * The directory, the job board, and the introduction.
 *
 * ── The one rule the product rests on ────────────────────────────────────
 *
 * Nothing is listed until somebody at Afrostrength has checked it. The whole
 * value of a directory is that being on it means something; an open one is a
 * phone book. And the cost of a bad introduction lands on Afrostrength — the
 * client does not come back — not on the contractor who oversold themselves.
 *
 * ── The other rule ───────────────────────────────────────────────────────
 *
 * A client's contact details are not shown to contractors. Ever, by any
 * route. If they were, the board would be a free lead list and the
 * introduction fee would be unenforceable — which is the entire business
 * model, so it is enforced in the query rather than in the template.
 */

const TRADES = [
    'Electrical', 'Plumbing', 'Carpentry', 'Masonry', 'Painting',
    'Tiling', 'Welding and fabrication', 'Air conditioning',
    'Solar and inverters', 'Generator servicing',
    'Interior finishing', 'Cleaning', 'Haulage',
    'IT and networking', 'CCTV and security systems',
];

const BUDGET_BANDS = [
    'Under ₦50,000',
    '₦50,000 – ₦200,000',
    '₦200,000 – ₦500,000',
    '₦500,000 – ₦2,000,000',
    'Over ₦2,000,000',
    'Not sure yet',
];

const CONTRACTOR_STATES = ['pending', 'verified', 'suspended', 'withdrawn'];

/* ══════════════════════════════════════════════════════════════════════
   Contractors
   ══════════════════════════════════════════════════════════════════════ */

function contractor_validate(array $in): array
{
    $errors = [];

    $name = trim((string)($in['full_name'] ?? ''));
    if ($name === '') $errors['full_name'] = 'We need your name — it is what a client sees.';
    elseif (mb_strlen($name) > 200) $errors['full_name'] = 'That is longer than we can store.';

    if (!in_array((string)($in['trade'] ?? ''), TRADES, true)) {
        $errors['trade'] = 'Pick the trade you mainly work in.';
    }

    $headline = trim((string)($in['headline'] ?? ''));
    if ($headline === '') {
        $errors['headline'] = 'One line about what you do. This is what a client reads first.';
    } elseif (mb_strlen($headline) > 200) {
        $errors['headline'] = 'Keep it to one line — 200 characters.';
    }

    $email = trim((string)($in['email'] ?? ''));
    if ($email === '') $errors['email'] = 'We need an email address to reach you about work.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That address does not look complete.';
    }

    if (trim((string)($in['city'] ?? '')) === '') {
        $errors['city'] = 'Where you work. A client in Ikeja is not going to call somebody in Abuja.';
    }

    $years = trim((string)($in['years'] ?? ''));
    if ($years !== '' && ((int)$years < 0 || (int)$years > 70)) {
        $errors['years'] = 'Check that — it should be the number of years you have been doing this.';
    }

    if (empty($in['consent'])) {
        $errors['consent'] = 'We need your agreement to list your details before we can.';
    }

    return $errors;
}

function contractor_create(array $in): array
{
    $errors = contractor_validate($in);
    if ($errors !== []) return ['ok' => false, 'errors' => $errors];

    $email = strtolower(trim((string)$in['email']));
    if (db_one("SELECT id FROM contractors WHERE email = ?", [$email]) !== null) {
        return ['ok' => false, 'errors' => ['email' =>
            'There is already a listing with that address. If it is yours and you cannot get into it, call the office.']];
    }

    $name = trim((string)$in['full_name']);
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-')) ?: 'contractor';
    $slug = $base;
    for ($i = 2; db_one("SELECT id FROM contractors WHERE slug = ?", [$slug]) !== null; $i++) {
        $slug = $base . '-' . $i;
    }

    $id = uuid_v4();
    $now = now_sql();

    db_transaction(function (PDO $pdo) use ($id, $slug, $in, $name, $email, $now): void {
        $pdo->prepare(
            "INSERT INTO contractors (id, slug, full_name, trade, headline, about, email, phone,
                                      city, years, state, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,'pending',?,?)"
        )->execute([
            $id, $slug, $name, (string)$in['trade'], trim((string)$in['headline']),
            trim((string)($in['about'] ?? '')) ?: null, $email,
            trim((string)($in['phone'] ?? '')) ?: null,
            trim((string)$in['city']),
            trim((string)($in['years'] ?? '')) === '' ? null : (int)$in['years'],
            $now, $now,
        ]);

        // Skills, split on commas and de-duplicated. A UNIQUE key backs this
        // up, so a doubled entry is refused by the database rather than by
        // remembering.
        $skills = array_unique(array_filter(array_map(
            fn($s) => mb_substr(trim($s), 0, 80),
            explode(',', (string)($in['skills'] ?? '')),
        )));
        $st = $pdo->prepare("INSERT IGNORE INTO contractor_skills (contractor_id, skill) VALUES (?,?)");
        foreach (array_slice($skills, 0, 12) as $skill) {
            if ($skill !== '') $st->execute([$id, $skill]);
        }
    });

    return ['ok' => true, 'id' => $id, 'slug' => $slug];
}

/**
 * The public directory. Verified only, and that is in the WHERE clause.
 *
 * Not filtered in PHP afterwards: a listing that has not been checked must
 * not be fetchable by a page that forgets to filter.
 */
function directory_search(array $filter, int $limit, int $offset): array
{
    [$where, $args] = directory_where($filter);
    return db_all(
        "SELECT c.* FROM contractors c WHERE $where
          ORDER BY c.verified_at DESC, c.full_name
          LIMIT " . (int)$limit . " OFFSET " . (int)$offset,
        $args,
    );
}

function directory_count(array $filter): int
{
    [$where, $args] = directory_where($filter);
    return (int)(db_one("SELECT COUNT(*) AS n FROM contractors c WHERE $where", $args)['n'] ?? 0);
}

function directory_where(array $filter): array
{
    // Verified, always. Not a default a caller can override.
    $where = ["c.state = 'verified'"];
    $args = [];

    if (!empty($filter['trade'])) { $where[] = 'c.trade = ?'; $args[] = $filter['trade']; }
    if (!empty($filter['city'])) { $where[] = 'c.city LIKE ?'; $args[] = '%' . $filter['city'] . '%'; }
    if (!empty($filter['q'])) {
        $where[] = '(c.full_name LIKE ? OR c.headline LIKE ? OR c.about LIKE ? OR c.trade LIKE ?
                     OR EXISTS (SELECT 1 FROM contractor_skills s WHERE s.contractor_id = c.id AND s.skill LIKE ?))';
        $like = '%' . $filter['q'] . '%';
        array_push($args, $like, $like, $like, $like, $like);
    }
    return [implode(' AND ', $where), $args];
}

function contractor_by_slug(string $slug, bool $publicOnly = true): ?array
{
    $sql = "SELECT * FROM contractors WHERE slug = ?" . ($publicOnly ? " AND state = 'verified'" : '');
    return db_one($sql, [$slug]);
}

function contractor_skills(string $id): array
{
    return array_column(
        db_all("SELECT skill FROM contractor_skills WHERE contractor_id = ? ORDER BY skill", [$id]),
        'skill',
    );
}

function contractor_set_state(string $id, string $state, string $note, array $staff): array
{
    if (!in_array($state, CONTRACTOR_STATES, true)) {
        return ['ok' => false, 'error' => 'That is not a state a listing can be in.'];
    }
    if (in_array($state, ['suspended', 'withdrawn'], true) && mb_strlen(trim($note)) < 8) {
        return ['ok' => false, 'error' => 'Say why, in a sentence the contractor could be shown.'];
    }
    db_run(
        "UPDATE contractors
            SET state = ?, state_note = ?, updated_at = ?,
                verified_at = CASE WHEN ? = 'verified' THEN COALESCE(verified_at, ?) ELSE verified_at END,
                verified_by = CASE WHEN ? = 'verified' THEN COALESCE(verified_by, ?) ELSE verified_by END
          WHERE id = ?",
        [$state, trim($note) ?: null, now_sql(), $state, now_sql(), $state, (string)$staff['name'], $id],
    );
    return ['ok' => true];
}

/* ══════════════════════════════════════════════════════════════════════
   Jobs
   ══════════════════════════════════════════════════════════════════════ */

function job_next_ref(PDO $pdo): string
{
    $year = date('y');
    $st = $pdo->prepare("SELECT COUNT(*) AS n FROM jobs WHERE ref LIKE ?");
    $st->execute(["AC-{$year}-%"]);
    return sprintf('AC-%s-%04d', $year, (int)($st->fetch()['n'] ?? 0) + 1);
}

function job_validate(array $in): array
{
    $errors = [];
    if (trim((string)($in['client_name'] ?? '')) === '') $errors['client_name'] = 'Your name, so we know who is asking.';

    $email = trim((string)($in['client_email'] ?? ''));
    if ($email === '') $errors['client_email'] = 'An email address — it is how we come back to you.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['client_email'] = 'That address does not look complete.';

    if (trim((string)($in['title'] ?? '')) === '') $errors['title'] = 'One line saying what needs doing.';
    if (mb_strlen(trim((string)($in['description'] ?? ''))) < 20) {
        $errors['description'] = 'A bit more detail. A contractor deciding whether to take this on needs something to go on.';
    }
    if (!in_array((string)($in['trade'] ?? ''), TRADES, true)) $errors['trade'] = 'Pick the kind of work.';
    if (trim((string)($in['city'] ?? '')) === '') $errors['city'] = 'Where the work is.';
    if (empty($in['consent'])) $errors['consent'] = 'We need your agreement to hold your details before we can take this.';
    return $errors;
}

function job_create(array $in): array
{
    $errors = job_validate($in);
    if ($errors !== []) return ['ok' => false, 'errors' => $errors];

    // Retried on a reference collision, for the same reason the academy's
    // applications are: two clients posting in the same second both count the
    // same rows, and the loser must not get a fatal error.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            return db_transaction(function (PDO $pdo) use ($in): array {
                $id = uuid_v4();
                $ref = job_next_ref($pdo);
                $now = now_sql();
                $pdo->prepare(
                    "INSERT INTO jobs (id, ref, client_name, client_email, client_phone, title,
                                       description, trade, city, budget_band, state, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?, 'open', ?,?)"
                )->execute([
                    $id, $ref,
                    trim((string)$in['client_name']), strtolower(trim((string)$in['client_email'])),
                    trim((string)($in['client_phone'] ?? '')) ?: null,
                    trim((string)$in['title']), trim((string)$in['description']),
                    (string)$in['trade'], trim((string)$in['city']),
                    in_array((string)($in['budget_band'] ?? ''), BUDGET_BANDS, true) ? (string)$in['budget_band'] : null,
                    $now, $now,
                ]);
                return ['ok' => true, 'id' => $id, 'ref' => $ref];
            });
        } catch (Throwable $e) {
            $dup = $e instanceof PDOException && ($e->errorInfo[1] ?? 0) === 1062
                && str_contains($e->getMessage(), "for key 'ref'");
            if (!$dup || $attempt === 4) throw $e;
            usleep(random_int(1000, 20000));
        }
    }
    return ['ok' => false, 'errors' => ['_form' => 'Could not save that. Try again.']];
}

/**
 * Open jobs, as a CONTRACTOR sees them.
 *
 * The client's name, email and phone are not in the SELECT. Not hidden in the
 * template — absent from the query, so a page that forgets cannot leak them.
 * This is the fee.
 */
function jobs_open_for_contractors(array $filter = []): array
{
    $where = ["j.state = 'open'"];
    $args = [];
    if (!empty($filter['trade'])) { $where[] = 'j.trade = ?'; $args[] = $filter['trade']; }
    if (!empty($filter['city'])) { $where[] = 'j.city LIKE ?'; $args[] = '%' . $filter['city'] . '%'; }

    return db_all(
        "SELECT j.id, j.ref, j.title, j.description, j.trade, j.city, j.budget_band, j.created_at,
                (SELECT COUNT(*) FROM job_responses r WHERE r.job_id = j.id) AS responses
           FROM jobs j
          WHERE " . implode(' AND ', $where) . "
          ORDER BY j.created_at DESC",
        $args,
    );
}

/** The full row, including the client. Staff only — never a public page. */
function job_get_full(string $id): ?array
{
    return db_one("SELECT * FROM jobs WHERE id = ?", [$id]);
}

function job_responses(string $jobId): array
{
    return db_all(
        "SELECT r.*, c.full_name, c.slug, c.trade, c.city, c.email, c.phone, c.state AS contractor_state
           FROM job_responses r JOIN contractors c ON c.id = r.contractor_id
          WHERE r.job_id = ? ORDER BY r.created_at",
        [$jobId],
    );
}

function job_respond(string $jobId, string $contractorId, string $message): array
{
    $message = trim($message);
    if (mb_strlen($message) < 20) {
        return ['ok' => false, 'error' => 'Say something useful — what you would do, and roughly what it takes. Three words loses you the job.'];
    }

    $job = db_one("SELECT state FROM jobs WHERE id = ?", [$jobId]);
    if ($job === null) return ['ok' => false, 'error' => 'That job no longer exists.'];
    if ((string)$job['state'] !== 'open') return ['ok' => false, 'error' => 'That job is no longer open.'];

    $c = db_one("SELECT state FROM contractors WHERE id = ?", [$contractorId]);
    if ($c === null || (string)$c['state'] !== 'verified') {
        return ['ok' => false, 'error' => 'Only checked listings can respond to work.'];
    }

    db_run(
        "INSERT INTO job_responses (id, job_id, contractor_id, message, created_at)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE message = VALUES(message), created_at = VALUES(created_at)",
        [uuid_v4(), $jobId, $contractorId, $message, now_sql()],
    );
    return ['ok' => true];
}

/* ══════════════════════════════════════════════════════════════════════
   Introductions — the product
   ══════════════════════════════════════════════════════════════════════ */

function introduction_fee_kobo(): int
{
    $r = db_one("SELECT value FROM settings WHERE name = 'introduction_fee_kobo'");
    return max(0, (int)($r['value'] ?? 0));
}

function set_introduction_fee(int $kobo, string $actor): void
{
    db_run(
        "INSERT INTO settings (name, value, updated_at, updated_by) VALUES ('introduction_fee_kobo',?,?,?)
         ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)",
        [(string)max(0, $kobo), now_sql(), $actor],
    );
}

/**
 * Make the introduction.
 *
 * The moment the client's details reach the contractor and Afrostrength has
 * earned its fee. Recorded once per pair — a second introduction of the same
 * two people is not a second fee.
 */
function introduction_make(string $jobId, string $contractorId, array $staff, ?int $feeKobo = null): array
{
    $job = db_one("SELECT id, state FROM jobs WHERE id = ?", [$jobId]);
    if ($job === null) return ['ok' => false, 'error' => 'That job no longer exists.'];

    $c = db_one("SELECT id, state, full_name FROM contractors WHERE id = ?", [$contractorId]);
    if ($c === null || (string)$c['state'] !== 'verified') {
        return ['ok' => false, 'error' => 'Only a checked contractor can be introduced. Verify the listing first.'];
    }

    if (db_one("SELECT id FROM introductions WHERE job_id = ? AND contractor_id = ?", [$jobId, $contractorId]) !== null) {
        return ['ok' => false, 'error' => 'These two have already been introduced. Introducing them again is not a second fee.'];
    }

    db_transaction(function (PDO $pdo) use ($jobId, $contractorId, $staff, $feeKobo): void {
        $pdo->prepare(
            "INSERT INTO introductions (id, job_id, contractor_id, fee_kobo, state, made_at, made_by)
             VALUES (?,?,?,?, 'made', ?,?)"
        )->execute([uuid_v4(), $jobId, $contractorId,
            $feeKobo ?? introduction_fee_kobo(), now_sql(),
            (string)$staff['name'] . ' (' . (string)$staff['email'] . ')']);

        $pdo->prepare("UPDATE jobs SET state = 'introducing', updated_at = ? WHERE id = ? AND state = 'open'")
            ->execute([now_sql(), $jobId]);
    });

    return ['ok' => true, 'contractor' => (string)$c['full_name']];
}

function introduction_settle(string $id, string $state, array $fields, array $staff): array
{
    if (!in_array($state, ['invoiced', 'paid', 'waived', 'void'], true)) {
        return ['ok' => false, 'error' => 'That is not a state an introduction can go to.'];
    }
    if ($state === 'paid' && trim((string)($fields['reference'] ?? '')) === '') {
        return ['ok' => false, 'error' => 'Put in the bank reference. It is how this gets checked later.'];
    }
    if (in_array($state, ['waived', 'void'], true) && mb_strlen(trim((string)($fields['note'] ?? ''))) < 8) {
        return ['ok' => false, 'error' => 'Say why. A waiver or a write-off with no reason is not a record.'];
    }

    db_run(
        "UPDATE introductions SET state = ?, reference = ?, note = ?, settled_at = ?, settled_by = ?
          WHERE id = ?",
        [$state, trim((string)($fields['reference'] ?? '')) ?: null,
         trim((string)($fields['note'] ?? '')) ?: null,
         now_sql(), (string)$staff['name'], $id],
    );
    return ['ok' => true];
}

function introductions_all(?string $state = null): array
{
    $where = $state !== null ? 'WHERE i.state = ?' : '';
    $args = $state !== null ? [$state] : [];
    return db_all(
        "SELECT i.*, j.ref, j.title, j.client_name, c.full_name AS contractor_name, c.slug
           FROM introductions i
           JOIN jobs j ON j.id = i.job_id
           JOIN contractors c ON c.id = i.contractor_id
           $where ORDER BY i.made_at DESC",
        $args,
    );
}

function introduction_totals(): array
{
    $out = [];
    foreach (['made', 'invoiced', 'paid', 'waived', 'void'] as $s) {
        $out[$s] = ['n' => 0, 'kobo' => 0];
    }
    foreach (db_all("SELECT state, COUNT(*) n, COALESCE(SUM(fee_kobo),0) kobo FROM introductions GROUP BY state") as $r) {
        $out[(string)$r['state']] = ['n' => (int)$r['n'], 'kobo' => (int)$r['kobo']];
    }
    return $out;
}
