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

/* Price fingerprint of a call: symbol+action+entry+targets+stops. Order-
   insensitive on the level lists. Same setup -> same key -> never re-alert.
   Any level edited (incl. a trailed stop or re-posted entry) -> new key -> the
   engine treats it as a fresh, alertable setup. */
function rn_setup_key($sym, $action, $entry, $targetsRaw, $stopsRaw) {
    $t = rn_num_list($targetsRaw); sort($t, SORT_NUMERIC);
    $s = rn_num_list($stopsRaw);   sort($s, SORT_NUMERIC);
    $fmt = function ($x) { return number_format((float) $x, 2, '.', ''); };
    $norm = strtoupper(trim((string) $sym)) . '|' . $action . '|' . $fmt($entry)
          . '|' . implode(',', array_map($fmt, $t))
          . '|' . implode(',', array_map($fmt, $s));
    return md5($norm);
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
        (call_id, symbol, action, kind, level_index, level_price, trigger_price, entry_price, pnl_pct, setup_key)
     VALUES (:cid, :sym, :act, :kind, :lvl, :lvlp, :trig, :entry, :pnl, :skey)"
);
$updSnap = $pdo->prepare(
    "UPDATE calls SET last_price = :p, last_checked_at = NOW() WHERE id = :id"
);
// Existing alerts for a price-setup — used to decide if it's already "closed".
$setupAlerts = $pdo->prepare(
    "SELECT kind, level_index FROM call_alerts WHERE setup_key = :k"
);

foreach ($calls as $c) {
    $checked++;
    $sym   = $c['symbol'];
    $isBuy = $c['action'] === 'BUY';
    $entry = floatval($c['entry_price']);
    $targetsRaw = !empty($c['targets'])     ? $c['targets']     : ($c['target_price'] ?? '');
    $stopsRaw   = !empty($c['stop_losses']) ? $c['stop_losses'] : ($c['stop_loss']    ?? '');
    $tl = rn_num_list($targetsRaw);
    $slList = rn_num_list($stopsRaw);
    $stop   = count($slList) ? $slList[0] : null;   // primary stop = first in the list
    // One fingerprint for this exact price-setup. Drives all dedup below.
    $setupKey = rn_setup_key($sym, $c['action'], $entry, $targetsRaw, $stopsRaw);

    // Confirmed ticker (yahoo_symbol) wins; else auto-derive from the symbol.
    $ticker = rn_resolve_ticker($sym, $c['yahoo_symbol'] ?? null);
    if ($ticker !== null && !array_key_exists($ticker, $priceCache)) {
        $priceCache[$ticker] = rn_fetch_price_by_ticker($ticker);
    }
    $price = $ticker !== null ? $priceCache[$ticker] : null;

    if ($price === null) {
        // Never fail silently: raise ONE visible "can't price" alert so the
        // team can set the right NSE ticker. Deduped per setup.
        $insAlert->execute([
            ':cid' => $c['id'], ':sym' => $sym, ':act' => $c['action'], ':kind' => 'no_price',
            ':lvl' => 0, ':lvlp' => null, ':trig' => null, ':entry' => $c['entry_price'], ':pnl' => null,
            ':skey' => $setupKey,
        ]);
        if ($insAlert->rowCount() > 0) { $newAlerts++; }
        $details[] = ['call' => $c['id'], 'symbol' => $sym, 'ticker' => $ticker, 'price' => null];
        continue;
    }
    $priced++;

    $updSnap->execute([':p' => $price, ':id' => $c['id']]);

    // ── "Closed" gate ──────────────────────────────────────────────
    // If this exact setup already stopped out OR hit its final target, the
    // position is logically closed — emit no further target/stop alerts for
    // it (a post-stop rebound to a target must not ping). Alerting only
    // resumes if a level changes, which yields a different setup_key.
    $N = count($tl);
    $setupAlerts->execute([':k' => $setupKey]);
    $closed = false;
    foreach ($setupAlerts->fetchAll() as $e) {
        if ($e['kind'] === 'stop_hit') { $closed = true; break; }
        if ($e['kind'] === 'target_hit' && $N > 0 && intval($e['level_index']) >= $N) { $closed = true; break; }
    }
    if ($closed) {
        $details[] = ['call' => $c['id'], 'symbol' => $sym, 'closed' => true, 'price' => $price];
        continue;
    }

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
            ':skey' => $setupKey,
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
                ':skey' => $setupKey,
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
