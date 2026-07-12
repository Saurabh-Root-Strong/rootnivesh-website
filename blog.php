<?php
/* ============================================================
   blog.php — server-rendered article page for /blog/<slug>.

   WHY THIS EXISTS
   ---------------
   The site is a single-page app: /blog/<slug> was served index.html and
   the article was fetched and rendered by JavaScript. Google runs JS and
   coped, but SOCIAL SCRAPERS (WhatsApp, Twitter, LinkedIn, Facebook) and
   plain crawlers do NOT run JS, so every shared blog link showed the
   generic homepage title and OG image. This renders the real article and
   its real <head> on the server, so a shared link previews correctly and
   the content is crawlable without JavaScript.

   NOT CLOAKING
   ------------
   The SAME HTML is served to everyone — human or bot. There is no
   user-agent sniffing. Humans get a fast, JS-free, fully readable article;
   in-app navigation elsewhere is still handled by the SPA. Serving
   different content to crawlers than to users is cloaking and is penalised;
   this deliberately does not do that.

   BLAST RADIUS
   ------------
   /blog/<slug> is routed here by .htaccess. If anything fails — DB down, an
   exception — this falls back to serving index.html (the SPA), so behaviour
   degrades to exactly what it was before, never worse. A genuinely missing
   post returns a real 404 so search engines drop dead URLs.
   ============================================================ */

$SLUG = isset($_GET['slug']) ? trim($_GET['slug']) : '';

/* An empty or malformed slug is not an article. Send bare /blog and junk to
   the SPA blog list rather than erroring. */
if ($SLUG === '' || !preg_match('/^[a-z0-9-]{1,140}$/', $SLUG)) {
    header('Location: /blog');
    exit;
}

/* ---- fetch the post; on ANY infra failure, fall back to the SPA ----
   We connect directly rather than via admin/db.php on purpose: db() EXITS
   (not throws) on a connection failure, which would print a raw error on a
   real article URL instead of degrading. Owning the connection here lets us
   guarantee the fallback — a DB outage serves the SPA, exactly as before. */
$post   = null;
$dbOk   = false;
if (file_exists(__DIR__ . '/admin/config.php')) {
    require_once __DIR__ . '/admin/config.php';
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $stmt = $pdo->prepare(
            'SELECT slug, title, category, excerpt, cover_image, body, author,
                    read_minutes, published_at, updated_at
             FROM posts WHERE slug = :s AND status = :st LIMIT 1'
        );
        $stmt->execute([':s' => $SLUG, ':st' => 'published']);
        $post = $stmt->fetch();
        $dbOk = true;
    } catch (Throwable $e) {
        $dbOk = false;
    }
}
if (!$dbOk) {
    // Config missing or DB down — don't 404 a real URL over infra. Hand the
    // request to the SPA, which will try posts.php from the client.
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

$ORIGIN   = 'https://rootnivesh.in';
$SITE     = 'RootNivesh Research';
$SEBI     = 'INH000XXXXX';
$DEFAULT_OG = $ORIGIN . '/images/og-image.png?v=2';

/* ---- genuinely not found -> real 404 (so Google drops it) ---- */
if (!$post) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $canonical = $ORIGIN . '/blog';
    echo blog_shell(
        'Article not found — ' . $SITE,
        'This article could not be found. Browse the latest research and market education on the RootNivesh blog.',
        $canonical, $DEFAULT_OG, 'noindex, follow',
        '<div class="bp-notfound"><h1>Article not found</h1>'
        . '<p>The article you are looking for may have been moved or removed.</p>'
        . '<p><a class="bp-btn" href="/blog">← Back to all articles</a></p></div>',
        ''
    );
    exit;
}

/* ---- helpers ---- */
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

/* Same href policy as the client renderer: same-site paths and https only.
   A body row is admin-authored but still stored data — never let a
   javascript:/data: URL through. */
function blog_safe_href($href) {
    if (preg_match('#^/[^/]#', $href)) return $href;          // same-site path
    if (preg_match('#^https://#i', $href)) return $href;      // absolute https
    return null;
}

