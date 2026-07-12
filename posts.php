<?php
/* posts.php — public blog API.
   GET /posts.php              -> list of published posts (no body)
   GET /posts.php?slug=xxx     -> a single published post WITH body
   GET /posts.php?category=xx  -> filter the list by category
*/

$allowedOrigins = ['https://rootnivesh.in', 'https://www.rootnivesh.in', 'http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

require_once __DIR__ . '/admin/db.php';

try {
    $slug = trim($_GET['slug'] ?? '');

    if ($slug !== '') {
        // Single post with full body.
        $stmt = db()->prepare(
            'SELECT id, slug, title, category, excerpt, cover_image, body, author,
                    read_minutes, published_at
             FROM posts WHERE slug = :s AND status = :st LIMIT 1'
        );
        $stmt->execute([':s' => $slug, ':st' => 'published']);
        $post = $stmt->fetch();
        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Post not found.']);
            exit;
        }
        echo json_encode(['post' => $post]);
        exit;
    }

    // Listing — no body, lighter payload.
    $category = trim($_GET['category'] ?? '');
    // Must list EVERY category the blog actually uses, or filtering by a missing
    // one (investing, personal-finance) silently falls through and returns the
    // whole list instead of that category.
    $allowedCats = ['education', 'strategy', 'markets', 'quant', 'investing', 'personal-finance'];
    $sql  = 'SELECT id, slug, title, category, excerpt, cover_image, author,
                    read_minutes, published_at
             FROM posts WHERE status = :st';
    $args = [':st' => 'published'];
    if (in_array($category, $allowedCats, true)) {
        $sql .= ' AND category = :c';
        $args[':c'] = $category;
    }
    $sql .= ' ORDER BY published_at DESC LIMIT 100';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'fetched_at' => date(DateTime::ATOM),
        'count'      => count($rows),
        'posts'      => $rows,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load posts.']);
}
