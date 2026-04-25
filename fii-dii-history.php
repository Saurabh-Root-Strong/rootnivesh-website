<?php
/* ============================================================
   fii-dii-history.php — last 30 days of FII/DII net flows.

   Pulls from NSE's historicalOR/fiiDiiData endpoint with the
   same cookie handshake fii-dii.php uses, then normalises the
   response into a flat array the frontend can render directly:

     [{ date: "24-Apr-2026", fii: -8827.87, dii: 4700.71 }, …]

   Cache: data/fiidii-history-cache.json, 6-hour TTL (history
   only changes once a day, no need to re-fetch frequently).
   ============================================================ */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$cacheDir  = __DIR__ . '/data';
$cacheFile = $cacheDir . '/fiidii-history-cache.json';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

$nseHome = 'https://www.nseindia.com/';

$now    = time();
$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$todayIST = $istNow->format('Y-m-d');

// Date range: 45 days back to give us ~30 trading days after weekend filtering.
$endIST   = clone $istNow;
$startIST = (clone $istNow)->modify('-45 days');
$fmt = function($d) { return $d->format('d-m-Y'); };
$nseAPI = 'https://www.nseindia.com/api/historicalOR/fiiDiiData?startDate=' .
          urlencode($fmt($startIST)) . '&endDate=' . urlencode($fmt($endIST));

$cache = null;
if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true);
}

$shouldRefresh = false;
if (!$cache || empty($cache['ts'])) {
    $shouldRefresh = true;
} else {
    $age = $now - intval($cache['ts']);
    // History changes once a day — 6h TTL is plenty.
    if ($age >= 6 * 3600) $shouldRefresh = true;
    // Force refresh if the latest cached date is before today and it's after 19:30 IST
    $cutoff = clone $istNow; $cutoff->setTime(19, 30, 0);
    if ($istNow >= $cutoff && (empty($cache['fetched_date']) || $cache['fetched_date'] !== $todayIST)) {
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
        CURLOPT_TIMEOUT        => 15,
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

function pick_num($row, $candidates) {
    foreach ($candidates as $k) {
        if (isset($row[$k]) && is_numeric($row[$k])) return floatval($row[$k]);
        if (isset($row[$k]) && is_string($row[$k]) && is_numeric(str_replace([',',' '], '', $row[$k]))) {
            return floatval(str_replace([',',' '], '', $row[$k]));
        }
    }
    return null;
}

function normalise_history($parsed) {
    $rows = null;
    if (is_array($parsed)) {
        if (isset($parsed['data']) && is_array($parsed['data']))      $rows = $parsed['data'];
        elseif (isset($parsed['Data']) && is_array($parsed['Data']))  $rows = $parsed['Data'];
        elseif (array_keys($parsed) === range(0, count($parsed) - 1)) $rows = $parsed;
    }
    if (!$rows) return [];

    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $date = $r['date'] ?? $r['Date'] ?? $r['DATE'] ?? $r['reportingDate'] ?? '';
        $fii  = pick_num($r, ['fiiNetDii','FII_NET','fiiNet','netFII','NET_FII','FII','fiiNetValue']);
        $dii  = pick_num($r, ['diiNetDii','DII_NET','diiNet','netDII','NET_DII','DII','diiNetValue']);
        if ($date === '' || ($fii === null && $dii === null)) continue;
        $out[] = ['date' => $date, 'fii' => $fii, 'dii' => $dii];
    }
    // newest first; trim to 30
    usort($out, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    return array_slice($out, 0, 30);
}

if ($shouldRefresh) {
    $cookieFile = tempnam(sys_get_temp_dir(), 'nseh_');
    nse_fetch($nseHome, $cookieFile);
    $resp = nse_fetch($nseAPI, $cookieFile, $nseHome);
    @unlink($cookieFile);

    if ($resp['status'] === 200 && $resp['body']) {
        $parsed = json_decode($resp['body'], true);
        $rows = normalise_history($parsed);
        if (count($rows) > 0) {
            $cache = [
                'ts'           => $now,
                'fetched_date' => $todayIST,
                'fetched_at'   => $istNow->format(DateTime::ATOM),
                'source'       => 'NSE India',
                'rows'         => $rows,
                'stale'        => false,
            ];
            @file_put_contents($cacheFile, json_encode($cache));
        } else if ($cache) {
            $cache['stale'] = true;
            $cache['last_attempt'] = $istNow->format(DateTime::ATOM);
            $cache['last_attempt_status'] = 'no rows parsed';
        }
    } else if ($cache) {
        $cache['stale'] = true;
        $cache['last_attempt'] = $istNow->format(DateTime::ATOM);
        $cache['last_attempt_status'] = $resp['status'];
    }
}

if ($cache) {
    echo json_encode($cache);
} else {
    http_response_code(503);
    echo json_encode(['error' => 'NSE history unreachable and no cache available']);
}
