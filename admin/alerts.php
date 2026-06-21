<?php
/* admin/alerts.php — price-watch Alerts inbox.

   Shows every alert the monitor engine raised (target hit / stop hit) for
   open calls. The on-duty person taps "Send to WhatsApp" (one tap, opens
   WhatsApp with the message pre-filled), then confirms the call's progress
   over in the Calls tab. Alerts can be dismissed once handled. */

require_once __DIR__ . '/auth.php';
admin_require_login();

$pdo  = db();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        $flash = 'Security check failed (CSRF). Reload and try again.';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
}

/* ---- POST: mark an alert as sent to WhatsApp ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'alert_sent') {
    $pdo->prepare("UPDATE call_alerts SET status='sent', sent_at=NOW(), handled_by=:u WHERE id=:id")
        ->execute([':u' => admin_user() ?: null, ':id' => intval($_POST['id'])]);
    $flash = 'Marked as sent.';
}

/* ---- POST: dismiss an alert ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'alert_dismiss') {
    $pdo->prepare("UPDATE call_alerts SET status='dismissed', handled_by=:u WHERE id=:id")
        ->execute([':u' => admin_user() ?: null, ':id' => intval($_POST['id'])]);
    $flash = 'Alert dismissed.';
}

/* ---- POST: dismiss every still-new alert ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'alert_dismiss_all') {
    $pdo->prepare("UPDATE call_alerts SET status='dismissed', handled_by=:u WHERE status='new'")
        ->execute([':u' => admin_user() ?: null]);
    $flash = 'Cleared all new alerts.';
}

/* ---- which bucket to show: new (default) | all ---- */
$view = ($_GET['view'] ?? 'new') === 'all' ? 'all' : 'new';
$sql = "SELECT a.*, c.company_name, c.call_type, c.status AS call_status
        FROM call_alerts a LEFT JOIN calls c ON c.id = a.call_id";
if ($view === 'new') $sql .= " WHERE a.status = 'new'";
$sql .= " ORDER BY a.created_at DESC LIMIT 200";
$alerts = $pdo->query($sql)->fetchAll();

$newCount = (int) $pdo->query("SELECT COUNT(*) FROM call_alerts WHERE status='new'")->fetchColumn();

function ordinal_lbl($n) {
    $suf = ['th','st','nd','rd'];
    $v = $n % 100;
    return $n . ($suf[($v - 20) % 10] ?? $suf[$v] ?? $suf[0]);
}

