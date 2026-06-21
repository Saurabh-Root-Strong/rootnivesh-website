<?php
/* ============================================================
   lots.php — F&O index lot sizes for the Options/Futures tool.

   GET /lots.php            -> { fetched_at, lots: { SYM: {name,exch,lot} } }
   GET /lots.php?refresh=1  -> force a refresh now (used by a weekly cron)

   Lot sizes are revised by SEBI/NSE/BSE periodically, so they must not be
   hardcoded forever. This endpoint:
     - serves a curated table (correct at deploy time, and the only source
       for BSE indices, which aren't in NSE's file), and
     - every week (anchored to Sunday 08:00 IST) pulls NSE's authoritative
       fo_mktlots.csv and overrides the NSE symbols if they changed, caching
       the result in data/lots-cache.json. If NSE is unreachable the last
       good / curated values are kept — never breaks the calculator.
   ============================================================ */

$allowedOrigins = ['https://rootnivesh.in', 'https://www.rootnivesh.in', 'http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

$cacheDir  = __DIR__ . '/data';
$cacheFile = $cacheDir . '/lots-cache.json';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

// Curated table. NSE values self-heal weekly from fo_mktlots.csv; BSE values
// (not in that file) are maintained here.
$CURATED = [
    'NIFTY'      => ['name' => 'Nifty 50',      'exch' => 'NSE', 'lot' => 75],
    'BANKNIFTY'  => ['name' => 'Bank Nifty',    'exch' => 'NSE', 'lot' => 35],
    'FINNIFTY'   => ['name' => 'Fin Nifty',     'exch' => 'NSE', 'lot' => 65],
    'MIDCPNIFTY' => ['name' => 'Midcap Nifty',  'exch' => 'NSE', 'lot' => 120],
    'NIFTYNXT50' => ['name' => 'Nifty Next 50', 'exch' => 'NSE', 'lot' => 25],
    'SENSEX'     => ['name' => 'Sensex',        'exch' => 'BSE', 'lot' => 20],
    'BANKEX'     => ['name' => 'Bankex',        'exch' => 'BSE', 'lot' => 30],
    'SENSEX50'   => ['name' => 'Sensex 50',     'exch' => 'BSE', 'lot' => 60],
];

/* Refresh if the cache is older than the most recent Sunday 08:00 IST — i.e.
   at most once per calendar week, the "every Sunday" check the team asked for. */
function lots_needs_refresh($fetchedAt) {
    if (!$fetchedAt) return true;
    $tz  = new DateTimeZone('Asia/Kolkata');
    $now = new DateTime('now', $tz);
    $lastSun = (clone $now);
    $dow = (int) $lastSun->format('w');         // 0 = Sunday
    $lastSun->modify('-' . $dow . ' days')->setTime(8, 0);
    if ($now < $lastSun) $lastSun->modify('-7 days');
    $f = (new DateTime('@' . (int) $fetchedAt))->setTimezone($tz);
    return $f < $lastSun;
}

/* Pull NSE's published F&O market-lot file and return [SYMBOL => lot] for the
   index underlyings we care about. Null on any failure. */
function lots_fetch_nse() {
    $url = 'https://nsearchives.nseindia.com/content/fo/fo_mktlots.csv';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_FOLLOWLOCATION  => false,
        CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT         => 12,
        CURLOPT_CONNECTTIMEOUT  => 5,
        CURLOPT_HTTPHEADER      => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: text/csv, text/plain, */*',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400 || strlen($body) < 50) return null;

    $want = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY', 'NIFTYNXT50'];
    $out  = [];
    foreach (preg_split('/\r\n|\n|\r/', $body) as $line) {
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) < 3) continue;
        $sym = strtoupper($cols[1]);                 // col 0 = underlying, col 1 = symbol
        if (in_array($sym, $want, true)) {
            for ($i = 2; $i < count($cols); $i++) {  // first numeric monthly lot
                if (is_numeric($cols[$i]) && (int) $cols[$i] > 0) { $out[$sym] = (int) $cols[$i]; break; }
            }
        }
    }
    return $out ?: null;
}

$cache = file_exists($cacheFile) ? (json_decode(@file_get_contents($cacheFile), true) ?: []) : [];

// Start from curated, overlay last-good cached values.
$lots = $CURATED;
if (!empty($cache['lots']) && is_array($cache['lots'])) {
    foreach ($cache['lots'] as $s => $l) {
        if (isset($lots[$s]) && is_numeric($l) && (int) $l > 0) $lots[$s]['lot'] = (int) $l;
    }
}

if (isset($_GET['refresh']) || lots_needs_refresh($cache['fetched_at'] ?? 0)) {
    $fresh = lots_fetch_nse();
    if ($fresh) {
        foreach ($fresh as $s => $l) { if (isset($lots[$s])) $lots[$s]['lot'] = (int) $l; }
        $source = 'nse-fo_mktlots';
    } else {
        $source = $cache['source'] ?? 'curated';   // NSE down → keep what we have
    }
    $store = [];
    foreach ($lots as $s => $d) $store[$s] = $d['lot'];
    @file_put_contents($cacheFile, json_encode(['fetched_at' => time(), 'source' => $source, 'lots' => $store]), LOCK_EX);
}

echo json_encode(['fetched_at' => date(DateTime::ATOM), 'lots' => $lots]);
