<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

$pdo  = db();
$flash = '';

// Every state-changing POST must carry a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        $flash = 'Security check failed (CSRF). Please reload the page and try again.';
        $_SERVER['REQUEST_METHOD'] = 'GET'; // skip the handlers below, just re-render
    }
}

/* ---------- Handle POST: add new call ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO calls (call_type, action, symbol, company_name, entry_price, target_price, stop_loss, thesis, is_public)
             VALUES (:call_type, :action, :symbol, :company_name, :entry, :target, :stop, :thesis, :is_public)'
        );
        $stmt->execute([
            ':call_type'    => $_POST['call_type'] ?? 'intraday',
            ':action'       => $_POST['call_action'] ?? 'BUY',
            ':symbol'       => strtoupper(trim($_POST['symbol'] ?? '')),
            ':company_name' => trim($_POST['company_name'] ?? '') ?: null,
            ':entry'        => floatval($_POST['entry_price'] ?? 0),
            ':target'       => $_POST['target_price'] !== '' ? floatval($_POST['target_price']) : null,
            ':stop'         => $_POST['stop_loss']    !== '' ? floatval($_POST['stop_loss'])    : null,
            ':thesis'       => trim($_POST['thesis'] ?? ''),
            ':is_public'    => isset($_POST['is_public']) ? 1 : 0,
        ]);
        $flash = 'Call posted successfully (#' . $pdo->lastInsertId() . ').';
    } catch (PDOException $e) {
        error_log('admin add call failed: ' . $e->getMessage());
        $flash = 'Could not post the call. Please try again.';
    }
}

/* ---------- Handle POST: close / mark a call ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    try {
        $allowedStatus = ['open','target_hit','stop_hit','closed','cancelled'];
        $status = in_array($_POST['status'] ?? '', $allowedStatus, true) ? $_POST['status'] : 'open';
        $stmt = $pdo->prepare(
            'UPDATE calls SET status = :status, exit_price = :exit, exit_at = :exit_at, pnl_pct = :pnl
             WHERE id = :id'
        );
        $exit = $_POST['exit_price'] !== '' ? floatval($_POST['exit_price']) : null;
        // Auto-compute PnL% from entry vs exit when both known.
        $pnl = null;
        if ($exit !== null) {
            $row = $pdo->prepare('SELECT action, entry_price FROM calls WHERE id = :id');
            $row->execute([':id' => intval($_POST['id'])]);
            $r = $row->fetch();
            if ($r && $r['entry_price'] > 0) {
                $pnl = $r['action'] === 'BUY'
                    ? round(($exit - $r['entry_price']) / $r['entry_price'] * 100, 2)
                    : round(($r['entry_price'] - $exit) / $r['entry_price'] * 100, 2);
            }
        }
        $stmt->execute([
            ':status'  => $status,
            ':exit'    => $exit,
            ':exit_at' => $exit !== null ? date('Y-m-d H:i:s') : null,
            ':pnl'     => $pnl,
            ':id'      => intval($_POST['id']),
        ]);
        $flash = 'Call updated.';
    } catch (PDOException $e) {
        error_log('admin update_status failed: ' . $e->getMessage());
        $flash = 'Could not update the call. Please try again.';
    }
}

/* ---------- Handle POST: mark as shared to WhatsApp ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_shared') {
    $pdo->prepare('UPDATE calls SET shared_to_wa_at = NOW() WHERE id = :id')
        ->execute([':id' => intval($_POST['id'])]);
    $flash = 'Marked as shared.';
}

/* ---------- Fetch recent calls ---------- */
$calls = $pdo->query('SELECT * FROM calls ORDER BY posted_at DESC LIMIT 50')->fetchAll();

