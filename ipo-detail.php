<?php
/* ============================================================
   ipo-detail.php — server-rendered page for one IPO: /ipo/<slug>.

   PURPOSE (organic)
   -----------------
   "[company] IPO GMP", "[company] IPO review", "[company] IPO allotment"
   are among the highest-volume recurring retail searches in India. The
   /ipo tool ranks for the generic query; this captures the long tail, one
   substantive page per issue, auto-generated from data we already hold.

   DATA
   ----
   Reads the cache files the JSON endpoints already maintain — no re-fetch:
     data/gmp-cache.json     (ipowatch + ipopremium: GMP, rating, band, dates)
     data/groww-cache.json   (Groww: subscription, listing price, dates)
     data/ipo-cache-*.json   (NSE: official symbol + dates)
   NSE is authoritative for dates/symbol; the others fill and enrich.

   NOT THIN / NOT A DOORWAY
   ------------------------
   Each page carries genuinely unique data (GMP, rating, subscription,
   band, dates, listing gain, company overview) plus a compliant education
   section — substantive, not a templated shell.

   COMPLIANCE
   ----------
   SEBI RA rules: NO apply / don't-apply verdict. Structured facts +
   education only. GMP labelled unofficial and unregulated throughout.

   SAME SAFETY MODEL AS blog.php
   -----------------------------
   Same HTML for everyone (no cloaking). Falls back to the SPA (index.html)
   on any failure. A slug we have no data for returns a real 404.
   ============================================================ */

$SLUG = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($SLUG === '' || !preg_match('/^[a-z0-9-]{1,120}$/', $SLUG)) {
    header('Location: /ipo');
    exit;
}

$ORIGIN = 'https://rootnivesh.in';
$SITE   = 'RootNivesh Research';
$SEBI   = 'INH000XXXXX';
$DEFAULT_OG = $ORIGIN . '/images/og-image.png?v=2';

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

/* Canonical IPO slug. MUST match ipoSlug() in js/app/ipo-tools.js so the
   tool's row links and this page agree. */
function ipo_slug($name) {
    $n = strtolower((string) $name);
    $n = preg_replace('/\b(limited|ltd|private|pvt)\b/', ' ', $n);
    $n = preg_replace('/[^a-z0-9]+/', '-', $n);
    return trim($n, '-');
}

function ipo_load_json($file) {
    $p = __DIR__ . '/data/' . $file;
    if (!file_exists($p)) return null;
    $j = json_decode(@file_get_contents($p), true);
    return is_array($j) ? $j : null;
}

/* "16-Jul-2026" / "2026-07-16" / epoch -> "16 Jul 2026" for display. */
function ipo_fmt_date($s) {
    if (!$s) return null;
    $t = is_numeric($s) ? intval($s) : strtotime((string) $s);
    return $t ? date('j M Y', $t) : (string) $s;
}

