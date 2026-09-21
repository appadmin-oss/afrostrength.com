<?php
declare(strict_types=1);

/**
 * afrostrength.com — the homepage.
 *
 * Built to `Afrostrength Homepage v10`. The copy is the handoff's, verbatim:
 * it was written and approved, and a paraphrase here is a change nobody asked
 * for. Where the design shows a placeholder — the three [PRICE TBC] slots and
 * the two [CLIENT QUOTE TBC] quotes — the placeholder ships, because the
 * handoff is explicit that the academy will not publish a price it has not
 * set or a quote a client has not approved.
 *
 * ── Rendered on the server, not in the browser ───────────────────────────
 *
 * The prototype drives its tabs and its wizard from a JavaScript class. This
 * does neither. The capabilities board reads ?tab= and the enquiry wizard
 * posts and re-renders, so both work with scripting off and both survive the
 * back button. The page has one job — get a qualified enquiry — and an
 * enquiry form that needs a script to submit is an enquiry form that loses
 * people on a bad connection.
 */

require_once __DIR__ . '/../lib/site-shell.php';
require_once __DIR__ . '/../lib/enquiries.php';
require_once __DIR__ . '/../lib/guards.php';
require_once __DIR__ . '/../lib/cloudflare.php';

/*
 * The brief and the wizard, server-side.
 *
 * State lives in the session, so a step survives a reload, the back button
 * and a failed validation — and the draft is never thrown away. The handoff
 * asks for the brief to be carried into the enquiry; here that is literally
 * the same session value, so what somebody typed at the top of the page is
 * still there three sections later.
 *
 * Nothing is cached once a person has started: a shared cache serving step 2
 * of somebody else's enquiry would be a data leak, not a stale page.
 */
session_start_once();
$wiz = &$_SESSION['enquiry'];
if (!is_array($wiz)) {
    $wiz = ['step' => 1, 'problem' => '', 'kind' => '', 'email' => '', 'name' => '',
            'consent' => false, 'errors' => [], 'ref' => '', 'carried' => false];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $wiz['errors'] = ['_form' => 'This page was open a while and the form expired. Nothing was lost — press it again.'];
    } elseif (trim((string)($_POST['website'] ?? '')) !== '') {
        // A honeypot. It answers as though it worked, because telling a bot it
        // was caught only teaches whoever wrote it to stop filling that field.
        $wiz = ['step' => 4, 'problem' => '', 'kind' => '', 'email' => (string)($_POST['email'] ?? ''),
                'name' => '', 'consent' => true, 'errors' => [], 'ref' => '', 'carried' => false];
    } elseif ($action === 'brief') {
        // One sentence at the top of the page, carried into step 2 below.
        $wiz['problem'] = trim((string)($_POST['problem'] ?? ''));
        $err = enquiry_validate($wiz, 1);
        if ($err !== []) {
            $wiz['errors'] = $err;
        } else {
            $wiz['errors'] = [];
            $wiz['carried'] = true;
            $wiz['step'] = 2;
        }
    } elseif ($action === 'back') {
        $wiz['errors'] = [];
        $wiz['step'] = max(1, (int)$wiz['step'] - 1);
    } elseif ($action === 'next') {
        $step = (int)$wiz['step'];
        if ($step === 1) $wiz['problem'] = trim((string)($_POST['problem'] ?? ''));
        if ($step === 2) $wiz['kind'] = (string)($_POST['kind'] ?? '');
        if ($step === 3) {
            $wiz['email'] = trim((string)($_POST['email'] ?? ''));
            $wiz['name'] = trim((string)($_POST['name'] ?? ''));
            $wiz['consent'] = !empty($_POST['consent']);
        }

        $err = enquiry_validate($wiz, $step);
        if ($err !== []) {
            // The draft stays exactly as typed. Re-rendering the step with the
            // boxes empty is how a form loses somebody for good.
            $wiz['errors'] = $err;
        } elseif ($step < 3) {
            $wiz['errors'] = [];
            $wiz['step'] = $step + 1;
        } else {
            $limit = rate_limit('enquiry:' . real_client_ip(), 6, 900);
            if (!$limit['allowed']) {
                $wiz['errors'] = ['_form' => 'That is several enquiries in a short time. Wait a few minutes — if you have already sent one, it is with us.'];
            } else {
                try {
                    $r = enquiry_create($wiz + ['source' => trim((string)($_GET['from'] ?? ''))]);
                    if ($r['ok']) {
                        // array_merge, not +. The union operator keeps the LEFT
                        // side for a duplicate key, and $wiz already carries an
                        // empty 'ref' — which sent a notice headed "New project
                        // enquiry —" with nothing after the dash.
                        enquiry_notify(array_merge($wiz, [
                            'ref' => $r['ref'],
                            'full_name' => $wiz['name'],
                        ]));
                        $wiz['ref'] = (string)$r['ref'];
                        $wiz['errors'] = [];
                        $wiz['step'] = 4;
                    } else {
                        $wiz['errors'] = $r['errors'];
                        // Send them back to whichever step the problem is on,
                        // rather than showing an error they cannot reach.
                        if (isset($r['errors']['problem'])) $wiz['step'] = 1;
                    }
                } catch (Throwable $ex) {
                    error_log('[enquiry] could not save: ' . $ex->getMessage());
                    $wiz['errors'] = ['_form' => 'Something broke at our end and your enquiry was not saved. Nothing you typed is lost — press Send enquiry again, or call us.'];
                }
            }
        }
    }

    // Redirect after POST, so a reload does not re-send.
    header('Location: ' . app_url('') . '#' . ($wiz['step'] >= 2 ? 'contact' : 'brief'));
    exit;
}