/* lite-markdown -> HTML, mirroring blogRenderBody() in js/app/content.js:
   ## -> h2, ### -> h3, blank line -> paragraph, **bold**, [text](/path) links.
   Text is escaped FIRST, then inline patterns are applied to the escaped
   string, so no user content can inject markup. Returns [html, toc]. */
function blog_render_body($raw) {
    $lines = explode("\n", str_replace("\r\n", "\n", (string) $raw));
    $html = ''; $para = []; $toc = []; $n = 0;

    $inline = function ($s) {
        $s = htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
        $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
            $href = blog_safe_href($m[2]);
            if ($href === null) return $m[1];                 // drop link, keep words
            $ext = preg_match('#^https://#i', $href) && !preg_match('#rootnivesh\.in#i', $href);
            $rel = $ext ? ' target="_blank" rel="noopener nofollow"' : '';
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '"' . $rel . '>' . $m[1] . '</a>';
        }, $s);
        return $s;
    };
    $flush = function () use (&$para, &$html, $inline) {
        if ($para) { $html .= '<p>' . $inline(implode(' ', $para)) . '</p>'; $para = []; }
    };

    foreach ($lines as $line) {
        $t = trim($line);
        if (preg_match('/^###\s+(.*)/', $t, $m)) {
            $flush(); $id = 'sec-' . (++$n);
            $html .= '<h3 id="' . $id . '">' . $inline($m[1]) . '</h3>';
            $toc[] = ['id' => $id, 'text' => $m[1], 'lvl' => 3];
        } elseif (preg_match('/^##\s+(.*)/', $t, $m)) {
            $flush(); $id = 'sec-' . (++$n);
            $html .= '<h2 id="' . $id . '">' . $inline($m[1]) . '</h2>';
            $toc[] = ['id' => $id, 'text' => $m[1], 'lvl' => 2];
        } elseif ($t === '') {
            $flush();
        } else {
            $para[] = $t;
        }
    }
    $flush();
    return [$html, $toc];
}

/* ---- derive meta ---- */
$title    = $post['title'] ?: 'Research article';
$excerpt  = $post['excerpt'] ?: mb_substr(trim(preg_replace('/\s+/', ' ',
              preg_replace('/[#*\[\]()]/', '', (string) $post['body']))), 0, 155);
$canonical = $ORIGIN . '/blog/' . rawurlencode($post['slug']);
$ogImage  = (!empty($post['cover_image']) && preg_match('#^https?://#', $post['cover_image']))
              ? $post['cover_image'] : $DEFAULT_OG;
$author   = $post['author'] ?: $SITE;
$pub      = $post['published_at'] ? date('c', strtotime($post['published_at'])) : null;
$mod      = $post['updated_at']   ? date('c', strtotime($post['updated_at']))   : $pub;
$pubHuman = $post['published_at'] ? date('j M Y', strtotime($post['published_at'])) : '';
$readMin  = intval($post['read_minutes']) ?: null;
$catLabel = ucwords(str_replace('-', ' ', (string) $post['category']));

list($bodyHtml, $toc) = blog_render_body($post['body']);

/* JSON-LD — Article + breadcrumb, for rich results. */
$ld = [
    '@context' => 'https://schema.org',
    '@type'    => 'Article',
    'headline' => $title,
    'description' => $excerpt,
    'image'    => [$ogImage],
    'author'   => ['@type' => 'Organization', 'name' => $author],
    'publisher'=> [
        '@type' => 'Organization', 'name' => $SITE,
        'logo'  => ['@type' => 'ImageObject', 'url' => $ORIGIN . '/images/logo.png'],
    ],
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
];
if ($pub) $ld['datePublished'] = $pub;
if ($mod) $ld['dateModified']  = $mod;

/* ---- build the article body markup ---- */
$tocHtml = '';
if (count($toc) >= 3) {
    $tocHtml = '<nav class="bp-toc" aria-label="In this article"><p class="bp-toc-title">In this article</p><ul>';
    foreach ($toc as $t) {
        $tocHtml .= '<li class="bp-toc-l' . $t['lvl'] . '"><a href="#' . h($t['id']) . '">' . h($t['text']) . '</a></li>';
    }
    $tocHtml .= '</ul></nav>';
}