/* ---------- Build a pre-filled WhatsApp share message for a call ---------- */
function build_wa_message($c) {
    $arrow = $c['action'] === 'BUY' ? '🟢' : '🔴';
    $msg  = $arrow . ' *' . strtoupper($c['call_type']) . " CALL*\n\n";
    $msg .= '*' . $c['action'] . '*: ' . $c['symbol'];
    if (!empty($c['company_name'])) $msg .= ' (' . $c['company_name'] . ')';
    $msg .= "\n";
    $msg .= 'Entry: ₹' . number_format(floatval($c['entry_price']), 2) . "\n";
    if ($c['target_price'] !== null) $msg .= 'Target: ₹' . number_format(floatval($c['target_price']), 2) . "\n";
    if ($c['stop_loss']    !== null) $msg .= 'Stop-Loss: ₹' . number_format(floatval($c['stop_loss']), 2) . "\n";
    if (!empty($c['thesis']))        $msg .= "\nThesis: " . $c['thesis'] . "\n";
    $msg .= "\n— RootNivesh Research\nSEBI Reg. No. " . SEBI_REG;
    $msg .= "\n*Investments are subject to market risk. Past performance is not indicative of future results.*";
    return $msg;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>RootNivesh Admin — Calls</title>
<link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
  <header class="admin-header">
    <h1>RootNivesh Admin</h1>
    <a href="logout.php" class="admin-link">Logout</a>
  </header>

  <main class="admin-main">

    <?php if ($flash): ?>
      <div class="admin-flash"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <!-- ===== ADD NEW CALL ===== -->
    <section class="admin-card">
      <h2>Post a new call</h2>
      <form method="post" class="admin-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <div class="admin-row">
          <label>Type
            <select name="call_type">
              <option value="intraday">Intraday</option>
              <option value="swing">Swing</option>
              <option value="positional">Positional</option>
              <option value="longterm">Long-term</option>
              <option value="fno">F&amp;O</option>
            </select>
          </label>
          <label>Action
            <select name="call_action">
              <option value="BUY">BUY</option>
              <option value="SELL">SELL</option>
            </select>
          </label>
          <label>Symbol *
            <input type="text" name="symbol" required placeholder="e.g. RELIANCE" style="text-transform:uppercase">
          </label>
        </div>
        <div class="admin-row">
          <label>Company name
            <input type="text" name="company_name" placeholder="Optional, e.g. Reliance Industries Ltd">
          </label>
          <label>Entry *
            <input type="number" step="0.01" name="entry_price" required>
          </label>
        </div>
        <div class="admin-row">
          <label>Target
            <input type="number" step="0.01" name="target_price">
          </label>
          <label>Stop-Loss
            <input type="number" step="0.01" name="stop_loss">
          </label>
          <label class="admin-check">
            <input type="checkbox" name="is_public" checked>
            <span>Show on public site</span>
          </label>
        </div>
        <label>Thesis
          <textarea name="thesis" rows="3" placeholder="Why this call — research thesis. Will be included in the WhatsApp share."></textarea>
        </label>
        <button type="submit" class="admin-btn">Post call</button>
      </form>
    </section>

    <!-- ===== RECENT CALLS ===== -->
    <section class="admin-card">
      <h2>Recent calls (<?php echo count($calls); ?>)</h2>
      <?php if (empty($calls)): ?>
        <p style="color:#8A9BB0">No calls posted yet. Add your first call above.</p>
      <?php else: foreach ($calls as $c):
        $waMsg = build_wa_message($c);
        $waUrl = 'https://wa.me/' . WA_NUMBER . '?text=' . rawurlencode($waMsg);
        $statusClass = $c['status'] === 'open' ? 'open' : ($c['status'] === 'target_hit' ? 'win' : ($c['status'] === 'stop_hit' ? 'loss' : 'closed'));
      ?>
        <div class="admin-call <?php echo $statusClass; ?>">
          <div class="admin-call-head">
            <span class="admin-call-action <?php echo strtolower($c['action']); ?>"><?php echo htmlspecialchars($c['action']); ?></span>
            <span class="admin-call-symbol"><?php echo htmlspecialchars($c['symbol']); ?></span>
            <span class="admin-call-type"><?php echo strtoupper(htmlspecialchars($c['call_type'])); ?></span>
            <span class="admin-call-status status-<?php echo $statusClass; ?>"><?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $c['status']))); ?></span>
            <span class="admin-call-date"><?php echo date('d M Y, H:i', strtotime($c['posted_at'])); ?></span>
          </div>
          <div class="admin-call-prices">
            Entry ₹<?php echo number_format($c['entry_price'], 2); ?>
            <?php if ($c['target_price'] !== null): ?>· Target ₹<?php echo number_format($c['target_price'], 2); ?><?php endif; ?>
            <?php if ($c['stop_loss']    !== null): ?>· SL ₹<?php echo number_format($c['stop_loss'], 2); ?><?php endif; ?>
            <?php if ($c['exit_price']   !== null): ?>· Exit ₹<?php echo number_format($c['exit_price'], 2); ?><?php endif; ?>
            <?php if ($c['pnl_pct']      !== null): ?>· PnL <strong><?php echo ($c['pnl_pct'] >= 0 ? '+' : '') . number_format($c['pnl_pct'], 2); ?>%</strong><?php endif; ?>
          </div>
          <?php if (!empty($c['thesis'])): ?>
            <div class="admin-call-thesis"><?php echo nl2br(htmlspecialchars($c['thesis'])); ?></div>
          <?php endif; ?>
          <div class="admin-call-actions">
            <a class="admin-btn admin-btn-wa" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($waUrl); ?>"
               onclick="markShared(<?php echo $c['id']; ?>)">
              📤 Share to WhatsApp
            </a>
            <?php if (!empty($c['shared_to_wa_at'])): ?>
              <span class="admin-call-shared">✓ Shared <?php echo date('d M, H:i', strtotime($c['shared_to_wa_at'])); ?></span>
            <?php endif; ?>

            <details style="display:inline-block; margin-left:8px">
              <summary class="admin-btn admin-btn-secondary">Update status</summary>
              <form method="post" class="admin-inline-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                <select name="status">
                  <option value="open"        <?php if ($c['status']==='open')        echo 'selected'; ?>>Open</option>
                  <option value="target_hit"  <?php if ($c['status']==='target_hit')  echo 'selected'; ?>>Target hit</option>
                  <option value="stop_hit"    <?php if ($c['status']==='stop_hit')    echo 'selected'; ?>>Stop hit</option>
                  <option value="closed"      <?php if ($c['status']==='closed')      echo 'selected'; ?>>Closed</option>
                  <option value="cancelled"   <?php if ($c['status']==='cancelled')   echo 'selected'; ?>>Cancelled</option>
                </select>
                <input type="number" step="0.01" name="exit_price" placeholder="Exit ₹" value="<?php echo $c['exit_price'] !== null ? htmlspecialchars($c['exit_price']) : ''; ?>">
                <button type="submit" class="admin-btn admin-btn-secondary">Save</button>
              </form>
            </details>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </section>

  </main>

  <form id="markSharedForm" method="post" style="display:none">
    <input type="hidden" name="action" value="mark_shared">
    <input type="hidden" name="id" id="markSharedId">
  </form>

  <script>
    const CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
    // When admin clicks Share-to-WA, fire-and-forget mark_shared in the background.
    function markShared(id) {
      const fd = new FormData();
      fd.append('action', 'mark_shared');
      fd.append('id', id);
      fd.append('csrf', CSRF_TOKEN);
      // Don't block the WhatsApp window from opening — just send.
      fetch(window.location.href, { method: 'POST', body: fd, credentials: 'same-origin' })
        .catch(() => {});
    }
  </script>
</body>
</html>
