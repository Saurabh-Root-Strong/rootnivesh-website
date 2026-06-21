<?php
/* admin/alerts_feed.php — tiny JSON used by the admin badge poller.
   Returns the count of still-"new" price-watch alerts. Auth-gated like every
   other admin page (session required). */

require_once __DIR__ . '/auth.php';
admin_require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

try {
    $n = (int) db()->query("SELECT COUNT(*) FROM call_alerts WHERE status='new'")->fetchColumn();
    echo json_encode(['new' => $n]);
} catch (Throwable $e) {
    // Table not migrated yet — report zero rather than erroring the poller.
    echo json_encode(['new' => 0]);
}
