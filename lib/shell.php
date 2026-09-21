<?php
declare(strict_types=1);

require_once __DIR__ . '/site-shell.php';
require_once __DIR__ . '/directory.php';

/**
 * The contractor directory's chrome — which is now the site's chrome.
 *
 * This used to render a masthead of its own that said "Afrostrength
 * contractors", from when the directory was going to be its own site on its
 * own subdomain. It is a feature of afrostrength.com, so a visitor moving
 * from the homepage to the directory should not feel the header change under
 * them, lose the navigation back, or wonder whether they have left.
 *
 * So page_head() and page_foot() stay — six pages call them and there was no
 * reason to touch six files — but they are a thin shim over the studio site's
 * header and footer, plus the narrow reading column those pages are written
 * for. One header, one footer, one site.
 */
function page_head(string $title, string $current = '', ?string $description = null): void
{
    /*
     * Everything under this shim is part of the directory, so the Directory
     * nav item is the one that says "you are here" — whichever of the four
     * pages is being read. The old per-page keys ('join/', 'post/', 'work/')
     * are accepted and collapsed rather than made into four nav items the
     * design does not have.
     */
    site_head($title . ' — Afrostrength', 'directory', $description);
    echo '<div class="wrap">';
}

function page_foot(): void
{
    /*
     * The two sentences the directory has to keep saying. They are the
     * product's terms in plain words — what the fee is for, and that nobody's
     * money passes through Afrostrength — and they belong near the directory
     * rather than in the studio footer, where they would be a non-sequitur on
     * a page about brand strategy.
     */
    ?>
  <p class="fine">
    Afrostrength checks every listing before it appears, and charges a fee when it introduces a
    contractor to a client. It does not hold anyone's money: a client pays their contractor
    directly.
  </p>
</div>
<?php
    site_foot();
}
