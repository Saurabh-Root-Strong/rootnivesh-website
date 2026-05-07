<?php
/* ============================================================
   fii-dii.php — server-side NSE FII/DII fetcher with cookies + cache.

   NSE's /api/fiidiiTradeReact endpoint blocks bare requests; you
   first have to GET https://www.nseindia.com/ to receive a
   session cookie, then call the API with that cookie. proxy.php
   doesn't do the handshake so FII/DII used to fall back to
   sample data. This dedicated endpoint does the handshake.

   Caching:
   - data/fiidii-cache.json holds the most recent successful pull
   - Re-fetches if cache is older than 60 min OR it's after
     7:30 PM IST and we don't yet have today's data
   - On NSE failure, returns the last good cached value with
     a "stale" flag so the frontend can label it accurately
   ============================================================ */

// Allow only requests from rootnivesh.in (production) and localhost (dev).
// Any other origin gets the response without an Access-Control-Allow-Origin
// header, so browsers will block it from reading the body.
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
$cacheFile = $cacheDir . '/fiidii-cache.json';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

$nseHome = 'https://www.nseindia.com/';
$nseAPI  = 'https://www.nseindia.com/api/fiidiiTradeReact';

$now           = time();
$istNow        = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$todayIST      = $istNow->format('Y-m-d');
$publishCutoff = clone $istNow;
$publishCutoff->setTime(19, 30, 0); // 7:30 PM IST

$cache = null;
if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true);
}

/* Decide whether to refresh:
   - No cache yet                                   -> refresh
   - Cache age < 60 min                             -> use cache
   - Cache age >= 60 min                            -> refresh
   - It's after 19:30 IST and cache fetched_date < today -> refresh */
$shouldRefresh = false;
$dayOfWeek = (int)$istNow->format('w');           // 0=Sun, 6=Sat
$mins      = (int)$istNow->format('G') * 60 + (int)$istNow->format('i');
$marketOpen = $dayOfWeek >= 1 && $dayOfWeek <= 5 && $mins >= 540 && $mins <= 930;
// 60s during market hours, 1 hour outside — same policy as fii-dii-history.php.
$ttl = $marketOpen ? 60 : 3600;
if (!$cache || empty($cache['ts'])) {
    $shouldRefresh = true;
} else {
    $age = $now - intval($cache['ts']);
    if ($age >= $ttl) {
        $shouldRefresh = true;
    }
    if ($istNow >= $publishCutoff && (empty($cache['fetched_date']) || $cache['fetched_date'] !== $todayIST)) {
        $shouldRefresh = true;
    }
}

function nse_fetch($url, $cookieFile, $referer = null) {
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: en-US,en;q=0.9',
        'Connection: keep-alive',
    ];
    if ($referer) $headers[] = 'Referer: ' . $referer;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $body, 'status' => $status];
}

if ($shouldRefresh) {
    $cookieFile = tempnam(sys_get_temp_dir(), 'nse_');
    // Step 1 — pick up cookies from the homepage
    nse_fetch($nseHome, $cookieFile);
    // Step 2 — call the API with the cookies
    $resp = nse_fetch($nseAPI, $cookieFile, $nseHome);
    @unlink($cookieFile);

    if ($resp['status'] === 200 && $resp['body']) {
        $parsed = json_decode($resp['body'], true);
        if (is_array($parsed)) {
            $cache = [
                'ts'           => $now,
                'fetched_date' => $todayIST,
                'fetched_at'   => $istNow->format(DateTime::ATOM),
                'source'       => 'NSE India',
                'data'         => $parsed,
                'stale'        => false,
            ];
            @file_put_contents($cacheFile, json_encode($cache));
        }
    } else if ($cache) {
        // Mark existing cache as stale rather than discard it
        $cache['stale']         = true;
        $cache['last_attempt']  = $istNow->format(DateTime::ATOM);
        $cache['last_attempt_status'] = $resp['status'];
    }
}

if ($cache) {
    echo json_encode($cache);
} else {
    http_response_code(503);
    echo json_encode(['error' => 'NSE unreachable and no cache available', 'fetched_at' => $istNow->format(DateTime::ATOM)]);
}
