<?php
/* admin/_sidebar.php — left navigation pane shared by all admin pages.
   Set $ADMIN_PAGE ('calls' | 'blog' | 'team') before including this file. */
$active = $ADMIN_PAGE ?? '';
// Count of unhandled price-watch alerts, for the nav badge. Guarded so the
// sidebar still renders on installs that haven't run the call_alerts migration.
$alertCount = 0;
try { $alertCount = (int) db()->query("SELECT COUNT(*) FROM call_alerts WHERE status='new'")->fetchColumn(); }
catch (Throwable $e) { $alertCount = 0; }
?>
<aside class="admin-sidebar">
  <div class="admin-brand">RootNivesh <span>Admin</span></div>
  <nav class="admin-nav">
    <a href="index.php" class="admin-nav-link<?php echo $active === 'calls' ? ' active' : ''; ?>"><span>📈</span> Calls</a>
    <a href="alerts.php" class="admin-nav-link<?php echo $active === 'alerts' ? ' active' : ''; ?>"><span>🔔</span> Alerts<?php if ($alertCount): ?> <span id="alert-badge" class="admin-nav-badge"><?php echo $alertCount; ?></span><?php else: ?> <span id="alert-badge" class="admin-nav-badge" style="display:none"></span><?php endif; ?></a>
    <a href="blog.php"  class="admin-nav-link<?php echo $active === 'blog'  ? ' active' : ''; ?>"><span>📝</span> Blog</a>
    <?php if (admin_is_owner()): ?>
      <a href="users.php" class="admin-nav-link<?php echo $active === 'team' ? ' active' : ''; ?>"><span>👥</span> Team</a>
    <?php endif; ?>
  </nav>
  <div class="admin-side-foot">
    <div class="admin-side-user">
      <?php echo htmlspecialchars(admin_display()); ?>
      <span><?php echo htmlspecialchars(admin_role()); ?></span>
    </div>
    <a href="logout.php" class="admin-nav-link"><span>🚪</span> Logout</a>
  </div>
</aside>
<script>
  // Live-refresh the Alerts badge every 45s so a new price-watch alert shows
  // up without a manual reload.
  (function () {
    var badge = document.getElementById('alert-badge');
    if (!badge) return;
    function poll() {
      fetch('alerts_feed.php', { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d) return;
          if (d.new > 0) { badge.textContent = d.new; badge.style.display = ''; }
          else { badge.style.display = 'none'; }
        })
        .catch(function () {});
    }
    setInterval(poll, 45000);
  })();
</script>
