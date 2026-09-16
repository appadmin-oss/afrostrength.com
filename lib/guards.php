<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/* ══════════════════════════════════════════════════════════════════════
   CSRF
   ══════════════════════════════════════════════════════════════════════
   Double-submit, same as the platform: the token lives in the session and
   in a hidden field, and a POST is only honoured when they match. A form
   that posts without one is a form somebody else's page submitted.
   ══════════════════════════════════════════════════════════════════════ */

function session_start_once(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('afrotech_reg');
    @session_start();
}

function csrf_token(): string
{
    session_start_once();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf'];
}

function csrf_ok(?string $submitted): bool
{
    session_start_once();
    $known = (string)($_SESSION['csrf'] ?? '');
    return $known !== '' && is_string($submitted) && hash_equals($known, $submitted);
}

/* ══════════════════════════════════════════════════════════════════════
   Rate limiting
   ══════════════════════════════════════════════════════════════════════
   The Node limiter keeps counters in process memory and says so. That does
   not port: on shared hosting every request is a fresh PHP process, so an
   in-memory counter would reset on every hit and limit precisely nothing.

   These are files under var/ratelimit, one per key, holding a window start
   and a count. Not distributed, not clever, and honest about it — but it
   survives between requests, which is the only property that matters here.
   ══════════════════════════════════════════════════════════════════════ */

/**
 * @return array{allowed:bool, remaining:int, retry_after:int}
 */
function rate_limit(string $key, int $max, int $windowSeconds): array
{
    $dir = dirname(__DIR__) . '/var/ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $file = $dir . '/' . hash('sha256', $key) . '.json';
    $now  = time();

    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        // Cannot write: fail OPEN rather than locking everyone out of
        // registration because a directory permission is wrong. The log says so.
        error_log('[ratelimit] cannot open ' . $file . ' — allowing the request');
        return ['allowed' => true, 'remaining' => $max, 'retry_after' => 0];
    }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $state = json_decode((string)$raw, true);
    $start = (int)($state['start'] ?? 0);
    $count = (int)($state['count'] ?? 0);

    if ($start === 0 || ($now - $start) >= $windowSeconds) {
        $start = $now;
        $count = 0;
    }
    $count++;

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode(['start' => $start, 'count' => $count]));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    $allowed = $count <= $max;
    return [
        'allowed'     => $allowed,
        'remaining'   => max(0, $max - $count),
        'retry_after' => $allowed ? 0 : max(1, $windowSeconds - ($now - $start)),
    ];
}

/** Delete counter files whose window is long gone. Called by the cron. */
function rate_limit_sweep(int $olderThanSeconds = 86400): int
{
    $dir = dirname(__DIR__) . '/var/ratelimit';
    if (!is_dir($dir)) return 0;
    $removed = 0;
    foreach (glob($dir . '/*.json') ?: [] as $file) {
        if (filemtime($file) < time() - $olderThanSeconds) {
            @unlink($file) && $removed++;
        }
    }
    return $removed;
}