// A person part-way through must not be served from a shared cache.
if ((int)$wiz['step'] > 1 || $wiz['problem'] !== '') { no_store(); } else { cache_public(300); }

$tabs = ['brand' => 'Brand system', 'digital' => 'Digital &amp; growth', 'software' => 'Software'];
$tab = (string)($_GET['tab'] ?? 'brand');
if (!isset($tabs[$tab])) $tab = 'brand';

site_head(
    'Afrostrength — brand identity and custom software',
    '',
    'Afrostrength builds brand identity and custom software for ambitious companies — one team, from strategy through launch, and still there after it.',
);
?>

<!-- ══ 1. Hero ═══════════════════════════════════════════════════════════ -->
<section class="hero" aria-labelledby="hero-h">
  <div class="shell">
    <div class="heroText">
      <a class="pill" href="<?= e(academy_url()) ?>">
        <span class="pillDot" aria-hidden="true"></span>
        <span class="pillNew">New</span>
        <span class="pillBar" aria-hidden="true"></span>
        <span>AfroTech Academy is taking applications</span>
        <?= icon('arrow-right', 13, 2.2) ?>
      </a>

      <h1 id="hero-h" class="heroH1">Brands that earn trust.<br>Software that runs the business.</h1>

      <p class="heroSub">
        Afrostrength builds brand identity and custom software for ambitious companies — one team,
        from strategy through launch, and still there after it.
      </p>

      <div class="heroCtas">
        <a class="btnGrad" href="#contact">Start a project</a>
        <a class="btnQuiet" href="#work">See our work<?= icon('arrow-right', 16, 2) ?></a>
      </div>

      <p class="heroMeta">
        Branding · Software · Marketing — working across Africa, Europe and North America since 2018
      </p>
    </div>

    <?php /*
      The hero mockup. Everything in it is markup and CSS except the one image
      slot, which the handoff names as the single real photograph on the page.
      It ships as an empty, labelled frame rather than as stock: the handoff
      says not to invent imagery to fill space, and a placeholder that states
      its own brief is more use to whoever shoots it than a stand-in nobody
      remembers to replace.
    */ ?>
    <div class="mock">
      <div class="mockChrome">
        <span class="mockDots" aria-hidden="true"><i></i><i></i><i></i></span>
        <span class="mockUrl"><?= icon('lock', 12, 2) ?>alimoshonigeria.com</span>
        <span class="mockLive">Live</span>
      </div>
      <div class="mockBody">
        <div class="mockSite">
          <div class="mockSiteTop">
            <span class="brand"><?= brand_mark(23, 7) ?><span class="mockSiteName">Alimosho Nigeria</span></span>
            <span class="mockSiteNav"><span>Products</span><span>Stockists</span><span>Story</span></span>
          </div>
          <p class="mockSiteH">Grown in Lagos.<br>Trusted in five states.</p>
          <p class="mockSiteP">One documented system behind the signboard, the pack, the invoice and the post.</p>
          <p class="mockSiteCtas">
            <span class="mockPill">Find a stockist</span>
            <span class="mockLink">Our story →</span>
          </p>
          <div class="imageSlot">
            <span class="imageSlotNote">Client product photograph · 1600 × 620</span>
          </div>
        </div>

        <div class="mockSide">
          <div class="mockCard">
            <p class="mockLabel">Identity system</p>
            <div class="swatches" aria-hidden="true"><i class="sw1"></i><i class="sw2"></i><i class="sw3"></i><i class="sw4"></i></div>
            <p class="mockTypeRow"><span class="mockAa">Aa</span><span class="mockFonts">Archivo<br>Instrument Sans</span></p>
          </div>
          <div class="mockCard">
            <p class="mockLabel">Orders tool <span class="chipSynced">synced</span></p>
            <ul class="orderList">
              <li><span>#1042 · Adeyemi</span><span class="orderState">packed</span></li>
              <li><span>#1043 · Bello Stores</span><span class="orderState">in transit</span></li>
              <li><span>#1044 · Chidera O.</span><span class="orderState orderWarn">needs stock</span></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="statCard">
        <p class="statNum">+40%</p>
        <p class="statWhat">customer engagement after the Alimosho Nigeria rebrand</p>
      </div>
    </div>
  </div>
