/* ===== HTML ESCAPING =====
   Any value that comes from a remote API (NSE / BSE / Yahoo / Groww /
   Chittorgarh) or the calls DB must be escaped before it is interpolated
   into an innerHTML string — a hostile or compromised upstream response
   could otherwise inject markup / event-handler XSS. Numbers formatted
   locally are safe; strings from upstream are not. */
function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/* ===== PAGE NAVIGATION + URL ROUTING =====
   Every "page" gets its own URL via the History API so visitors can
   bookmark, share, and use back/forward. .htaccess rewrites unknown
   paths to index.html so direct hits to /calls etc. still load.
   PAGE_TO_PATH and PAGE_TITLES live in data.js. */
const PATH_TO_PAGE = Object.fromEntries(
  Object.entries(PAGE_TO_PATH).map(function (e) { return [e[1], e[0]]; })
);
// Back-compat: the Calls page is now /membership; keep old /calls links working.
PATH_TO_PAGE['/calls'] = 'calls';

function showPage(id, fromPopState) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const target = document.getElementById('page-' + id);
  if (target) target.classList.add('active');
  document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
  window.scrollTo(0, 0);
  initPage(id);

  // Update the address bar (skip if we got here from a popstate event,
  // because the browser already changed the URL).
  if (!fromPopState && PAGE_TO_PATH[id]) {
    const newPath = PAGE_TO_PATH[id];
    if (window.location.pathname !== newPath) {
      history.pushState({ page: id }, '', newPath);
    }
  }
  // Update document.title + canonical + meta/OG so each route is its own
  // indexable entity (otherwise Google collapses every route into the homepage).
  if (PAGE_TITLES[id]) document.title = PAGE_TITLES[id];
  updateRouteMeta(id);

  // Auto-close the mobile menu on navigation
  const mm = document.getElementById('mobileMenu');
  if (mm && mm.classList.contains('open')) {
    mm.classList.remove('open');
    document.getElementById('mainNav').classList.remove('menu-open');
  }
}

// Browser back / forward buttons
window.addEventListener('popstate', function (e) {
  const path = window.location.pathname;
  const art = path.match(/^\/blog\/(.+)$/);
  if (art) { openArticle(encodeURIComponent(decodeURIComponent(art[1])), true); return; }
  const page = PATH_TO_PAGE[path] || 'home';
  showPage(page, true);
});

// Resolve the initial page from the URL once everything is parsed.
function resolveInitialPage() {
  const path = window.location.pathname;
  const art = path.match(/^\/blog\/(.+)$/);
  if (art) { openArticle(encodeURIComponent(decodeURIComponent(art[1])), true); return; }
  const page = PATH_TO_PAGE[path];
  if (page && page !== 'home') showPage(page, true);
  else { if (PAGE_TITLES.home) document.title = PAGE_TITLES.home; updateRouteMeta('home'); }
}

/* Sync <link rel=canonical>, meta description and OG/Twitter tags to the
   current route. The base tags in index.html stay as the homepage default. */
const SITE_ORIGIN = 'https://rootnivesh.in';
function updateRouteMeta(id) {
  const path = PAGE_TO_PATH[id] || '/';
  const url  = SITE_ORIGIN + (path === '/' ? '/' : path);
  const desc = PAGE_DESCRIPTIONS[id] || PAGE_DESCRIPTIONS.home;
  const title = PAGE_TITLES[id] || PAGE_TITLES.home;

  const setAttr = (selector, attr, value) => {
    const el = document.head.querySelector(selector);
    if (el) el.setAttribute(attr, value);
  };
  setAttr('link[rel="canonical"]', 'href', url);
  setAttr('meta[name="description"]', 'content', desc);
  setAttr('meta[property="og:url"]', 'content', url);
  setAttr('meta[property="og:title"]', 'content', title);
  setAttr('meta[property="og:description"]', 'content', desc);
  setAttr('meta[name="twitter:title"]', 'content', title);
  setAttr('meta[name="twitter:description"]', 'content', desc);
  setAttr('meta[property="og:type"]', 'content', 'website');
  removeArticleSchema();
}

