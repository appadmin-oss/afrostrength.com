<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Console components, in the grammar of Cloudscape.
 *
 * Cloudscape (cloudscape.design) is the system AWS built for consoles, and a
 * console is exactly what an advisor works in: dense tables, one row at a
 * time, all day, on whatever machine is free at the centre. Its grammar is
 * the part worth having —
 *
 *   app layout        top bar, side navigation, breadcrumbs, a notifications
 *                     region, content, and a help panel on the right
 *   header            heading + counter + description + actions, one shape
 *                     used at every level so "where are the actions" has one
 *                     answer
 *   container         the bordered box with its own header; the grouping
 *                     primitive, used instead of a card for everything
 *   key-value pairs   how a detail page reads out; label above value
 *   status indicator  icon + word, in ten fixed types, never colour alone
 *   flashbar          dismissible messages above the content, in a live region
 *   table view        header with a counter, filter, pagination and
 *                     preferences, plus separate empty and no-match states
 *   the Info link     a small link beside a heading that opens the help panel
 *
 * What is deliberately NOT taken is the skin. Cloudscape's greys and its blue
 * belong to AWS; dropping them on Afrotech would make the console look like a
 * borrowed thing sitting next to the site. The tokens below are the academy's
 * own, and the type is Archivo and Instrument Sans as everywhere else.
 *
 * The other deliberate departure: Cloudscape is React and this host has no
 * Node. Every component here renders on the server and works with JavaScript
 * off. Sorting, filtering and paging are query-string round trips. The
 * progressive enhancement — the navigation drawer, the help panel, dismissing
 * a flash — is a few dozen lines of vanilla JS, and each has a no-JS fallback
 * that is a real link to a real URL.
 */

/* ══════════════════════════════════════════════════════════════════════
   Layout primitives
   ══════════════════════════════════════════════════════════════════════ */

/**
 * Cloudscape's SpaceBetween: vertical rhythm decided by the parent, so a
 * child never carries a margin that is wrong in a different place.
 */
function ui_stack_open(string $size = 'm', string $class = ''): void
{
    echo '<div class="stack stack-' . e($size) . ($class !== '' ? ' ' . e($class) : '') . '">';
}
function ui_stack_close(): void { echo '</div>'; }

/** ColumnLayout. Collapses to one column under 768px, always. */
function ui_columns_open(int $columns = 2, string $class = ''): void
{
    $columns = max(1, min(4, $columns));
    echo '<div class="cols cols-' . $columns . ($class !== '' ? ' ' . e($class) : '') . '">';
}
function ui_columns_close(): void { echo '</div>'; }

/* ══════════════════════════════════════════════════════════════════════
   Header
   ══════════════════════════════════════════════════════════════════════ */

/**
 * @param array{
 *   variant?:string, counter?:?string, description?:?string,
 *   actions?:?string, info?:?string, tag?:?string
 * } $opts
 *
 * `counter` is Cloudscape's convention and it earns its place: "(47)" beside
 * a heading answers "how many of these are there" without the eye going
 * anywhere else. `info` is the Info link — the id of a help panel topic.
 */
function ui_header(string $text, array $opts = []): void
{
    $variant = $opts['variant'] ?? 'h2';
    $tag = $opts['tag'] ?? ($variant === 'h1' ? 'h1' : ($variant === 'h3' ? 'h3' : 'h2'));
    ?>
    <div class="hdr hdr-<?= e($variant) ?>">
      <div class="hdrRow">
        <<?= $tag ?> class="hdrTitle">
          <?= e($text) ?>
          <?php if (!empty($opts['counter'])): ?><span class="hdrCounter"><?= e((string)$opts['counter']) ?></span><?php endif; ?>
          <?php if (!empty($opts['info'])): ?>
            <a class="infoLink" href="?<?= e(http_build_query(array_merge($_GET, ['help' => $opts['info']]))) ?>"
               data-help="<?= e((string)$opts['info']) ?>">Info<span class="srOnly"> about <?= e($text) ?></span></a>
          <?php endif; ?>
        </<?= $tag ?>>
        <?php if (!empty($opts['actions'])): ?>
          <div class="hdrActions"><?= $opts['actions'] ?></div>
        <?php endif; ?>
      </div>
      <?php if (!empty($opts['description'])): ?>
        <p class="hdrDesc"><?= e((string)$opts['description']) ?></p>
      <?php endif; ?>
    </div>
    <?php
}

