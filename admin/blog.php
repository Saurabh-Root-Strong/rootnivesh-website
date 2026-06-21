<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

$pdo   = db();
$flash = '';

/* Slugify a title into a URL-safe, unique slug. */
function blog_slugify($s) {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? substr($s, 0, 180) : 'post';
}
function blog_unique_slug($pdo, $slug, $ignoreId = 0) {
    $base = $slug; $i = 2;
    while (true) {
        $st = $pdo->prepare('SELECT id FROM posts WHERE slug = :s AND id <> :id LIMIT 1');
        $st->execute([':s' => $slug, ':id' => $ignoreId]);
        if (!$st->fetch()) return $slug;
        $slug = $base . '-' . $i++;
    }
}
/* Rough read-time from the body word count (≈200 wpm). */
function blog_read_minutes($body) {
    $words = str_word_count(strip_tags((string) $body));
    return max(1, (int) round($words / 200));
}

// CSRF gate on every POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        $flash = 'Security check failed (CSRF). Reload and try again.';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
}

/* ---------- Create / update a post ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['add', 'edit'], true)) {
    try {
        $id       = intval($_POST['id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'markets';
        $excerpt  = trim($_POST['excerpt'] ?? '');
        $cover    = trim($_POST['cover_image'] ?? '');
        $body     = trim($_POST['body'] ?? '');
        $status   = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
        $allowedCats = ['education', 'strategy', 'markets', 'quant'];
        if (!in_array($category, $allowedCats, true)) $category = 'markets';

        if ($title === '') { throw new RuntimeException('Title is required.'); }

        $slug = trim($_POST['slug'] ?? '');
        $slug = $slug !== '' ? blog_slugify($slug) : blog_slugify($title);
        $slug = blog_unique_slug($pdo, $slug, $id);

        $readMin = ($_POST['read_minutes'] ?? '') !== ''
                 ? max(1, intval($_POST['read_minutes'])) : blog_read_minutes($body);

        if ($id > 0) {
            $pdo->prepare(
                'UPDATE posts SET slug=:slug, title=:title, category=:category, excerpt=:excerpt,
                        cover_image=:cover, body=:body, read_minutes=:rm, status=:status
                 WHERE id=:id'
            )->execute([
                ':slug' => $slug, ':title' => $title, ':category' => $category,
                ':excerpt' => $excerpt ?: null, ':cover' => $cover ?: null, ':body' => $body,
                ':rm' => $readMin, ':status' => $status, ':id' => $id,
            ]);
            $flash = 'Post updated (#' . $id . ').';
        } else {
            $pdo->prepare(
                'INSERT INTO posts (slug, title, category, excerpt, cover_image, body, author, read_minutes, status)
                 VALUES (:slug, :title, :category, :excerpt, :cover, :body, :author, :rm, :status)'
            )->execute([
                ':slug' => $slug, ':title' => $title, ':category' => $category,
                ':excerpt' => $excerpt ?: null, ':cover' => $cover ?: null, ':body' => $body,
                ':author' => admin_display() ?: admin_user(), ':rm' => $readMin, ':status' => $status,
            ]);
            $flash = 'Post published (#' . $pdo->lastInsertId() . ').';
        }
    } catch (Throwable $e) {
        error_log('admin blog save failed: ' . $e->getMessage());
        $flash = 'Could not save the post: ' . htmlspecialchars($e->getMessage());
    }
}

/* ---------- Delete a post ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pdo->prepare('DELETE FROM posts WHERE id = :id')->execute([':id' => intval($_POST['id'])]);
    $flash = 'Post deleted.';
}

/* ---------- Load a post for editing ---------- */
$editing = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM posts WHERE id = :id');
    $st->execute([':id' => intval($_GET['edit'])]);
    $editing = $st->fetch() ?: null;
}