</section>

<!-- ══ 2. Client row ═════════════════════════════════════════════════════ -->
<section class="clients" aria-label="Organisations we have worked with">
  <div class="shell">
    <p class="eyebrow eyebrowMuted">Among the organisations we have worked with</p>
    <?php /*
      Text, by design. The handoff asks for single-colour SVG wordmarks once
      they are supplied and warns that mixed full-colour logos would turn the
      strip into a jumble — so these stay type until those arrive.
    */ ?>
    <ul class="clientList">
      <?php foreach (["Alimosho Nigeria", "Afrovanguard", "D'Vanguard Summit 2024", "Maryams Movement"] as $c): ?>
        <li><?= e($c) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ══ 3. Capabilities ═══════════════════════════════════════════════════ -->
<section class="caps" id="capabilities" aria-labelledby="cap-h">
  <div class="shell">
    <div class="capsIntro">
      <p class="eyebrow">Capabilities</p>
      <h2 id="cap-h" class="h2">One studio for the brand, the channels and the systems underneath</h2>
      <p class="lede">
        Engagements run remotely with written scope and weekly reviews, so location never decides
        the standard of the work.
      </p>
    </div>

    <?php /*
      Links, not buttons. The panel is rendered on the server, so each tab is
      a real URL somebody can share or come back to — and it costs nothing
      when the script never loads. role="tab" would be a lie about markup
      that navigates, so the group is labelled as what it is.
    */ ?>
    <div class="tabs" role="group" aria-label="Capability areas">
      <?php foreach ($tabs as $key => $label): ?>
        <a class="tab<?= $tab === $key ? ' tabOn' : '' ?>"
           href="?tab=<?= e($key) ?>#capabilities"
           <?= $tab === $key ? 'aria-current="true"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
    </div>

    <?php
    $panels = [
      'brand' => [
        'h' => 'Decide what you stand for, then look like it everywhere',
        'p' => 'Positioning and messaging written down, then a complete identity — and the rules that keep it consistent on a signboard, an invoice and an Instagram post.',
        'points' => [
          'Positioning, audience and message architecture',
          'Logo, type and colour as a documented system',
          'Guidelines your team can apply without us',
        ],
        'meta' => [['Deliverables', 'Identity kit · Guidelines · Templates'], ['', '4—6 weeks']],
      ],
      'digital' => [
        'h' => 'Put it in front of the people who actually buy',
        'p' => 'Website, social and campaign work built off the identity rather than beside it, so a paid post, a landing page and a printed flier read as one company.',
        'points' => [
          'Websites and landing pages that stay fast on any connection',
          'Social systems and campaign asset kits',
          'Rebrands and refreshes for established businesses',
        ],
        'meta' => [['Channels', 'Web · Instagram · Print · Signage'], ['Asset kit', 'Templates the team edits themselves']],
      ],
      'software' => [
        'h' => 'Custom applications, built to integrate with what you already run',
        'p' => 'Internal tools, customer portals and integrations — usually the need that brand work exposes.',
        'points' => [
          'Built for one business, not configured from a template',
          'Integrates with the tools and records you keep today',
          'Support and maintenance on a stated response time',
        ],
        'meta' => [],
      ],
    ];
    $panel = $panels[$tab];
    ?>
    <div class="capBoard">
      <div class="capWords">
        <h3 class="h3"><?= e($panel['h']) ?></h3>
        <p class="lede"><?= e($panel['p']) ?></p>
        <ul class="ticks">
          <?php foreach ($panel['points'] as $point): ?>
            <li><span class="tickMark" aria-hidden="true"><?= icon('tick', 15, 2.6) ?></span><?= e($point) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="capVisual">
        <?php if ($tab === 'brand'): ?>
          <div class="capTile">
            <p class="mockLabel">Logo lockup</p>
            <p class="capLockup"><?= brand_mark(30, 8) ?><span>Client mark</span></p>
          </div>
          <div class="capTile">
            <p class="mockLabel">Type</p>
            <p class="mockTypeRow"><span class="mockAa">Aa</span><span class="mockFonts">Display / Text / Mono</span></p>
          </div>
          <div class="capTile">
            <p class="mockLabel">Palette</p>
            <div class="swatches" aria-hidden="true"><i class="sw1"></i><i class="sw2"></i><i class="sw3"></i><i class="sw4"></i></div>
          </div>
        <?php elseif ($tab === 'digital'): ?>
          <div class="capTile capTileWide">
            <p class="mockLabel">Engagement over a campaign cycle</p>
            <p class="capBig">+40%</p>
            <div class="capBars" aria-hidden="true"><i style="height:34%"></i><i style="height:48%"></i><i style="height:57%"></i><i style="height:71%"></i><i style="height:84%"></i><i style="height:100%"></i></div>
            <p class="capAxis"><span>Month 1</span><span>Month 6</span></p>
          </div>
        <?php else: ?>
          <div class="capTile capTileWide">
            <p class="mockLabel">orders — internal tool</p>
            <div class="capApp">
              <ul class="capAppNav"><li>Menu</li><li class="capAppOn">Orders</li><li>Customers</li><li>Inventory</li><li>Reports</li></ul>
              <div class="capAppMain">
                <p class="capAppTop"><span>Today</span><span class="chipSynced">synced</span></p>
                <ul class="orderList">
                  <li><span>#1042 · Adeyemi</span><span class="orderState">packed</span></li>
                  <li><span>#1043 · Bello Stores</span><span class="orderState">in transit</span></li>
                  <li><span>#1044 · Chidera O.</span><span class="orderState orderWarn">needs stock</span></li>
                  <li><span>#1045 · Maryams</span><span class="orderState">delivered</span></li>
                </ul>
              </div>
            </div>
          </div>
        <?php endif; ?>
        <?php foreach ($panel['meta'] as [$k, $v]): ?>
          <p class="capMeta"><?php if ($k !== ''): ?><span class="mockLabel"><?= e($k) ?></span><?php endif; ?><span><?= e($v) ?></span></p>
        <?php endforeach; ?>
        <?php if ($tab === 'software'): ?>
          <p class="capNote">Illustrative interface — a real screenshot replaces this once a client clears it.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- ══ 4. By the numbers ═════════════════════════════════════════════════ -->
