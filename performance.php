<?php
/* performance.php — public track-record API.
   Aggregates every PUBLIC, DECIDED call (target_hit / stop_hit / closed with a
   known PnL) into headline stats + a closed-calls list for the Performance tab.

   GET /performance.php              -> all-time
   GET /performance.php?type=swing   -> filter by call_type
   GET /performance.php?days=90      -> only calls posted in the last N days
*/

$allowedOrigins = ['https://rootnivesh.in', 'https://www.rootnivesh.in', 'http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');

require_once __DIR__ . '/admin/db.php';

try {
    $type = $_GET['type'] ?? '';
    $allowedTypes = ['intraday','swing','positional','longterm','fno'];
    $days = isset($_GET['days']) ? max(1, min(3650, intval($_GET['days']))) : 0;

    // "Decided" = the call has a final outcome we can score.
    $where = "is_public = 1 AND status IN ('target_hit','stop_hit','closed') AND pnl_pct IS NOT NULL";
    $args  = [];
    if (in_array($type, $allowedTypes, true)) { $where .= ' AND call_type = :t'; $args[':t'] = $type; }
    if ($days > 0) { $where .= ' AND posted_at >= (NOW() - INTERVAL :d DAY)'; $args[':d'] = $days; }

    $sql = "SELECT id, posted_at, exit_at, call_type, action, symbol, company_name,
                   entry_price, target_price, stop_loss, exit_price, pnl_pct, status
            FROM calls WHERE $where
            ORDER BY COALESCE(exit_at, posted_at) DESC
            LIMIT 500";
    $stmt = db()->prepare($sql);
    foreach ($args as $k => $v) {
        // :d must bind as int for the INTERVAL clause.
        $stmt->bindValue($k, $v, $k === ':d' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $total   = count($rows);
    $wins    = 0;
    $sumPnl  = 0.0;
    $sumWin  = 0.0; $nWin = 0;
    $sumLoss = 0.0; $nLoss = 0;
    $best    = null;

    foreach ($rows as $r) {
        $p = floatval($r['pnl_pct']);
        $sumPnl += $p;
        if ($p >= 0) { $wins++; $sumWin += $p; $nWin++; }
        else         { $sumLoss += $p; $nLoss++; }
        if ($best === null || $p > floatval($best['pnl_pct'])) $best = $r;
    }

    $stats = [
        'decided_calls' => $total,
        'wins'          => $wins,
        'losses'        => $total - $wins,
        'win_rate'      => $total ? round($wins / $total * 100, 1) : null,
        'avg_return'    => $total ? round($sumPnl / $total, 2) : null,
        'avg_win'       => $nWin  ? round($sumWin / $nWin, 2) : null,
        'avg_loss'      => $nLoss ? round($sumLoss / $nLoss, 2) : null,
        'best_call'     => $best ? [
            'symbol'  => $best['symbol'],
            'pnl_pct' => round(floatval($best['pnl_pct']), 2),
        ] : null,
    ];

    echo json_encode([
        'fetched_at' => date(DateTime::ATOM),
        'filter'     => ['type' => $type ?: 'all', 'days' => $days ?: 'all'],
        'stats'      => $stats,
        'calls'      => $rows,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load performance data.']);
}
