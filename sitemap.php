<?php
/* ============================================================
   sitemap.php — dynamic XML sitemap.
   Served at /sitemap.xml via an .htaccess rewrite. Lists every
   indexable static route plus every PUBLISHED blog post, so
   Google discovers new articles the moment the team publishes.
   ============================================================ */
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$ORIGIN = 'https://rootnivesh.in';

/* Static, indexable routes (path => changefreq, priority). Legal pages
   and gated/app routes are intentionally excluded. */
$static = [
    '/'            => ['weekly',  '1.0'],
    '/ipo'         => ['daily',   '0.8'],
    '/membership'  => ['weekly',  '0.9'],
    '/performance' => ['daily',   '0.8'],
    '/reports'     => ['weekly',  '0.7'],
    '/tools'       => ['monthly', '0.6'],
    '/blog'        => ['daily',   '0.8'],
    '/about'       => ['monthly', '0.5'],
    '/contact'     => ['monthly', '0.5'],
];

$today = date('Y-m-d');

/* Pull published posts for per-article URLs. Fail soft — a DB hiccup
   should still leave a valid static sitemap. */
$posts = [];
try {
    require_once __DIR__ . '/admin/db.php';
    $rows = db()->query(
        "SELECT slug, COALESCE(updated_at, published_at, created_at) AS lastmod
         FROM posts WHERE status = 'published' ORDER BY published_at DESC"
    )->fetchAll();
    foreach ($rows as $r) {
        if (!empty($r['slug'])) $posts[] = $r;
    }
} catch (Throwable $e) {
    // Static sitemap is still served below.
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($static as $path => $meta) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($ORIGIN . $path, ENT_XML1) . "</loc>\n";
    echo "    <lastmod>$today</lastmod>\n";
    echo "    <changefreq>{$meta[0]}</changefreq>\n";
    echo "    <priority>{$meta[1]}</priority>\n";
    echo "  </url>\n";
}

/* Per-broker brokerage-calculator deep links — each ranks for
   "<broker> brokerage calculator", a high-intent India search term. */
$brokers = ['zerodha', 'groww', 'angelone', 'upstox', 'dhan', 'fyers', 'fivepaisa', 'paytmmoney', 'icicidirect', 'hdfcsec', 'kotak', 'sbisec', 'motilal'];
foreach ($brokers as $bid) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($ORIGIN . '/tools?broker=' . $bid, ENT_XML1) . "</loc>\n";
    echo "    <lastmod>$today</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.6</priority>\n";
    echo "  </url>\n";
}

foreach ($posts as $p) {
    $loc  = $ORIGIN . '/blog/' . rawurlencode($p['slug']);
    $lm   = $p['lastmod'] ? date('Y-m-d', strtotime($p['lastmod'])) : $today;
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
    echo "    <lastmod>$lm</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>0.7</priority>\n";
    echo "  </url>\n";
}

/* Per-IPO pages (/ipo/<slug>) — only issues we hold substantive data for
   (GMP trackers + Groww), never the thin NSE-only closed archive. daily
   changefreq because GMP/subscription move day to day while an issue is live. */
$ipoSlug = function ($name) {
    $n = strtolower((string) $name);
    $n = preg_replace('/\b(limited|ltd|private|pvt)\b/', ' ', $n);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $n), '-');
};
$ipoSlugs = [];
$gc = @json_decode(@file_get_contents(__DIR__ . '/data/gmp-cache.json'), true);
foreach (($gc['rows'] ?? []) as $r) { if (!empty($r['name'])) $ipoSlugs[$ipoSlug($r['name'])] = true; }
$wc = @json_decode(@file_get_contents(__DIR__ . '/data/groww-cache.json'), true);
foreach (['open', 'upcoming', 'closed'] as $t) {
    foreach (($wc['data'][$t] ?? []) as $r) { if (!empty($r['companyName'])) $ipoSlugs[$ipoSlug($r['companyName'])] = true; }
}
foreach (array_keys($ipoSlugs) as $slug) {
    if ($slug === '') continue;
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($ORIGIN . '/ipo/' . rawurlencode($slug), ENT_XML1) . "</loc>\n";
    echo "    <lastmod>$today</lastmod>\n";
    echo "    <changefreq>daily</changefreq>\n";
    echo "    <priority>0.7</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