$posts = $pdo->query('SELECT id, title, slug, category, status, published_at FROM posts ORDER BY published_at DESC LIMIT 100')->fetchAll();
$catLabels = ['education' => 'Education', 'strategy' => 'Strategy', 'markets' => 'Markets', 'quant' => 'Quant Research'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>RootNivesh Admin — Blog</title>
<link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
  <header class="admin-header">
    <h1>RootNivesh Admin — Blog</h1>
    <span class="admin-link">
      Signed in as <strong><?php echo htmlspecialchars(admin_display()); ?></strong> (<?php echo htmlspecialchars(admin_role()); ?>)
      &nbsp;·&nbsp; <a href="index.php" class="admin-link">Calls</a>
      <?php if (admin_is_owner()): ?>&nbsp;·&nbsp; <a href="users.php" class="admin-link">Team</a><?php endif; ?>
      &nbsp;·&nbsp; <a href="logout.php" class="admin-link">Logout</a>
    </span>
  </header>

  <main class="admin-main">

    <?php if ($flash): ?>
      <div class="admin-flash"><?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- ===== WRITE / EDIT A POST ===== -->
    <section class="admin-card">
      <h2><?php echo $editing ? 'Edit post #' . intval($editing['id']) : 'Write a new post'; ?></h2>
      <p style="color:#8A9BB0; font-size:13px; margin:0 0 12px">
        Formatting: start a line with <strong>##</strong> for a section heading (these build the
        “In this article” menu), leave a blank line between paragraphs, and wrap text in
        <strong>**double asterisks**</strong> for bold. No HTML needed.
      </p>
      <form method="post" class="admin-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $editing ? 'edit' : 'add'; ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo intval($editing['id']); ?>"><?php endif; ?>

        <label>Title *
          <input type="text" name="title" required placeholder="e.g. How to Read an IPO Prospectus"
                 value="<?php echo $editing ? htmlspecialchars($editing['title']) : ''; ?>">
        </label>

        <div class="admin-row">
          <label>Category
            <select name="category">
              <?php foreach ($catLabels as $k => $v): ?>
                <option value="<?php echo $k; ?>" <?php echo ($editing && $editing['category'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Read time (min) — auto if blank
            <input type="number" name="read_minutes" min="1" placeholder="auto"
                   value="<?php echo $editing && $editing['read_minutes'] ? intval($editing['read_minutes']) : ''; ?>">
          </label>
          <label>Status
            <select name="status">
              <option value="published" <?php echo (!$editing || $editing['status'] === 'published') ? 'selected' : ''; ?>>Published (live)</option>
              <option value="draft" <?php echo ($editing && $editing['status'] === 'draft') ? 'selected' : ''; ?>>Draft (hidden)</option>
            </select>
          </label>
        </div>

        <label>Cover image URL
          <input type="url" name="cover_image" placeholder="https://… (paste an image link)"
                 value="<?php echo $editing ? htmlspecialchars($editing['cover_image']) : ''; ?>">
        </label>

        <label>Short summary (shown on the blog card)
          <textarea name="excerpt" rows="2" placeholder="One or two sentences that tease the article."><?php echo $editing ? htmlspecialchars($editing['excerpt']) : ''; ?></textarea>
        </label>

        <label>Article body
          <textarea name="body" rows="16" placeholder="## Introduction&#10;&#10;Write your paragraph here…&#10;&#10;## Next Section&#10;&#10;More text. Use **bold** for emphasis."><?php echo $editing ? htmlspecialchars($editing['body']) : ''; ?></textarea>
        </label>

        <div style="display:flex; gap:10px; align-items:center">
          <button type="submit" class="admin-btn"><?php echo $editing ? 'Save changes' : 'Publish post'; ?></button>
          <?php if ($editing): ?><a href="blog.php" class="admin-btn admin-btn-secondary">Cancel edit</a><?php endif; ?>
        </div>
      </form>
    </section>

    <!-- ===== EXISTING POSTS ===== -->
    <section class="admin-card">
      <h2>Posts (<?php echo count($posts); ?>)</h2>
      <?php if (empty($posts)): ?>
        <p style="color:#8A9BB0">No posts yet. Write your first one above.</p>
      <?php else: foreach ($posts as $p): ?>
        <div class="admin-call <?php echo $p['status'] === 'published' ? 'open' : 'closed'; ?>">
          <div class="admin-call-head">
            <span class="admin-call-symbol"><?php echo htmlspecialchars($p['title']); ?></span>
            <span class="admin-call-type"><?php echo htmlspecialchars(strtoupper($catLabels[$p['category']] ?? $p['category'])); ?></span>
            <span class="admin-call-status status-<?php echo $p['status'] === 'published' ? 'open' : 'closed'; ?>"><?php echo htmlspecialchars(strtoupper($p['status'])); ?></span>
            <span class="admin-call-date"><?php echo date('d M Y', strtotime($p['published_at'])); ?></span>
          </div>
          <div class="admin-call-prices">/blog/<?php echo htmlspecialchars($p['slug']); ?></div>
          <div class="admin-call-actions">
            <a class="admin-btn admin-btn-secondary" href="blog.php?edit=<?php echo intval($p['id']); ?>">✏️ Edit</a>
            <a class="admin-btn admin-btn-secondary" href="https://rootnivesh.in/blog/<?php echo htmlspecialchars($p['slug']); ?>" target="_blank" rel="noopener">🔗 View</a>
            <form method="post" style="display:inline-block; margin-left:8px"
                  onsubmit="return confirm('Delete this post permanently?');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo intval($p['id']); ?>">
              <button type="submit" class="admin-btn admin-btn-danger">🗑 Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </section>

  </main>
</body>
</html>