/* ══════════════════════════════════════════════════════════════════════
   Container
   ══════════════════════════════════════════════════════════════════════ */

function ui_container_open(?string $title = null, array $opts = []): void
{
    echo '<section class="box' . (!empty($opts['class']) ? ' ' . e((string)$opts['class']) : '') . '">';
    if ($title !== null) {
        echo '<div class="boxHead">';
        ui_header($title, $opts + ['variant' => 'h3']);
        echo '</div>';
    }
    echo '<div class="boxBody">';
}
function ui_container_close(?string $footer = null): void
{
    echo '</div>';
    if ($footer !== null) echo '<div class="boxFoot">' . $footer . '</div>';
    echo '</section>';
}

/* ══════════════════════════════════════════════════════════════════════
   Status indicator
   ══════════════════════════════════════════════════════════════════════ */

/**
 * Ten types, matching Cloudscape's set. Each is an icon AND a word: a status
 * told in colour alone fails WCAG 1.4.1 and fails the advisor with the common
 * form of colour blindness, who is looking at forty of these in a column.
 */
function ui_status(string $type, string $text): string
{
    $icons = [
        'success'     => '<path d="M4 8.5 7 11.5 12.5 4.5"/>',
        'error'       => '<circle cx="8" cy="8" r="6"/><path d="M8 5v4M8 11v.01"/>',
        'warning'     => '<path d="M8 2.5 14.5 13.5h-13z"/><path d="M8 7v3M8 11.8v.01"/>',
        'info'        => '<circle cx="8" cy="8" r="6"/><path d="M8 7.5v4M8 5v.01"/>',
        'in-progress' => '<circle cx="8" cy="8" r="6"/><path d="M8 4.5V8l2.5 1.5"/>',
        'pending'     => '<circle cx="8" cy="8" r="6"/><path d="M5 8h6"/>',
        'stopped'     => '<circle cx="8" cy="8" r="6"/><path d="M6 6h4v4H6z"/>',
        'loading'     => '<circle cx="8" cy="8" r="6" stroke-dasharray="9 5"/>',
        'not-started' => '<circle cx="8" cy="8" r="6" stroke-dasharray="2 3"/>',
        'log'         => '<path d="M4 3h8v10H4z"/><path d="M6 6h4M6 9h4"/>',
    ];
    $icon = $icons[$type] ?? $icons['info'];
    return '<span class="stat stat-' . e($type) . '">'
        . '<svg class="statIcon" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"'
        . ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icon . '</svg>'
        . '<span class="statText">' . e($text) . '</span></span>';
}

/** Applications carry ten statuses. One place decides how each one reads. */
function ui_application_status(string $status): string
{
    return match ($status) {
        'received'   => ui_status('pending', 'Received'),
        'in_review'  => ui_status('in-progress', 'In review'),
        'place_held' => ui_status('success', 'Place held'),
        'enrolled'   => ui_status('success', 'Enrolled'),
        'completed'  => ui_status('success', 'Completed'),
        'deferred'   => ui_status('stopped', 'Deferred'),
        'withdrawn'  => ui_status('stopped', 'Withdrawn'),
        'lapsed'     => ui_status('warning', 'Lapsed'),
        'declined'   => ui_status('error', 'Declined'),
        default      => ui_status('info', ucfirst(str_replace('_', ' ', $status))),
    };
}

/* ══════════════════════════════════════════════════════════════════════
   Badge, Box, Link
   ══════════════════════════════════════════════════════════════════════ */

function ui_badge(string $text, string $tone = 'grey'): string
{
    return '<span class="badge badge-' . e($tone) . '">' . e($text) . '</span>';
}

/* ══════════════════════════════════════════════════════════════════════
   Key-value pairs
   ══════════════════════════════════════════════════════════════════════ */

/**
 * @param array<int,array{label:string,value:string,info?:string}|array{type:'group',title:string,items:array}> $items
 *
 * Label above value, never beside it: a two-column label/value grid breaks
 * badly at 320px, which is most of the traffic at a centre.
 */
