<?php
/* ============================================================
   gmp.php — Grey Market Premium (GMP) feed for the IPO page.

   WHY THIS EXISTS
   ---------------
   NSE publishes IPO issues, but NOT grey market premium — the grey
   market is an unofficial, unregulated market with no exchange feed.
   The only sources are IPO-tracking sites. So this endpoint scrapes
   them, normalises the rows, caches, and serves clean JSON.

   SOURCE CHOICE (measured, not guessed)
   -------------------------------------
   • ipowatch.in         — server-rendered HTML table. PRIMARY.
   • ipopremium.in       — SPA, but ships a <noscript> table. FALLBACK.
   • investorgain.com    — Next.js SPA; the table is empty in the HTML
                           (client-hydrated), so it cannot be scraped
                           server-side. NOT USED.
   • groww.in/ipo/gmp    — same problem (SPA). NOT USED.

   Sources are tried in order; the first one that yields rows wins, and
   the loser is not even fetched. If both fail we serve the last good
   cache marked stale rather than blanking the page.

   RATING
   ------
   No source exposes a rating we can legally restate as our own, and a
   SEBI-registered research analyst should not republish a third party's
   "fire rating" as a view. So the rating here is DERIVED, in the open,
   from the estimated listing gain (GMP as a % of the issue cap price):

        >= 30%  ****  Strong        15-30%  ****  Positive
        5-15%   ***   Moderate      0-5%    **    Flat
        <= 0%   *     Weak          no GMP  -     No GMP

   It is a restatement of grey-market sentiment, not a recommendation.
   The frontend labels it as such.

   Frontend calls: /gmp.php
   Cache: data/gmp-cache.json, 30 min TTL (GMP updates a few times a day).
   ============================================================ */

