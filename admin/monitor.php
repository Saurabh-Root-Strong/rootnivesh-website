<?php
/* admin/monitor.php — the price-watch engine.

   Runs from a cron job every few minutes during market hours. For every
   OPEN call it pulls the live price (Yahoo .NS via price_source.php) and,
   when a target or the stop-loss is breached, drops ONE alert row into
   `call_alerts`. It NEVER edits the call itself — the team confirms each
   alert in the Alerts tab. Notify-only by design: a single bad tick or an
   intrabar wick can't auto-close a real position.

   How it's triggered
   ──────────────────
   • Web cron (Hostinger "Cron Jobs" with a URL, or any uptime pinger):
        https://rootnivesh.in/admin/monitor.php?key=YOUR_MONITOR_SECRET
   • Shell cron (php-cli):
        php /home/USER/public_html/admin/monitor.php key=YOUR_MONITOR_SECRET
     (CLI runs are also allowed without a key.)

   Suggested schedule — every 5 min, Mon–Fri, 9:15–15:35 IST. The script
   self-guards on market hours, so an always-on every-5-min cron is also fine.
*/

require_once __DIR__ . '/db.php';          // defines db() + loads config.php
require_once __DIR__ . '/price_source.php';

header('Content-Type: application/json; charset=utf-8');

$IS_CLI = (PHP_SAPI === 'cli');

/* ---- collect args from GET (web) or argv (cli, "key=..." / "force=1") ---- */
$arg = $_GET;
if ($IS_CLI && !empty($argv)) {
    foreach (array_slice($argv, 1) as $a) {
        if (strpos($a, '=') !== false) { [$k, $v] = explode('=', $a, 2); $arg[$k] = $v; }
    }
}