function ui_key_values(array $items, int $columns = 3): void
{
    echo '<div class="kv cols-' . max(1, min(4, $columns)) . '">';
    foreach ($items as $item) {
        if (($item['type'] ?? '') === 'group') {
            echo '<div class="kvGroup"><h4 class="kvGroupTitle">' . e($item['title']) . '</h4>';
            foreach ($item['items'] as $pair) ui_key_value_pair($pair);
            echo '</div>';
        } else {
            ui_key_value_pair($item);
        }
    }
    echo '</div>';
}

function ui_key_value_pair(array $pair): void
{
    $value = (string)($pair['value'] ?? '');
    echo '<div class="kvPair">';
    echo '<dt class="kvLabel">' . e((string)$pair['label']) . '</dt>';
    // An empty value is said, not left blank. A blank cell reads as a bug.
    echo '<dd class="kvValue' . ($value === '' ? ' kvEmpty' : '') . '">'
        . ($value === '' ? '<span class="kvDash" aria-label="not given">—</span>' : $value)
        . '</dd>';
    echo '</div>';
}

/* ══════════════════════════════════════════════════════════════════════
   Buttons
   ══════════════════════════════════════════════════════════════════════ */

/**
 * Three variants, and the rule Cloudscape enforces: one primary per view.
 * Two primaries is the same as none.
 */
function ui_button(string $label, array $opts = []): string
{
    $variant = $opts['variant'] ?? 'normal';
    $class = 'btn btn-' . $variant . (!empty($opts['full']) ? ' btnFull' : '');
    $attrs = '';
    foreach (($opts['attrs'] ?? []) as $k => $v) $attrs .= ' ' . $k . '="' . e((string)$v) . '"';

    if (!empty($opts['href'])) {
        return '<a class="' . $class . '" href="' . e((string)$opts['href']) . '"' . $attrs . '>'
            . e($label) . '</a>';
    }
    return '<button class="' . $class . '" type="' . e($opts['type'] ?? 'submit') . '"'
        . (!empty($opts['name']) ? ' name="' . e((string)$opts['name']) . '"' : '')
        . (!empty($opts['value']) ? ' value="' . e((string)$opts['value']) . '"' : '')
        . (!empty($opts['disabled']) ? ' disabled' : '')
        . $attrs . '>' . e($label) . '</button>';
}

/* ══════════════════════════════════════════════════════════════════════
   Alert and Flashbar
   ══════════════════════════════════════════════════════════════════════ */

function ui_alert(string $type, string $header, string $body = '', ?string $action = null): void
{
    ?>
    <div class="alert alert-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
      <span class="alertIcon"><?= ui_status($type, '') ?></span>
      <div class="alertBody">
        <p class="alertHead"><?= e($header) ?></p>
        <?php if ($body !== ''): ?><p class="alertText"><?= e($body) ?></p><?php endif; ?>
      </div>
      <?php if ($action !== null): ?><div class="alertAction"><?= $action ?></div><?php endif; ?>
    </div>
    <?php
}

/**
 * The flashbar sits above the content and outlives one request.
 *
 * Kept in the session rather than the query string so that a refresh after a
 * decision does not re-announce it, and so the message can be longer than a
 * URL wants to be.
 */
function flash(string $type, string $header, string $body = ''): void
{
    session_start_once();
    $_SESSION['flash'][] = ['type' => $type, 'header' => $header, 'body' => $body];
}

function ui_flashbar(): void
{
    session_start_once();
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    // The region exists even when empty, so a message arriving by JS later is
    // announced. An aria-live region added at the same moment as its content
    // is not announced at all.
    echo '<div class="flashbar" aria-live="polite" aria-atomic="false">';
    foreach ($items as $i => $f) {
        ?>
        <div class="flash flash-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
          <span class="flashIcon"><?= ui_status($f['type'], '') ?></span>
          <div class="flashBody">
            <p class="flashHead"><?= e($f['header']) ?></p>
            <?php if ($f['body'] !== ''): ?><p class="flashText"><?= e($f['body']) ?></p><?php endif; ?>
          </div>
          <button type="button" class="flashClose" data-dismiss aria-label="Dismiss this message">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
          </button>
        </div>
        <?php
    }
    echo '</div>';
}

/* ══════════════════════════════════════════════════════════════════════
   Empty states
   ══════════════════════════════════════════════════════════════════════ */