$allowedOrigins = ['https://rootnivesh.in', 'https://www.rootnivesh.in', 'http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$cacheDir  = __DIR__ . '/data';
$cacheFile = $cacheDir . '/gmp-cache.json';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

$TTL    = 1800;                                    // 30 min
$now    = time();
$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$UA     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

/* ---------- helpers ---------- */

function gmp_http_get($url) {
    global $UA;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => [
            'User-Agent: ' . $UA,
            'Accept: text/html,application/xhtml+xml,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($status === 200 && $body) ? $body : null;
}

/* Strip tags + entities to plain text. */
function gmp_text($html) {
    $t = preg_replace('/<[^>]+>/', ' ', (string) $html);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace(["\xc2\xa0", '₹'], [' ', ''], $t);
    return trim(preg_replace('/\s+/u', ' ', $t));
}

/* First signed number in a string, or null. Handles "-12", "₹1,250", "1.9%". */
function gmp_num($s) {
    if (!preg_match('/-?\d[\d,]*(?:\.\d+)?/', (string) $s, $m)) return null;
    return floatval(str_replace(',', '', $m[0]));
}

/* Highest number in a price band ("105-111" -> 111). Cap price is what the
   grey market prices against, so we take the upper end. */
function gmp_cap_price($s) {
    preg_match_all('/\d[\d,]*(?:\.\d+)?/', (string) $s, $m);
    if (empty($m[0])) return null;
    $vals = array_map(function ($x) { return floatval(str_replace(',', '', $x)); }, $m[0]);
    return max($vals);
}

/* Match key: lowercase, drop corporate suffixes and punctuation. This is what
   lets "Laser Power & Infra" (ipowatch) meet "Laser Power & Infra Limited"
   (NSE). The frontend normalises the NSE side with the same rules. */
function gmp_key($name) {
    $n = strtolower(gmp_text($name));
    $n = preg_replace('/\b(limited|ltd|private|pvt|india|ipo|the)\b/', ' ', $n);
    $n = preg_replace('/\((?:bse|nse)?\s*sme\)|\(mainboard\)|\(eq\)/', ' ', $n);
    $n = preg_replace('/[^a-z0-9 ]/', ' ', $n);
    return trim(preg_replace('/\s+/', ' ', $n));
}

/* Derived rating — see the header block. Input is estimated listing gain %. */
function gmp_rating($gainPct, $hasGmp) {
    if (!$hasGmp)        return ['stars' => 0, 'label' => 'No GMP'];
    if ($gainPct === null) return ['stars' => 0, 'label' => 'No GMP'];
    if ($gainPct >= 30)  return ['stars' => 5, 'label' => 'Strong'];
    if ($gainPct >= 15)  return ['stars' => 4, 'label' => 'Positive'];
    if ($gainPct >= 5)   return ['stars' => 3, 'label' => 'Moderate'];
    if ($gainPct > 0)    return ['stars' => 2, 'label' => 'Flat'];
    return ['stars' => 1, 'label' => 'Weak'];
}

/* Tidy a price-band string for display: "₹105-111" / "545-574" -> "₹105 - ₹111".
   Returns null when the band is absent or a placeholder ("-", "0-0"). */
function gmp_band_text($raw) {
    preg_match_all('/\d[\d,]*(?:\.\d+)?/', (string) $raw, $m);
    if (empty($m[0])) return null;
    $vals = array_values(array_unique(array_map(function ($x) {
        return floatval(str_replace(',', '', $x));
    }, $m[0])));
    $vals = array_values(array_filter($vals, function ($v) { return $v > 0; }));
    if (!$vals) return null;
    sort($vals, SORT_NUMERIC);
    $fmt = function ($v) { return '₹' . rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.'); };
    if (count($vals) === 1) return $fmt($vals[0]);
    return $fmt($vals[0]) . ' - ' . $fmt(end($vals));
}

/* Assemble one normalised row + its derived fields. */
function gmp_row($name, $gmp, $capPrice, $type, $status, $updated, $bandRaw = null) {
    $hasGmp = ($gmp !== null);
    $gain   = null;
    $est    = null;
    if ($hasGmp && $capPrice !== null && $capPrice > 0) {
        $est  = round($capPrice + $gmp, 2);
        $gain = round($gmp / $capPrice * 100, 2);
    }
    $r = gmp_rating($gain, $hasGmp && abs($gmp) > 0);
    return [
        'name'          => $name,
        'key'           => gmp_key($name),
        'gmp'           => $hasGmp ? round($gmp, 2) : null,
        'cap_price'     => $capPrice,
        // NSE's SME feed often omits the price band entirely, so the tracker's
        // band is used as a display fallback on the IPO table.
        'price_band'    => gmp_band_text($bandRaw),
        'est_listing'   => $est,
        'est_gain_pct'  => $gain,
        'rating_stars'  => $r['stars'],
        'rating_label'  => $r['label'],
        'type'          => $type ?: null,
        'status'        => $status ?: null,
        'updated'       => $updated ?: null,
    ];
}

/* ---------- source 1: ipowatch.in (server-rendered table) ----------
   Columns: IPO Name | GMP | Trend | Price Band | Est. Listing | Date | Type | Status | Last Updated */
function fetch_ipowatch() {
    $html = gmp_http_get('https://ipowatch.in/ipo-grey-market-premium-latest-ipo-gmp/');
    if (!$html) return [];
    if (!preg_match('/<table[\s\S]*?<\/table>/i', $html, $tm)) return [];

    $rows = [];
    preg_match_all('/<tr[^>]*>([\s\S]*?)<\/tr>/i', $tm[0], $trs);
    foreach ($trs[1] as $tr) {
        preg_match_all('/<t[dh][^>]*>([\s\S]*?)<\/t[dh]>/i', $tr, $tds);
        $c = array_map('gmp_text', $tds[1]);
        if (count($c) < 8) continue;
        if (stripos($c[0], 'IPO Name') !== false) continue;   // header
        $name = $c[0];
        if ($name === '') continue;

        $gmp  = gmp_num($c[1]);                 // "₹2" / "₹0" / "₹-"
        $band = gmp_cap_price($c[3]);           // "₹105" or "₹105-111"
        $rows[] = gmp_row($name, $gmp, $band, $c[6], $c[7], $c[8] ?? null, $c[3]);
    }
    return $rows;
}

/* ---------- source 2: ipopremium.in (<noscript> table) ----------
   Columns: Company Name | Type | GMP | Open | Close | Price Band | Listing Date */
function fetch_ipopremium() {
    $html = gmp_http_get('https://www.ipopremium.in/');
    if (!$html) return [];
    if (!preg_match('/<noscript>[\s\S]*?<table[\s\S]*?<\/table>/i', $html, $nm)) return [];
    if (!preg_match('/<table[\s\S]*?<\/table>/i', $nm[0], $tm)) return [];

    $rows = [];
    preg_match_all('/<tr[^>]*>([\s\S]*?)<\/tr>/i', $tm[0], $trs);
    foreach ($trs[1] as $tr) {
        preg_match_all('/<t[dh][^>]*>([\s\S]*?)<\/t[dh]>/i', $tr, $tds);
        $c = array_map('gmp_text', $tds[1]);
        if (count($c) < 6) continue;
        if (stripos($c[0], 'Company Name') !== false) continue;
        $name = preg_replace('/\s*\((?:BSE\s+SME|NSE\s+SME|SME|MAINBOARD)\)\s*$/i', '', $c[0]);
        if ($name === '') continue;

        $gmp  = gmp_num($c[2]);
        $band = gmp_cap_price($c[5]);           // "545-574"; 0-0 means unannounced
        if ($band !== null && $band <= 0) $band = null;
        $rows[] = gmp_row($name, $gmp, $band, $c[1], null, null, $c[5]);
    }
    return $rows;
}

/* ---------- fetch + merge ----------
   The two sources are COMPLEMENTARY, not merely a failover pair:
     • ipowatch  — GMP, trend, status. Its price column is only the CAP price
                   ("₹574"), not the band.
     • ipopremium — carries the full band ("545-574") and covers issues whose
                   band ipowatch leaves blank.
   So both are fetched and merged rather than taking the first that answers.
   ipowatch stays authoritative for GMP; ipopremium only fills the gaps. If
   either is down the other still produces a usable table on its own. */

function gmp_merge($primary, $secondary) {
    $byKey = [];
    foreach ($primary as $r) { $byKey[$r['key']] = $r; }

    foreach ($secondary as $s) {
        $k = $s['key'];
        if (!isset($byKey[$k])) { $byKey[$k] = $s; continue; }   // an issue the primary missed
        $p = $byKey[$k];

        // Prefer a real BAND ("₹545 - ₹574") over a lone cap price ("₹574").
        $pHasRange = $p['price_band'] !== null && strpos($p['price_band'], '-') !== false;
        $sHasRange = $s['price_band'] !== null && strpos($s['price_band'], '-') !== false;
        if ((!$pHasRange && $sHasRange) || ($p['price_band'] === null && $s['price_band'] !== null)) {
            $p['price_band'] = $s['price_band'];
        }
        // Fill anything else the primary simply doesn't have. GMP is NOT
        // overwritten — two trackers quote slightly different premiums and
        // mixing them would produce a number neither source published.
        foreach (['cap_price', 'type', 'status', 'updated'] as $f) {
            if (($p[$f] === null || $p[$f] === '') && isset($s[$f]) && $s[$f] !== null && $s[$f] !== '') {
                $p[$f] = $s[$f];
            }
        }
        $byKey[$k] = $p;
    }
    return array_values($byKey);
}

$cache = null;
if (file_exists($cacheFile)) $cache = json_decode(file_get_contents($cacheFile), true);
$fresh = $cache && !empty($cache['ts']) && ($now - intval($cache['ts'])) < $TTL;

if (!$fresh) {
    $watch   = fetch_ipowatch();
    $premium = fetch_ipopremium();

    $srcs = [];
    if ($watch)   $srcs[] = 'ipowatch.in';
    if ($premium) $srcs[] = 'ipopremium.in';

    // Whichever answered leads; if both did, ipowatch leads and ipopremium fills.
    if ($watch)        $rows = gmp_merge($watch, $premium);
    elseif ($premium)  $rows = $premium;
    else               $rows = [];

    if ($rows) {
        $cache = [
            'ts'         => $now,
            'fetched_at' => $istNow->format(DateTime::ATOM),
            'source'     => implode(' + ', $srcs),
            'count'      => count($rows),
            'stale'      => false,
            'rows'       => $rows,
        ];
        @file_put_contents($cacheFile, json_encode($cache), LOCK_EX);
    } elseif ($cache) {
        // Both sources down — keep serving the last good data, flagged, so the
        // page degrades to "GMP as of <time>" instead of going blank.
        $cache['stale']        = true;
        $cache['last_attempt'] = $istNow->format(DateTime::ATOM);
    }
}

if ($cache) {
    echo json_encode($cache);
} else {
    http_response_code(503);
    echo json_encode(['error' => 'GMP sources unreachable and no cache available', 'rows' => []]);
}
