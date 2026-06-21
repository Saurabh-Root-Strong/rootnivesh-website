<?php
/* admin/resolve_symbol.php — "Check price" endpoint for the post-call form.

   GET ?symbol=AEROFLEX             -> tries the auto-derived ticker
   GET ?symbol=JUBILANT&ticker=JUBLINGREA  -> verifies a hand-typed override

   Returns JSON: { ok, ticker, price, source }. ok=true means we got a live
   price for that ticker, so the form can store it as the call's yahoo_symbol.
   Session-gated like every admin endpoint. */

require_once __DIR__ . '/auth.php';
admin_require_login();
require_once __DIR__ . '/price_source.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$symbol   = trim($_GET['symbol'] ?? '');
$override = trim($_GET['ticker'] ?? '');

if ($symbol === '' && $override === '') {
    echo json_encode(['ok' => false, 'error' => 'no symbol']);
    exit;
}

$ticker = rn_resolve_ticker($symbol, $override !== '' ? $override : null);
if ($ticker === null) {
    echo json_encode(['ok' => false, 'error' => 'could not derive a ticker']);
    exit;
}

$price = rn_fetch_price_by_ticker($ticker);
echo json_encode([
    'ok'     => $price !== null,
    'ticker' => $ticker,
    'price'  => $price,
    'source' => $override !== '' ? 'override' : 'auto',
]);
