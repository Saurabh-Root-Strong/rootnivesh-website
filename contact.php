<?php
/* ============================================================
   contact.php — receives both the Contact form and the homepage
   mini lead form, validates the input, and emails it to
   contact@rootnivesh.in via PHP's mail() (Hostinger has it
   enabled by default for any address on the domain).

   Returns JSON the frontend can render a friendly success or
   error message from.
   ============================================================ */

$allowedOrigins = ['https://rootnivesh.in', 'https://www.rootnivesh.in', 'http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

// Block obvious auto-submit floods: at most 1 submission per IP per 30 seconds.
// Tracked in data/contact-rate.json. This is best-effort, not crypto-grade.
$rateDir  = __DIR__ . '/data';
$rateFile = $rateDir . '/contact-rate.json';
if (!is_dir($rateDir)) { @mkdir($rateDir, 0755, true); }
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rate = file_exists($rateFile) ? (json_decode(@file_get_contents($rateFile), true) ?: []) : [];
$nowTs = time();
foreach ($rate as $k => $v) { if ($nowTs - $v > 600) unset($rate[$k]); }
if (isset($rate[$ip]) && ($nowTs - $rate[$ip]) < 30) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Please wait a moment before submitting again.']);
    exit;
}
$rate[$ip] = $nowTs;
@file_put_contents($rateFile, json_encode($rate), LOCK_EX);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$TO    = 'contact@rootnivesh.in';
$FROM  = 'contact@rootnivesh.in';

function trim_field($key, $max = 500) {
    $v = isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
    return mb_substr($v, 0, $max);
}
function strip_header_injection($s) {
    return preg_replace('/[\r\n]+/', ' ', $s);
}

$source   = trim_field('source', 30);    // 'contact' | 'lead'
$name     = trim_field('name', 100);
$email    = trim_field('email', 120);
$phone    = trim_field('phone', 30);
$interest = trim_field('interest', 80);
$message  = trim_field('message', 4000);
$honeypot = trim_field('website', 200);  // bots tend to fill hidden field named "website"

if ($honeypot !== '') {
    // Silent success so bots don't retry, but we drop the message.
    echo json_encode(['ok' => true, 'note' => 'queued']);
    exit;
}
if ($name === '' || $email === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Name and email are required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'That email address looks invalid.']);
    exit;
}

$tag     = $source === 'lead' ? 'Lead form' : 'Contact form';
$subject = "[rootnivesh.in] {$tag} — {$name}";

$bodyLines = [
    "New {$tag} submission from rootnivesh.in",
    str_repeat('-', 48),
    "Name:     {$name}",
    "Email:    {$email}",
];
if ($phone !== '')    $bodyLines[] = "Phone:    {$phone}";
if ($interest !== '') $bodyLines[] = "Interest: {$interest}";
if ($message !== '')  { $bodyLines[] = ""; $bodyLines[] = "Message:"; $bodyLines[] = $message; }
$bodyLines[] = "";
$bodyLines[] = str_repeat('-', 48);
$bodyLines[] = 'Submitted: ' . (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('d M Y, H:i:s') . ' IST';
$bodyLines[] = 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '?');
$body = implode("\n", $bodyLines);

$headers = [
    "From: RootNivesh Website <{$FROM}>",
    "Reply-To: " . strip_header_injection($name) . " <" . strip_header_injection($email) . ">",
    "Content-Type: text/plain; charset=utf-8",
    "X-Mailer: rootnivesh-form",
];

$sent = @mail($TO, strip_header_injection($subject), $body, implode("\r\n", $headers));

if ($sent) {
    echo json_encode(['ok' => true, 'message' => 'Sent']);
} else {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Could not send right now. Please WhatsApp us instead.']);
}