<section class="numbers" aria-label="Afrostrength by the numbers">
  <div class="shell">
    <ul class="numberList">
      <?php foreach ([
        ['2018',  'Delivering internationally since'],
        ['5',     'Disciplines under one roof, no subcontractors'],
        ['4—6',   'Typical weeks from kickoff to identity handover'],
        ['1 day', 'Reply time on a project enquiry'],
      ] as [$n, $what]): ?>
        <li><span class="numberNum"><?= e($n) ?></span><span class="numberWhat"><?= e($what) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ══ 5. Sectors ════════════════════════════════════════════════════════ -->
<section class="sectors" id="sectors" aria-labelledby="sect-h">
  <div class="shell">
    <p class="eyebrow">Who we work with</p>
    <h2 id="sect-h" class="h2">Sectors where identity and systems have to agree</h2>
    <p class="lede">
      If your category is not listed, the question we ask first is the same: what decision are
      customers failing to make?
    </p>
    <ul class="sectorList">
      <?php foreach ([
        'Consumer goods &amp; retail', 'Financial &amp; professional services',
        'Health &amp; education providers', 'Logistics &amp; distribution',
        'Nonprofits &amp; member organisations', 'Events &amp; cultural programmes',
        'Technology &amp; software teams', 'Hospitality &amp; food service',
      ] as $s): ?>
        <li><?= $s ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ══ 6. Work ═══════════════════════════════════════════════════════════ -->
