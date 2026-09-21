<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Schema migrations, without a shell.
 *
 * Africa-GATES applies its whole schema idempotently and keeps timestamped
 * files beside it in database/migrations. That shape works here, with one
 * change forced by the constraint: it runs `php bin/console db:migrate` over
 * SSH, and there is no SSH. So the runner is a library, and the ops page
 * calls it through the browser behind a token.
 *
 * Two rules, and they are what makes it safe to press a button on a web page
 * that alters a production schema:
 *
 *  1. Every migration is idempotent on its own — CREATE TABLE IF NOT EXISTS,
 *     a column check before ALTER. Running the whole set twice changes
 *     nothing the second time.
 *  2. Applied migrations are recorded, so the runner is also honest about
 *     what it did rather than replaying everything and calling it success.
 *
 * Both together mean a half-finished run — the browser tab closed, the host
 * killed the request at 30 seconds — can simply be run again.
 */

function migrations_dir(): string
{
    return dirname(__DIR__) . '/migrations';
}

/** The ledger. Created before anything else, and by hand, because it cannot migrate itself. */
function migrations_init(): void
{
    db_run(
        "CREATE TABLE IF NOT EXISTS schema_migrations (
           name       VARCHAR(160) NOT NULL PRIMARY KEY,
           applied_at DATETIME     NOT NULL,
           applied_by VARCHAR(60)  NOT NULL,
           ms         INT UNSIGNED NOT NULL
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );
}

/** @return string[] every migration file, in order */
function migrations_all(): array
{
    $files = glob(migrations_dir() . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    return array_map('basename', $files);
}

/** @return string[] the ones already recorded */
function migrations_applied(): array
{
    migrations_init();
    return array_column(db_all("SELECT name FROM schema_migrations ORDER BY name"), 'name');
}

/** @return string[] the ones still to run */
function migrations_pending(): array
{
    return array_values(array_diff(migrations_all(), migrations_applied()));
}

/**
 * Split a file into statements.
 *
 * `PDO::exec` will not run several statements at once with emulation off, so
 * the file has to be split. Quoted strings and comments are respected; the
 * alternative — exploding on every semicolon — breaks the moment a default
 * value or a comment contains one, and it breaks silently.
 *
 * @return string[]
 */
function sql_statements(string $sql): array
{
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $inSingle = $inDouble = $inBacktick = $inLineComment = $inBlockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($inLineComment) { $buf .= $c; if ($c === "\n") $inLineComment = false; continue; }
        if ($inBlockComment) { $buf .= $c; if ($c === '*' && $next === '/') { $buf .= $next; $i++; $inBlockComment = false; } continue; }
        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($c === '-' && $next === '-') { $inLineComment = true; $buf .= $c; continue; }
            if ($c === '#') { $inLineComment = true; $buf .= $c; continue; }
            if ($c === '/' && $next === '*') { $inBlockComment = true; $buf .= $c; continue; }
        }

        if ($c === "'" && !$inDouble && !$inBacktick) $inSingle = !$inSingle;
        elseif ($c === '"' && !$inSingle && !$inBacktick) $inDouble = !$inDouble;
        elseif ($c === '`' && !$inSingle && !$inDouble) $inBacktick = !$inBacktick;
        elseif ($c === '\\' && ($inSingle || $inDouble)) { $buf .= $c . $next; $i++; continue; }

        if ($c === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            if (trim($buf) !== '') $out[] = trim($buf);
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if (trim($buf) !== '') $out[] = trim($buf);

    // Drop anything that is only a comment.
    return array_values(array_filter($out, static function (string $s): bool {
        $stripped = preg_replace('~(--[^\n]*\n)|(#[^\n]*\n)|(/\*.*?\*/)~s', '', $s . "\n");
        return trim((string)$stripped) !== '';
    }));
}

/**
 * Apply everything outstanding.
 *
 * @return array{applied:string[], skipped:string[], failed:?array{name:string,error:string,statement:string}}
 */
function migrations_run(string $actor = 'ops'): array
{
    migrations_init();
    $applied = [];
    $already = migrations_applied();

    foreach (migrations_all() as $name) {
        if (in_array($name, $already, true)) continue;

        $path = migrations_dir() . '/' . $name;
        $sql = (string)file_get_contents($path);
        $started = microtime(true);

        foreach (sql_statements($sql) as $statement) {
            try {
                db()->exec($statement);
            } catch (Throwable $e) {
                // Stop at the first failure rather than carrying on into
                // migrations that assume it worked. Everything before this
                // one is recorded, so a re-run resumes here.
                return [
                    'applied' => $applied,
                    'skipped' => $already,
                    'failed'  => [
                        'name'      => $name,
                        'error'     => $e->getMessage(),
                        'statement' => mb_substr($statement, 0, 400),
                    ],
                ];
            }
        }

        $ms = (int)round((microtime(true) - $started) * 1000);
        db_run(
            "INSERT INTO schema_migrations (name, applied_at, applied_by, ms) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at)",
            [$name, now_sql(), mb_substr($actor, 0, 60), $ms],
        );
        $applied[] = $name;
    }

    return ['applied' => $applied, 'skipped' => $already, 'failed' => null];
}

/**
 * Which tables the platform expects, and whether they are there.
 *
 * Lifted straight from Africa-GATES' database/check-state.php, which is the
 * single most useful file in that repository when something is wrong on a
 * host you cannot log in to: it turns "the site is broken" into "the
 * introductions table is missing".
 *
 * @return array<string,bool>
 */
function schema_state(): array
{
    // This product's own tables. It came over from the academy still listing
    // the academy's — enrolments, submissions, certificates — so the ops page
    // reported a healthy schema as eighteen missing tables, which is the one
    // thing an ops page must never do.
    $expected = [
        // 0001 base
        'contractors', 'contractor_skills', 'jobs', 'job_responses',
        'introductions', 'staff', 'staff_signin_attempts', 'settings',
        'schema_migrations',
        // 0002 studio enquiries and the outbox
        'enquiries', 'notifications',
    ];
    $rows = db_all(
        "SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()",
    );
    $present = array_map(static fn(array $r): string => strtolower((string)$r['t']), $rows);

    $state = [];
    foreach ($expected as $table) $state[$table] = in_array($table, $present, true);
    return $state;
}

/** Row counts for the tables worth knowing the size of. */
function schema_counts(): array
{
    $out = [];
    foreach (['enquiries', 'contractors', 'jobs', 'introductions', 'notifications'] as $t) {
        try {
            $row = db_one("SELECT COUNT(*) AS n FROM `{$t}`");
            $out[$t] = (int)($row['n'] ?? 0);
        } catch (Throwable $e) {
            $out[$t] = null;
        }
    }
    return $out;
}