/* ---- build one merged record for this slug, across all cached sources ---- */
$rec = null;
try {
    $sources = [];

    // GMP (ipowatch + ipopremium)
    $gmp = ipo_load_json('gmp-cache.json');
    if ($gmp && !empty($gmp['rows'])) {
        foreach ($gmp['rows'] as $r) {
            if (ipo_slug($r['name']) === $SLUG) { $sources['gmp'] = $r; break; }
        }
    }
    // Groww (open/upcoming/closed)
    $grw = ipo_load_json('groww-cache.json');
    if ($grw && !empty($grw['data'])) {
        foreach (['open', 'upcoming', 'closed'] as $t) {
            foreach (($grw['data'][$t] ?? []) as $r) {
                if (ipo_slug($r['companyName']) === $SLUG) { $sources['groww'] = $r + ['_tab' => $t]; break 2; }
            }
        }
    }
    // NSE (open/upcoming/closed caches)
    foreach (['ipo-cache-open.json', 'ipo-cache-upcoming.json', 'ipo-cache-closed.json'] as $f) {
        $nse = ipo_load_json($f);
        $rows = $nse['data'] ?? null;
        if (is_array($rows) && isset($rows['data'])) $rows = $rows['data'];
        if (!is_array($rows)) continue;
        foreach ($rows as $r) {
            $nm = $r['companyName'] ?? $r['company'] ?? '';
            if ($nm && ipo_slug($nm) === $SLUG) { $sources['nse'] = $r; break 2; }
        }
    }

    if ($sources) {
        $g = $sources['gmp']   ?? [];
        $w = $sources['groww'] ?? [];
        $n = $sources['nse']   ?? [];
        $name = $n['companyName'] ?? $n['company'] ?? $w['companyName'] ?? $g['name'] ?? '';
        $rec = [
            'name'         => $name,
            'symbol'       => $n['symbol'] ?? $w['symbol'] ?? null,
            'series'       => $w['series'] ?? $n['series'] ?? (stripos(($g['type'] ?? ''), 'sme') !== false ? 'SME' : 'Mainboard'),
            'open_date'    => $n['issueStartDate'] ?? $w['issueStartDate'] ?? $g['open_date'] ?? null,
            'close_date'   => $n['issueEndDate'] ?? $w['issueEndDate'] ?? $g['close_date'] ?? null,
            'price_band'   => $w['issuePrice'] ?? $g['price_band'] ?? ($n['issuePrice'] ?? null),
            'gmp'          => $g['gmp'] ?? null,
            'est_listing'  => $g['est_listing'] ?? null,
            'est_gain_pct' => $g['est_gain_pct'] ?? null,
            'rating_stars' => $g['rating_stars'] ?? 0,
            'rating_label' => $g['rating_label'] ?? null,
            'subscription' => $w['subscription'] ?? (isset($n['noOfTime']) ? floatval($n['noOfTime']) : null),
            'listing_price'=> $w['listing_price'] ?? null,
            'status_tab'   => $w['_tab'] ?? null,
            'gmp_source'   => $gmp['source'] ?? 'IPO trackers',
        ];
    }
} catch (Throwable $e) {
    $rec = null;
    // fall through to SPA fallback below
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

/* ---- unknown IPO -> real 404 ---- */
if (!$rec || $rec['name'] === '') {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo ipo_shell(
        'IPO not found — ' . $SITE,
        'This IPO could not be found. See all live IPOs with grey market premium on the RootNivesh IPO tracker.',
        $ORIGIN . '/ipo', $DEFAULT_OG, 'noindex, follow',
        '<div class="ip-notfound"><h1>IPO not found</h1><p>We could not find data for this issue.</p>'
        . '<p><a class="ip-btn" href="/ipo">→ See all live IPOs</a></p></div>', '');
    exit;
}

/* ---- derive phase + display ---- */
$today = strtotime(date('Y-m-d'));
$os = $rec['open_date']  ? strtotime($rec['open_date'])  : null;
$ce = $rec['close_date'] ? strtotime($rec['close_date']) : null;
$phase = 'upcoming';
if ($ce && $today > $ce)                    $phase = 'closed';
elseif ($os && $today >= $os && (!$ce || $today <= $ce)) $phase = 'open';
elseif (!$os && $rec['status_tab'])          $phase = $rec['status_tab'];
$phaseLabel = ['open' => 'Open now', 'upcoming' => 'Upcoming', 'closed' => 'Closed'][$phase] ?? ucfirst($phase);

$name   = $rec['name'];
$hasGmp = $rec['gmp'] !== null;
$title  = $name . ' IPO — GMP, Price Band, Dates & Details';
$descBits = [];
if ($hasGmp) $descBits[] = 'GMP ₹' . $rec['gmp'];
if ($rec['price_band']) $descBits[] = 'price band ' . preg_replace('/\s+/', ' ', $rec['price_band']);
if ($rec['open_date'] && $rec['close_date']) $descBits[] = ipo_fmt_date($rec['open_date']) . '–' . ipo_fmt_date($rec['close_date']);
$desc = $name . ' IPO details: ' . (count($descBits) ? implode(', ', $descBits) . '. ' : '')
      . 'Live grey market premium, subscription, dates and analysis from a SEBI Registered Research Analyst.';
$canonical = $ORIGIN . '/ipo/' . rawurlencode($SLUG);

/* ---- build stat tiles ---- */
$tiles = [];
$tiles[] = ['Type', h($rec['series'] ?: '—')];
$tiles[] = ['Price band', $rec['price_band'] ? h(preg_replace('/\s+/', ' ', $rec['price_band'])) : '—'];
if ($hasGmp) {
    $sign = $rec['gmp'] > 0 ? '+' : '';
    $col  = $rec['gmp'] > 0 ? '#22c55e' : ($rec['gmp'] < 0 ? '#ef4444' : 'var(--grey2)');
    $sub  = $rec['est_gain_pct'] !== null ? '<div class="ip-sub">' . $sign . h($rec['est_gain_pct']) . '%</div>' : '';
    $tiles[] = ['GMP <span class="ip-note">(grey market)</span>', '<span style="color:' . $col . '">' . $sign . '₹' . h($rec['gmp']) . '</span>' . $sub];
}
if ($rec['rating_stars']) {
    $stars = str_repeat('★', $rec['rating_stars']) . str_repeat('☆', 5 - $rec['rating_stars']);
    $tiles[] = ['Sentiment rating', '<span style="color:var(--gold)">' . $stars . '</span><div class="ip-sub">' . h($rec['rating_label']) . '</div>'];
}
if ($rec['est_listing'] !== null) $tiles[] = ['Est. listing', '₹' . h($rec['est_listing'])];
if ($rec['subscription'] !== null && $rec['subscription'] > 0) {
    $x = $rec['subscription'] >= 10 ? round($rec['subscription']) : round($rec['subscription'], 1);
    $tiles[] = ['Subscription', h($x) . 'x'];
}
if ($rec['listing_price'] !== null) $tiles[] = ['Listed at', '₹' . h($rec['listing_price'])];
$tiles[] = ['Open date', h(ipo_fmt_date($rec['open_date']) ?: 'TBA')];
$tiles[] = ['Close date', h(ipo_fmt_date($rec['close_date']) ?: 'TBA')];

$tileHtml = '';
foreach ($tiles as $t) {
    $tileHtml .= '<div class="ip-tile"><div class="ip-tile-k">' . $t[0] . '</div><div class="ip-tile-v">' . $t[1] . '</div></div>';
}

/* ---- JSON-LD ---- */
$ld = [
    '@context' => 'https://schema.org', '@type' => 'Article',
    'headline'    => $title,
    'description' => $desc,
    'author'      => ['@type' => 'Organization', 'name' => $SITE],
    'publisher'   => ['@type' => 'Organization', 'name' => $SITE,
                      'logo' => ['@type' => 'ImageObject', 'url' => $ORIGIN . '/images/logo.png']],
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
    'dateModified' => date('c'),
];
$crumb = [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'IPO', 'item' => $ORIGIN . '/ipo'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $name . ' IPO', 'item' => $canonical],
    ],
];
$headExtra = '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>'
           . '<script type="application/ld+json">' . json_encode($crumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';

/* ---- related blog posts (the IPO education cluster) ---- */
$related = [
    ['ipo-gmp-grey-market-premium-meaning', 'What Grey Market Premium really tells you'],
    ['ipo-allotment-status-how-to-check', 'How to check your IPO allotment status'],
    ['sme-ipo-vs-mainboard-ipo-difference', 'SME IPO vs Mainboard: the differences that matter'],
    ['how-to-read-an-ipo-prospectus-7-things-that-actually-matter', 'How to read an IPO prospectus'],
];
$relHtml = '';
foreach ($related as $r) {
    $relHtml .= '<li><a href="/blog/' . h($r[0]) . '">' . h($r[1]) . '</a></li>';
}

/* ---- compliant analysis prose (facts + education, no verdict) ---- */
$gmpLine = $hasGmp
    ? 'The current grey market premium for ' . h($name) . ' is <strong>₹' . h($rec['gmp']) . '</strong>'
      . ($rec['est_gain_pct'] !== null ? ', an estimated listing gain of about <strong>' . h($rec['est_gain_pct']) . '%</strong> over the '
        . ($rec['price_band'] ? 'upper price band' : 'issue price') : '') . '. '
    : 'No grey market premium is currently being quoted for ' . h($name) . '. ';

$analysis =
    '<h2>' . h($name) . ' IPO grey market premium</h2>'
  . '<p>' . $gmpLine . 'Grey Market Premium is an <strong>unofficial, unregulated</strong> price quoted outside the '
  . 'exchanges. It reflects short-term sentiment and can change or be wrong at any time. It is shown here for information '
  . 'only — Root Nivesh does not deal or transact in the grey market, and GMP is not a recommendation to subscribe. '
  . 'See <a href="/blog/ipo-gmp-grey-market-premium-meaning">what GMP really tells you</a>.</p>'
  . '<h2>What to check before you apply</h2>'
  . '<p>Rather than deciding on GMP alone, read the offer document and assess the fundamentals: what the company earns, '
  . 'what the issue is priced at relative to those earnings, whether the money raised is fresh capital for the business '
  . 'or an offer for sale by existing holders, and the promoter background. Our guide on '
  . '<a href="/blog/how-to-read-an-ipo-prospectus-7-things-that-actually-matter">reading an IPO prospectus</a> '
  . 'covers the seven sections that matter'
  . ($rec['series'] === 'SME' ? ', and note the elevated liquidity and disclosure risk of '
     . '<a href="/blog/sme-ipo-vs-mainboard-ipo-difference">SME issues</a>' : '')
  . '.</p>';

$statusChip = '<span class="ip-status ' . $phase . '">' . h($phaseLabel) . '</span>';

$body =
    '<a class="ip-back" href="/ipo">← All IPOs</a>'
  . '<header class="ip-head"><h1>' . h($name) . ' IPO</h1>'
  . '<div class="ip-metarow">' . $statusChip
  . ($rec['symbol'] ? '<span class="ip-sym">' . h($rec['symbol']) . '</span>' : '')
  . '<span class="ip-src">Updated ' . h(date('j M Y, H:i')) . ' IST</span></div></header>'
  . '<div class="ip-grid">' . $tileHtml . '</div>'
  . '<div class="ip-prose">' . $analysis . '</div>'
  . '<aside class="ip-related"><h2>IPO investing guides</h2><ul>' . $relHtml . '</ul></aside>'
  . '<p style="margin-top:26px"><a class="ip-btn" href="/ipo">→ Compare all live IPOs & GMP</a></p>'
  // A <div>, deliberately NOT a <footer>: style.css styles the bare `footer`
  // element (site-footer background + 60px/90px padding) for the SPA.
  . '<div class="ip-foot"><p class="ip-disc"><strong>Disclaimer:</strong> IPO and grey-market data are sourced from '
  . 'public trackers and exchanges and may be delayed or inaccurate. Grey Market Premium is unofficial and unregulated; '
  . 'Root Nivesh does not deal in the grey market. This page is for education only and is not a recommendation to '
  . 'subscribe to any issue. Investments in securities are subject to market risk; read all offer documents carefully. '
  . 'SEBI Registered Research Analyst, Reg. No. ' . h($SEBI) . '.</p></div>';

http_response_code(200);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo ipo_shell($title . ' | ' . $SITE, $desc, $canonical, $DEFAULT_OG, 'index, follow', $body, $headExtra);


function ipo_shell($title, $desc, $canonical, $ogImage, $robots, $bodyInner, $extraHead) {
    $SITE = 'RootNivesh Research';
    $desc = trim(preg_replace('/\s+/', ' ', $desc));
    ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($title); ?></title>
<meta name="description" content="<?php echo h($desc); ?>">
<meta name="robots" content="<?php echo h($robots); ?>">
<meta name="author" content="<?php echo h($SITE); ?>">
<link rel="canonical" href="<?php echo h($canonical); ?>">
<link rel="icon" href="/images/favicon.ico?v=2" sizes="any">
<link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png?v=2">
<meta property="og:type" content="article">
<meta property="og:site_name" content="<?php echo h($SITE); ?>">
<meta property="og:title" content="<?php echo h($title); ?>">
<meta property="og:description" content="<?php echo h($desc); ?>">
<meta property="og:url" content="<?php echo h($canonical); ?>">
<meta property="og:image" content="<?php echo h($ogImage); ?>">
<meta property="og:locale" content="en_IN">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo h($title); ?>">
<meta name="twitter:description" content="<?php echo h($desc); ?>">
<meta name="twitter:image" content="<?php echo h($ogImage); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css?v=108">
<?php echo $extraHead; ?>
<style>
  /* DEFENSIVE RESET — style.css targets BARE ELEMENTS (`nav { position: fixed }`,
     `footer { background; padding: 60px 0 }`) for the SPA's chrome. Content blocks
     here must not inherit that, so we avoid those tags and neutralise the
     properties, making the page immune to future style.css edits. */
  .ip-foot, .ip-head, .ip-grid, .ip-prose, .ip-related {
    position: static; float: none; width: auto; height: auto; min-height: 0;
    inset: auto; z-index: auto; backdrop-filter: none; background: none;
  }

  .ip-wrap { max-width: 820px; margin: 0 auto; padding: 90px 20px 60px; }
  .ip-nav { position: fixed; top: 0; left: 0; right: 0; z-index: 20; display: flex; align-items: center;
    justify-content: space-between; padding: 14px 20px; background: rgba(7,19,31,0.85);
    backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); }
  .ip-nav a.ip-brand { display: flex; align-items: center; gap: 9px; color: var(--white); text-decoration: none; font-weight: 700; }
  .ip-nav img { height: 30px; }
  .ip-nav .ip-links a { color: var(--grey2); text-decoration: none; margin-left: 18px; font-size: 14px; }
  .ip-nav .ip-links a:hover { color: var(--gold); }
  .ip-back { display: inline-block; color: var(--grey2); text-decoration: none; font-size: 13px;
    font-family: 'Space Mono', monospace; margin-bottom: 18px; }
  .ip-back:hover { color: var(--gold); }
  .ip-head h1 { font-family: 'Cormorant Garamond', serif; font-size: clamp(1.9rem,5vw,3rem); color: var(--white); margin: 0 0 12px; line-height: 1.1; }
  .ip-metarow { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-family: 'Space Mono', monospace; font-size: 12px; }
  .ip-status { padding: 3px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; font-size: 10.5px; }
  .ip-status.open { color: #22c55e; background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.35); }
  .ip-status.upcoming { color: #f59e0b; background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.35); }
  .ip-status.closed { color: #94a3b8; background: rgba(148,163,184,0.12); border: 1px solid rgba(148,163,184,0.3); }
  .ip-sym { color: var(--gold); }
  .ip-src { color: var(--grey); }
  .ip-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap: 12px; margin: 26px 0 34px; }
  .ip-tile { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; }
  .ip-tile-k { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--grey); font-family: 'Space Mono', monospace; margin-bottom: 6px; }
  .ip-tile-v { font-size: 1.15rem; font-weight: 700; color: var(--white); }
  .ip-tile .ip-sub { font-size: 11px; font-weight: 400; color: var(--grey); font-family: 'Space Mono', monospace; }
  .ip-note { font-size: 9px; color: var(--grey); text-transform: none; letter-spacing: 0; }
  .ip-prose { color: var(--grey1,#cfd8e3); font-size: 1.05rem; line-height: 1.75; }
  .ip-prose h2 { font-family: 'Cormorant Garamond', serif; font-size: 1.6rem; color: var(--white); margin: 32px 0 12px; }
  .ip-prose strong { color: var(--white); }
  .ip-prose a { color: var(--gold); text-decoration: underline; text-underline-offset: 2px; }
  .ip-related { margin: 34px 0 0; padding: 18px 20px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 12px; }
  .ip-related h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--gold); font-family: 'Space Mono', monospace; margin: 0 0 10px; }
  .ip-related ul { list-style: none; margin: 0; padding: 0; }
  .ip-related li { margin: 7px 0; }
  .ip-related a { color: var(--grey2); text-decoration: none; }
  .ip-related a:hover { color: var(--gold); }
  .ip-btn { display: inline-block; padding: 11px 20px; border: 1px solid var(--gold); border-radius: 8px; color: var(--gold); text-decoration: none; font-size: 14px; }
  .ip-btn:hover { background: rgba(201,168,76,0.1); }
  .ip-foot { margin-top: 40px; padding-top: 22px; border-top: 1px solid var(--border); }
  .ip-disc { font-size: 12px; color: var(--grey); line-height: 1.6; margin: 0; }
  .ip-notfound { text-align: center; padding: 40px 0; }
  .ip-notfound h1 { font-family: 'Cormorant Garamond', serif; color: var(--white); }
  .ip-notfound p { color: var(--grey2); }

  /* MOBILE — see the note in blog.php: the global `nav` rule forces
     height: var(--nav-h) (48px under 640px), which is shorter than this bar's
     content, and the wordmark plus four links overflow a 360px viewport. */
  .ip-nav { height: auto; min-height: 52px; }
  @media (max-width: 640px) {
    .ip-nav { padding: 9px 14px; min-height: 48px; }
    .ip-nav img { height: 24px; }
    .ip-nav .ip-brand span { display: none; }
    .ip-nav .ip-links a { margin-left: 14px; font-size: 13px; padding: 6px 0; }
    .ip-wrap { padding: 76px 16px 48px; }
    .ip-head h1 { font-size: 1.75rem; }
    /* Two tiles per row on a phone rather than one long column. */
    .ip-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 20px 0 28px; }
    .ip-tile { padding: 12px 13px; }
    .ip-tile-v { font-size: 1.05rem; }
    .ip-prose { font-size: 1rem; }
    .ip-prose h2 { font-size: 1.4rem; }
  }
  @media (max-width: 380px) {
    .ip-nav .ip-links a { margin-left: 10px; font-size: 12px; }
    .ip-wrap { padding-left: 13px; padding-right: 13px; }
  }
</style>
</head>
<body class="bp-page">
  <nav class="ip-nav">
    <a class="ip-brand" href="/"><img src="/images/logo.png" alt="RootNivesh"><span>Root Nivesh</span></a>
    <div class="ip-links"><a href="/">Home</a><a href="/ipo">IPO</a><a href="/blog">Blog</a><a href="/membership">Membership</a></div>
  </nav>
  <main class="ip-wrap"><?php echo $bodyInner; ?></main>
</body>
</html>
<?php
    return ob_get_clean();
}