$meta = [];
if ($catLabel) $meta[] = '<span class="bp-chip">' . h($catLabel) . '</span>';
if ($pubHuman) $meta[] = h($pubHuman);
if ($readMin)  $meta[] = $readMin . ' min read';

$cover = '';
if (!empty($post['cover_image'])) {
    $cover = '<img class="bp-cover" src="' . h($post['cover_image']) . '" alt="' . h($title)
           . '" width="1200" height="600" loading="eager">';
}

$article =
    '<article class="bp-article">'
  . '<a class="bp-back" href="/blog">← All articles</a>'
  . '<header class="bp-head">'
  . '<h1>' . h($title) . '</h1>'
  . '<div class="bp-meta">' . implode('<span class="bp-dot">·</span>', $meta) . '</div>'
  . '</header>'
  . $cover
  . ($excerpt ? '<p class="bp-excerpt">' . h($excerpt) . '</p>' : '')
  . $tocHtml
  . '<div class="bp-body">' . $bodyHtml . '</div>'
  . '<footer class="bp-foot">'
  . '<p class="bp-disc"><strong>Disclaimer:</strong> This article is for educational purposes only and is not '
  . 'investment advice or a recommendation to buy or sell any security. Investments in securities are subject to '
  . 'market risk; read all related documents carefully. RootNivesh is a SEBI Registered Research Analyst '
  . '(Reg. No. ' . h($SEBI) . ').</p>'
  . '<p><a class="bp-btn" href="/blog">← Back to all articles</a></p>'
  . '</footer>'
  . '</article>';

$head = '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
if ($pub) $head .= '<meta property="article:published_time" content="' . h($pub) . '">';
if ($mod) $head .= '<meta property="article:modified_time" content="' . h($mod) . '">';
$head .= '<meta property="article:section" content="' . h($catLabel) . '">';

http_response_code(200);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');   // 5 min — new posts still surface quickly
echo blog_shell($title . ' — ' . $SITE, $excerpt, $canonical, $ogImage, 'index, follow', $article, $head);


/* ============================================================
   The page shell — one <head> + minimal styled chrome. Reuses the site's
   stylesheet and fonts so the article matches the brand, then adds a small
   article-specific stylesheet. Standalone and fully functional without JS.
   ============================================================ */
