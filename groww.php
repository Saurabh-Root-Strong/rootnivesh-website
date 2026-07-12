<?php
/* ============================================================
   groww.php — IPO listings from Groww, server-side.

   WHY
   ---
   NSE lists an issue only in a narrow approved window, so its Open and
   Upcoming feeds are routinely thin or empty. Groww's IPO page carries a
   far richer set — open issues with live subscription, and recent closed
   issues with actual listing prices — and, crucially, it is
   server-readable: the page is Next.js and embeds the whole dataset in a
   <script id="__NEXT_DATA__"> blob in the initial HTML, so a plain curl
   gets it. (The visible table is JS-hydrated and useless to scrape; the
   __NEXT_DATA__ blob is the real data.)

   This is a LISTINGS source (like NSE), NOT a grey-market source. GMP and
   the derived rating still come from gmp.php (ipowatch + ipopremium). The
   frontend merges Groww in alongside NSE, with NSE authoritative.

   HARD RULE — no rumours in the tabs
   ----------------------------------
   Groww's upcoming list is mostly SPECULATIVE: famous private companies
   (Flipkart, PhonePe, OYO, Zepto...) with placeholder symbols and NO
   dates. Those must never reach a real tab. So a row is emitted for Open
   or Upcoming ONLY if it has a genuine bid-start timestamp. The dateless
   rumours are dropped here, at the source.

   Frontend calls: /groww.php
   Cache: data/groww-cache.json, 15 min.
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
$cacheFile = $cacheDir . '/groww-cache.json';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

$TTL    = 900;                                     // 15 min
$now    = time();
$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$UA     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

/* epoch-millis -> "16-Jul-2026" (NSE display format the frontend expects).
   Rendered in IST because that is the timezone NSE and Groww both quote. */
function groww_date($ms) {
    if (!$ms || !is_numeric($ms)) return null;
    $d = new DateTime('@' . intval($ms / 1000));
    $d->setTimezone(new DateTimeZone('Asia/Kolkata'));
    return $d->format('j-M-Y');
}

/* Normalise one Groww record to the canonical IPO shape the table uses.
   $requireDate drops rumours (no bid-start) for the Open/Upcoming lists;
   closed rows are dated by their listing/closing time and always kept. */
function groww_norm($r, $requireDate) {
    $name  = trim($r['companyName'] ?? '');
    if ($name === '') return null;

    $start = groww_date($r['bidStartTimestamp'] ?? null);
    $end   = groww_date($r['bidEndTimestamp'] ?? ($r['closingDate'] ?? null));
    // closingDate/openingDate on closed rows may already be a date string.
    if (!$end && !empty($r['closingDate']))  $end   = groww_date_str($r['closingDate']);
    if (!$start && !empty($r['openingDate'])) $start = groww_date_str($r['openingDate']);

    if ($requireDate && !$start) return null;      // rumour — no scheduled date

    return [
        'companyName'  => $name,
        'symbol'       => (isset($r['symbol']) && !preg_match('/TEMP|^NSE$/i', $r['symbol'])) ? $r['symbol'] : null,
        'series'       => !empty($r['isSme']) ? 'SME' : 'Mainboard',
        'issueStartDate' => $start,
        'issueEndDate'   => $end,
        'issuePrice'   => isset($r['issuePrice']) && $r['issuePrice'] ? ('₹' . $r['issuePrice']) : null,
        'subscription' => isset($r['overallSubscription']) ? floatval($r['overallSubscription']) : null,
        'listing_price'=> isset($r['listingPrice']) && $r['listingPrice'] ? floatval($r['listingPrice']) : null,
    ];
}

/* Groww sometimes carries a plain date string instead of a timestamp. */
function groww_date_str($s) {
    $t = strtotime((string) $s);
    return $t ? date('j-M-Y', $t) : null;
}

function groww_fetch() {
    global $UA;
    $ch = curl_init('https://groww.in/ipo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 18,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => [
            'User-Agent: ' . $UA,
            'Accept: text/html,application/xhtml+xml,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    $html = curl_exec($ch);
    $ok   = (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200);
    curl_close($ch);
    if (!$ok || !$html) return null;

    if (!preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $m)) return null;
    $data = json_decode($m[1], true);
    if (!is_array($data)) return null;
    $pp = $data['props']['pageProps'] ?? null;
    if (!is_array($pp)) return null;

    $out = ['open' => [], 'upcoming' => [], 'closed' => []];

    foreach (($pp['openDataList'] ?? []) as $r) {
        $n = groww_norm($r, true);   if ($n) $out['open'][] = $n;
    }
    foreach (($pp['upcomingDataList'] ?? []) as $r) {
        $n = groww_norm($r, true);   if ($n) $out['upcoming'][] = $n;   // dateless rumours dropped
    }
    foreach (($pp['closedDataList'] ?? []) as $r) {
        $n = groww_norm($r, false);  if ($n) $out['closed'][] = $n;
    }
    return $out;
}

$cache = null;
if (file_exists($cacheFile)) $cache = json_decode(file_get_contents($cacheFile), true);
$fresh = $cache && !empty($cache['ts']) && ($now - intval($cache['ts'])) < $TTL;

if (!$fresh) {
    $g = groww_fetch();
    if ($g !== null) {
        $cache = [
            'ts'         => $now,
            'fetched_at' => $istNow->format(DateTime::ATOM),
            'source'     => 'groww.in',
            'counts'     => ['open' => count($g['open']), 'upcoming' => count($g['upcoming']), 'closed' => count($g['closed'])],
            'stale'      => false,
            'data'       => $g,
        ];
        @file_put_contents($cacheFile, json_encode($cache), LOCK_EX);
    } elseif ($cache) {
        $cache['stale']        = true;
        $cache['last_attempt'] = $istNow->format(DateTime::ATOM);
    }
}

if ($cache) {
    echo json_encode($cache);
} else {
    http_response_code(503);
    echo json_encode(['error' => 'Groww unreachable and no cache available', 'data' => ['open' => [], 'upcoming' => [], 'closed' => []]]);
}