/* Build the WhatsApp broadcast text for one alert. */
function alert_wa_message($a) {
    $sym  = $a['symbol'];
    $side = $a['action'];
    $ltp  = number_format(floatval($a['trigger_price']), 2);
    $pnl  = $a['pnl_pct'] !== null ? (($a['pnl_pct'] >= 0 ? '+' : '') . $a['pnl_pct'] . '%') : '';
    if ($a['kind'] === 'target_hit') {
        $lvl = number_format(floatval($a['level_price']), 2);
        $msg = "🎯 *TARGET HIT* — {$sym} ({$side})\n"
             . ordinal_lbl((int) $a['level_index']) . " Target ₹{$lvl} achieved.\n"
             . "LTP ₹{$ltp}" . ($pnl !== '' ? " ({$pnl} from entry)" : '') . "\n";
    } else {
        $lvl = number_format(floatval($a['level_price']), 2);
        $msg = "🛑 *STOP-LOSS HIT* — {$sym} ({$side})\n"
             . "Stop ₹{$lvl} triggered.\n"
             . "LTP ₹{$ltp}" . ($pnl !== '' ? " ({$pnl})" : '') . "\n";
    }
    $msg .= "\n— RootNivesh Research\nSEBI Reg. No. " . (defined('SEBI_REG') ? SEBI_REG : '');
    $msg .= "\n*Investments are subject to market risk. Past performance is not indicative of future results.*";
    return $msg;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>RootNivesh Admin — Alerts</title>
<link rel="stylesheet" href="admin.css?v=5">
</head>
<body class="admin-body">
<div class="admin-layout">
  <?php $ADMIN_PAGE = 'alerts'; include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-content">
  <div class="admin-topbar"><h1>Alerts</h1></div>

  <main class="admin-main">

    <?php if ($flash): ?>
      <div class="admin-flash"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <section class="admin-card">
      <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; justify-content:space-between">
        <div>
          <h2 style="margin:0">Price-watch alerts</h2>
          <p style="color:#8A9BB0; font-size:13px; margin:6px 0 0">
            Auto-raised when an open call hits a target or its stop. Tap
            <strong>Send to WhatsApp</strong>, pick your group, send — then mark
            the call's progress in <a href="index.php" style="color:#C9A84C">Calls</a>.
          </p>
        </div>
        <div style="display:flex; gap:8px; align-items:center">
          <a href="alerts.php?view=new"  class="admin-btn<?php echo $view==='new'?'':' admin-btn-ghost'; ?>">New <?php echo $newCount ? '('.$newCount.')' : ''; ?></a>
          <a href="alerts.php?view=all"  class="admin-btn<?php echo $view==='all'?'':' admin-btn-ghost'; ?>">All</a>
          <?php if ($newCount): ?>
            <form method="post" style="margin:0" onsubmit="return confirm('Dismiss all new alerts?')">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="alert_dismiss_all">
              <button type="submit" class="admin-btn admin-btn-ghost">Clear all</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <?php if (!$alerts): ?>
      <section class="admin-card">
        <p style="color:#8A9BB0; margin:0">
          <?php echo $view === 'new' ? 'No new alerts. All quiet. 🟢' : 'No alerts yet.'; ?>
        </p>
      </section>
    <?php else: ?>
      <?php foreach ($alerts as $a):
        $isTarget = $a['kind'] === 'target_hit';
        $accent   = $isTarget ? '#2BB673' : '#E05656';
        $icon     = $isTarget ? '🎯' : '🛑';
        $headline = $isTarget
          ? (ordinal_lbl((int)$a['level_index']) . ' Target Hit')
          : 'Stop-Loss Hit';
        $waMsg = alert_wa_message($a);
        $waUrl = 'https://wa.me/' . (defined('WA_NUMBER') ? WA_NUMBER : '') . '?text=' . rawurlencode($waMsg);
        $pnl   = $a['pnl_pct'];
      ?>
        <section class="admin-card" style="border-left:4px solid <?php echo $accent; ?>; opacity:<?php echo $a['status']==='new'?'1':'0.62'; ?>">
          <div style="display:flex; gap:14px; align-items:flex-start; flex-wrap:wrap; justify-content:space-between">
            <div style="min-width:0">
              <div style="font-size:16px; font-weight:700; color:#E8EEF5">
                <?php echo $icon; ?> <?php echo htmlspecialchars($a['symbol']); ?>
                <span style="font-size:12px; font-weight:600; color:<?php echo $a['action']==='BUY'?'#2BB673':'#E05656'; ?>"><?php echo htmlspecialchars($a['action']); ?></span>
                <span style="font-size:12px; color:#8A9BB0; font-weight:500">· <?php echo strtoupper(htmlspecialchars($a['call_type'] ?? '')); ?></span>
              </div>
              <div style="margin-top:6px; color:#C7D2DE; font-size:14px">
                <strong style="color:<?php echo $accent; ?>"><?php echo $headline; ?></strong>
                — level ₹<?php echo number_format(floatval($a['level_price']), 2); ?>,
                LTP ₹<?php echo number_format(floatval($a['trigger_price']), 2); ?>
                <?php if ($pnl !== null): ?>
                  <span style="color:<?php echo $pnl>=0?'#2BB673':'#E05656'; ?>; font-weight:600">(<?php echo ($pnl>=0?'+':'').$pnl; ?>%)</span>
                <?php endif; ?>
              </div>
              <div style="margin-top:4px; color:#7C8B9C; font-size:12px">
                Entry ₹<?php echo number_format(floatval($a['entry_price']), 2); ?>
                · <?php echo date('d M, H:i', strtotime($a['created_at'])); ?>
                <?php if ($a['status'] === 'sent'): ?>
                  · <span style="color:#2BB673">✓ Sent<?php echo $a['handled_by'] ? ' by '.htmlspecialchars($a['handled_by']) : ''; ?></span>
                <?php elseif ($a['status'] === 'dismissed'): ?>
                  · <span style="color:#7C8B9C">Dismissed</span>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap">
              <a href="<?php echo htmlspecialchars($waUrl, ENT_QUOTES); ?>" target="_blank" rel="noopener"
                 class="admin-btn" style="background:#25D366; border-color:#25D366; color:#06281A"
                 onclick="markSent(<?php echo (int)$a['id']; ?>)">📤 Send to WhatsApp</a>
              <?php if ($a['status'] !== 'dismissed'): ?>
                <form method="post" style="margin:0">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="alert_dismiss">
                  <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                  <button type="submit" class="admin-btn admin-btn-ghost">Dismiss</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
          <!-- Hidden form so opening WhatsApp also stamps the alert as sent. -->
          <form method="post" id="sentform-<?php echo (int)$a['id']; ?>" style="display:none">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="alert_sent">
            <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
          </form>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>

  </main>
  </div>
</div>
<script>
  // When the team taps "Send to WhatsApp", also stamp the alert as sent so it
  // leaves the "New" bucket. The WhatsApp link still opens in its new tab.
  function markSent(id) {
    var f = document.getElementById('sentform-' + id);
    if (f) { setTimeout(function () { f.submit(); }, 200); }
  }
</script>
</body>
</html>