<section class="work" id="work" aria-labelledby="work-h">
  <div class="shell">
    <div class="workTop">
      <div>
        <p class="eyebrow">Customer stories</p>
        <h2 id="work-h" class="h2">Four examples of how the work runs</h2>
      </div>
      <a class="btnQuiet" href="#brief">Bring us a different problem<?= icon('arrow-right', 16, 2) ?></a>
    </div>

    <ul class="workGrid">
      <?php foreach ([
        ['01 · brand identity',  "Alimosho<br>Nigeria",        'From invisible to a name buyers trust',
         'One identity applied across signage, print and social, plus a written system the team runs without us.',
         '+40% engagement', true],
        ['02 · digital branding', "Afro<br>vanguard",           'A campaign system the team could run itself',
         'Templates, type and layout rules so every post ships looking like the same organisation.',
         '2022 · social + web', false],
        ['03 · event identity',   "D'Vanguard<br>Summit &rsquo;24", 'One identity across a three-day summit',
         'Stage, badge, signage and social assets produced from a single kit, on an event deadline.',
         '2024 · event', false],
        ['04 · website + social', "Maryams<br>Movement",        'A site that loads fast on any connection',
         'Built light and measured on real handsets across markets, with a social system that matches exactly.',
         '2023 · web build', false],
      ] as [$kicker, $cover, $h, $p, $meta, $grad]): ?>
        <li class="workCard">
          <div class="workCover<?= $grad ? ' workCoverGrad' : '' ?>">
            <span class="workKicker"><?= e($kicker) ?></span>
            <span class="workCoverName"><?= $cover ?></span>
          </div>
          <h3 class="h4"><?= e($h) ?></h3>
          <p class="workBody"><?= e($p) ?></p>
          <p class="workFoot">
            <span class="workMeta"><?= e($meta) ?></span>
            <a class="workLink" href="#brief">Read the story<?= icon('arrow-right', 14, 2.2) ?></a>
          </p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ══ 7. Case study ═════════════════════════════════════════════════════ -->
<section class="caseWrap" id="case" aria-labelledby="case-h">
  <div class="casePanel">
    <div class="caseWords">
      <p class="eyebrow eyebrowOnDark">Case study · Alimosho Nigeria</p>
      <h2 id="case-h" class="h2 h2OnDark">A business that needed to look as credible as it already was</h2>
      <p class="caseP">
        Recognised by existing customers and almost invisible to new ones. Low visibility brought
        the trust problem that follows it — from the outside, buyers could not tell whether this was
        a serious operation.
      </p>
      <p class="caseP">
        We built one identity, applied it across signage, print and social, and handed over a
        written system the team could keep using without us.
      </p>
      <a class="btnGrad" href="#contact">Talk about your brand</a>
    </div>
    <div class="caseFigures">
      <p class="caseFig"><span class="caseFigNum">+40%</span><span class="caseFigWhat">Engagement</span></p>
      <p class="caseFig"><span class="caseFigNum">+25%</span><span class="caseFigWhat">Sales, six months</span></p>
      <?php /*
        This line ships with the numbers, always. The handoff states it as a
        rule and it is the difference between a measurement and a boast: the
        academy did not audit the client's books, and says so where the
        figures are read rather than in a footnote further down.
      */ ?>
      <p class="caseAttrib">Figures reported by the client.</p>
    </div>
  </div>
</section>

<!-- ══ 8. Why Afrostrength ═══════════════════════════════════════════════ -->
<section class="why" id="why" aria-labelledby="why-h">
  <div class="shell">
    <p class="eyebrow">Why Afrostrength</p>
    <h2 id="why-h" class="h2">Made to hold up in any market</h2>
    <ul class="whyGrid">
      <?php foreach ([
        ['Multi-market fluency', 'Work that reads correctly in Lagos, London or New York — and is tested in each.'],
        ['Proven success',       'Clients since 2018, with results they were willing to put their name to.'],
        ['Remote by default',    'We run projects across time zones with written scope, weekly reviews and clear handover.'],
        ['End-to-end delivery',  'Strategy, identity and software from one team that stays after launch.'],
      ] as [$h, $p]): ?>
        <li class="whyCard">
          <h3 class="h4"><?= e($h) ?></h3>
          <p><?= e($p) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ══ 9. What clients say ═══════════════════════════════════════════════ -->