/**
 * Cloudscape separates these two and it matters: "no applications yet" and
 * "your filter matched nothing" need different words and different buttons.
 * Showing the empty state after a filter tells the advisor the system is
 * broken.
 */
function ui_empty(string $title, string $body, ?string $action = null): void
{
    ?>
    <div class="emptyState">
      <?= mark_empty(48) ?>
      <p class="emptyTitle"><?= e($title) ?></p>
      <p class="emptyText"><?= e($body) ?></p>
      <?php if ($action !== null): ?><div class="emptyAction"><?= $action ?></div><?php endif; ?>
    </div>
    <?php
}

function ui_no_match(string $clearHref): void
{
    ?>
    <div class="emptyState">
      <?= mark_no_results(48) ?>
      <p class="emptyTitle">No matches</p>
      <p class="emptyText">Nothing here matches what you searched for.</p>
      <div class="emptyAction"><?= ui_button('Clear filters', ['href' => $clearHref]) ?></div>
    </div>
    <?php
}

/* ══════════════════════════════════════════════════════════════════════
   Table view
   ══════════════════════════════════════════════════════════════════════ */

/** The query string minus the keys being replaced — for sort and page links. */
function ui_query(array $changes): string
{
    $q = $_GET;
    foreach ($changes as $k => $v) {
        if ($v === null) unset($q[$k]); else $q[$k] = $v;
    }
    unset($q['help']);
    return $q === [] ? '?' : '?' . http_build_query($q);
}

/**
 * A sortable column header.
 *
 * A link, not a button with JavaScript, so it is bookmarkable, works with the
 * keyboard for free, and survives a browser back. aria-sort tells a screen
 * reader which way the column is currently ordered.
 */
function ui_sort_header(string $key, string $label, string $current, string $dir): string
{
    $on = $current === $key;
    $next = ($on && $dir === 'asc') ? 'desc' : 'asc';
    $sortAttr = $on ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';
    $arrow = $on
        ? ($dir === 'asc' ? '<path d="M8 12V4M4.5 7.5 8 4l3.5 3.5"/>' : '<path d="M8 4v8M4.5 8.5 8 12l3.5-3.5"/>')
        : '';
    return '<th scope="col" aria-sort="' . $sortAttr . '">'
        . '<a class="sortLink' . ($on ? ' sortOn' : '') . '" href="'
        . e(ui_query(['sort' => $key, 'dir' => $next, 'page' => null])) . '">'
        . e($label)
        . ($on ? '<svg class="sortIcon" width="16" height="16" viewBox="0 0 16 16" fill="none"'
            . ' stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"'
            . ' aria-hidden="true">' . $arrow . '</svg>' : '')
        . '</a></th>';
}

/** Cloudscape's text filter: one input, submits on enter, no JS required. */
function ui_text_filter(string $value, string $placeholder = 'Find by name, email or reference'): void
{
    ?>
    <form class="tableFilter" method="get" role="search">
      <?php foreach ($_GET as $k => $v):
        if (in_array($k, ['q', 'page', 'help'], true) || is_array($v)) continue; ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>">
      <?php endforeach; ?>
      <label class="srOnly" for="tableQ">Search</label>
      <span class="filterWrap">
        <svg class="filterIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="6"/><path d="M15.5 15.5 20 20"/>
        </svg>
        <input id="tableQ" class="filterInput" type="search" name="q" value="<?= e($value) ?>"
               placeholder="<?= e($placeholder) ?>" autocomplete="off">
      </span>
      <button class="btn btn-normal" type="submit">Search</button>
      <?php if ($value !== ''): ?>
        <a class="btn btn-link" href="<?= e(ui_query(['q' => null, 'page' => null])) ?>">Clear</a>
      <?php endif; ?>
    </form>
    <?php
}

/**
 * A select that submits itself, degrading to a Go button without JavaScript.
 * Cloudscape's property filter is the richer answer; this is the honest
 * subset that works on a page with no framework.
 */
function ui_select_filter(string $name, string $label, array $options, string $value): void
{
    ?>
    <form class="selectFilter" method="get">
      <?php foreach ($_GET as $k => $v):
        if ($k === $name || $k === 'page' || $k === 'help' || is_array($v)) continue; ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>">
      <?php endforeach; ?>
      <label class="selectLabel" for="f_<?= e($name) ?>"><?= e($label) ?></label>
      <select id="f_<?= e($name) ?>" name="<?= e($name) ?>" class="selectInput" data-autosubmit>
        <?php foreach ($options as $val => $text): ?>
          <option value="<?= e((string)$val) ?>"<?= (string)$val === $value ? ' selected' : '' ?>><?= e((string)$text) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-normal selectGo" type="submit">Apply</button>
    </form>
    <?php
}

