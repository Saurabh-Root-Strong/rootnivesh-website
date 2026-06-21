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
/* Rough read-time from the body word count (â‰ˆ200 wpm). */
function blog_read_minutes($body) {
    $words = str_word_count(strip_tags((string) $body));
    return max(1, (int) round($words / 200));
}

/* Handle a cover-image file upload. Validates it's a real image, gives it a
   random safe name, stores it under /uploads/blog/, returns the public URL. */
function blog_handle_upload($file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (code ' . ($file['error'] ?? '?') . ').'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Image too large (max 5 MB).'];
    }
    $info = @getimagesize($file['tmp_name']);   // real image check, not the client mime
    if (!$info) return ['ok' => false, 'error' => 'That file is not a valid image.'];
    $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $ext = $extByMime[$info['mime']] ?? '';
    if ($ext === '') return ['ok' => false, 'error' => 'Use a JPG, PNG, WEBP or GIF image.'];
    $dir = dirname(__DIR__) . '/uploads/blog';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['ok' => false, 'error' => 'Could not create the upload folder.'];
    }
    $name = 'post-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded image.'];
    }
    return ['ok' => true, 'url' => '/uploads/blog/' . $name];
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
        // An uploaded file wins over the URL field; keep the old cover if neither given on edit.
        if (!empty($_FILES['cover_file']['name']) && is_uploaded_file($_FILES['cover_file']['tmp_name'] ?? '')) {
            $up = blog_handle_upload($_FILES['cover_file']);
            if (!$up['ok']) throw new RuntimeException($up['error']);
            $cover = $up['url'];
        }
        $body     = trim($_POST['body'] ?? '');
        $status   = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
        $allowedCats = ['education', 'strategy', 'markets', 'quant', 'investing', 'personal-finance'];
        if (!in_array($category, $allowedCats, true)) $category = 'markets';

        if ($title === '') { throw new RuntimeException('Title is required.'); }

        $slug = trim($_POST['slug'] ?? '');
        $slug = $slug !== '' ? blog_slugify($slug) : blog_slugify($title);
        $slug = blog_unique_slug($pdo, $slug, $id);

        $readMin = ($_POST['read_minutes'] ?? '') !== ''
                 ? max(1, intval($_POST['read_minutes'])) : blog_read_minutes($body);

        // Publish date â€” accepts a datetime-local ("Y-m-dTH:i") or a plain date.
        // Blank = leave as-is on edit, or default to now on a new post.
        $pubIn = trim($_POST['published_at'] ?? '');
        $publishedAt = null;
        if ($pubIn !== '') {
            $ts = strtotime($pubIn);
            if ($ts) $publishedAt = date('Y-m-d H:i:s', $ts);
        }

        if ($id > 0) {
            $sql = 'UPDATE posts SET slug=:slug, title=:title, category=:category, excerpt=:excerpt,
                        cover_image=:cover, body=:body, read_minutes=:rm, status=:status';
            $args = [
                ':slug' => $slug, ':title' => $title, ':category' => $category,
                ':excerpt' => $excerpt ?: null, ':cover' => $cover ?: null, ':body' => $body,
                ':rm' => $readMin, ':status' => $status, ':id' => $id,
            ];
            if ($publishedAt !== null) { $sql .= ', published_at=:pub'; $args[':pub'] = $publishedAt; }
            $sql .= ' WHERE id=:id';
            $pdo->prepare($sql)->execute($args);
            $flash = 'Post updated (#' . $id . ').';
        } else {
            $pdo->prepare(
                'INSERT INTO posts (slug, title, category, excerpt, cover_image, body, author, read_minutes, status, published_at)
                 VALUES (:slug, :title, :category, :excerpt, :cover, :body, :author, :rm, :status, :pub)'
            )->execute([
                ':slug' => $slug, ':title' => $title, ':category' => $category,
                ':excerpt' => $excerpt ?: null, ':cover' => $cover ?: null, ':body' => $body,
                ':author' => admin_display() ?: admin_user(), ':rm' => $readMin, ':status' => $status,
                ':pub' => $publishedAt ?: date('Y-m-d H:i:s'),
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
$catLabels = ['education' => 'Education', 'strategy' => 'Strategy', 'markets' => 'Markets', 'quant' => 'Quant Research', 'investing' => 'Investing', 'personal-finance' => 'Personal Finance'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>RootNivesh Admin â€” Blog</title>
<link rel="stylesheet" href="admin.css?v=4">
</head>
<body class="admin-body">
<div class="admin-layout">
  <?php $ADMIN_PAGE = 'blog'; include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-content">
  <div class="admin-topbar"><h1>Blog</h1></div>

  <main class="admin-main">

    <?php if ($flash): ?>
      <div class="admin-flash"><?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- ===== WRITE / EDIT A POST ===== -->
    <section class="admin-card">
      <h2><?php echo $editing ? 'Edit post #' . intval($editing['id']) : 'Write a new post'; ?></h2>
      <p style="color:#8A9BB0; font-size:13px; margin:0 0 12px">
        Formatting: start a line with <strong>##</strong> for a section heading (these build the
        â€œIn this articleâ€ menu), leave a blank line between paragraphs, and wrap text in
        <strong>**double asterisks**</strong> for bold. No HTML needed.
      </p>
      <form method="post" class="admin-form" enctype="multipart/form-data" id="blogForm"
            onsubmit="var b=document.getElementById('blogSaveBtn'); if(b){b.disabled=true; b.textContent='Savingâ€¦';}">
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
          <label>Read time (min) â€” auto if blank
            <input type="number" name="read_minutes" min="1" placeholder="auto"
                   value="<?php echo $editing && $editing['read_minutes'] ? intval($editing['read_minutes']) : ''; ?>">
          </label>
          <label>Status
            <select name="status">
              <option value="published" <?php echo (!$editing || $editing['status'] === 'published') ? 'selected' : ''; ?>>Published (live)</option>
              <option value="draft" <?php echo ($editing && $editing['status'] === 'draft') ? 'selected' : ''; ?>>Draft (hidden)</option>
            </select>
          </label>
          <label>Publish date â€” blank = today
            <input type="date" name="published_at"
                   value="<?php echo ($editing && !empty($editing['published_at'])) ? date('Y-m-d', strtotime($editing['published_at'])) : ''; ?>">
          </label>
        </div>

        <div class="admin-label">Cover image â€” upload from your device
          <input type="file" id="coverFile" name="cover_file" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
        <?php if ($editing && !empty($editing['cover_image'])): ?>
          <div style="display:flex; align-items:center; gap:12px; margin-top:-4px">
            <img src="<?php echo htmlspecialchars($editing['cover_image']); ?>" alt="" style="height:54px; border-radius:6px; border:1px solid var(--border)">
            <span style="color:#8A9BB0; font-size:12px">Current cover. Upload a new file or change the link below to replace it.</span>
          </div>
        <?php endif; ?>
        <label>â€¦or paste an image URL (optional)
          <input type="url" name="cover_image" placeholder="https://â€¦ (leave blank if you uploaded a file)"
                 value="<?php echo $editing ? htmlspecialchars($editing['cover_image']) : ''; ?>">
        </label>

        <label>Short summary (shown on the blog card)
          <textarea name="excerpt" rows="2" placeholder="One or two sentences that tease the article."><?php echo $editing ? htmlspecialchars($editing['excerpt']) : ''; ?></textarea>
        </label>

        <label>Article body
          <textarea name="body" rows="16" placeholder="## Introduction&#10;&#10;Write your paragraph hereâ€¦&#10;&#10;## Next Section&#10;&#10;More text. Use **bold** for emphasis."><?php echo $editing ? htmlspecialchars($editing['body']) : ''; ?></textarea>
        </label>

        <div style="display:flex; gap:10px; align-items:center">
          <button type="submit" id="blogSaveBtn" class="admin-btn"><?php echo $editing ? 'Save changes' : 'Publish post'; ?></button>
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
            <a class="admin-btn admin-btn-secondary" href="blog.php?edit=<?php echo intval($p['id']); ?>">âœï¸ Edit</a>
            <a class="admin-btn admin-btn-secondary" href="https://rootnivesh.in/blog/<?php echo htmlspecialchars($p['slug']); ?>" target="_blank" rel="noopener">ðŸ”— View</a>
            <form method="post" style="display:inline-block; margin-left:8px"
                  onsubmit="return confirm('Delete this post permanently?');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo intval($p['id']); ?>">
              <button type="submit" class="admin-btn admin-btn-danger">ðŸ—‘ Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </section>

  </main>
  </div><!-- /.admin-content -->
</div><!-- /.admin-layout -->
</body>
</html>