<section class="quotes" aria-labelledby="quotes-h">
  <div class="shell">
    <h2 id="quotes-h" class="h2">What clients say</h2>
    <?php /*
      The placeholders are the feature. Two real quotes are outstanding from
      the client, and until they arrive the design says so in the open rather
      than filling the space with something nobody said. The note below is
      part of the copy, not a developer comment.
    */ ?>
    <ul class="quoteGrid">
      <?php foreach ([
        ['[CLIENT QUOTE TBC — one or two sentences on what changed after the rebrand.]', '[NAME TBC]', 'Alimosho Nigeria'],
        ['[CLIENT QUOTE TBC — one or two sentences on working with the team on the summit.]', '[NAME TBC]', "D'Vanguard Summit 2024"],
      ] as [$q, $who, $org]): ?>
        <li class="quoteCard">
          <blockquote class="quoteText">&ldquo;<?= e($q) ?>&rdquo;</blockquote>
          <p class="quoteWho"><span class="quoteName"><?= e($who) ?></span><span class="quoteOrg"><?= e($org) ?></span></p>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="quoteNote">Placeholders — we will not publish a quote a client has not approved.</p>
  </div>
</section>

<!-- ══ 10. Services ══════════════════════════════════════════════════════ -->
<section class="services" id="services" aria-labelledby="serv-h">
  <div class="shell">
    <p class="eyebrow">Services</p>
    <h2 id="serv-h" class="h2">Five disciplines, one team</h2>
    <p class="lede">
      Most clients start with one engagement and keep us for the next. Nothing is handed to a
      subcontractor.
    </p>
    <ul class="servList">
      <?php foreach ([
        ['01', 'Brand strategy development',    'Positioning, audience and messaging, written down.'],
        ['02', 'Brand identity design',         'Logo, type and colour, documented as a system.'],
        ['03', 'Digital branding &amp; marketing', 'Websites, social and campaigns off one identity.'],
        ['04', 'Rebranding and refresh',        'For businesses whose look stayed behind the work.'],
        ['05', 'Software development',          'Custom applications, integrations, maintenance.'],
      ] as [$n, $h, $p]): ?>
        <li class="servRow">
          <span class="servNum"><?= e($n) ?></span>
          <span class="servWords">
            <span class="servName"><?= $h ?></span>
            <span class="servDesc"><?= e($p) ?></span>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ══ 11. How we work ═══════════════════════════════════════════════════ -->
<section class="process" id="process" aria-labelledby="proc-h">
  <div class="shell">
    <p class="eyebrow">How we work</p>
    <h2 id="proc-h" class="h2">Four stages, agreed in writing</h2>
    <ol class="procGrid">
      <?php foreach ([
        ['STAGE 01', 'Discovery', 'We learn the business, agree the scope and put it in writing before anything starts.'],
        ['STAGE 02', 'Build',     'Design or development in weekly increments, with you reviewing as it goes.'],
        ['STAGE 03', 'Handover',  'Files, access and written guidelines for using them — all of it yours to keep.'],
        ['STAGE 04', 'Support',   'A stated response time, from people who already know your setup.'],
      ] as [$stage, $h, $p]): ?>
        <li class="procCard">
          <p class="procStage"><?= e($stage) ?></p>
          <h3 class="h4"><?= e($h) ?></h3>
          <p><?= e($p) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<!-- ══ 12. Engagement models ═════════════════════════════════════════════ -->
<section class="pricing" id="pricing" aria-labelledby="price-h">
  <div class="shell">
    <p class="eyebrow">Engagement models</p>
    <h2 id="price-h" class="h2">Ways to work with us</h2>
    <p class="lede">
      Scope decides the shape. If you are not sure which fits, describe the problem and we will
      tell you.
    </p>
    <?php /*
      [PRICE TBC] three times, and it stays. The client has not set these, and
      a number invented here would be quoted back to them by somebody who read
      it on their own website.
    */ ?>
    <ul class="priceGrid">
      <?php foreach ([
        ['Project',    'Fixed scope, one-time fee',   'For a defined launch or rebrand',
         ['Discovery and agreed deliverables', 'Handover files and guidelines', '30 days of post-launch support']],
        ['Retainer',   'Ongoing, billed monthly',     'For teams shipping every month',
         ['Agreed monthly hours', 'Design, build and maintenance', 'Priority response times']],
        ['Consulting', 'Hourly or per engagement',    'For teams that need direction',
         ['Brand and technical review', 'Written recommendations', 'Working sessions with your team']],
      ] as [$name, $shape, $who, $points]): ?>
        <li class="priceCard">
          <h3 class="h4"><?= e($name) ?></h3>
          <p class="priceShape"><?= e($shape) ?></p>
          <p class="priceValue">[PRICE TBC]</p>
          <p class="priceWho"><?= e($who) ?></p>
          <ul class="ticks">
            <?php foreach ($points as $point): ?>
              <li><span class="tickMark" aria-hidden="true"><?= icon('tick', 15, 2.6) ?></span><?= e($point) ?></li>
            <?php endforeach; ?>
          </ul>
          <a class="btnQuiet btnBlock" href="#contact">Enquire</a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ══ 13. Academy CTA ═══════════════════════════════════════════════════ -->
