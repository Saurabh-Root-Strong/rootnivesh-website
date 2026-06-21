<?php
/* ============================================================
   brokers.php — Indian stock-broker brokerage models for the
   Brokerage Calculator.

   GET /brokers.php -> { reviewed, brokers: { id: {name, plan, note,
                        delivery, intraday, futures, options} } }

   Each segment is a rule:
     {type:'zero'}                         -> ₹0
     {type:'flat', flat:N}                 -> ₹N per order
     {type:'pct',  pct:P}                  -> P% of order value
     {type:'minpctcap', pct:P, cap:N}      -> min(P% of order value, ₹N)

   There is no public API for broker brokerage, so these are CURATED from
   each broker's published charge list. Update here when a broker revises a
   plan; REVIEWED is the "rates as of" date shown to users. Discount-broker
   rates are exact; full-service entries use the broker's flat/discount plan
   and are marked accordingly.
   ============================================================ */

$allowedOrigins = ['https://rootnivesh.in', 'https://www.rootnivesh.in', 'http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');

$REVIEWED = '2026-06-21';   // bump when any plan below changes

$zero  = ['type' => 'zero'];
$flat20 = ['type' => 'flat', 'flat' => 20];
function mc($pct, $cap = 20) { return ['type' => 'minpctcap', 'pct' => $pct, 'cap' => $cap]; }

$BROKERS = [
    'zerodha' => ['name' => 'Zerodha', 'plan' => '',
        'delivery' => $zero, 'intraday' => mc(0.03), 'futures' => mc(0.03), 'options' => $flat20],
    'groww' => ['name' => 'Groww', 'plan' => '',
        'delivery' => mc(0.1), 'intraday' => mc(0.1), 'futures' => $flat20, 'options' => $flat20],
    'angelone' => ['name' => 'Angel One', 'plan' => '',
        'delivery' => mc(0.1), 'intraday' => mc(0.03), 'futures' => mc(0.03), 'options' => $flat20],
    'upstox' => ['name' => 'Upstox', 'plan' => '',
        'delivery' => mc(2.5), 'intraday' => mc(0.05), 'futures' => mc(0.05), 'options' => $flat20],
    'dhan' => ['name' => 'Dhan', 'plan' => '',
        'delivery' => $zero, 'intraday' => mc(0.03), 'futures' => mc(0.03), 'options' => $flat20],
    'fyers' => ['name' => 'Fyers', 'plan' => '',
        'delivery' => $zero, 'intraday' => mc(0.03), 'futures' => mc(0.03), 'options' => $flat20],
    'fivepaisa' => ['name' => '5paisa', 'plan' => '',
        'delivery' => $flat20, 'intraday' => $flat20, 'futures' => $flat20, 'options' => $flat20],
    'paytmmoney' => ['name' => 'Paytm Money', 'plan' => '',
        'delivery' => mc(2.5), 'intraday' => $flat20, 'futures' => $flat20, 'options' => $flat20],
    'icicidirect' => ['name' => 'ICICI Direct', 'plan' => 'Neo plan', 'note' => 'Standard plan — verify on broker site',
        'delivery' => $zero, 'intraday' => $flat20, 'futures' => $flat20, 'options' => $flat20],
    'hdfcsec' => ['name' => 'HDFC Securities', 'plan' => 'Sky plan', 'note' => 'Standard plan — verify on broker site',
        'delivery' => mc(0.10), 'intraday' => $flat20, 'futures' => $flat20, 'options' => $flat20],
    'kotak' => ['name' => 'Kotak Securities', 'plan' => 'Trade Free plan', 'note' => 'Standard plan — verify on broker site',
        'delivery' => $zero, 'intraday' => $flat20, 'futures' => $flat20, 'options' => $flat20],
    'sbisec' => ['name' => 'SBI Securities', 'plan' => '', 'note' => 'Standard plan — verify on broker site',
        'delivery' => mc(0.50), 'intraday' => mc(0.05), 'futures' => $flat20, 'options' => $flat20],
    'motilal' => ['name' => 'Motilal Oswal', 'plan' => '', 'note' => 'Standard plan — verify on broker site',
        'delivery' => mc(0.20), 'intraday' => mc(0.02), 'futures' => $flat20, 'options' => $flat20],
];

echo json_encode(['reviewed' => $REVIEWED, 'brokers' => $BROKERS]);
