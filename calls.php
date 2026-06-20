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

    $sql  = 'SELECT id, posted_at, call_type, action, symbol, company_name,
                    entry_price, target_price, targets, stop_loss, stop_losses, thesis, status,
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

    echo json_encode([
        'fetched_at' => date(DateTime::ATOM),
        'count'      => count($rows),
        'calls'      => $rows,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load calls.']);
}