<section class="acadWrap" aria-labelledby="acad-h">
  <div class="acadPanel">
    <h2 id="acad-h" class="h2 h2OnDark">Learn. Get certified. Earn.</h2>
    <p class="acadP">
      AfroTech Academy trains the next set of designers and developers — taught by the team that
      does the client work.
    </p>
    <a class="btnOnGrad" href="<?= e(academy_url()) ?>">Visit the Academy<?= icon('arrow-ne', 15, 2) ?></a>
  </div>
</section>

<!-- ══ 14. Brief capture ═════════════════════════════════════════════════ -->
<section class="brief" id="brief" aria-labelledby="brief-h">
  <div class="shell">
    <div class="briefCard">
      <h2 id="brief-h" class="h2">What do you want built?</h2>
      <p class="lede">
        One sentence is enough to start. We reply within one working day with either a scope or an
        honest no.
      </p>

      <form method="post" action="<?= e(app_url('')) ?>#brief" class="briefForm">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="brief">
        <p class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>

        <label class="srOnly" for="briefProblem">Describe the problem</label>
        <div class="briefRow">
          <input class="field<?= isset($wiz['errors']['problem']) ? ' fieldBad' : '' ?>"
                 id="briefProblem" name="problem" type="text"
                 placeholder="Describe the problem"
                 value="<?= e((string)$wiz['problem']) ?>"
                 <?= isset($wiz['errors']['problem']) ? 'aria-invalid="true" aria-describedby="briefErr"' : '' ?>>
          <button class="btnGrad" type="submit">Continue</button>
        </div>

        <?php if (isset($wiz['errors']['problem'])): ?>
          <p class="fieldError" id="briefErr"><?= e($wiz['errors']['problem']) ?></p>
        <?php elseif (!empty($wiz['carried'])): ?>
          <p class="fieldGood">Carried into the enquiry below. Two answers left.</p>
        <?php endif; ?>

        <?php /* Examples that fill the box, so nobody has to start on a blank one. */ ?>
        <ul class="briefExamples">
          <?php foreach ([
            'Rebrand an established business'      => 'We are an established business and our look stayed behind the work we do.',
            'Identity plus a fast website'         => 'We need an identity and a fast website before we launch in two new markets.',
            'Internal tool to replace spreadsheets' => 'We run orders on spreadsheets and WhatsApp and need an internal tool.',
          ] as $label => $text): ?>
            <li><button class="chip" type="submit" name="problem" value="<?= e($text) ?>"><?= e($label) ?></button></li>
          <?php endforeach; ?>
        </ul>
      </form>
    </div>
  </div>
</section>

