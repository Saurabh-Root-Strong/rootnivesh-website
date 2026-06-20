<?php
/* calls.php — public API.
   Returns the latest public calls as JSON. Used by the website's
   "Latest Calls" feed (and can be used by any external integration).

   GET /calls.php           -> latest 20 public calls
   GET /calls.php?limit=10  -> latest 10
   GET /calls.php?type=intraday  -> filter by type
*/

$allowedOrigins = ['https://rootnivesh.in', 'https://www.rootnivesh.in', 'http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/admin/db.php';

try {
    $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
    $type  = $_GET['type'] ?? '';
    $allowedTypes = ['intraday','swing','positional','longterm','fno'];

    // NOTE: `thesis` is deliberately NOT selected — it's premium research
    // reasoning and must never reach the public API.
    $sql  = 'SELECT id, posted_at, call_type, action, symbol, company_name,
                    entry_price, target_price, targets, targets_hit, stop_loss, stop_losses, status,
                    exit_price, exit_at, pnl_pct
             FROM calls WHERE is_public = 1';
    $args = [];
    if (in_array($type, $allowedTypes, true)) {
        $sql .= ' AND call_type = :t';
        $args[':t'] = $type;
    }
    $sql .= ' ORDER BY posted_at DESC LIMIT ' . $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();

    // Server-side gating: for OPEN (live) calls, never expose the actionable
    // levels. Reveal only the ACHIEVED targets + booked %, and how many
    // targets remain — everything else (entry, unhit targets, stop) is locked
    // behind the WhatsApp CTA. Cosmetic CSS blur alone is bypassable.
    $fmtNum = function ($v) {
        return ($v == intval($v)) ? (string) intval($v) : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    };
    foreach ($rows as &$r) {
        if ($r['status'] === 'open') {
            preg_match_all('/\d+(?:\.\d+)?/',
                (string) (!empty($r['targets']) ? $r['targets'] : ($r['target_price'] ?? '')), $m);
            $tl = array_map('floatval', $m[0]);
            $N  = count($tl);
            $hit = max(0, min(intval($r['targets_hit']), $N));
            $entry = floatval($r['entry_price']);
            $isBuy = $r['action'] === 'BUY';

            $r['targets_total']    = $N;
            $r['targets_achieved'] = $hit > 0 ? implode(', ', array_map($fmtNum, array_slice($tl, 0, $hit))) : null;
            $r['booked_pct']       = ($hit > 0 && $entry > 0)
                ? round(($isBuy ? ($tl[$hit - 1] - $entry) : ($entry - $tl[$hit - 1])) / $entry * 100, 2)
                : null;

            // Lock the secrets.
            $r['entry_price'] = null;
            $r['target_price'] = null;
            $r['targets'] = null;
            $r['stop_loss'] = null;
            $r['stop_losses'] = null;
        }
    }
    unset($r);

    echo json_encode([
        'fetched_at' => date(DateTime::ATOM),
        'count'      => count($rows),
        'calls'      => $rows,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load calls.']);
}
