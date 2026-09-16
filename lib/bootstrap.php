<?php
declare(strict_types=1);

/**
 * One entry point for every PHP file in this app.
 *
 * Shared hosting gives us no shell, no process manager and no environment
 * beyond what cPanel writes, so configuration is a file and nothing here
 * assumes anything else is running.
 */

error_reporting(E_ALL);

/*
 * The vendor autoloader, IF there is one.
 *
 * This product does not send email yet, so PHPMailer is not vendored and
 * there is nothing to autoload. Requiring it unconditionally — as the
 * academy's copy of this file does, where mail is the point — turns a
 * missing optional dependency into a fatal error on every page.
 */
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/** The loaded configuration, or null if the install is not finished. */
function app_config(): ?array
{
    static $config = null;
    static $loaded = false;
    if ($loaded) return $config;
    $loaded = true;
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) return null;
    $value = require $path;
    $config = is_array($value) ? $value : null;
    return $config;
}

/** Read a dotted path out of the config: cfg('smtp.host', 'localhost'). */
function cfg(string $path, mixed $fallback = null): mixed
{
    $node = app_config();
    foreach (explode('.', $path) as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) return $fallback;
        $node = $node[$key];
    }
    return $node;
}

/* Errors go to a log the operator can read over FTP, never to the page. A
   stack trace on a registration form tells a stranger the database name. */
ini_set('display_errors', cfg('debug', false) ? '1' : '0');
ini_set('log_errors', '1');
$logDir = dirname(__DIR__) . '/var/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
ini_set('error_log', $logDir . '/php-error.log');

date_default_timezone_set('Africa/Lagos');

/** MySQL DATETIME for right now, in the academy's own timezone. */
function now_sql(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

/**
 * A random identifier, RFC 4122 v4 — the same shape randomUUID() produces, so
 * a row written here is indistinguishable from one the Node platform wrote.
 *
 * Lives in bootstrap rather than beside the admissions code because the LMS
 * needs it too. It did not, at first, and a submission fatalled on a call to
 * an undefined function — which the loop test caught, but only because the
 * test submitted real work rather than checking the form rendered.
 */
function uuid_v4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/** Escape for HTML. Short name because it is used on every output. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * The connecting address, as the web server saw it.
 *
 * Deliberately does NOT read X-Forwarded-For. An earlier version did, and it
 * was wrong in both directions: behind a proxy the header is right but
 * trusting it unconditionally lets anyone reaching the origin directly set
 * their own value and mint a fresh identity per request, which turns a rate
 * limiter off. Anything that needs the real visitor calls real_client_ip()
 * in lib/cloudflare.php, which only trusts CF-Connecting-IP when the
 * connection genuinely came from a Cloudflare address.
 */
function client_ip(): string
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}