/* Per-article SEO. Honest, content-matched tags — the description is the
   real excerpt, the image is the real cover, dates/author are the real
   record. No keyword stuffing: Google rewards content that matches intent,
   and penalises manipulated metadata. */
function setArticleSeo(post) {
  if (!post) return;
  const url = SITE_ORIGIN + '/blog/' + encodeURIComponent(post.slug);
  const desc = (post.excerpt || '').toString().slice(0, 300);
  const title = post.title + ' | RootNivesh Research';
  const img = post.cover_image || (SITE_ORIGIN + '/images/og-image.png?v=2');
  const setAttr = (sel, attr, val) => { const el = document.head.querySelector(sel); if (el) el.setAttribute(attr, val); };

  document.title = title;
  setAttr('link[rel="canonical"]', 'href', url);
  setAttr('meta[name="description"]', 'content', desc);
  setAttr('meta[property="og:type"]', 'content', 'article');
  setAttr('meta[property="og:url"]', 'content', url);
  setAttr('meta[property="og:title"]', 'content', post.title);
  setAttr('meta[property="og:description"]', 'content', desc);
  setAttr('meta[property="og:image"]', 'content', img);
  setAttr('meta[name="twitter:title"]', 'content', post.title);
  setAttr('meta[name="twitter:description"]', 'content', desc);
  setAttr('meta[name="twitter:image"]', 'content', img);

  // BlogPosting structured data — only fields we can truthfully fill.
  const schema = {
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    'mainEntityOfPage': { '@type': 'WebPage', '@id': url },
    'headline': post.title,
    'description': desc,
    'image': img,
    'datePublished': post.published_at || undefined,
    'dateModified': post.updated_at || post.published_at || undefined,
    'author': { '@type': 'Organization', 'name': post.author || 'RootNivesh Research', 'url': SITE_ORIGIN + '/' },
    'publisher': {
      '@type': 'Organization',
      'name': 'RootNivesh Research',
      'logo': { '@type': 'ImageObject', 'url': SITE_ORIGIN + '/images/logo.png' }
    },
    'isPartOf': { '@type': 'Blog', 'name': 'RootNivesh Research Blog', '@id': SITE_ORIGIN + '/blog' }
  };
  Object.keys(schema).forEach(k => schema[k] === undefined && delete schema[k]);

  removeArticleSchema();
  const s = document.createElement('script');
  s.type = 'application/ld+json';
  s.id = 'article-schema';
  s.textContent = JSON.stringify(schema);
  document.head.appendChild(s);
}

function removeArticleSchema() {
  const old = document.getElementById('article-schema');
  if (old) old.remove();
}

