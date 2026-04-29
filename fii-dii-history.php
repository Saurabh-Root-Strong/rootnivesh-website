<?php
/* ============================================================
   fii-dii-history.php — last 30 days of FII/DII net flows.

   Primary source: Groww's /fii-dii-data page, which embeds the
   full historical series as JSON inside a __NEXT_DATA__ script
   tag. Faster, more reliable, and richer than NSE's historical
   API (which returns 404 for FII/DII directly).

   Result is cached at data/fiidii-history-cache.json. Refresh
   policy: 6 hours, OR after 19:30 IST if today's date isn't
   already in the cache.

   Output shape consumed by the frontend:
     { ts, fetched_date, fetched_at, source, stale, rows: [...] }
     rows: [{ date, fii, dii, fiiBuy, fiiSell, diiBuy, diiSell }, ...]
     newest-first, capped at 30 entries. Buy/sell may be null on
     days where Groww doesn't expose them.
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
$cacheFile = $cacheDir . '/fiidii-history-cache.json';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

$now    = time();
$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$todayIST = $istNow->format('Y-m-d');

$cache = null;
if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true);
}

$shouldRefresh = false;
if (!$cache || empty($cache['ts'])) {
    $shouldRefresh = true;
} else {
    $age = $now - intval($cache['ts']);
    if ($age >= 6 * 3600) $shouldRefresh = true;
    $cutoff = clone $istNow; $cutoff->setTime(19, 30, 0);
    if ($istNow >= $cutoff && (empty($cache['fetched_date']) || $cache['fetched_date'] !== $todayIST)) {
        $shouldRefresh = true;
    }
    // Schema migration: caches written before the buy/sell fields were added
    // lack the fiiBuy key. Force a refresh once so the new tiles can show
    // matching buy/sell numbers without manual cache deletion.
    if (!empty($cache['rows']) && is_array($cache['rows']) && !array_key_exists('fiiBuy', $cache['rows'][0])) {
        $shouldRefresh = true;
    }
}

function format_date($iso) {
    // "2026-04-24" -> "24-Apr-2026"
    if (!is_string($iso)) return '';
    $parts = explode('-', $iso);
    if (count($parts) !== 3) return $iso;
    $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
    $m = intval($parts[1]);
    if (!isset($months[$m])) return $iso;
    return $parts[2] . '-' . $months[$m] . '-' . $parts[0];
}

function fetch_groww_history() {
    $url = 'https://groww.in/fii-dii-data';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200 || !$body) return null;

    // Pull the __NEXT_DATA__ JSON blob
    if (!preg_match('#<script id="__NEXT_DATA__"[^>]*>(.+?)</script>#s', $body, $m)) return null;
    $obj = json_decode($m[1], true);
    if (!$obj) return null;

    // Walk to props.pageProps.initialData
    $arr = $obj['props']['pageProps']['initialData'] ?? null;
    if (!is_array($arr)) return null;

    $rows = [];
    foreach ($arr as $entry) {
        if (!is_array($entry)) continue;
        $date = $entry['date'] ?? null;
        $fii  = $entry['fii']['netBuySell'] ?? null;
        $dii  = $entry['dii']['netBuySell'] ?? null;
        if ($date === null || $fii === null || $dii === null) continue;

        // Buy/sell values when Groww provides them — used by the live tiles
        // so the headline numbers always reconcile with the net (buy − sell = net).
        $fiiBuy  = $entry['fii']['buyValue']  ?? null;
        $fiiSell = $entry['fii']['sellValue'] ?? null;
        $diiBuy  = $entry['dii']['buyValue']  ?? null;
        $diiSell = $entry['dii']['sellValue'] ?? null;

        $rows[] = [
            'date'    => format_date($date),
            'iso'     => $date,
            'fii'     => floatval($fii),
            'dii'     => floatval($dii),
            'fiiBuy'  => $fiiBuy  !== null ? floatval($fiiBuy)  : null,
            'fiiSell' => $fiiSell !== null ? floatval($fiiSell) : null,
            'diiBuy'  => $diiBuy  !== null ? floatval($diiBuy)  : null,
            'diiSell' => $diiSell !== null ? floatval($diiSell) : null,
        ];
    }

    // Newest first, dedupe by iso, cap 30
    usort($rows, function($a, $b) { return strcmp($b['iso'], $a['iso']); });
    $seen = [];
    $clean = [];
    foreach ($rows as $r) {
        if (isset($seen[$r['iso']])) continue;
        $seen[$r['iso']] = true;
        $clean[] = [
            'date'    => $r['date'],
            'fii'     => $r['fii'],
            'dii'     => $r['dii'],
            'fiiBuy'  => $r['fiiBuy'],
            'fiiSell' => $r['fiiSell'],
            'diiBuy'  => $r['diiBuy'],
            'diiSell' => $r['diiSell'],
        ];
        if (count($clean) >= 30) break;
    }
    return $clean;
}

if ($shouldRefresh) {
    $rows = fetch_groww_history();
    if ($rows && count($rows) > 0) {
        $cache = [
            'ts'           => $now,
            'fetched_date' => $todayIST,
            'fetched_at'   => $istNow->format(DateTime::ATOM),
            'source'       => 'Groww (FII/DII page)',
            'rows'         => $rows,
            'stale'        => false,
        ];
        @file_put_contents($cacheFile, json_encode($cache));
    } else if ($cache) {
        $cache['stale'] = true;
        $cache['last_attempt'] = $istNow->format(DateTime::ATOM);
    }
}

if ($cache) {
    echo json_encode($cache);
} else {
    http_response_code(503);
    echo json_encode(['error' => 'Groww history unreachable and no cache available']);
}
