<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/cloudflare.php';

/**
 * Staff accounts and the console's guards.
 *
 * Taken from the academy's, because the security reasoning is the same and
 * re-deriving it would mean re-deriving the mistakes. The ROLES are not:
 * this product has admins and agents only — there is no mentor, because
 * nobody here marks anything.
 *
 * Passwords rather than emailed links, because a member of staff signs in
 * several times a day from a shared machine and a link every time would be
 * unworkable. `password_hash()` picks the algorithm, so an account made today
 * under bcrypt keeps working when the default moves.
 *
 * Two roles, and the separation is structural rather than cosmetic:
 *
 *   admin  everything, including adding colleagues and setting the fee
 *   agent  checks contractors and works jobs; cannot change the fee or the
 *          money already owed, and cannot make another account
 *
 * The directory's promise is that somebody at Afrostrength checked each
 * listing. That is only worth anything if the checking and the charging are
 * different hands, which is what require_admin() is for.
 */

const STAFF_SESSION_KEY = 'staff_id';

/** How many failures before an account or an address is made to wait. */
const STAFF_MAX_PER_EMAIL = 8;
const STAFF_MAX_PER_IP = 20;
const STAFF_WINDOW = 900;

function staff_count(): int
{
    try {
        $r = db_one("SELECT COUNT(*) AS n FROM staff WHERE is_active = 1");
        return (int)($r['n'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Create an account.
 *
 * @return array{ok:bool, error?:string, id?:string}
 */
function staff_create(string $email, string $name, string $password, string $role): array
{
    $email = strtolower(trim($email));
    $name = trim($name);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'That does not look like a complete email address.'];
    }
    if ($name === '') {
        return ['ok' => false, 'error' => 'Give the account a name — it is what a contractor sees when you write to them.'];
    }
    // Length, not character classes. A long passphrase beats a short one with
    // a symbol in it, and the rules that demand symbols produce Password1!
    if (mb_strlen($password) < 12) {
        return ['ok' => false, 'error' => 'Use at least 12 characters. Three or four unrelated words is easier to remember and harder to guess than a short one with symbols in it.'];
    }
    if (!in_array($role, ['admin', 'agent'], true)) {
        return ['ok' => false, 'error' => 'Pick a role.'];
    }

    if (db_one("SELECT id FROM staff WHERE email = ?", [$email]) !== null) {
        return ['ok' => false, 'error' => 'There is already an account with that address.'];
    }

    $id = uuid_v4();
    db_run(
        "INSERT INTO staff (id, email, name, password_hash, role, is_active, created_at)
         VALUES (?,?,?,?,?,1,?)",
        [$id, $email, $name, password_hash($password, PASSWORD_DEFAULT), $role, now_sql()],
    );
    return ['ok' => true, 'id' => $id];
}

/** Record every attempt, good or bad. The record of who tried is evidence. */
function staff_log_attempt(string $email, bool $ok): void
{
    try {
        db_run("INSERT INTO staff_signin_attempts (email, ip, ok, at) VALUES (?,?,?,?)",
            [mb_substr(strtolower($email), 0, 320), real_client_ip(), $ok ? 1 : 0, now_sql()]);
    } catch (Throwable $e) {
        error_log('[staff] could not record a sign-in attempt: ' . $e->getMessage());
    }
}

/** Recent failures, so a restart does not hand an attacker a fresh budget. */
function staff_recent_failures(string $email, string $ip): array
{
    try {
        $byEmail = db_one(
            "SELECT COUNT(*) AS n FROM staff_signin_attempts
              WHERE email = ? AND ok = 0 AND at > (NOW() - INTERVAL ? SECOND)",
            [strtolower($email), STAFF_WINDOW],
        );
        $byIp = db_one(
            "SELECT COUNT(*) AS n FROM staff_signin_attempts
              WHERE ip = ? AND ok = 0 AND at > (NOW() - INTERVAL ? SECOND)",
            [$ip, STAFF_WINDOW],
        );
        return ['email' => (int)($byEmail['n'] ?? 0), 'ip' => (int)($byIp['n'] ?? 0)];
    } catch (Throwable $e) {
        return ['email' => 0, 'ip' => 0];
    }
}

/**
 * Sign in.
 *
 * The refusal is the same whether the address is unknown or the password is
 * wrong. Distinguishing them turns the form into a way of finding out who
 * works here.
 *
 * @return array{ok:bool, error?:string}
 */
function staff_signin(string $email, string $password): array
{
    $email = strtolower(trim($email));
    $ip = real_client_ip();
    $same = 'Those details do not match an account.';

    $fails = staff_recent_failures($email, $ip);
    if ($fails['email'] >= STAFF_MAX_PER_EMAIL || $fails['ip'] >= STAFF_MAX_PER_IP) {
        // Counted before the hash is checked. Verifying a password is
        // deliberately expensive, so an unlimited form is a way to use up the
        // CPU of a shared host as much as a way to guess.
        return ['ok' => false, 'error' => 'Too many attempts just now. Wait fifteen minutes, or call the office.'];
    }

    $row = db_one("SELECT id, name, password_hash, role, is_active FROM staff WHERE email = ?", [$email]);

    if ($row === null) {
        // Hash anyway, so a missing account does not answer faster than a
        // wrong password and become detectable by timing.
        password_verify($password, '$2y$12$usesomesillystringfoorsalt0000000000000000000000000000000');
        staff_log_attempt($email, false);
        return ['ok' => false, 'error' => $same];
    }

    if (!password_verify($password, (string)$row['password_hash']) || !$row['is_active']) {
        staff_log_attempt($email, false);
        return ['ok' => false, 'error' => $same];
    }

    // Rehash if PHP's default has moved on since this password was set.
    if (password_needs_rehash((string)$row['password_hash'], PASSWORD_DEFAULT)) {
        db_run("UPDATE staff SET password_hash = ? WHERE id = ?",
            [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }

    staff_log_attempt($email, true);
    db_run("UPDATE staff SET last_seen_at = ? WHERE id = ?", [now_sql(), $row['id']]);

    session_start_once();
    session_regenerate_id(true);
    $_SESSION[STAFF_SESSION_KEY] = (string)$row['id'];
    return ['ok' => true];
}

function staff_signout(): void
{
    session_start_once();
    unset($_SESSION[STAFF_SESSION_KEY]);
    session_regenerate_id(true);
}

/** The signed-in staff member, or null. */
function current_staff(): ?array
{
    session_start_once();
    $id = (string)($_SESSION[STAFF_SESSION_KEY] ?? '');
    if ($id === '') return null;
    $row = db_one("SELECT id, email, name, role, is_active FROM staff WHERE id = ?", [$id]);
    if ($row === null || !$row['is_active']) return null;
    return $row;
}

/* ══════════════════════════════════════════════════════════════════════
   Guards — one place decides what a role may reach
   ══════════════════════════════════════════════════════════════════════ */

function is_admissions(array $staff): bool
{
    // Every staff account here works the directory. Kept as a function so
    // the guards read the same as the academy's.
    return in_array((string)$staff['role'], ['admin', 'agent'], true);
}

function is_admin(array $staff): bool
{
    return (string)$staff['role'] === 'admin';
}

/** Anyone on staff. Used only to tell somebody they are in the wrong console. */
function require_staff(): array
{
    $staff = current_staff();
    if ($staff === null) {
        // Remember where they were going, so signing in lands them there
        // rather than on the overview with the link lost.
        session_start_once();
        $here = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        $query = (string)($_SERVER['QUERY_STRING'] ?? '');
        $_SESSION['after_signin'] = $here . ($query !== '' ? '?' . $query : '');
        header('Location: signin.php');
        exit;
    }
    return $staff;
}

/**
 * Generate a password for a new account.
 *
 * Never a default. A shipped default password is a backdoor with the door
 * label printed on it, and it is the single most common way a small install
 * is taken over. This is read from the system CSPRNG, shown once, and stored
 * only as a hash.
 */
function staff_random_password(): string
{
    // Ambiguous characters left out: this gets read off a screen and typed
    // by someone else, and 1/l/I/0/O is where that goes wrong.
    $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < 20; $i++) $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return rtrim(chunk_split($out, 5, '-'), '-');
}

/** Change your own password. Requires the current one. */
function staff_change_password(string $id, string $current, string $next): array
{
    $row = db_one("SELECT password_hash FROM staff WHERE id = ?", [$id]);
    if ($row === null) return ['ok' => false, 'error' => 'That account no longer exists.'];
    if (!password_verify($current, (string)$row['password_hash'])) {
        return ['ok' => false, 'error' => 'That is not your current password.'];
    }
    if (mb_strlen($next) < 12) {
        return ['ok' => false, 'error' => 'Use at least 12 characters. Three or four unrelated words works well.'];
    }
    if ($next === $current) {
        return ['ok' => false, 'error' => 'That is the password you already have.'];
    }
    db_run("UPDATE staff SET password_hash = ? WHERE id = ?", [password_hash($next, PASSWORD_DEFAULT), $id]);
    return ['ok' => true];
}

/** An administrator sets someone else a new password. Returned once. */
function staff_reset_password(string $id): array
{
    $row = db_one("SELECT email FROM staff WHERE id = ?", [$id]);
    if ($row === null) return ['ok' => false, 'error' => 'That account no longer exists.'];
    $password = staff_random_password();
    db_run("UPDATE staff SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $id]);
    // Cleared so the person is not locked out by their own lockout.
    db_run("DELETE FROM staff_signin_attempts WHERE email = ?", [$row['email']]);
    return ['ok' => true, 'password' => $password];
}

/** Deactivate rather than delete: the notes and decisions keep their author. */
function staff_set_active(string $id, bool $active): void
{
    db_run("UPDATE staff SET is_active = ? WHERE id = ?", [$active ? 1 : 0, $id]);
}

function staff_all(): array
{
    return db_all("SELECT id, email, name, role, is_active, created_at, last_seen_at
                     FROM staff ORDER BY is_active DESC, role, name");
}

/**
 * Directory work — every account here does it. Kept as its own guard so the
 * day a read-only role is added, the pages that write already say so.
 */
function require_admissions(): array
{
    $staff = require_staff();
    if (!is_admissions($staff)) { http_response_code(404); exit; }
    return $staff;
}

/**
 * The money and the colleagues. An agent reaching this gets a 404, not a
 * sign-in form: the page does not exist for them, and saying so is more
 * honest than implying a different password would help.
 */
function require_admin(): array
{
    $staff = require_staff();
    if (!is_admin($staff)) { http_response_code(404); exit; }
    return $staff;
}

/**
 * The centre this account may see, or null for all of it.
 *
 * Derived from the session every time and re-asserted in each query's WHERE
 * clause — never passed in from a form, where it would be a request rather
 * than a fact.
 */
function staff_scope(array $staff): ?string
{
    // No centre scoping in this product: the directory is one list.
    return null;
}