/* ===== MEGA MENU ===== */
function toggleMega(menuId, triggerId) {
  console.log('toggleMega called with', menuId, triggerId);
  const menu = document.getElementById(menuId);
  const trigger = document.getElementById(triggerId);
  console.log('menu element:', menu);
  console.log('trigger element:', trigger);
  const isOpen = menu.classList.contains('open');
  console.log('isOpen:', isOpen);
  closeMegas();
  if (!isOpen) {
    menu.classList.add('open');
    trigger.classList.add('open');
    console.log('Added open class');
  }
}
function closeMegas() {
  document.querySelectorAll('.mega-menu').forEach(m => m.classList.remove('open'));
  document.querySelectorAll('.nav-dd-trigger').forEach(t => t.classList.remove('open'));
}
document.addEventListener('click', function(e) {
  if (!e.target.closest('nav') && !e.target.closest('.mega-menu')) closeMegas();
});
function megaToggleSub(e, subId) {
  e.stopPropagation();
  const sub = document.getElementById(subId);
  const btn = e.currentTarget;
  const isOpen = sub.classList.contains('open');
  // close all subs in this mega
  sub.closest('.mega-inner').querySelectorAll('.mega-sub').forEach(s => s.classList.remove('open'));
  sub.closest('.mega-inner').querySelectorAll('.mega-plus').forEach(p => p.textContent = '+');
  if (!isOpen) { sub.classList.add('open'); btn.textContent = '−'; }
}
function megaGoCall(type) {
  closeMegas();
  showPage('calls');
  const el = document.getElementById('snav-' + type);
  if (el) snavSelectCall(type, el);
  // On phones the hero pushes the call list far below the fold;
  // bring the active content into view.
  setTimeout(() => {
    const target = document.querySelector('#page-calls .snav-content');
    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 60);
}
function megaGoTool(type) {
  closeMegas();
  showPage('tools');
  const el = document.getElementById('snav-' + type);
  if (el) snavSelectTool(type, el);
  setTimeout(() => {
    const target = document.getElementById('toolContent-' + type);
    if (target && target.style.display !== 'none') target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 60);
}

function initPage(id) {
  if (id === 'reports') renderReports('all');
  if (id === 'learner') renderCourses('all');
  if (id === 'calls') { renderPlans(); liveCalls = null; loadLiveCalls().then(() => renderCalls('intraday')); }
  if (id === 'performance') {
    // Reset to a clean default view (Achieved · All · All Dates) on every entry.
    currentPerfType = '';
    perfMode = 'achieved';
    perfDateFrom = ''; perfDateTo = '';
    document.querySelectorAll('#perfModeTabs .perf-mode').forEach((b, i) => b.classList.toggle('active', i === 0));
    document.querySelectorAll('#perfTabs .tab').forEach((b, i) => b.classList.toggle('active', i === 0));
    document.querySelectorAll('#perfDateBar .perf-date').forEach((b, i) => b.classList.toggle('active', i === 0));
    const cw = document.getElementById('perfCustomWrap'); if (cw) cw.style.display = 'none';
    const aw = document.getElementById('perfAchievedWrap'), lw = document.getElementById('perfLiveWrap');
    if (aw) aw.style.display = '';
    if (lw) lw.style.display = 'none';
    loadPerformance('');
  }
  if (id === 'blog') { showBlogListView(); loadBlogList(); }
  if (id === 'ipo') { fetchIpo(currentIpoTab); }
  if (id === 'contact') resetContactForm();
}

/* Bring the Contact form back to its empty pre-submission state.
   Called every time the visitor lands on the Contact page so the
   "Message Sent!" success block from a previous submission doesn't
   replace the form for their next enquiry. */
function resetContactForm() {
  const wrap    = document.getElementById('contactFormWrap');
  const success = document.getElementById('formSuccess');
  if (wrap)    wrap.style.display = '';
  if (success) success.classList.remove('show');
  // Also clear all input values so it reads as a fresh form. Pre-fill
  // logic in enquireFor() runs after this on the same tick, so plan /
  // course / report context still lands in the message field.
  ['cfName','cfLast','cfEmail','cfPhone','cfMessage'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  const subj = document.getElementById('cfSubject');
  if (subj) subj.selectedIndex = 0;
}

function toggleMenu() {
  const mm  = document.getElementById('mobileMenu');
  const nav = document.getElementById('mainNav');
  mm.classList.toggle('open');
  if (nav) nav.classList.toggle('menu-open', mm.classList.contains('open'));
}

function toggleMobGroup(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const willOpen = !el.classList.contains('open');
  // Close every other mobile-menu group first so only one can be open at a time.
  document.querySelectorAll('.mob-group').forEach(g => {
    if (g !== el) g.classList.remove('open');
  });
  el.classList.toggle('open', willOpen);
  // After the expand animation, scroll the open accordion fully into view
  // so the last item is never hidden below the fold.
  if (willOpen) {
    setTimeout(() => {
      el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 360);
  }
}

/* ===== SIDEBAR NAV — CALLS =====
   callsMeta lives in data.js. */
function snavSelectCall(type, el) {
  document.querySelectorAll('#page-calls .snav-item').forEach(i => i.classList.remove('active'));
  el.classList.add('active');
  const m = callsMeta[type];
  document.getElementById('callsTitle').textContent = m.title;
  document.getElementById('callsBreadcrumb').textContent = m.crumb;
  document.getElementById('callsDesc').textContent = m.desc;
  renderCalls(type);
}

/* ===== SIDEBAR NAV — TOOLS =====
   TOOL_TYPES lives in data.js. */
function snavSelectTool(type, el) {
  document.querySelectorAll('#page-tools .snav-item').forEach(i => i.classList.remove('active'));
  el.classList.add('active');
  TOOL_TYPES.forEach(t => {
    const panel = document.getElementById('toolContent-' + t);
    if (panel) panel.style.display = (t === type) ? 'block' : 'none';
  });
}

/* called from navbar dropdown / footer. `which` is a tool id in TOOL_TYPES
   (brokerage, options, average, sip, returns, currency, emi). */
function setTool(which) {
  showPage('tools');
  const type = (typeof TOOL_TYPES !== 'undefined' && TOOL_TYPES.indexOf(which) !== -1) ? which : 'brokerage';
  const el = document.getElementById('snav-' + type);
  if (el) snavSelectTool(type, el);
}

/* ===== + / – EXPAND INLINE (child items) ===== */
function snavToggle(event, subId, itemId) {
  event.stopPropagation();
  const sub  = document.getElementById(subId);
  const item = document.getElementById(itemId);
  const plus = item.querySelector('.snav-plus');
  const isOpen = sub.classList.contains('open');
  const page = item.closest('.page');
  page.querySelectorAll('.snav-sub').forEach(s => s.classList.remove('open'));
  page.querySelectorAll('.snav-item').forEach(i => {
    const p = i.querySelector('.snav-plus'); if (p) p.textContent = '+';
    i.classList.remove('expanded');
  });
  if (!isOpen) {
    sub.classList.add('open');
    item.classList.add('expanded');
    plus.textContent = '−';
  }
}

/* ===== + / – PARENT SECTION TOGGLE ===== */
function snavToggleSection(headerId, bodyId) {
  const header = document.getElementById(headerId);
  const body   = document.getElementById(bodyId);
  const plus   = header.querySelector('.snav-section-plus');
  const isOpen = body.classList.contains('open');
  if (isOpen) {
    body.classList.remove('open');
    header.classList.remove('open');
    plus.textContent = '+';
  } else {
    body.classList.add('open');
    header.classList.add('open');
    plus.textContent = '−';
  }
}

/* ===== CAROUSEL JS ===== */
let carIdx = 0;
const carTotal = 5;
let carTimer;
function carBuildDots() {
  const d = document.getElementById('carDots');
  if (!d) return;
  d.innerHTML = '';
  for (let i = 0; i < carTotal; i++) {
    const dot = document.createElement('div');
    dot.className = 'car-dot' + (i === 0 ? ' active' : '');
    dot.onclick = () => carGoTo(i);
    d.appendChild(dot);
  }
}
function carGoTo(i) {
  carIdx = i;
  const track = document.getElementById('carouselTrack');
  if (track) track.style.transform = `translateX(-${carIdx * 100}%)`;
  document.querySelectorAll('.car-dot').forEach((d, idx) => d.classList.toggle('active', idx === carIdx));
}
function carNext() { carGoTo((carIdx + 1) % carTotal); }
function carPrev() { carGoTo((carIdx - 1 + carTotal) % carTotal); }
function startCarousel() {
  carBuildDots();
  clearInterval(carTimer);
  carTimer = setInterval(carNext, 5000);
}

/* ===== MINI LEAD FORM ===== */
async function submitMiniForm() {
  const name = document.getElementById('mfName').value.trim();
  const phone = document.getElementById('mfPhone').value.trim();
  const email = document.getElementById('mfEmail').value.trim();
  const interest = document.getElementById('mfInterest').value;
  if (!name || !phone || !email || !interest) {
    alert('Please fill Name, Phone, Email and select your interest.'); return;
  }
  const fd = new FormData();
  fd.append('source', 'lead');
  fd.append('name', name);
  fd.append('phone', phone);
  fd.append('email', email);
  fd.append('interest', interest);
  try {
    const res = await fetch('/contact.php', { method: 'POST', body: fd, signal: AbortSignal.timeout(15000) });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok !== true) throw new Error(data.error || 'send failed');
  } catch (e) {
    alert('Could not send right now. Please WhatsApp us at +91 74670 94575 instead.');
    return;
  }
  document.getElementById('miniFormFields').style.display = 'none';
  const s = document.getElementById('miniFormSuccess');
  s.style.display = 'block'; s.classList.add('show');
}


