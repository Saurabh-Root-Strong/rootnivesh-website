<?php
/* admin/price_source.php — live last-price fetcher for the monitor engine.

   One job: given a call's symbol, return the latest traded price as a float
   (or null if it can't be fetched). Source is Yahoo Finance's public chart
   endpoint for the NSE ticker ("<SYMBOL>.NS"), which needs no API key and is
   reachable from Hostinger's servers. NSE's own API blocks datacenter IPs, so
   Yahoo is the pragmatic primary; prices are near-real-time (occasionally a
   minute or two delayed) which is fine for the ~5-min monitor cadence.

   Symbol mapping:
     - default:  "RELIANCE"  -> "RELIANCE.NS"
     - overrides: $GLOBALS['YAHOO_SYMBOL_MAP'] in config.php (indices etc.)
*/

require_once __DIR__ . '/config.php';

/* Map a call symbol to its Yahoo Finance ticker. */
function rn_yahoo_ticker($symbol) {
    $key = strtoupper(trim($symbol));
    $map = $GLOBALS['YAHOO_SYMBOL_MAP'] ?? [];
    // exact match first (allows "AKUM DRUGS" => "AKUMS.NS")
    if (isset($map[$key])) return $map[$key];
    // default: strip spaces/dots, suffix .NS
    $clean = preg_replace('/[^A-Z0-9-]/', '', $key);
    if ($clean === '') return null;
    return $clean . '.NS';
}

/* Fetch the last traded price for a symbol. Returns float|null. */
function rn_fetch_price($symbol) {
    $ticker = rn_yahoo_ticker($symbol);
    if ($ticker === null) return null;

    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/'
         . rawurlencode($ticker) . '?interval=5m&range=1d';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: application/json',
        ],
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) return null;

    $data = json_decode($body, true);
    if (!is_array($data)) return null;

    // Prefer the live meta price; fall back to the last non-null 5-min close.
    $meta = $data['chart']['result'][0]['meta'] ?? null;
    if (is_array($meta) && isset($meta['regularMarketPrice']) && is_numeric($meta['regularMarketPrice'])) {
        return floatval($meta['regularMarketPrice']);
    }
    $closes = $data['chart']['result'][0]['indicators']['quote'][0]['close'] ?? null;
    if (is_array($closes)) {
        for ($i = count($closes) - 1; $i >= 0; $i--) {
            if ($closes[$i] !== null && is_numeric($closes[$i])) return floatval($closes[$i]);
        }
    }
    return null;
}
