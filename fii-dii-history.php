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
$dayOfWeek = (int)$istNow->format('w');           // 0=Sun, 6=Sat
$mins      = (int)$istNow->format('G') * 60 + (int)$istNow->format('i');
$marketOpen = $dayOfWeek >= 1 && $dayOfWeek <= 5 && $mins >= 540 && $mins <= 930;
// 60s during market hours (Mon-Fri 9:00-15:30 IST) so the displayed data
// matches every other live widget on the site. 1 hour outside since FII/DII
// publishes once daily — no point hammering Groww off-hours.
$ttl = $marketOpen ? 60 : 3600;
if (!$cache || empty($cache['ts'])) {
    $shouldRefresh = true;
} else {
    $age = $now - intval($cache['ts']);
    if ($age >= $ttl) $shouldRefresh = true;
    $cutoff = clone $istNow; $cutoff->setTime(19, 30, 0);
    if ($istNow >= $cutoff && (empty($cache['fetched_date']) || $cache['fetched_date'] !== $todayIST)) {
        $shouldRefresh = true;
    }
    // Schema migration: caches written before buy/sell extraction was added,
    // OR caches that captured buy/sell as null because the path lookup was
    // wrong (Groww uses grossBuy/grossSell, not buyValue/sellValue), are
    // force-refreshed so the new code can populate them correctly.
    if (!empty($cache['rows']) && is_array($cache['rows'])) {
        $first = $cache['rows'][0];
        if (!array_key_exists('fiiBuy', $first) || $first['fiiBuy'] === null) {
            $shouldRefresh = true;
        }
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
        // Try several path variants because Groww's __NEXT_DATA__ schema has
        // changed across releases (sometimes buyValue, sometimes grossBuy,
        // sometimes nested under "gross").
        $fiiBuy  = $entry['fii']['buyValue']  ?? $entry['fii']['grossBuy']  ?? $entry['fii']['totalBuy']  ?? $entry['fii']['gross']['buy']  ?? $entry['fii']['buy']  ?? null;
        $fiiSell = $entry['fii']['sellValue'] ?? $entry['fii']['grossSell'] ?? $entry['fii']['totalSell'] ?? $entry['fii']['gross']['sell'] ?? $entry['fii']['sell'] ?? null;
        $diiBuy  = $entry['dii']['buyValue']  ?? $entry['dii']['grossBuy']  ?? $entry['dii']['totalBuy']  ?? $entry['dii']['gross']['buy']  ?? $entry['dii']['buy']  ?? null;
        $diiSell = $entry['dii']['sellValue'] ?? $entry['dii']['grossSell'] ?? $entry['dii']['totalSell'] ?? $entry['dii']['gross']['sell'] ?? $entry['dii']['sell'] ?? null;

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
            'iso'     => $r['iso'],   // retained so NSE's latest day can be merged on top
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

function date_to_iso($d) {
    // "15-Jun-2026" -> "2026-06-15"
    if (!is_string($d) || $d === '') return null;
    $dt = DateTime::createFromFormat('d-M-Y', $d);
    return $dt ? $dt->format('Y-m-d') : null;
}

/* Latest 1–2 trading days straight from NSE's official FII/DII report
   (https://www.nseindia.com/reports/fii-dii). NSE publishes the current day's
   provisional figures hours before Groww mirrors them, so NSE is treated as the
   authoritative source for the most recent day. Requires the cookie handshake. */
function fetch_nse_latest() {
    $home = 'https://www.nseindia.com/';
    $api  = 'https://www.nseindia.com/api/fiidiiTradeReact';
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: en-US,en;q=0.9',
        'Connection: keep-alive',
    ];
    $get = function ($url, $cookieFile, $referer = null) use ($headers) {
        $h = $headers;
        if ($referer) $h[] = 'Referer: ' . $referer;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_HTTPHEADER     => $h,
        ]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['body' => $body, 'status' => $status];
    };

    $cookieFile = tempnam(sys_get_temp_dir(), 'nseh_');
    $get($home, $cookieFile);                       // step 1: collect session cookie
    $resp = $get($api, $cookieFile, $home);         // step 2: call API with cookie
    @unlink($cookieFile);
    if ($resp['status'] !== 200 || !$resp['body']) return [];

    $arr = json_decode($resp['body'], true);
    if (!is_array($arr)) return [];

    // NSE returns one object per category per day; fold FII/FPI + DII into one row/day.
    $byDate = [];
    foreach ($arr as $row) {
        if (!is_array($row)) continue;
        $iso = date_to_iso($row['date'] ?? '');
        if (!$iso) continue;
        if (!isset($byDate[$iso])) $byDate[$iso] = ['iso' => $iso, 'date' => format_date($iso)];
        $cat  = strtoupper($row['category'] ?? '');
        $net  = isset($row['netValue'])  ? floatval($row['netValue'])  : null;
        $buy  = isset($row['buyValue'])  ? floatval($row['buyValue'])  : null;
        $sell = isset($row['sellValue']) ? floatval($row['sellValue']) : null;
        if (strpos($cat, 'FII') !== false || strpos($cat, 'FPI') !== false) {
            $byDate[$iso]['fii'] = $net; $byDate[$iso]['fiiBuy'] = $buy; $byDate[$iso]['fiiSell'] = $sell;
        } elseif (strpos($cat, 'DII') !== false) {
            $byDate[$iso]['dii'] = $net; $byDate[$iso]['diiBuy'] = $buy; $byDate[$iso]['diiSell'] = $sell;
        }
    }

    $out = [];
    foreach ($byDate as $r) {
        if (!isset($r['fii']) || !isset($r['dii'])) continue; // need both sides
        $out[] = [
            'date'    => $r['date'],   'iso'     => $r['iso'],
            'fii'     => $r['fii'],    'dii'     => $r['dii'],
            'fiiBuy'  => $r['fiiBuy']  ?? null, 'fiiSell' => $r['fiiSell'] ?? null,
            'diiBuy'  => $r['diiBuy']  ?? null, 'diiSell' => $r['diiSell'] ?? null,
        ];
    }
    return $out;
}

