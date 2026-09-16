<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Cache-busted asset URLs.
 *
 * NextGenGen ships its CSS and JSX with a seven-day max-age and
 * `must-revalidate`, which is the right instinct on shared hosting — long
 * cache, but the browser checks before reusing. It has a gap, though, and
 * Cloudflare widens it: `must-revalidate` governs the browser, and the edge
 * happily serves a stale file for its own TTL. Push a CSS fix and some
 * visitors keep the old one until the edge decides otherwise, or until
 * somebody remembers to purge.
 *
 * This closes it the boring way: the URL changes when the file does. A file
 * whose URL is new cannot be served from any cache, browser or edge, because
 * nothing has it. And an old URL nobody references can sit in the edge cache
 * forever at no cost.
 *
 * The hash is the file's mtime and size, not its contents. Reading and
 * hashing every asset on every request is real work on a host with one shared
 * CPU; `stat` is nearly free, and mtime+size changes on every upload. The
 * one case it misses — a file rewritten to exactly the same size within the
 * same second — is not a case that happens to a CSS file uploaded over FTP.
 */
/**
 * Where this app is mounted, with no trailing slash.
 *
 * On the academy's host the registration desk sits at /apply, under a
 * document root that belongs to the static site — so a root-relative asset
 * URL points at the wrong place and the form renders unstyled. It is a
 * setting rather than a guess from SCRIPT_NAME, because the include
 * bootstrap in public_html means SCRIPT_NAME and __DIR__ disagree.
 */
function base_path(): string
{
    return rtrim((string)cfg('site.base_path', ''), '/');
}

/**
 * A link from one surface to another.
 *
 * The surfaces — apply, console, portal, help — are siblings at the site
 * root, so a link between them is root-relative, not "../". Relative ones
 * were already wrong: the portal's "Ask for a place" pointed at ../apply/,
 * which is right when the surfaces are siblings and 404s when they are not.
 * One helper means there is one place to be wrong, and it is right.
 */
function app_url(string $path): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $clean = ltrim($path, '/');
    $file = dirname(__DIR__) . '/public/' . $clean;
    $stat = @stat($file);
    if ($stat === false) {
        // Missing file: return the plain path rather than a fake version, so
        // the 404 in the browser console names the real problem.
        error_log('[asset] not found: ' . $file);
        return base_path() . '/' . $clean;
    }
    // xxh3 is PHP 8.1+; a host on an older build falls back rather than
    // fataling on a page that is otherwise fine.
    static $algo = null;
    if ($algo === null) $algo = in_array('xxh3', hash_algos(), true) ? 'xxh3' : 'crc32b';
    $version = substr(hash($algo, $stat['mtime'] . ':' . $stat['size']), 0, 10);
    return base_path() . '/' . $clean . '?v=' . $version;
}

/**
 * Cache headers for a page that is the same for everybody.
 *
 * Not used by the form — that one calls no_store(), because it carries a CSRF
 * token. This is here for anything static that gets added later.
 */
function cache_public(int $seconds = 300): void
{
    if (headers_sent()) return;
    header('Cache-Control: public, max-age=' . $seconds . ', must-revalidate');
    header('CDN-Cache-Control: public, max-age=' . ($seconds * 12));
}
