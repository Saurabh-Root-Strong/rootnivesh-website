<?php
/* admin/_sidebar.php — left navigation pane shared by all admin pages.
   Set $ADMIN_PAGE ('calls' | 'blog' | 'team') before including this file. */
$active = $ADMIN_PAGE ?? '';
?>
<aside class="admin-sidebar">
  <div class="admin-brand">RootNivesh <span>Admin</span></div>
  <nav class="admin-nav">
    <a href="index.php" class="admin-nav-link<?php echo $active === 'calls' ? ' active' : ''; ?>"><span>📈</span> Calls</a>
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
