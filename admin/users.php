<?php
require_once __DIR__ . '/auth.php';
admin_require_owner();           // owner role only

$pdo   = db();
$flash = '';
$err   = '';

// CSRF gate for every state change.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        $err = 'Security check failed (CSRF). Reload and try again.';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
}

/* ---------- Add a team member ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $display  = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = in_array($_POST['role'] ?? '', ['owner','analyst'], true) ? $_POST['role'] : 'analyst';

    if (!preg_match('/^[a-z0-9_.]{3,50}$/', $username)) {
        $err = 'Username must be 3–50 chars: lowercase letters, numbers, dot or underscore.';
    } elseif (strlen($password) < 8) {
        $err = 'Password must be at least 8 characters.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, pass_hash, display_name, role)
                 VALUES (:u, :h, :d, :r)'
            );
            $stmt->execute([
                ':u' => $username,
                ':h' => password_hash($password, PASSWORD_BCRYPT),
                ':d' => $display ?: null,
                ':r' => $role,
            ]);
            $flash = 'User "' . $username . '" created.';
        } catch (PDOException $e) {
            // 23000 = duplicate key (username already taken)
            $err = ($e->getCode() === '23000')
                 ? 'That username is already taken.'
                 : 'Could not create the user. Please try again.';
            if ($e->getCode() !== '23000') error_log('admin add_user failed: ' . $e->getMessage());
        }
    }
}

/* ---------- Toggle active / reset role ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    try {
        $id = intval($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = :id')
            ->execute([':id' => $id]);
        $flash = 'User access updated.';
    } catch (PDOException $e) { error_log('toggle_active failed: ' . $e->getMessage()); $err = 'Could not update user.'; }
}

/* ---------- Reset a user's password ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_pw') {
    $id  = intval($_POST['id'] ?? 0);
    $pw  = $_POST['new_password'] ?? '';
    if (strlen($pw) < 8) {
        $err = 'New password must be at least 8 characters.';
    } else {
        try {
            $pdo->prepare('UPDATE users SET pass_hash = :h WHERE id = :id')
                ->execute([':h' => password_hash($pw, PASSWORD_BCRYPT), ':id' => $id]);
            $flash = 'Password reset.';
        } catch (PDOException $e) { error_log('reset_pw failed: ' . $e->getMessage()); $err = 'Could not reset password.'; }
    }
}

$users = $pdo->query('SELECT id, username, display_name, role, is_active, created_at, last_login_at
                      FROM users ORDER BY created_at DESC')->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>RootNivesh Admin — Team</title>
<link rel="stylesheet" href="admin.css?v=3">
</head>
<body class="admin-body">
<div class="admin-layout">
  <?php $ADMIN_PAGE = 'team'; include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-content">
  <div class="admin-topbar"><h1>Team</h1></div>

  <main class="admin-main">
    <?php if ($flash): ?><div class="admin-flash"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
    <?php if ($err):   ?><div class="admin-error"><?php echo htmlspecialchars($err);   ?></div><?php endif; ?>

    <section class="admin-card">
      <h2>Add a team member</h2>
      <form method="post" class="admin-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_user">
        <div class="admin-row">
          <label>Username *
            <input type="text" name="username" required placeholder="e.g. rahul" autocomplete="off" style="text-transform:lowercase">
          </label>
          <label>Display name
            <input type="text" name="display_name" placeholder="e.g. Rahul Sharma" autocomplete="off">
          </label>
        </div>
        <div class="admin-row">
          <label>Password *
            <input type="password" name="password" required minlength="8" placeholder="min 8 characters" autocomplete="new-password">
          </label>
          <label>Role
            <select name="role">
              <option value="analyst">Analyst (post &amp; manage calls)</option>
              <option value="owner">Owner (also manage team)</option>
            </select>
          </label>
        </div>
        <button type="submit" class="admin-btn">Create user</button>
      </form>
    </section>

    <section class="admin-card">
      <h2>Team (<?php echo count($users); ?>)</h2>
      <?php if (empty($users)): ?>
        <p style="color:#8A9BB0">No team accounts yet. You're signed in as the bootstrap owner from config.php.</p>
      <?php else: foreach ($users as $u): ?>
        <div class="admin-call <?php echo $u['is_active'] ? 'open' : 'closed'; ?>">
          <div class="admin-call-head">
            <span class="admin-call-symbol"><?php echo htmlspecialchars($u['username']); ?></span>
            <?php if (!empty($u['display_name'])): ?><span class="admin-call-type"><?php echo htmlspecialchars($u['display_name']); ?></span><?php endif; ?>
            <span class="admin-call-status status-<?php echo $u['role']==='owner'?'win':'open'; ?>"><?php echo htmlspecialchars(strtoupper($u['role'])); ?></span>
            <span class="admin-call-status status-<?php echo $u['is_active']?'open':'loss'; ?>"><?php echo $u['is_active'] ? 'ACTIVE' : 'DISABLED'; ?></span>
            <span class="admin-call-date">
              <?php echo $u['last_login_at'] ? 'Last login ' . date('d M, H:i', strtotime($u['last_login_at'])) : 'Never logged in'; ?>
            </span>
          </div>
          <div class="admin-call-actions">
            <form method="post" style="display:inline">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
              <button type="submit" class="admin-btn admin-btn-secondary"><?php echo $u['is_active'] ? 'Disable' : 'Enable'; ?></button>
            </form>
            <details style="display:inline-block; margin-left:8px">
              <summary class="admin-btn admin-btn-secondary">Reset password</summary>
              <form method="post" class="admin-inline-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reset_pw">
                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                <input type="password" name="new_password" minlength="8" placeholder="New password (min 8)" required autocomplete="new-password">
                <button type="submit" class="admin-btn admin-btn-secondary">Save</button>
              </form>
            </details>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </section>
  </main>
  </div><!-- /.admin-content -->
</div><!-- /.admin-layout -->
</body>
</html>
