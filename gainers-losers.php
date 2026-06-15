<?php
/* ============================================================
   gainers-losers.php — Top 5 gainers + top 5 losers from Nifty 50.

   NSE's /api/equity-stockIndices?index=NIFTY%2050 returns every
   constituent of Nifty 50 with current price and percent change.
   We fetch through the same cookie handshake fii-dii.php uses,
   sort by pChange, and return the top 5 of each direction.

   Cache: 5 min during market hours, 60 min outside, in
   data/gainers-losers-cache.json.
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
$cacheFile = $cacheDir . '/gainers-losers-cache.json';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

$nseHome = 'https://www.nseindia.com/';
$nseAPI  = 'https://www.nseindia.com/api/equity-stockIndices?index=NIFTY%2050';

$now    = time();
$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

function is_market_open($istNow) {
    $day = (int)$istNow->format('w'); // 0=Sun, 6=Sat
    if ($day === 0 || $day === 6) return false;
    $h = (int)$istNow->format('G');
    $m = (int)$istNow->format('i');
    $mins = $h * 60 + $m;
    return $mins >= 540 && $mins <= 945; // 9:00 - 15:45 IST (incl. post-close auction window)
}

$marketOpen = is_market_open($istNow);
$ttl = $marketOpen ? 60 : 3600; // 60s during market, 60 min after

$cache = null;
if (file_exists($cacheFile)) $cache = json_decode(file_get_contents($cacheFile), true);

$shouldRefresh = !$cache || empty($cache['ts']) || ($now - intval($cache['ts'])) >= $ttl;

function nse_fetch($url, $cookieFile, $referer = null) {
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: en-US,en;q=0.9',
    ];
    if ($referer) $headers[] = 'Referer: ' . $referer;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $body, 'status' => $status];
}

if ($shouldRefresh) {
    $cookieFile = tempnam(sys_get_temp_dir(), 'nseg_');
    nse_fetch($nseHome, $cookieFile);
    $resp = nse_fetch($nseAPI, $cookieFile, $nseHome);
    @unlink($cookieFile);

    if ($resp['status'] === 200 && $resp['body']) {
        $parsed = json_decode($resp['body'], true);
        $rows = $parsed['data'] ?? null;
        if (is_array($rows)) {
            $clean = [];
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                // Skip the index meta row (first entry has symbol "NIFTY 50")
                $sym = $r['symbol'] ?? '';
                if ($sym === '' || strpos(strtoupper($sym), 'NIFTY') === 0) continue;
                $clean[] = [
                    'symbol'   => $sym,
                    'price'    => isset($r['lastPrice'])   ? floatval($r['lastPrice'])   : null,
                    'change'   => isset($r['change'])      ? floatval($r['change'])      : null,
                    'pChange'  => isset($r['pChange'])     ? floatval($r['pChange'])     : null,
                ];
            }

            usort($clean, function($a, $b) {
                $pa = $a['pChange'] ?? -INF; $pb = $b['pChange'] ?? -INF;
                return $pa <=> $pb;
            });
            $losers  = array_slice($clean, 0, 5);
            $gainers = array_slice(array_reverse($clean), 0, 5);

            $cache = [
                'ts'           => $now,
                'fetched_at'   => $istNow->format(DateTime::ATOM),
                'market_open'  => $marketOpen,
                'source'       => 'NSE India (NIFTY 50)',
                'gainers'      => $gainers,
                'losers'       => $losers,
                'stale'        => false,
            ];
            @file_put_contents($cacheFile, json_encode($cache), LOCK_EX);
        } else if ($cache) {
            $cache['stale'] = true;
        }
    } else if ($cache) {
        $cache['stale'] = true;
        $cache['last_attempt_status'] = $resp['status'];
    }
}

if ($cache) {
    echo json_encode($cache);
} else {
    http_response_code(503);
    echo json_encode(['error' => 'NSE unreachable and no cache available']);
}
