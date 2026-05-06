<?php
/* ============================================================
   indices.php — live Nifty 50 + BSE Sensex tiles for the hero.

   Sources (each falls back independently):
     NIFTY 50  → NSE direct (cookie handshake)  → Yahoo (^NSEI)
     SENSEX    → BSE direct (api.bseindia.com)  → Yahoo (^BSESN)

   Per-index 'updated' timestamp comes from the upstream source
   so the UI can show "Updated 15:29" exactly when the exchange
   ticked it.

   Cache: 5 min during market hours (Mon–Fri 09:15–15:30 IST),
   60 min outside, in data/indices-cache.json.
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
$cacheFile = $cacheDir . '/indices-cache-v2.json';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

$now    = time();
$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

function is_market_open($istNow) {
    $day = (int)$istNow->format('w'); // 0=Sun, 6=Sat
    if ($day === 0 || $day === 6) return false;
    $mins = (int)$istNow->format('G') * 60 + (int)$istNow->format('i');
    return $mins >= 555 && $mins <= 930; // 9:15–15:30 IST
}

$marketOpen = is_market_open($istNow);
$ttl = $marketOpen ? 300 : 3600;

$cache = null;
if (file_exists($cacheFile)) $cache = json_decode(file_get_contents($cacheFile), true);
$shouldRefresh = !$cache || empty($cache['ts']) || ($now - intval($cache['ts'])) >= $ttl;

$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

function http_get($url, $headers = [], $cookieFile = null) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($cookieFile) { $opts[CURLOPT_COOKIEJAR] = $cookieFile; $opts[CURLOPT_COOKIEFILE] = $cookieFile; }
    curl_setopt_array($ch, $opts);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $body, 'status' => $status];
}

/* Try parsing a timestamp string against several common Indian-exchange formats. */
function parse_ist_time($str) {
    if (!$str) return null;
    foreach (['d-M-Y H:i:s', 'd-M-Y H:i', 'M d, Y h:i:s A', 'Y-m-d H:i:s'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $str, new DateTimeZone('Asia/Kolkata'));
        if ($dt) return $dt->format(DateTime::ATOM);
    }
    return null;
}

function fetch_nse_nifty() {
    global $UA;
    $cookieFile = tempnam(sys_get_temp_dir(), 'nseidx_');
    $base = ['User-Agent: ' . $UA, 'Accept: text/html,application/json,*/*', 'Accept-Language: en-US,en;q=0.9'];
    http_get('https://www.nseindia.com/', $base, $cookieFile);
    $resp = http_get(
        'https://www.nseindia.com/api/equity-stockIndices?index=NIFTY%2050',
        array_merge($base, ['Referer: https://www.nseindia.com/']),
        $cookieFile
    );
    @unlink($cookieFile);
    if ($resp['status'] !== 200 || !$resp['body']) return null;
    $j = json_decode($resp['body'], true);
    $rows = $j['data'] ?? [];
    foreach ($rows as $r) {
        if (strtoupper($r['symbol'] ?? '') === 'NIFTY 50') {
            $price = floatval($r['last'] ?? $r['lastPrice'] ?? 0);
            if ($price <= 0) return null;
            return [
                'price'   => $price,
                'change'  => floatval($r['change']  ?? 0),
                'pChange' => floatval($r['pChange'] ?? 0),
                'updated' => parse_ist_time($r['lastUpdateTime'] ?? ''),
                'source'  => 'NSE',
            ];
        }
    }
    return null;
}

function fetch_bse_sensex() {
    global $UA;
    $resp = http_get('https://api.bseindia.com/BseIndiaAPI/api/SensexData/w?json=true', [
        'User-Agent: ' . $UA,
        'Accept: application/json, */*',
        'Referer: https://www.bseindia.com/',
        'Origin: https://www.bseindia.com',
    ]);
    if ($resp['status'] !== 200 || !$resp['body']) return null;
    $j = json_decode($resp['body'], true);
    if (!$j) return null;
    $row = (is_array($j) && isset($j[0]) && is_array($j[0])) ? $j[0] : $j;
    $price   = floatval($row['CurrValue'] ?? $row['Currvalue'] ?? $row['Curr_Val'] ?? 0);
    $change  = floatval($row['Chg']       ?? $row['Change']    ?? 0);
    $pChange = floatval($row['PerChg']    ?? $row['perchg']    ?? 0);
    if ($price <= 0) return null;
    return [
        'price'   => $price,
        'change'  => $change,
        'pChange' => $pChange,
        'updated' => parse_ist_time($row['Updtime'] ?? $row['updtime'] ?? ''),
        'source'  => 'BSE',
    ];
}

function fetch_yahoo($symbol) {
    global $UA;
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($symbol) . '?interval=1d&range=1d';
    $resp = http_get($url, ['User-Agent: ' . $UA, 'Accept: application/json,*/*']);
    if ($resp['status'] !== 200 || !$resp['body']) return null;
    $j = json_decode($resp['body'], true);
    $meta = $j['chart']['result'][0]['meta'] ?? null;
    if (!$meta) return null;
    $price = isset($meta['regularMarketPrice']) ? floatval($meta['regularMarketPrice']) : null;
    $prev  = isset($meta['chartPreviousClose']) ? floatval($meta['chartPreviousClose']) : null;
    if ($price === null || $prev === null || $prev <= 0) return null;
    $iso = null;
    if (!empty($meta['regularMarketTime'])) {
        $dt = (new DateTime('@' . intval($meta['regularMarketTime'])))->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $iso = $dt->format(DateTime::ATOM);
    }
    return [
        'price'   => $price,
        'change'  => $price - $prev,
        'pChange' => (($price - $prev) / $prev) * 100,
        'updated' => $iso,
        'source'  => 'Yahoo',
    ];
}

if ($shouldRefresh) {
    $nifty  = fetch_nse_nifty();
    if (!$nifty)  $nifty  = fetch_yahoo('^NSEI');
    $sensex = fetch_bse_sensex();
    if (!$sensex) $sensex = fetch_yahoo('^BSESN');

    if ($nifty && $sensex) {
        $cache = [
            'ts'          => $now,
            'fetched_at'  => $istNow->format(DateTime::ATOM),
            'market_open' => $marketOpen,
            'nifty'       => $nifty,
            'sensex'      => $sensex,
            'stale'       => false,
        ];
        @file_put_contents($cacheFile, json_encode($cache));
    } else if ($cache) {
        $cache['stale'] = true;
    }
}

if ($cache) {
    echo json_encode($cache);
} else {
    http_response_code(503);
    echo json_encode(['error' => 'index data unavailable']);
}
