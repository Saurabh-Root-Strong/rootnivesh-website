<?php
require_once __DIR__ . '/auth.php';

/* ---- Brute-force throttle: lock an IP for 15 min after 5 failed attempts. ---- */
const LOGIN_MAX_FAILS   = 5;
const LOGIN_WINDOW      = 900; // 15 min
$throttleDir  = dirname(__DIR__) . '/data';
$throttleFile = $throttleDir . '/login-attempts.json';
if (!is_dir($throttleDir)) { @mkdir($throttleDir, 0755, true); }
$loginIp  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$attempts = file_exists($throttleFile) ? (json_decode(@file_get_contents($throttleFile), true) ?: []) : [];
$nowTs    = time();
// Drop expired records.
foreach ($attempts as $k => $v) { if (($nowTs - ($v['first'] ?? 0)) > LOGIN_WINDOW) unset($attempts[$k]); }
$rec    = $attempts[$loginIp] ?? ['count' => 0, 'first' => $nowTs];
$locked = $rec['count'] >= LOGIN_MAX_FAILS && ($nowTs - $rec['first']) <= LOGIN_WINDOW;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($locked) {
        $error = 'Too many attempts. Please wait 15 minutes and try again.';
    } else {
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        if (admin_login($u, $p)) {
            unset($attempts[$loginIp]);
            @file_put_contents($throttleFile, json_encode($attempts), LOCK_EX);
            header('Location: index.php');
            exit;
        }
        // Record the failure.
        $rec['count']++;
        $attempts[$loginIp] = $rec;
        @file_put_contents($throttleFile, json_encode($attempts), LOCK_EX);
        $error = 'Invalid username or password.';
        sleep(1); // soft per-request delay on top of the lockout
    }
}

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login — RootNivesh</title>
<link rel="stylesheet" href="admin.css?v=4">
</head>
<body class="admin-body">
  <div class="admin-login-card">
    <h1 style="margin:0 0 8px">RootNivesh Admin</h1>
    <p style="color:#8A9BB0; font-size:13px; margin:0 0 20px">Sign in to post and manage daily calls.</p>
    <?php if ($error): ?>
      <div class="admin-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
      <label class="admin-label">Username
        <input type="text" name="username" required autocomplete="username" autofocus>
      </label>
      <label class="admin-label">Password
        <input type="password" name="password" required autocomplete="current-password">
      </label>
      <button type="submit" class="admin-btn">Sign in</button>
    </form>
  </div>
</body>
</html>
