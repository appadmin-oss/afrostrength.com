<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Project enquiries — the thing the homepage exists to produce.
 *
 * The page makes one promise about these, in two places: "We reply within one
 * working day with either a scope or an honest no." Nothing in this file can
 * make that true — only a person answering can — but everything here is built
 * so the enquiry reaches that person rather than sitting in a table.
 */

const ENQUIRY_KINDS = [
    'brand'    => 'Brand strategy or identity',
    'digital'  => 'Website, social or campaign',
    'software' => 'Software or an internal tool',
    'unsure'   => 'Not sure — tell me',
];

/** AS-YY-NNNN. Counted per year, and retried on collision by enquiry_create(). */
function enquiry_next_ref(PDO $pdo, int $skip = 0): string
{
    $year = date('y');
    $st = $pdo->prepare("SELECT COUNT(*) AS n FROM enquiries WHERE ref LIKE ?");
    $st->execute(["AS-{$year}-%"]);
    return sprintf('AS-%s-%04d', $year, (int)($st->fetch()['n'] ?? 0) + 1 + $skip);
}

/**
 * Check one step of the wizard, or all of it.
 *
 * Steps are validated separately because the form shows one at a time and a
 * message about an email address on the screen that asks about the problem is
 * a message nobody can act on.
 *
 * @return array<string,string> field => message
 */
function enquiry_validate(array $in, int $step = 0): array
{
    $e = [];

    if ($step === 0 || $step === 1) {
        $problem = trim((string)($in['problem'] ?? ''));
        if ($problem === '') {
            $e['problem'] = 'Tell us what needs to change — a sentence is enough.';
        } elseif (mb_strlen($problem) < 10) {
            $e['problem'] = 'A little more than that, so we can tell whether we are the right people.';
        } elseif (mb_strlen($problem) > 4000) {
            $e['problem'] = 'That is longer than we can store. The short version is better anyway.';
        }
    }

    if ($step === 0 || $step === 3) {
        $email = trim((string)($in['email'] ?? ''));
        if ($email === '') {
            $e['email'] = 'We need somewhere to reply.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'That address does not look complete — check for a missing @ or a typo.';
        } elseif (mb_strlen($email) > 320) {
            $e['email'] = 'That address is too long to store.';
        }
        if (mb_strlen(trim((string)($in['name'] ?? ''))) > 200) {
            $e['name'] = 'That name is longer than we can store.';
        }
        // Not optional, and the message says why rather than scolding.
        if (empty($in['consent'])) {
            $e['consent'] = 'We need your agreement to keep your details, or we have no lawful way to write back.';
        }
    }

    return $e;
}

/**
 * Write the enquiry.
 *
 * Retried on a reference collision for the same reason the academy's
 * applications are: the reference is counted rather than allocated, so two
 * people pressing Send in the same second propose the same one. The UNIQUE key
 * refuses the second, and an uncaught exception there would be a fatal page
 * for somebody who did nothing wrong.
 *
 * @return array{ok:bool, id?:string, ref?:string, errors?:array<string,string>}
 */
function enquiry_create(array $in): array
{
    $errors = enquiry_validate($in, 0);
    if ($errors !== []) return ['ok' => false, 'errors' => $errors];

    for ($attempt = 0; ; $attempt++) {
        try {
            return db_transaction(function (PDO $pdo) use ($in, $attempt): array {
                $id = uuid_v4();
                $ref = enquiry_next_ref($pdo, $attempt);
                $now = now_sql();
                $kind = (string)($in['kind'] ?? '');

                $pdo->prepare(
                    "INSERT INTO enquiries
                       (id, ref, problem, kind, full_name, email, consent, consent_at, source, state, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,'new',?)",
                )->execute([
                    $id, $ref,
                    trim((string)$in['problem']),
                    isset(ENQUIRY_KINDS[$kind]) ? $kind : null,
                    trim((string)($in['name'] ?? '')) ?: null,
                    trim((string)$in['email']),
                    1, $now,
                    trim((string)($in['source'] ?? '')) ?: null,
                    $now,
                ]);
                return ['ok' => true, 'id' => $id, 'ref' => $ref];
            });
        } catch (Throwable $ex) {
            $collision = $ex instanceof PDOException
                && ($ex->errorInfo[1] ?? 0) === 1062
                && str_contains($ex->getMessage(), 'uq_enquiry_ref');
            if ($attempt >= 4 || !$collision) throw $ex;
            usleep(random_int(1000, 20000));
        }
    }
}

/**
 * Tell the studio. Failure is logged, never shown.
 *
 * ── What is NOT here yet ─────────────────────────────────────────────────
 *
 * Nothing drains this queue. The academy has PHPMailer, a cron.php and an
 * SMTP configuration; this app has none of those, so the row is written and
 * sits there. That is said plainly rather than left to be discovered: until a
 * sender is added, somebody has to read new enquiries in the console, and the
 * ops page counts pending notifications so the backlog is visible.
 *
 * It is still queued rather than skipped, because the day a sender arrives
 * every enquiry taken in the meantime goes out rather than being lost.
 *
 * The enquiry is already saved by the time this runs. Somebody who has just
 * pressed Send should not be told that our mail server is unhappy — that is
 * our problem, and the record is safe either way.
 */
function enquiry_notify(array $enquiry): void
{
    try {
        $to = (string)cfg('site.office_email', 'reachus@afrostrength.com');
        if (trim($to) === '') return;

        $kind = (string)($enquiry['kind'] ?? '');
        $lines = [
            'New project enquiry — ' . $enquiry['ref'],
            '',
            'The homepage promises a reply within one working day.',
            '',
            '  From      ' . (($enquiry['full_name'] ?? '') ?: 'no name given'),
            '  Email     ' . $enquiry['email'],
            '  Kind      ' . (ENQUIRY_KINDS[$kind] ?? 'not said'),
            '  Source    ' . (($enquiry['source'] ?? '') ?: 'the homepage'),
            '',
            'What they said needs to change:',
            '',
            '  ' . str_replace("\n", "\n  ", (string)$enquiry['problem']),
            '',
        ];
        $text = implode("\n", $lines);

        db_run(
            "INSERT INTO notifications (kind, to_email, subject, body, status, created_at)
             VALUES ('enquiry_notice', ?, ?, ?, 'pending', ?)",
            [$to, 'New project enquiry — ' . $enquiry['ref'], $text, now_sql()],
        );
    } catch (Throwable $e) {
        error_log('[enquiries] could not queue the notice: ' . $e->getMessage());
    }
}

function enquiries_all(string $state = ''): array
{
    $where = in_array($state, ['new', 'answered', 'closed'], true) ? 'WHERE state = ?' : '';
    return db_all("SELECT * FROM enquiries {$where} ORDER BY created_at DESC",
                  $where === '' ? [] : [$state]);
}