/* Merge two series by ISO date; $overlay (NSE) wins on conflict. Newest first, cap 30. */
function merge_series($base, $overlay) {
    $map = [];
    foreach (array_merge($base, $overlay) as $r) {
        $iso = $r['iso'] ?? date_to_iso($r['date'] ?? '');
        if (!$iso) continue;
        $r['iso'] = $iso;
        $map[$iso] = $r; // later entries (overlay passed last) override earlier ones
    }
    $rows = array_values($map);
    usort($rows, function ($a, $b) { return strcmp($b['iso'], $a['iso']); });
    return array_slice($rows, 0, 30);
}

if ($shouldRefresh) {
    $growwRows = fetch_groww_history();             // 30-day history (may lag a day or two)
    $nseRows   = fetch_nse_latest();                // authoritative latest day(s)

    // Prefer fresh Groww as the history base; otherwise reuse cached history so a
    // Groww hiccup doesn't wipe the table — NSE still refreshes the latest day.
    $base = ($growwRows && count($growwRows) > 0) ? $growwRows
          : (($cache && !empty($cache['rows'])) ? $cache['rows'] : []);

    if (count($base) > 0 || count($nseRows) > 0) {
        $merged   = merge_series($base, $nseRows);
        $gotFresh = (count($growwRows ?? []) > 0) || (count($nseRows) > 0);
        $source   = count($nseRows) > 0 ? 'NSE + Groww' : 'Groww';
        if (count($merged) > 0) {
            $cache = [
                'ts'           => $now,
                'fetched_date' => $todayIST,
                'fetched_at'   => $istNow->format(DateTime::ATOM),
                'source'       => $source,
                'nse_latest'   => count($nseRows) > 0 ? $nseRows[0]['date'] : null,
                'rows'         => $merged,
                'stale'        => !$gotFresh,
            ];
            @file_put_contents($cacheFile, json_encode($cache), LOCK_EX);
        }
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
