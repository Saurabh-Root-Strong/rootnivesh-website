<?php
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (admin_login($u, $p)) {
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid username or password.';
    sleep(1); // soft brute-force throttle
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
<link rel="stylesheet" href="admin.css">
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
