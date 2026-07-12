/* ===== REPORTS — render only (reportsData lives in data.js) ===== */
function renderReports(cat) {
  const data = cat === 'all' ? reportsData : reportsData.filter(r => r.cat === cat);
  document.getElementById('reportsGrid').innerHTML = data.map(r => {
    const safeTitle = (r.title + ' (' + r.ticker + ')').replace(/'/g, "\\'");
    return `
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:flex-start">
        <div class="card-tag tag-${r.tag}">${r.tag.toUpperCase()}</div>
        <div style="font-family:'Space Mono',monospace; font-size:12px; color:var(--grey)">${r.ticker}</div>
      </div>
      <h3>${r.title}</h3>
      <p>${r.desc}</p>
      <div class="card-meta">
        <div class="meta-item"><span>${r.target}</span>Target</div>
        <div class="meta-item"><span style="color:${r.upside.startsWith('+') ? 'var(--green)' : r.upside.startsWith('-') ? 'var(--red)' : 'var(--gold)'}">${r.upside}</span>Upside</div>
        <div class="meta-item"><span>${r.horizon}</span>Horizon</div>
      </div>
      <div class="card-footer" style="margin-top:14px; padding-top:12px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center">
        <span style="font-size:11px; color:var(--grey)">${r.date}</span>
        <button class="btn btn-outline" style="padding:6px 14px; font-size:12px" onclick="enquireFor('Research Report','${safeTitle}','request')">Read Full Report →</button>
      </div>
    </div>`;
  }).join('');
}
function filterReports(cat, btn) {
  document.querySelectorAll('#reportTabs .tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  renderReports(cat);
}

/* ===== COURSES — render only (coursesData lives in data.js) ===== */
function renderCourses(level) {
  const data = level === 'all' ? coursesData : coursesData.filter(c => c.level === level);
  document.getElementById('coursesGrid').innerHTML = data.map(c => {
    const safeTitle = c.title.replace(/'/g, "\\'");
    const intent = c.badge === 'Free' ? 'enrol' : 'enrol';
    return `
    <div class="course-card card">
      <div class="course-icon">${c.icon}</div>
      <div class="card-tag ${c.badge === 'Free' ? 'tag-free' : 'tag-premium'}">${c.badge}</div>
      <h3>${c.title}</h3>
      <p>${c.desc}</p>
      <div class="course-meta">
        <span><strong>${c.lessons}</strong></span>
        <span><strong>${c.duration}</strong></span>
        <span style="text-transform:capitalize; color:var(--gold); text-transform:capitalize">${c.level}</span>
      </div>
      <div class="card-footer" style="margin-top:16px">
        <button class="btn btn-gold" style="width:100%; justify-content:center" onclick="enquireFor('Learner Club Course','${safeTitle}','${intent}')">
          ${c.badge === 'Free' ? 'Start Free →' : 'Enroll Now →'}
        </button>
      </div>
    </div>`;
  }).join('');
}
function filterCourses(level, btn) {
  document.querySelectorAll('#page-learner .tabs .tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  renderCourses(level);
}

/* ===== BLOG — admin-managed posts (posts.php) ===== */
let blogPosts = null;            // cached list payload
let blogCurrentCat = 'all';
const BLOG_CAT_LABELS = { education: 'Education', strategy: 'Strategy', markets: 'Markets', quant: 'Quant Research', investing: 'Investing', 'personal-finance': 'Personal Finance' };
function blogFmtDate(iso) {
  const d = new Date((iso || '').replace(' ', 'T'));
  return isNaN(d) ? '' : d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function loadBlogList() {
  const grid = document.getElementById('blogGrid');
  if (!grid) return;
  grid.innerHTML = '<p class="blog-loading">⟳ Loading articles…</p>';
  fetch('/posts.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => { blogPosts = d.posts || []; renderBlogList(); })
    .catch(() => { grid.innerHTML = '<p class="blog-loading">Could not load articles right now.</p>'; });
}

function renderBlogList() {
  const grid = document.getElementById('blogGrid');
  if (!grid || !blogPosts) return;
  const list = blogCurrentCat === 'all' ? blogPosts : blogPosts.filter(p => p.category === blogCurrentCat);
  if (!list.length) { grid.innerHTML = '<p class="blog-loading">No articles in this category yet.</p>'; return; }
  grid.innerHTML = list.map(p => {
    const cat = BLOG_CAT_LABELS[p.category] || p.category;
    const cover = p.cover_image
      ? `<div class="blog-card2-cover" style="background-image:url('${encodeURI(p.cover_image)}')"></div>`
      : `<div class="blog-card2-cover blog-card2-cover--none">📈</div>`;
    const read = p.read_minutes ? `${p.read_minutes} min read` : '';
    return `<article class="blog-card2" onclick="openArticle('${encodeURIComponent(p.slug)}')">
      ${cover}
      <div class="blog-card2-body">
        <span class="blog-card2-cat">${escapeHtml(cat)}</span>
        <h3>${escapeHtml(p.title)}</h3>
        <p>${escapeHtml(p.excerpt || '')}</p>
        <div class="blog-card2-meta"><span>${blogFmtDate(p.published_at)}</span>${read ? `<span>·</span><span>${read}</span>` : ''}</div>
      </div>
    </article>`;
  }).join('');
}

function filterBlogs(cat, btn) {
  document.querySelectorAll('#blogTabs .tab').forEach(t => t.classList.remove('active'));
  if (btn) btn.classList.add('active');
  blogCurrentCat = cat;
  if (blogPosts) renderBlogList(); else loadBlogList();
}

/* Show the list view (used on normal nav to the blog page). */
function showBlogListView() {
  const list = document.getElementById('blogListView');
  const view = document.getElementById('blogArticleView');
  const hero = document.getElementById('blogHero');
  if (view) { view.style.display = 'none'; view.innerHTML = ''; }
  if (list) list.style.display = '';
  if (hero) hero.style.display = '';
  blogCurrentCat = 'all';
  document.querySelectorAll('#blogTabs .tab').forEach((b, i) => b.classList.toggle('active', i === 0));
}

/* lite-markdown → HTML + a heading list for the "In this article" TOC.
   ## → h2, ### → h3, blank line separates paragraphs, **x** → bold,
   [text](/path) → link.

   Links matter for more than convenience: internal links are how a blog passes
   authority to the pages that earn money (the IPO tool, Performance, Calls) and
   how Google works out what this site is actually about. Without them every post
   was a dead end.

   Safety: the text is escaped FIRST, then the link pattern is applied to the
   escaped string, and the href is whitelisted to same-site paths and https URLs
   only. A body row is admin-authored, but it is still stored data — a
   javascript: or data: href must never survive into the DOM. */
function blogRenderBody(raw) {
  const lines = String(raw || '').replace(/\r\n/g, '\n').split('\n');
  const toc = []; let html = ''; let para = []; let n = 0;
  const safeHref = h => (/^\/[^\/]/.test(h) || /^https:\/\//i.test(h)) ? h : null;
  const inline = s => escapeHtml(s)
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (m, text, href) => {
      const h = safeHref(href);
      if (!h) return text;                                   // drop the link, keep the words
      const ext = /^https:\/\//i.test(h) && !/rootnivesh\.in/i.test(h);
      return `<a href="${h}"${ext ? ' target="_blank" rel="noopener nofollow"' : ''}>${text}</a>`;
    });
  const flush = () => { if (para.length) { html += '<p>' + inline(para.join(' ')) + '</p>'; para = []; } };
  lines.forEach(line => {
    const t = line.trim();
    const h3 = t.match(/^###\s+(.*)/), h2 = t.match(/^##\s+(.*)/);
    if (h3) { flush(); const id = 'sec-' + (++n); html += `<h3 id="${id}">${inline(h3[1])}</h3>`; toc.push({ id, text: h3[1], lvl: 3 }); }
    else if (h2) { flush(); const id = 'sec-' + (++n); html += `<h2 id="${id}">${inline(h2[1])}</h2>`; toc.push({ id, text: h2[1], lvl: 2 }); }
    else if (t === '') { flush(); }
    else { para.push(t); }
  });
  flush();
  return { html, toc };
}

function openArticle(slugEnc, fromPopState) {
  const slug = decodeURIComponent(slugEnc);
  // Activate the blog page shell directly (skip initPage's list reset).
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const bp = document.getElementById('page-blog'); if (bp) bp.classList.add('active');
  const list = document.getElementById('blogListView');
  const view = document.getElementById('blogArticleView');
  const hero = document.getElementById('blogHero');
  if (list) list.style.display = 'none';
  if (hero) hero.style.display = 'none';
  if (view) { view.style.display = ''; view.innerHTML = '<p class="blog-loading">⟳ Loading…</p>'; }
  if (!fromPopState) history.pushState({ page: 'blog', slug }, '', '/blog/' + slug);
  window.scrollTo(0, 0);
  // Close mobile menu if open.
  const mm = document.getElementById('mobileMenu');
  if (mm && mm.classList.contains('open')) { mm.classList.remove('open'); const nv = document.getElementById('mainNav'); if (nv) nv.classList.remove('menu-open'); }
  fetch('/posts.php?slug=' + encodeURIComponent(slug), { credentials: 'same-origin' })
    .then(r => { if (!r.ok) throw 0; return r.json(); })
    .then(d => renderArticle(d.post))
    .catch(() => { if (view) view.innerHTML = '<div class="article-back"><a onclick="backToBlog()">← Back to blog</a></div><p class="blog-loading">Article not found.</p>'; });
}

function renderArticle(post) {
  const view = document.getElementById('blogArticleView');
  if (!view || !post) return;
  const { html, toc } = blogRenderBody(post.body);
  const cat = BLOG_CAT_LABELS[post.category] || post.category;
  const read = post.read_minutes ? `${post.read_minutes} min read` : '';
  const cover = post.cover_image
    ? `<div class="article-cover"><img src="${encodeURI(post.cover_image)}" alt="${escapeHtml(post.title)}" loading="lazy"></div>` : '';
  // Collapsible TOC: open on desktop (sticky sidebar), collapsed on mobile so
  // the article title + body are visible immediately instead of a full list.
  const tocOpen = !window.matchMedia('(max-width: 820px)').matches;
  const tocHtml = toc.length ? `<details class="article-toc"${tocOpen ? ' open' : ''}>
      <summary class="article-toc-title">In this article</summary>
      <ul>${toc.map(h => `<li class="toc-l${h.lvl}"><a onclick="blogScrollTo('${h.id}')">${escapeHtml(h.text)}</a></li>`).join('')}</ul>
    </details>` : '';
  setArticleSeo(post);
  view.innerHTML = `
    <div class="article-back"><a onclick="backToBlog()">← Back to blog</a></div>
    <div class="article-wrap">
      ${tocHtml}
      <article class="article-body">
        <span class="blog-card2-cat">${escapeHtml(cat)}</span>
        <h1>${escapeHtml(post.title)}</h1>
        <div class="article-meta">${post.author ? escapeHtml(post.author) + ' · ' : ''}${blogFmtDate(post.published_at)}${read ? ' · ' + read : ''}</div>
        ${cover}
        <div class="article-content">${html}</div>
        <div class="article-cta">
          <a class="btn btn-gold" href="https://wa.me/917467094575?text=Hi%20RootNivesh%2C%20I%20read%20your%20article%20and%20want%20to%20know%20more%20about%20your%20research." target="_blank" rel="noopener">Get research on WhatsApp →</a>
        </div>
        ${articleAuthorBox(post)}
      </article>
    </div>`;
}

/* Author / credentials box — E-E-A-T trust signal under every article.
   Pulls name + SEBI reg from the single constants in data.js. */
function articleAuthorBox(post) {
  const name = (typeof SITE_ANALYST !== 'undefined') ? SITE_ANALYST : 'RootNivesh Research';
  const reg  = (typeof SITE_SEBI_REG !== 'undefined') ? SITE_SEBI_REG : '';
  const bio  = (typeof SITE_ANALYST_BIO !== 'undefined') ? SITE_ANALYST_BIO : '';
  const initials = name.split(/\s+/).map(w => w[0]).join('').slice(0, 2).toUpperCase();
  return `
    <aside class="article-author">
      <div class="article-author-avatar">${escapeHtml(initials)}</div>
      <div class="article-author-body">
        <div class="article-author-name">${escapeHtml(name)}</div>
        <div class="article-author-cred">SEBI Registered Research Analyst${reg ? ' · Reg. No. ' + escapeHtml(reg) : ''}</div>
        <p class="article-author-bio">${escapeHtml(bio)}</p>
        <a class="article-author-link" onclick="showPage('disclaimer')">Read full disclaimer &amp; disclosures →</a>
      </div>
    </aside>`;
}

function blogScrollTo(id) { const el = document.getElementById(id); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }

function backToBlog() {
  showBlogListView();
  history.pushState({ page: 'blog' }, '', '/blog');
  document.title = PAGE_TITLES.blog || 'Research Blog | RootNivesh Research';
  updateRouteMeta('blog'); // restore blog-page meta + drop the article schema
  if (blogPosts) renderBlogList(); else loadBlogList();
  window.scrollTo(0, 0);
}