/** Pagination. Page numbers, not "load more": an advisor needs to come back. */
function ui_pagination(int $page, int $pages, int $total, string $noun = 'application'): void
{
    if ($pages <= 1) return;
    $window = 2;
    ?>
    <nav class="pager" aria-label="Pages">
      <p class="pagerCount">
        Page <?= $page ?> of <?= $pages ?> · <?= number_format($total) ?> <?= e($noun) ?><?= $total === 1 ? '' : 's' ?>
      </p>
      <ul class="pagerList">
        <li><?= $page > 1
            ? '<a class="pagerLink" href="' . e(ui_query(['page' => $page - 1])) . '" rel="prev">Previous</a>'
            : '<span class="pagerLink pagerOff">Previous</span>' ?></li>
        <?php for ($i = 1; $i <= $pages; $i++):
          if ($i !== 1 && $i !== $pages && abs($i - $page) > $window) {
              if (abs($i - $page) === $window + 1) echo '<li><span class="pagerGap">…</span></li>';
              continue;
          } ?>
          <li><?= $i === $page
              ? '<span class="pagerLink pagerOn" aria-current="page">' . $i . '<span class="srOnly">, current page</span></span>'
              : '<a class="pagerLink" href="' . e(ui_query(['page' => $i])) . '">' . $i . '</a>' ?></li>
        <?php endfor; ?>
        <li><?= $page < $pages
            ? '<a class="pagerLink" href="' . e(ui_query(['page' => $page + 1])) . '" rel="next">Next</a>'
            : '<span class="pagerLink pagerOff">Next</span>' ?></li>
      </ul>
    </nav>
    <?php
}

/* ══════════════════════════════════════════════════════════════════════
   Collection preferences
   ══════════════════════════════════════════════════════════════════════ */

const UI_DENSITIES = ['comfortable', 'compact'];
const UI_PAGE_SIZES = [10, 25, 50, 100];

/**
 * Density and page size, remembered in a cookie.
 *
 * Cloudscape's compact mode exists because someone reading forty rows all day
 * wants forty rows on the screen, and the same person's colleague, once a
 * week, wants room to read. Neither is wrong, so it is a preference. A cookie
 * rather than a column on `staff`, because it belongs to the machine as much
 * as the person — the shared desk at the centre is set up how that desk is
 * used.
 */
function ui_prefs(): array
{
    $density = $_COOKIE['at_density'] ?? 'comfortable';
    $size = (int)($_COOKIE['at_page_size'] ?? 25);
    return [
        'density' => in_array($density, UI_DENSITIES, true) ? $density : 'comfortable',
        'page_size' => in_array($size, UI_PAGE_SIZES, true) ? $size : 25,
    ];
}

function ui_prefs_save(string $density, int $size): void
{
    $opts = [
        'expires' => time() + 31536000,
        'path' => base_path() === '' ? '/' : base_path(),
        'httponly' => false,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ];
    setcookie('at_density', in_array($density, UI_DENSITIES, true) ? $density : 'comfortable', $opts);
    setcookie('at_page_size', (string)(in_array($size, UI_PAGE_SIZES, true) ? $size : 25), $opts);
}

function ui_preferences_control(array $prefs): void
{
    ?>
    <details class="prefs">
      <summary class="prefsButton">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
          <circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/>
        </svg>
        Preferences
      </summary>
      <form class="prefsPanel" method="post" action="<?= e(ui_query([])) ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="prefs">
        <fieldset class="prefsGroup">
          <legend>Rows per page</legend>
          <?php foreach (UI_PAGE_SIZES as $n): ?>
            <label class="prefsRadio">
              <input type="radio" name="page_size" value="<?= $n ?>"<?= $prefs['page_size'] === $n ? ' checked' : '' ?>>
              <span><?= $n ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <fieldset class="prefsGroup">
          <legend>Density</legend>
          <label class="prefsRadio">
            <input type="radio" name="density" value="comfortable"<?= $prefs['density'] === 'comfortable' ? ' checked' : '' ?>>
            <span>Comfortable</span>
          </label>
          <label class="prefsRadio">
            <input type="radio" name="density" value="compact"<?= $prefs['density'] === 'compact' ? ' checked' : '' ?>>
            <span>Compact</span>
          </label>
        </fieldset>
        <div class="prefsFoot"><?= ui_button('Save preferences', ['variant' => 'primary']) ?></div>
      </form>
    </details>
    <?php
}

