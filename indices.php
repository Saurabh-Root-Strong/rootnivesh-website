<?php
/* ============================================================
   indices.php — live Nifty 50 + BSE Sensex tiles for the hero.

   Source: Yahoo Finance chart API (no auth needed). Symbols:
     ^NSEI  → NIFTY 50
     ^BSESN → BSE SENSEX

   Cache: 5 min during market hours (Mon–Fri 9:15–15:30 IST),
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
$cacheFile = $cacheDir . '/indices-cache.json';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

$now    = time();
$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

function is_market_open($istNow) {
    $day = (int)$istNow->format('w'); // 0=Sun, 6=Sat
    if ($day === 0 || $day === 6) return false;
    $mins = (int)$istNow->format('G') * 60 + (int)$istNow->format('i');
    return $mins >= 555 && $mins <= 930; // 9:15 - 15:30 IST
}

$marketOpen = is_market_open($istNow);
$ttl = $marketOpen ? 300 : 3600;

$cache = null;
if (file_exists($cacheFile)) $cache = json_decode(file_get_contents($cacheFile), true);
$shouldRefresh = !$cache || empty($cache['ts']) || ($now - intval($cache['ts'])) >= $ttl;

function fetch_index($symbol) {
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($symbol) . '?interval=1d&range=1d';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: application/json,*/*',
        ],
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200 || !$body) return null;
    $j = json_decode($body, true);
    $meta = $j['chart']['result'][0]['meta'] ?? null;
    if (!$meta) return null;
    $price = isset($meta['regularMarketPrice']) ? floatval($meta['regularMarketPrice']) : null;
    $prev  = isset($meta['chartPreviousClose']) ? floatval($meta['chartPreviousClose']) : null;
    if ($price === null || $prev === null || $prev <= 0) return null;
    $change  = $price - $prev;
    $pChange = ($change / $prev) * 100;
    return ['price' => $price, 'change' => $change, 'pChange' => $pChange];
}

if ($shouldRefresh) {
    $nifty  = fetch_index('^NSEI');
    $sensex = fetch_index('^BSESN');
    if ($nifty && $sensex) {
        $cache = [
            'ts'          => $now,
            'fetched_at'  => $istNow->format(DateTime::ATOM),
            'market_open' => $marketOpen,
            'source'      => 'Yahoo Finance',
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
