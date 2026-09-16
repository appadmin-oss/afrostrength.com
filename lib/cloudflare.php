<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Sitting behind Cloudflare.
 *
 * Africa-GATES puts the free tier in front of cPanel for edge caching and
 * Turnstile, and that is the right shape here too — a Lagos shared host with
 * one CPU benefits more from an edge cache than from anything we can do in
 * PHP. But the proxy changes two things that are easy to get silently wrong.
 *
 * FIRST, AND THIS ONE IS A BUG UNTIL IT IS FIXED: every request now arrives
 * from a Cloudflare address. `REMOTE_ADDR` is Cloudflare's edge, so a rate
 * limiter keyed on it limits the entire planet to five registrations per
 * fifteen minutes. And the obvious fix — trusting `X-Forwarded-For` — is
 * worse, because anyone can send that header directly to the origin and mint
 * a fresh identity per request, which turns the limiter off. The header to
 * trust is `CF-Connecting-IP`, and it is only trustworthy when the connection
 * actually came from Cloudflare, which is what the range check below is for.
 *
 * SECOND, the edge caches. A page that sets a session cookie or renders a
 * CSRF token must never be cached, or two visitors share one token and the
 * first POST after a deploy fails for everybody.
 */

/**
 * Cloudflare's published origin ranges.
 *
 * Hard-coded rather than fetched: this runs on a host that may have no
 * outbound HTTP at all, and a rate limiter that fails open because an API
 * call timed out is not a rate limiter. The list changes rarely — check
 * https://www.cloudflare.com/ips/ once a year, and note that a stale entry
 * here fails SAFE (we fall back to REMOTE_ADDR, which over-limits rather than
 * under-limits).
 */
const CF_RANGES_V4 = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
    '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
    '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
];

const CF_RANGES_V6 = [
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
    '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
];

function ip_in_cidr(string $ip, string $cidr): bool
{
    [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
    $bits = (int)$bits;
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false) return false;
    if (strlen($ipBin) !== strlen($subnetBin)) return false;

    $whole = intdiv($bits, 8);
    $rest  = $bits % 8;
    if ($whole > 0 && substr($ipBin, 0, $whole) !== substr($subnetBin, 0, $whole)) return false;
    if ($rest === 0) return true;

    $mask = chr((0xff << (8 - $rest)) & 0xff);
    return (($ipBin[$whole] & $mask) === ($subnetBin[$whole] & $mask));
}

/** True when this request really did come through Cloudflare. */
function behind_cloudflare(): bool
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remote === '' || !isset($_SERVER['HTTP_CF_CONNECTING_IP'])) return false;
    foreach (array_merge(CF_RANGES_V4, CF_RANGES_V6) as $cidr) {
        if (ip_in_cidr($remote, $cidr)) return true;
    }
    // The CF header is present but the connection did not come from
    // Cloudflare. That is somebody spoofing it, and it is worth a log line.
    error_log('[cloudflare] CF-Connecting-IP from a non-Cloudflare address ' . $remote . ' — ignored');
    return false;
}

/**
 * The address to hold responsible for this request.
 *
 * Replaces the naive X-Forwarded-For read in bootstrap.php, which was both
 * spoofable and wrong behind a proxy.
 */
function real_client_ip(): string
{
    if (behind_cloudflare()) {
        $cf = trim((string)$_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($cf, FILTER_VALIDATE_IP)) return $cf;
    }
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

/** The visitor's country, when Cloudflare has worked it out. Never trusted for access. */
function cf_country(): ?string
{
    if (!behind_cloudflare()) return null;
    $c = (string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');
    return preg_match('/^[A-Z]{2}$/', $c) ? $c : null;
}

/**
 * Tell every cache — Cloudflare's edge included — never to store this page.
 *
 * Any page that issues a session cookie or prints a CSRF token. Getting this
 * wrong does not look like a caching bug; it looks like the form randomly
 * telling people it expired, which is much harder to trace.
 */
function no_store(): void
{
    if (headers_sent()) return;
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('CDN-Cache-Control: no-store');
    header('Cloudflare-CDN-Cache-Control: no-store');
    header('Vary: Cookie');
}