/* ---- access gate: web hits MUST carry the secret; CLI is trusted ---- */
if (!$IS_CLI) {
    $key = $arg['key'] ?? '';
    if (!defined('MONITOR_SECRET') || !hash_equals(MONITOR_SECRET, (string) $key)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
}

if (defined('MONITOR_ENABLED') && MONITOR_ENABLED !== true) {
    echo json_encode(['ok' => true, 'skipped' => 'monitor disabled in config']);
    exit;
}

/* ---- market-hours guard (IST). Override with force=1. ---- */
$force = !empty($arg['force']);
$ist   = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$dow   = (int) $ist->format('N');               // 1=Mon..7=Sun
$mins  = (int) $ist->format('H') * 60 + (int) $ist->format('i');
$open  = 9 * 60 + 15;                            // 09:15
$close = 15 * 60 + 35;                           // 15:35 (a little slack after 15:30)
$inHours = ($dow >= 1 && $dow <= 5 && $mins >= $open && $mins <= $close);
if (!$inHours && !$force) {
    echo json_encode(['ok' => true, 'skipped' => 'outside market hours', 'ist' => $ist->format('c')]);
    exit;
}

$pdo = db();

/* ---- helpers ---- */
function rn_num_list($raw) {
    preg_match_all('/\d+(?:\.\d+)?/', (string) $raw, $m);
    return array_map('floatval', $m[0]);
}

/* ---- pull every open call ---- */
$calls = $pdo->query(
    "SELECT id, action, symbol, yahoo_symbol, entry_price, target_price, targets,
            targets_hit, stop_loss, stop_losses
     FROM calls WHERE status = 'open' ORDER BY id"
)->fetchAll();

$priceCache = [];     // ticker -> float, dedupe fetches within a run
$checked = 0; $priced = 0; $newAlerts = 0; $details = [];

$insAlert = $pdo->prepare(
    "INSERT IGNORE INTO call_alerts
        (call_id, symbol, action, kind, level_index, level_price, trigger_price, entry_price, pnl_pct)
     VALUES (:cid, :sym, :act, :kind, :lvl, :lvlp, :trig, :entry, :pnl)"
);
$updSnap = $pdo->prepare(
    "UPDATE calls SET last_price = :p, last_checked_at = NOW() WHERE id = :id"
);

foreach ($calls as $c) {
    $checked++;
    $sym   = $c['symbol'];
    // Confirmed ticker (yahoo_symbol) wins; else auto-derive from the symbol.
    $ticker = rn_resolve_ticker($sym, $c['yahoo_symbol'] ?? null);
    if ($ticker !== null && !array_key_exists($ticker, $priceCache)) {
        $priceCache[$ticker] = rn_fetch_price_by_ticker($ticker);
    }
    $price = $ticker !== null ? $priceCache[$ticker] : null;

    if ($price === null) {
        // Never fail silently: raise ONE visible "can't price" alert so the
        // team can set the right NSE ticker. Deduped by the unique key.
        $insAlert->execute([
            ':cid' => $c['id'], ':sym' => $sym, ':act' => $c['action'], ':kind' => 'no_price',
            ':lvl' => 0, ':lvlp' => null, ':trig' => null, ':entry' => $c['entry_price'], ':pnl' => null,
        ]);
        if ($insAlert->rowCount() > 0) { $newAlerts++; }
        $details[] = ['call' => $c['id'], 'symbol' => $sym, 'ticker' => $ticker, 'price' => null];
        continue;
    }
    $priced++;

    $updSnap->execute([':p' => $price, ':id' => $c['id']]);

    $isBuy = $c['action'] === 'BUY';
    $entry = floatval($c['entry_price']);
    $tl = rn_num_list(!empty($c['targets']) ? $c['targets'] : ($c['target_price'] ?? ''));
    // primary stop = first number in the SL list, else stop_loss column
    $slList = rn_num_list(!empty($c['stop_losses']) ? $c['stop_losses'] : ($c['stop_loss'] ?? ''));
    $stop   = count($slList) ? $slList[0] : null;

    $pnlAt = function ($p) use ($isBuy, $entry) {
        return ($entry > 0) ? round(($isBuy ? ($p - $entry) : ($entry - $p)) / $entry * 100, 2) : null;
    };

    // Targets: a level k is breached when BUY price >= Tk, SELL price <= Tk.
    // Skip levels the team already confirmed (targets_hit) — no need to re-alert.
    $alreadyHit = max(0, intval($c['targets_hit']));
    foreach ($tl as $i => $lvl) {
        $k = $i + 1;
        if ($k <= $alreadyHit) continue;
        $hit = $isBuy ? ($price >= $lvl) : ($price <= $lvl);
        if (!$hit) continue;
        $insAlert->execute([
            ':cid' => $c['id'], ':sym' => $sym, ':act' => $c['action'], ':kind' => 'target_hit',
            ':lvl' => $k, ':lvlp' => $lvl, ':trig' => $price, ':entry' => $entry, ':pnl' => $pnlAt($price),
        ]);
        if ($insAlert->rowCount() > 0) { $newAlerts++; $details[] = ['call' => $c['id'], 'symbol' => $sym, 'target' => $k, 'price' => $price]; }
    }

    // Stop: BUY breached when price <= stop; SELL when price >= stop.
    if ($stop !== null) {
        $stopHit = $isBuy ? ($price <= $stop) : ($price >= $stop);
        if ($stopHit) {
            $insAlert->execute([
                ':cid' => $c['id'], ':sym' => $sym, ':act' => $c['action'], ':kind' => 'stop_hit',
                ':lvl' => 0, ':lvlp' => $stop, ':trig' => $price, ':entry' => $entry, ':pnl' => $pnlAt($price),
            ]);
            if ($insAlert->rowCount() > 0) { $newAlerts++; $details[] = ['call' => $c['id'], 'symbol' => $sym, 'stop' => $stop, 'price' => $price]; }
        }
    }
}

echo json_encode([
    'ok'         => true,
    'ist'        => $ist->format('c'),
    'open_calls' => $checked,
    'priced'     => $priced,
    'new_alerts' => $newAlerts,
    'details'    => $details,
], JSON_PRETTY_PRINT);