/* ══════════════════════════════════════════════════════════════════════
   Form layout
   ══════════════════════════════════════════════════════════════════════ */

/**
 * @param array{label:string,name:string,type?:string,value?:string,hint?:string,
 *              error?:string,required?:bool,options?:array,rows?:int,
 *              autocomplete?:string,inputmode?:string} $f
 *
 * The label is persistent and above the field; the hint is below the label
 * and above the control, so it is read before the field is filled rather than
 * after; the error is beside the field and wired with aria-describedby.
 * Placeholder is never the label.
 */
function ui_field(array $f): void
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $f['name']);
    $type = $f['type'] ?? 'text';
    $hasHint = !empty($f['hint']);
    $hasError = !empty($f['error']);
    $describedBy = trim(($hasHint ? "$id-hint " : '') . ($hasError ? "$id-err" : ''));
    ?>
    <div class="field<?= $hasError ? ' fieldBad' : '' ?>">
      <label class="fieldLabel" for="<?= e($id) ?>">
        <?= e($f['label']) ?>
        <?php if (empty($f['required'])): ?><span class="fieldOptional"> — optional</span><?php endif; ?>
      </label>
      <?php if ($hasHint): ?><p class="fieldHint" id="<?= e($id) ?>-hint"><?= e((string)$f['hint']) ?></p><?php endif; ?>
      <?php if ($type === 'textarea'): ?>
        <textarea class="control" id="<?= e($id) ?>" name="<?= e($f['name']) ?>"
                  rows="<?= (int)($f['rows'] ?? 5) ?>"
                  <?= !empty($f['required']) ? 'required' : '' ?>
                  <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?>
                  <?= $hasError ? 'aria-invalid="true"' : '' ?>><?= e((string)($f['value'] ?? '')) ?></textarea>
      <?php elseif ($type === 'select'): ?>
        <select class="control" id="<?= e($id) ?>" name="<?= e($f['name']) ?>"
                <?= !empty($f['required']) ? 'required' : '' ?>
                <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?>
                <?= $hasError ? 'aria-invalid="true"' : '' ?>>
          <?php foreach (($f['options'] ?? []) as $val => $text): ?>
            <option value="<?= e((string)$val) ?>"<?= (string)$val === (string)($f['value'] ?? '') ? ' selected' : '' ?>><?= e((string)$text) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input class="control" id="<?= e($id) ?>" name="<?= e($f['name']) ?>" type="<?= e($type) ?>"
               value="<?= e((string)($f['value'] ?? '')) ?>"
               <?= !empty($f['required']) ? 'required' : '' ?>
               <?= !empty($f['autocomplete']) ? 'autocomplete="' . e((string)$f['autocomplete']) . '"' : '' ?>
               <?= !empty($f['inputmode']) ? 'inputmode="' . e((string)$f['inputmode']) . '"' : '' ?>
               <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?>
               <?= $hasError ? 'aria-invalid="true"' : '' ?>>
      <?php endif; ?>
      <?php if ($hasError): ?>
        <p class="fieldError" id="<?= e($id) ?>-err">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
               stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
            <circle cx="8" cy="8" r="6"/><path d="M8 5v4M8 11v.01"/>
          </svg>
          <?= e((string)$f['error']) ?>
        </p>
      <?php endif; ?>
    </div>
    <?php
}

/** The form's actions: right-aligned, cancel first, one primary. */
function ui_form_actions(string $primary, ?string $cancelHref = null, array $opts = []): void
{
    echo '<div class="formActions">';
    if ($cancelHref !== null) {
        echo ui_button($opts['cancel_label'] ?? 'Cancel', ['href' => $cancelHref, 'variant' => 'link']);
    }
    echo ui_button($primary, ['variant' => 'primary'] + $opts);
    echo '</div>';
}