<!-- ══ 15. Enquiry wizard ════════════════════════════════════════════════ -->
<section class="wizWrap" id="contact" aria-labelledby="c-h">
  <div class="shell wizShell">
    <div class="wizIntro">
      <h2 id="c-h" class="h2">Start a project in three answers</h2>
      <p class="lede">No long form. Answer what is on screen, and we take it from there.</p>

      <ol class="wizSteps">
        <?php foreach ([1 => 'The problem', 2 => 'What kind of help', 3 => 'Where to reply'] as $n => $label):
          $step = (int)$wiz['step'];
          $state = $step > $n || $step === 4 ? 'done' : ($step === $n ? 'now' : 'todo'); ?>
          <li class="wizStep wizStep-<?= $state ?>">
            <span class="wizStepNum" aria-hidden="true"><?= $state === 'done' ? icon('tick', 13, 3) : $n ?></span>
            <span><?= e($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>

      <p class="wizCall">
        Rather talk? Call <a href="<?= e((string)cfg('site.phone_href', 'tel:+2348100191456')) ?>"><?= e((string)cfg('site.phone', '+234 810 019 1456')) ?></a>
        or visit 2 Abolude/Oremeji Str., Egbeda, Lagos.
      </p>
    </div>

    <div class="wizCard">
      <?php if ((int)$wiz['step'] === 4): ?>
        <div class="wizDone">
          <span class="wizDoneMark" aria-hidden="true"><?= icon('tick', 22, 2.6) ?></span>
          <h3 class="h3">Sent. Talk soon.</h3>
          <p>We reply within one working day, to <strong><?= e((string)$wiz['email']) ?></strong>.</p>
          <?php if ($wiz['ref'] !== ''): ?>
            <p class="wizRef">Your reference is <strong><?= e((string)$wiz['ref']) ?></strong>.</p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <form method="post" action="<?= e(app_url('')) ?>#contact">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <p class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>

          <?php if (isset($wiz['errors']['_form'])): ?>
            <p class="formAlert" role="alert"><?= e($wiz['errors']['_form']) ?></p>
          <?php endif; ?>

          <p class="wizLabel">Step <?= (int)$wiz['step'] ?> of 3</p>

          <?php if ((int)$wiz['step'] === 1): ?>
            <label class="fieldLabel" for="wizProblem">What needs to change?</label>
            <textarea class="field fieldArea<?= isset($wiz['errors']['problem']) ? ' fieldBad' : '' ?>"
                      id="wizProblem" name="problem" rows="4" required
                      <?= isset($wiz['errors']['problem']) ? 'aria-invalid="true" aria-describedby="wizProblemErr"' : '' ?>><?= e((string)$wiz['problem']) ?></textarea>
            <?php if (isset($wiz['errors']['problem'])): ?>
              <p class="fieldError" id="wizProblemErr"><?= e($wiz['errors']['problem']) ?></p>
            <?php endif; ?>

          <?php elseif ((int)$wiz['step'] === 2): ?>
            <fieldset class="fieldSet">
              <legend class="fieldLabel">What kind of help is that?</legend>
              <?php foreach (ENQUIRY_KINDS as $key => $label): ?>
                <label class="option<?= (string)$wiz['kind'] === $key ? ' optionOn' : '' ?>">
                  <input type="radio" name="kind" value="<?= e($key) ?>" <?= (string)$wiz['kind'] === $key ? 'checked' : '' ?>>
                  <span><?= e($label) ?></span>
                </label>
              <?php endforeach; ?>
            </fieldset>

          <?php else: ?>
            <label class="fieldLabel" for="wizEmail">Where should we reply?</label>
            <input class="field<?= isset($wiz['errors']['email']) ? ' fieldBad' : '' ?>"
                   id="wizEmail" name="email" type="email" required autocomplete="email"
                   value="<?= e((string)$wiz['email']) ?>"
                   <?= isset($wiz['errors']['email']) ? 'aria-invalid="true" aria-describedby="wizEmailErr"' : '' ?>>
            <?php if (isset($wiz['errors']['email'])): ?>
              <p class="fieldError" id="wizEmailErr"><?= e($wiz['errors']['email']) ?></p>
            <?php endif; ?>

            <label class="fieldLabel fieldLabelSpaced" for="wizName">Your name <span class="fieldOptional">— optional</span></label>
            <input class="field" id="wizName" name="name" type="text" autocomplete="name"
                   value="<?= e((string)$wiz['name']) ?>">

            <label class="consent">
              <input type="checkbox" name="consent" value="1" <?= !empty($wiz['consent']) ? 'checked' : '' ?>>
              <span>You may keep my details to reply to this enquiry.</span>
            </label>
            <?php if (isset($wiz['errors']['consent'])): ?>
              <p class="fieldError"><?= e($wiz['errors']['consent']) ?></p>
            <?php endif; ?>
          <?php endif; ?>

          <div class="wizActions">
            <button class="btnBack" type="submit" name="action" value="back"
                    formnovalidate <?= (int)$wiz['step'] === 1 ? 'disabled' : '' ?>>Back</button>
            <button class="btnGrad" type="submit" name="action" value="next">
              <?= (int)$wiz['step'] === 3 ? 'Send enquiry' : 'Continue' ?>
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php site_foot(); ?>