function blog_shell($title, $desc, $canonical, $ogImage, $robots, $bodyInner, $extraHead) {
    $SITE = 'RootNivesh Research';
    $desc = trim(preg_replace('/\s+/', ' ', $desc));
    ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($title); ?></title>
<meta name="description" content="<?php echo h($desc); ?>">
<meta name="robots" content="<?php echo h($robots); ?>">
<meta name="author" content="<?php echo h($SITE); ?>">
<link rel="canonical" href="<?php echo h($canonical); ?>">
<link rel="icon" href="/images/favicon.ico?v=2" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32.png?v=2">
<link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png?v=2">
<meta property="og:type" content="article">
<meta property="og:site_name" content="<?php echo h($SITE); ?>">
<meta property="og:title" content="<?php echo h($title); ?>">
<meta property="og:description" content="<?php echo h($desc); ?>">
<meta property="og:url" content="<?php echo h($canonical); ?>">
<meta property="og:image" content="<?php echo h($ogImage); ?>">
<meta property="og:locale" content="en_IN">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo h($title); ?>">
<meta name="twitter:description" content="<?php echo h($desc); ?>">
<meta name="twitter:image" content="<?php echo h($ogImage); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css?v=108">
<?php echo $extraHead; ?>
<style>
  .bp-wrap { max-width: 760px; margin: 0 auto; padding: 90px 20px 60px; }
  .bp-nav { position: fixed; top: 0; left: 0; right: 0; z-index: 20; display: flex;
    align-items: center; justify-content: space-between; padding: 14px 20px;
    background: rgba(7,19,31,0.85); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); }
  .bp-nav a.bp-brand { display: flex; align-items: center; gap: 9px; color: var(--white);
    text-decoration: none; font-weight: 700; font-size: 15px; }
  .bp-nav img { height: 30px; width: auto; }
  .bp-nav .bp-links a { color: var(--grey2); text-decoration: none; margin-left: 18px; font-size: 14px; }
  .bp-nav .bp-links a:hover { color: var(--gold); }
  .bp-back { display: inline-block; color: var(--grey2); text-decoration: none; font-size: 13px;
    font-family: 'Space Mono', monospace; margin-bottom: 20px; }
  .bp-back:hover { color: var(--gold); }
  .bp-head h1 { font-family: 'Cormorant Garamond', serif; font-size: clamp(1.9rem, 5vw, 3rem);
    line-height: 1.12; color: var(--white); margin: 0 0 14px; }
  .bp-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; color: var(--grey);
    font-size: 12.5px; font-family: 'Space Mono', monospace; }
  .bp-chip { color: var(--gold); text-transform: uppercase; letter-spacing: 0.06em; }
  .bp-dot { color: var(--border2); }
  .bp-cover { width: 100%; height: auto; border-radius: 14px; margin: 26px 0; border: 1px solid var(--border); }
  .bp-excerpt { font-size: 1.15rem; line-height: 1.6; color: var(--grey2); margin: 0 0 30px;
    padding-bottom: 24px; border-bottom: 1px solid var(--border); }
  .bp-toc { background: rgba(255,255,255,0.02); border: 1px solid var(--border);
    border-radius: 12px; padding: 16px 20px; margin: 0 0 30px; }
  .bp-toc-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em;
    color: var(--gold); font-family: 'Space Mono', monospace; margin: 0 0 10px; }
  .bp-toc ul { list-style: none; margin: 0; padding: 0; }
  .bp-toc li { margin: 5px 0; }
  .bp-toc a { color: var(--grey2); text-decoration: none; font-size: 14px; }
  .bp-toc a:hover { color: var(--gold); }
  .bp-toc-l3 { padding-left: 16px; font-size: 13px; }
  .bp-body { color: var(--grey1, #cfd8e3); font-size: 1.05rem; line-height: 1.75; }
  .bp-body h2 { font-family: 'Cormorant Garamond', serif; font-size: 1.7rem; color: var(--white);
    margin: 38px 0 14px; scroll-margin-top: 80px; }
  .bp-body h3 { font-size: 1.2rem; color: var(--white); margin: 28px 0 10px; scroll-margin-top: 80px; }
  .bp-body p { margin: 0 0 18px; }
  .bp-body strong { color: var(--white); }
  .bp-body a { color: var(--gold); text-decoration: underline; text-underline-offset: 2px; }
  .bp-foot { margin-top: 44px; padding-top: 24px; border-top: 1px solid var(--border); }
  .bp-disc { font-size: 12.5px; color: var(--grey); line-height: 1.6; margin: 0 0 20px; }
  .bp-btn { display: inline-block; padding: 10px 18px; border: 1px solid var(--gold);
    border-radius: 8px; color: var(--gold); text-decoration: none; font-size: 14px; }
  .bp-btn:hover { background: rgba(201,168,76,0.1); }
  .bp-notfound { text-align: center; padding: 40px 0; }
  .bp-notfound h1 { font-family: 'Cormorant Garamond', serif; color: var(--white); font-size: 2.2rem; }
  .bp-notfound p { color: var(--grey2); }
</style>
</head>
<body class="bp-page">
  <nav class="bp-nav">
    <a class="bp-brand" href="/"><img src="/images/logo.png" alt="RootNivesh"><span>Root Nivesh</span></a>
    <div class="bp-links">
      <a href="/">Home</a>
      <a href="/blog">Blog</a>
      <a href="/ipo">IPO</a>
      <a href="/membership">Membership</a>
    </div>
  </nav>
  <main class="bp-wrap">
    <?php echo $bodyInner; ?>
  </main>
</body>
</html>
<?php
    return ob_get_clean();
}
