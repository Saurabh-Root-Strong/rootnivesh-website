<?php
// JSON-fetching proxy for rootnivesh.in
// Frontend calls: /proxy.php?url=<URL-encoded target>
// Whitelist enforced for safety.

$allowedOrigins = ['https://rootnivesh.in', 'https://www.rootnivesh.in', 'http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$url = isset($_GET['url']) ? $_GET['url'] : '';
if ($url === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing url']);
    exit;
}

$allowed_hosts = ['query1.finance.yahoo.com', 'query2.finance.yahoo.com', 'www.nseindia.com', 'www.chittorgarh.com'];
$parsed = parse_url($url);
$scheme = strtolower($parsed['scheme'] ?? '');
$host   = strtolower($parsed['host'] ?? '');
// Only https to a whitelisted host. Rejecting non-https here also blocks
// file://, gopher://, dict://, etc. that could otherwise reach internal targets.
if ($scheme !== 'https' || !in_array($host, $allowed_hosts, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'host not allowed', 'host' => $host]);
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    // SSRF guard: do NOT follow redirects. The whitelist only validates the
    // initial URL — following a 30x to an attacker-chosen Location (internal
    // service, 169.254.169.254 metadata, localhost) would bypass it entirely.
    CURLOPT_FOLLOWLOCATION => false,
    // Belt-and-suspenders: even if redirects were ever re-enabled, only allow
    // HTTP(S) — never file/gopher/dict/etc.
    CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/html, */*',
        'Accept-Language: en-US,en;q=0.9'
    ]
]);

$result = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$err = curl_error($ch);
curl_close($ch);

if ($result === false || $status >= 500) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(502);
    echo json_encode(['error' => 'upstream failed', 'status' => $status, 'curl_error' => $err]);
    exit;
}

// Pass through the upstream content type so HTML responses parse correctly client-side.
if ($content_type) {
    header('Content-Type: ' . $content_type);
} else {
    header('Content-Type: application/json; charset=utf-8');
}
http_response_code($status);
echo $result;
