<?php
/* ============================================================
   ipo.php — authoritative NSE IPO data for the IPO page.

   The plain proxy.php does a stateless curl to NSE which
   intermittently returns stale or empty data because NSE's
   /api/* endpoints require a cookie handshake first (visit
   nseindia.com home → get cookies → call API with them).

   This endpoint replicates the cookie-handshake pattern used by
   indices.php so the IPO tabs always reflect what nseindia.com
   itself is showing.

   Frontend calls: /ipo.php?tab=open|upcoming|closed

   Cache: 60s during market hours, 1h outside, in
   data/ipo-cache-<tab>.json
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

$tab = isset($_GET['tab']) ? strtolower($_GET['tab']) : 'open';
$endpoints = [
    'open'     => 'https://www.nseindia.com/api/ipo-current-issue',
    'upcoming' => 'https://www.nseindia.com/api/all-upcoming-issues?category=ipo',
    'closed'   => 'https://www.nseindia.com/api/public-past-issues',
];
if (!isset($endpoints[$tab])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid tab', 'allowed' => array_keys($endpoints)]);
    exit;
}

$cacheDir  = __DIR__ . '/data';
$cacheFile = $cacheDir . '/ipo-cache-' . $tab . '.json';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

$now    = time();
$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

function ipo_market_open($istNow) {
    $day = (int)$istNow->format('w'); // 0=Sun, 6=Sat
    if ($day === 0 || $day === 6) return false;
    $mins = (int)$istNow->format('G') * 60 + (int)$istNow->format('i');
    return $mins >= 540 && $mins <= 930; // 9:00–15:30 IST
}

$marketOpen = ipo_market_open($istNow);
$ttl = $marketOpen ? 60 : 3600;

$cache = null;
if (file_exists($cacheFile)) $cache = json_decode(file_get_contents($cacheFile), true);
$shouldRefresh = !$cache || empty($cache['ts']) || ($now - intval($cache['ts'])) >= $ttl;

$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

function ipo_http_get($url, $headers = [], $cookieFile = null) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($cookieFile) { $opts[CURLOPT_COOKIEJAR] = $cookieFile; $opts[CURLOPT_COOKIEFILE] = $cookieFile; }
    curl_setopt_array($ch, $opts);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $body, 'status' => $status];
}

function fetch_nse_ipo($endpointUrl) {
    global $UA;
    $cookieFile = tempnam(sys_get_temp_dir(), 'nseipo_');
    $base = [
        'User-Agent: ' . $UA,
        'Accept: text/html,application/json,*/*',
        'Accept-Language: en-US,en;q=0.9',
    ];
    // Step 1: hit homepage to seed cookies (nsit, nseappid, etc.)
    ipo_http_get('https://www.nseindia.com/', $base, $cookieFile);
    // Step 2: hit a market-data referrer once so the API endpoints accept us.
    ipo_http_get('https://www.nseindia.com/market-data/all-upcoming-issues-public-issues',
        array_merge($base, ['Referer: https://www.nseindia.com/']),
        $cookieFile);
    // Step 3: now call the actual IPO endpoint with cookies + matching referer.
    $resp = ipo_http_get(
        $endpointUrl,
        array_merge($base, [
            'Referer: https://www.nseindia.com/market-data/all-upcoming-issues-public-issues',
            'X-Requested-With: XMLHttpRequest',
        ]),
        $cookieFile
    );
    @unlink($cookieFile);
    if ($resp['status'] !== 200 || !$resp['body']) return null;
    $j = json_decode($resp['body'], true);
    if (!is_array($j)) return null;
    return $j;
}

if ($shouldRefresh) {
    $data = fetch_nse_ipo($endpoints[$tab]);
    if ($data !== null) {
        $cache = [
            'ts'           => $now,
            'fetched_at'   => $istNow->format(DateTime::ATOM),
            'tab'          => $tab,
            'market_open'  => $marketOpen,
            'data'         => $data,
            'stale'        => false,
        ];
        @file_put_contents($cacheFile, json_encode($cache));
    } else if ($cache) {
        // Upstream failed but we have prior data — mark stale so the
        // frontend can warn the user, but don't break the page.
        $cache['stale'] = true;
        $cache['last_attempt'] = $istNow->format(DateTime::ATOM);
    }
}

if ($cache) {
    echo json_encode($cache);
} else {
    http_response_code(503);
    echo json_encode(['error' => 'NSE IPO endpoint unreachable and no cache available', 'tab' => $tab]);
}
