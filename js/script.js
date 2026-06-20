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
  const page = PATH_TO_PAGE[path] || 'home';
  showPage(page, true);
});

// Resolve the initial page from the URL once everything is parsed.
function resolveInitialPage() {
  const path = window.location.pathname;
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
  if (id === 'performance') loadPerformance(currentPerfType);
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

/* called from navbar dropdown */
function setTool(which) {
  showPage('tools');
  const type = which === 'emi' ? 'emi'
            : which === 'sip' ? 'sip'
            : 'pos';
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


/* ================================================================
   LIVE MARKET TICKER  — Yahoo Finance v8/chart via local PHP proxy
   Data is 15-min delayed (Yahoo's standard for free quotes).
   Server-side proxy at /proxy.php avoids CORS and rate-limit issues.
   PROXY and TICKER_SYMBOLS live in data.js.
   ================================================================ */
function isMarketOpen() {
  const ist = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Kolkata' }));
  const day = ist.getDay();
  const mins = ist.getHours() * 60 + ist.getMinutes();
  return day >= 1 && day <= 5 && mins >= 540 && mins <= 930; // Mon-Fri, 9:00-15:30 IST
}

function buildTickerHTML(quotes) {
  const items = quotes.map(q => {
    const isUp = q.changePct >= 0;
    const arrow = isUp ? '▲' : '▼';
    const dirClass = isUp ? 'up' : 'down';
    const priceFmt = q.price.toLocaleString('en-IN', { maximumFractionDigits: 2 });
    const pctFmt = Math.abs(q.changePct).toFixed(2) + '%';
    return '<span class="ticker-item">' +
      '<span class="t-symbol">' + q.label + '</span>' +
      '<span class="t-price">₹' + priceFmt + '</span>' +
      '<span class="t-' + dirClass + '">' + arrow + ' ' + pctFmt + '</span>' +
    '</span>';
  }).join('');
  return items + items; // duplicate for seamless infinite scroll
}

async function fetchOneSymbol(sym) {
  const yahooURL = 'https://query1.finance.yahoo.com/v8/finance/chart/' + encodeURIComponent(sym.yahoo) + '?interval=1d&range=1d';
  const url = PROXY + encodeURIComponent(yahooURL);
  const res = await fetch(url, { signal: AbortSignal.timeout(10000) });
  if (!res.ok) throw new Error('proxy ' + res.status);
  const data = await res.json();
  const meta = data && data.chart && data.chart.result && data.chart.result[0] && data.chart.result[0].meta;
  if (!meta) return null;
  const price = meta.regularMarketPrice;
  const prev  = meta.chartPreviousClose != null ? meta.chartPreviousClose : meta.previousClose;
  if (price == null || prev == null || price <= 0) return null;
  const changePct = ((price - prev) / prev) * 100;
  return { label: sym.label, price: price, changePct: changePct };
}

async function fetchTickerOnce() {
  const results = await Promise.all(
    TICKER_SYMBOLS.map(s => fetchOneSymbol(s).catch(() => null))
  );
  return results.filter(q => q && q.price > 0);
}

async function refreshTicker() {
  try {
    const quotes = await fetchTickerOnce();
    if (quotes.length > 0) {
      const inner = document.getElementById('tickerInner');
      if (inner) inner.innerHTML = buildTickerHTML(quotes);
      const status = document.getElementById('marketStatus');
      if (status) {
        const open = isMarketOpen();
        status.textContent = open ? 'NSE • LIVE' : 'NSE • CLOSED';
        status.style.color = open ? 'var(--green)' : 'var(--grey)';
      }
      return;
    }
  } catch (e) { /* fall through */ }
  showStaticTicker();
}

function showStaticTicker() {
  const fallback = [
    { label: 'NIFTY 50',   price: 24380.25, changePct: 0.87 },
    { label: 'SENSEX',     price: 80218.37, changePct: 0.79 },
    { label: 'BANKNIFTY',  price: 52414.60, changePct: 1.12 },
    { label: 'RELIANCE',   price: 2913.50,  changePct: 1.34 },
    { label: 'TCS',        price: 4021.80,  changePct: 0.62 },
    { label: 'INFOSYS',    price: 1752.40,  changePct: -0.38 },
    { label: 'HDFCBANK',   price: 1648.90,  changePct: 1.08 },
    { label: 'ICICIBANK',  price: 1378.25,  changePct: 0.93 },
    { label: 'WIPRO',      price: 487.60,   changePct: 0.44 },
    { label: 'BAJFINANCE', price: 7182.35,  changePct: 2.17 },
    { label: 'TATAMOTORS', price: 854.30,   changePct: 1.56 },
    { label: 'SBIN',       price: 792.45,   changePct: 0.71 },
    { label: 'AXISBANK',   price: 1218.60,  changePct: 0.85 },
    { label: 'L&T',        price: 3640.70,  changePct: 0.49 },
    { label: 'MARUTI',     price: 12140.50, changePct: 1.22 }
  ];
  const inner = document.getElementById('tickerInner');
  if (inner) inner.innerHTML = buildTickerHTML(fallback);
  const status = document.getElementById('marketStatus');
  if (status) {
    status.textContent = 'NSE • SAMPLE DATA';
    status.style.color = 'var(--grey)';
  }
}

function initLiveTicker() {
  refreshTicker();
  // 60s refresh during market hours only (Mon-Fri 9:00-15:30 IST). Outside
  // those hours the previous close stays on screen — no point hammering Yahoo.
  setInterval(() => {
    if (isMarketOpen()) refreshTicker();
  }, 60000);
}


/* ================================================================
   FII / DII LIVE TALLY  — NSE India API via CORS proxy
   ================================================================ */
function fmtCr(val) {
  const n = parseFloat(val);
  if (isNaN(n)) return '—';
  const abs = Math.abs(n);
  const str = abs >= 10000
    ? (abs / 100).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
    : abs.toLocaleString('en-IN', { maximumFractionDigits: 2 });
  return (n < 0 ? '−' : '+') + '₹' + str + ' Cr';
}
function fmtCrAbs(val) {
  const n = Math.abs(parseFloat(val));
  // Unit is already in the "Buy (₹Cr)" / "Sell (₹Cr)" label below, so we
  // omit it here to keep the value compact (was breaking onto two lines on
  // narrower cards and pushing them to unequal heights).
  return '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function applyFiiDii(fii, dii) {
  // Helper: a buy/sell value is "available" if it's a finite, non-null number.
  // We treat null/undefined as missing (Groww sometimes omits buy/sell). 0 is
  // a valid value (a no-trade day) and stays "available".
  const has = v => v !== null && v !== undefined && !isNaN(parseFloat(v));
  // Hide a card's Buy/Sell sub-row entirely when neither value is available,
  // so the tile doesn't show stranded "—" placeholders.
  const setBsRowVisible = (cardId, visible) => {
    const card = document.getElementById(cardId);
    if (!card) return;
    const row = card.querySelector('.fiidii-bsrow');
    if (row) row.style.display = visible ? '' : 'none';
    const bar = card.querySelector('.fiidii-bar-wrap');
    if (bar) bar.style.display = visible ? '' : 'none';
  };

  // FII
  const fiiNet = parseFloat(fii.netValue || fii.NET_VALUE || 0);
  const fiiBuyRaw  = fii.buyValue  ?? fii.BUY_VALUE;
  const fiiSellRaw = fii.sellValue ?? fii.SELL_VALUE;
  const fiiBuy  = has(fiiBuyRaw)  ? parseFloat(fiiBuyRaw)  : 0;
  const fiiSell = has(fiiSellRaw) ? parseFloat(fiiSellRaw) : 0;
  const fiiPos = fiiNet >= 0;
  const fiiHasBs = has(fiiBuyRaw) || has(fiiSellRaw);
  document.getElementById('fiiNet').textContent   = fmtCr(fiiNet);
  document.getElementById('fiiNet').className     = 'fiidii-net ' + (fiiPos ? 'pos' : 'neg');
  document.getElementById('fiiNetLabel').textContent = fiiPos ? 'Net Buy' : 'Net Sell';
  if (fiiHasBs) {
    document.getElementById('fiiBuy').textContent  = has(fiiBuyRaw)  ? fmtCrAbs(fiiBuy)  : '—';
    document.getElementById('fiiSell').textContent = has(fiiSellRaw) ? fmtCrAbs(fiiSell) : '—';
  }
  setBsRowVisible('fiiCard', fiiHasBs);
  const fiiBadge = document.getElementById('fiiBadge');
  fiiBadge.textContent = fiiPos ? 'NET BUY' : 'NET SELL';
  fiiBadge.className   = 'fiidii-net-badge ' + (fiiPos ? 'buy-badge' : 'sell-badge');
  const fiiBuyPct = fiiBuy + fiiSell > 0 ? (fiiBuy / (fiiBuy + fiiSell) * 100) : 50;
  document.getElementById('fiiBarBuy').style.width = fiiBuyPct + '%';

  // DII
  const diiNet = parseFloat(dii.netValue || dii.NET_VALUE || 0);
  const diiBuyRaw  = dii.buyValue  ?? dii.BUY_VALUE;
  const diiSellRaw = dii.sellValue ?? dii.SELL_VALUE;
  const diiBuy  = has(diiBuyRaw)  ? parseFloat(diiBuyRaw)  : 0;
  const diiSell = has(diiSellRaw) ? parseFloat(diiSellRaw) : 0;
  const diiPos = diiNet >= 0;
  const diiHasBs = has(diiBuyRaw) || has(diiSellRaw);
  document.getElementById('diiNet').textContent   = fmtCr(diiNet);
  document.getElementById('diiNet').className     = 'fiidii-net ' + (diiPos ? 'pos' : 'neg');
  document.getElementById('diiNetLabel').textContent = diiPos ? 'Net Buy' : 'Net Sell';
  if (diiHasBs) {
    document.getElementById('diiBuy').textContent  = has(diiBuyRaw)  ? fmtCrAbs(diiBuy)  : '—';
    document.getElementById('diiSell').textContent = has(diiSellRaw) ? fmtCrAbs(diiSell) : '—';
  }
  setBsRowVisible('diiCard', diiHasBs);
  const diiBadge = document.getElementById('diiBadge');
  diiBadge.textContent = diiPos ? 'NET BUY' : 'NET SELL';
  diiBadge.className   = 'fiidii-net-badge ' + (diiPos ? 'buy-badge' : 'sell-badge');
  const diiBuyPct = diiBuy + diiSell > 0 ? (diiBuy / (diiBuy + diiSell) * 100) : 50;
  document.getElementById('diiBarBuy').style.width = diiBuyPct + '%';

  // Sentiment
  const totalNet = fiiNet + diiNet;
  const maxSwing = 8000; // ₹8000Cr considered extreme
  let score = 50 + (totalNet / maxSwing) * 50;
  score = Math.min(98, Math.max(2, score));
  const sv = document.getElementById('sentimentVal');
  const sd = document.getElementById('sentimentDesc');
  const sf = document.getElementById('sentimentFill');
  sf.style.left = score + '%';
  if (score >= 62) {
    sv.textContent = 'Bullish'; sv.className = 'sentiment-val bullish';
    sd.textContent = 'Institutions net buying. Positive flow supports market upside.';
  } else if (score <= 38) {
    sv.textContent = 'Bearish'; sv.className = 'sentiment-val bearish';
    sd.textContent = 'Institutions net selling. Caution advised in near term.';
  } else {
    sv.textContent = 'Neutral'; sv.className = 'sentiment-val neutral';
    sd.textContent = 'Mixed institutional flow. Watch for direction clarity.';
  }
}

/* ---- Sample series shared by the summary card AND the 30-day
   table, so both panels always agree on the latest figures.
   When you switch to a real cookie-aware backend, this is the
   single function to replace. SAMPLE_LATEST lives in data.js. */
function buildSampleFiiDiiSeries() {
  const series = [];
  const today = new Date();
  let seed = 1234;
  const rnd = () => { seed = (seed * 16807 + 0) % 2147483647; return (seed % 10000) / 100; };

  // Walk backwards from today, skipping weekends, until we have 22 rows.
  const cursor = new Date(today);
  while (series.length < 22) {
    if (cursor.getDay() !== 0 && cursor.getDay() !== 6) {
      series.push({
        date: cursor.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }),
        fii: ((rnd() - 50) * 80).toFixed(2),
        dii: ((rnd() - 40) * 60).toFixed(2)
      });
    }
    cursor.setDate(cursor.getDate() - 1);
  }

  // Pin the most-recent row (index 0) to the headline summary numbers
  // so the table's latest entry == the summary card.
  series[0].fii = SAMPLE_LATEST.fii.net.toFixed(2);
  series[0].dii = SAMPLE_LATEST.dii.net.toFixed(2);

  return series;
}

/* Sets the date label on the section header AND on each FII / DII card so
   the user can always see WHICH trading day the numbers belong to (NSE
   publishes FII/DII T+1 in the evening, so "today's" data may actually be
   yesterday's session). `kind` = 'live' | 'stale' | 'sample'. */
function setFiiDiiDate(dateStr, kind) {
  const headEl  = document.getElementById('fiidiiDate');
  const fiiEl   = document.getElementById('fiiDate');
  const diiEl   = document.getElementById('diiDate');
  const tableEl = document.getElementById('histTableDate');
  const label   = dateStr || 'today';
  let headTxt, cardTxt;
  if (kind === 'stale') {
    headTxt = 'Stale (NSE unreachable) — last good: ' + label + ' • Source: NSE India';
    cardTxt = 'Stale — ' + label;
  } else if (kind === 'sample') {
    headTxt = 'Sample data — ' + label;
    cardTxt = 'Sample — ' + label;
  } else {
    headTxt = 'As of ' + label + ' • Source: NSE India';
    cardTxt = 'As of ' + label;
  }
  if (headEl) headEl.textContent = headTxt;
  [fiiEl, diiEl, tableEl].forEach(el => {
    if (!el) return;
    el.textContent = cardTxt;
    el.classList.toggle('stale', kind === 'stale' || kind === 'sample');
  });
}

async function fetchFiiDii() {
  // 1. Try the cookie-aware server-side endpoint first.
  try {
    const res = await fetch('/fii-dii.php?cb=' + Date.now(), { signal: AbortSignal.timeout(15000) });
    if (res.ok) {
      const payload = await res.json();
      const arr = payload && Array.isArray(payload.data) ? payload.data : null;
      if (arr && arr.length > 0) {
        const upper = s => (s || '').toString().toUpperCase();
        const fii = arr.find(r => upper(r.category || r.CATEGORY).includes('FII'));
        const dii = arr.find(r => upper(r.category || r.CATEGORY).includes('DII'));
        if (fii && dii) {
          const dateStr = fii.date || fii.DATE || payload.fetched_date || '';
          setFiiDiiDate(dateStr, payload.stale ? 'stale' : 'live');
          applyFiiDii(fii, dii);
          return;
        }
      }
    }
  } catch (e) { /* fall through to sample */ }

  // 2. Fallback to the same sample series the 30-day table uses.
  applyFiiDii(
    { buyValue: SAMPLE_LATEST.fii.buy.toFixed(2), sellValue: SAMPLE_LATEST.fii.sell.toFixed(2), netValue: SAMPLE_LATEST.fii.net.toFixed(2) },
    { buyValue: SAMPLE_LATEST.dii.buy.toFixed(2), sellValue: SAMPLE_LATEST.dii.sell.toFixed(2), netValue: SAMPLE_LATEST.dii.net.toFixed(2) }
  );
  const series = buildSampleFiiDiiSeries();
  const latestDateLabel = (series[0] && series[0].date) || new Date().toLocaleDateString('en-IN', { day:'2-digit', month:'short' });
  const yr = new Date().getFullYear();
  setFiiDiiDate(latestDateLabel + ' ' + yr, 'sample');
}

function initFiiDii() {
  // History is the single source of truth for BOTH the tiles and the 30-day
  // table — so the headline numbers always reconcile with the table's first row.
  // fetchFiiDii() (NSE) only runs as a fallback if Groww's history is unreachable.
  fetchFiiDiiHistory();
  fetchTopMovers();
  fetchIndices();
  // All live widgets refresh every 60s during NSE hours (Mon-Fri, 09:00-15:30 IST).
  // Server-side caches in each PHP endpoint protect upstream APIs (NSE/BSE/Yahoo)
  // from being hammered — clients hit our cache, not NSE directly.
  // Holidays are gated server-side via the market_open flag in each endpoint.
  setInterval(() => {
    if (!isMarketOpenIST()) return;
    fetchIndices();
    fetchTopMovers();
    fetchFiiDiiHistory(); // FII/DII publishes T+1 evening but we still poll so the new day's data appears within 60s of the 7:30 PM cutoff
  }, 60 * 1000);
}

function isMarketOpenIST() {
  const nowIST = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Kolkata' }));
  const day = nowIST.getDay(); // 0 = Sun, 6 = Sat
  if (day === 0 || day === 6) return false;
  const minutes = nowIST.getHours() * 60 + nowIST.getMinutes();
  // 9:00-15:30 IST is the live session. We extend the polling window to 15:45
  // so we can capture the official closing-auction value that NSE/BSE publish
  // a few minutes AFTER the bell. Without this, the site stays on the last
  // intraday tick and disagrees with Zerodha/NSE by 5-10 points all evening.
  return minutes >= (9 * 60) && minutes <= (15 * 60 + 45); // 9:00-15:45 IST
}

/* ===== Top 5 Gainers / Top 5 Losers (Nifty 50) =====
   Hits /gainers-losers.php which does the cookie handshake
   server-side. Refreshed every 5 min during market hours by
   the auto-poll set up in DOMContentLoaded. */
async function fetchTopMovers() {
  try {
    const res = await fetch('/gainers-losers.php?cb=' + Date.now(), { signal: AbortSignal.timeout(15000) });
    if (!res.ok) throw new Error('http ' + res.status);
    const data = await res.json();
    if (!data || !Array.isArray(data.gainers) || !Array.isArray(data.losers)) throw new Error('bad payload');
    renderMovers('gainersBody', data.gainers, 'up');
    renderMovers('losersBody',  data.losers,  'down');
    const dateEl = document.getElementById('moversDate');
    if (dateEl) {
      const at = data.fetched_at ? new Date(data.fetched_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '';
      const stale = !!data.stale;
      const tag = data.market_open ? 'LIVE' : 'CLOSED';
      dateEl.textContent = (stale ? 'Stale — ' : 'Updated ') + at + ' • ' + tag + ' • Source: NSE India';
    }
  } catch (e) {
    const fail = '<tr><td colspan="3" style="text-align:center; padding:14px; color:var(--grey); font-size:11.5px">Could not load NSE data.</td></tr>';
    const g = document.getElementById('gainersBody'); if (g) g.innerHTML = fail;
    const l = document.getElementById('losersBody');  if (l) l.innerHTML = fail;
  }
}

/* ===== Hero indices: Nifty 50 + BSE Sensex =====
   Hits /indices.php which pulls from Yahoo Finance and caches
   server-side. Refreshed every 5 min during market hours. */
async function fetchIndices() {
  try {
    const res = await fetch('/indices.php?cb=' + Date.now(), { signal: AbortSignal.timeout(15000) });
    if (!res.ok) throw new Error('http ' + res.status);
    const data = await res.json();
    if (!data || !data.nifty || !data.sensex) throw new Error('bad payload');
    renderIndex('niftyPrice',  'niftyChange',  data.nifty);
    renderIndex('sensexPrice', 'sensexChange', data.sensex);
    // Both cards share the server fetch time so they always display the same
    // timestamp. NSE / BSE tick at different cadences, so showing per-source
    // times confused users into thinking one feed was lagging.
    const sharedTs = data.fetched_at || data.nifty.updated || data.sensex.updated;
    setCardTime('niftyTime',  sharedTs, data.market_open);
    setCardTime('sensexTime', sharedTs, data.market_open);
  } catch (e) {
    // Silent fail — cards keep whatever they had; per-card "—" placeholders
    // surface the issue to the user without a noisy meta line.
  }
}

function setCardTime(elId, isoStr, marketOpen) {
  const el = document.getElementById(elId);
  if (!el) return;
  if (!marketOpen) {
    // Market shut → show the canonical NSE/BSE close (15:30 IST) regardless
    // of what the upstream reports (NSE often emits 16:00 from the closing
    // auction tick, which confuses users who expect "3:30 PM").
    el.textContent = '3:30 PM';
    el.classList.add('closed');
    return;
  }
  if (!isoStr) { el.textContent = '—'; el.classList.remove('closed'); return; }
  // Live market: show the source's actual tick time, IST 12-hour.
  el.textContent = new Date(isoStr).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
  el.classList.remove('closed');
}

function renderIndex(priceId, changeId, idx) {
  const p = document.getElementById(priceId);
  const c = document.getElementById(changeId);
  if (p) p.textContent = idx.price.toLocaleString('en-IN', { maximumFractionDigits: 2 });
  if (c) {
    const up  = idx.change >= 0;
    // Sign on the points (+298.15), but the % already carries its own sign when negative,
    // so we omit a redundant + when positive — matches the Groww-style "+298.15 (1.24%)".
    const pts = (up ? '+' : '') + idx.change.toFixed(2);
    const pct = idx.pChange.toFixed(2) + '%';
    c.textContent = pts + ' (' + pct + ')';
    c.classList.toggle('idx-up',   up);
    c.classList.toggle('idx-down', !up);
  }
}

function renderMovers(bodyId, rows, direction) {
  const tbody = document.getElementById(bodyId);
  if (!tbody) return;
  if (!rows || rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:14px; color:var(--grey); font-size:11.5px">No data.</td></tr>';
    return;
  }
  const cls = direction === 'up' ? 'mv-pct-up' : 'mv-pct-down';
  tbody.innerHTML = rows.map(r => {
    const sym = escapeHtml((r.symbol || '?').toString());
    const price = r.price != null ? r.price.toLocaleString('en-IN', { maximumFractionDigits: 2 }) : '—';
    const pct = r.pChange != null ? ((r.pChange >= 0 ? '+' : '') + r.pChange.toFixed(2) + '%') : '—';
    return '<tr>' +
      '<td class="mv-symbol">' + sym + '</td>' +
      '<td class="mv-price">' + price + '</td>' +
      '<td class="' + cls + '">' + pct + '</td>' +
    '</tr>';
  }).join('');
}

/* ---- 30-day historical table — uses the same shared series. ---- */
function genFallbackHistory() {
  // Returned in oldest -> newest order so renderHistTable's reverse() works correctly.
  return buildSampleFiiDiiSeries().slice().reverse();
}

function renderHistTable(rows) {
  const maxAbs = Math.max(...rows.map(r => Math.max(Math.abs(+r.fii), Math.abs(+r.dii))));
  const tbody = document.getElementById('histTableBody');
  if (!tbody) return;
  tbody.innerHTML = rows.slice().reverse().map(r => {
    const fv = +r.fii, dv = +r.dii;
    const fPos = fv >= 0, dPos = dv >= 0;
    const fW = Math.round(Math.abs(fv) / maxAbs * 40);
    const dW = Math.round(Math.abs(dv) / maxAbs * 40);
    const fStr = (fPos ? '+' : '') + (+fv).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    const dStr = (dPos ? '+' : '') + (+dv).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    return `<tr>
      <td class="td-date">${escapeHtml(r.date)}</td>
      <td class="${fPos ? 'td-pos' : 'td-neg'}">${fStr}</td>
      <td class="${dPos ? 'td-pos' : 'td-neg'}">${dStr}</td>
      <td>
        <div class="hist-flow-bar">
          <div class="hist-fii-bar" style="width:${fW}px; opacity:${fPos?1:0.45}"></div>
          <div class="hist-dii-bar" style="width:${dW}px; opacity:${dPos?1:0.45}"></div>
        </div>
      </td>
    </tr>`;
  }).join('');
}

async function fetchFiiDiiHistory() {
  // 1. Try Groww-backed history endpoint. This drives BOTH the table and the tiles.
  try {
    const res = await fetch('/fii-dii-history.php?cb=' + Date.now(), { signal: AbortSignal.timeout(20000) });
    if (res.ok) {
      const payload = await res.json();
      const rows = payload && Array.isArray(payload.rows) ? payload.rows : null;
      if (rows && rows.length > 0) {
        // Convert "24-Apr-2026" to "24 Apr" for the table.
        const formatted = rows.map(r => {
          let label = r.date;
          if (typeof label === 'string') {
            const parts = label.split(/[-\s]/);
            if (parts.length >= 2) label = parts[0] + ' ' + parts[1];
          }
          return {
            date: label,
            fii:  (r.fii != null ? Number(r.fii) : 0).toFixed(2),
            dii:  (r.dii != null ? Number(r.dii) : 0).toFixed(2)
          };
        });
        // renderHistTable expects oldest-first (it does its own reverse).
        formatted.reverse();
        renderHistTable(formatted);

        // Drive the tiles from row[0] of the SAME data so headline tiles and
        // table's first row are guaranteed to match.
        const latest = rows[0];
        applyFiiDii(
          { netValue: latest.fii, buyValue: latest.fiiBuy, sellValue: latest.fiiSell },
          { netValue: latest.dii, buyValue: latest.diiBuy, sellValue: latest.diiSell }
        );
        setFiiDiiDate(latest.date || payload.fetched_date || '', payload.stale ? 'stale' : 'live');

        const statusEl = document.getElementById('histLoadStatus');
        if (statusEl) {
          const stale = !!payload.stale;
          const src = payload.source || 'Live';
          statusEl.textContent = src + ' data' + (stale ? ' (stale cache)' : '');
        }
        return;
      }
    }
  } catch (e) { /* fall through to NSE/sample */ }

  // 2. Fallback path: try NSE for the tiles, and use the sample series for the table.
  fetchFiiDii();
  renderHistTable(genFallbackHistory());
  const statusEl = document.getElementById('histLoadStatus');
  if (statusEl) statusEl.textContent = 'Sample data';
}

function toggleHistTable() {
  /* table is always visible — this was an earlier toggle, no-op now */
}

window.addEventListener('DOMContentLoaded', () => {
  const currentYear = new Date().getFullYear();
  const yrEl = document.getElementById('footerYear');
  if (yrEl) yrEl.textContent = currentYear;
  const yrElMob = document.getElementById('footerYearMob');
  if (yrElMob) yrElMob.textContent = currentYear;
  resolveInitialPage();
  renderReports('all');
  renderCourses('all');
  renderPlans();
  renderCalls('intraday');
  fetchIpo('open');
  observeFadeIns();
  startCarousel();
  initLiveTicker();
  initFiiDii();
  // set initial + / − state for section headers
  const shortPlus = document.getElementById('secplus-short');
  if (shortPlus) shortPlus.textContent = '−';
  const toolsPlus = document.getElementById('secplus-tools');
  if (toolsPlus) toolsPlus.textContent = '−';
});

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

/* ===== BLOG — category filter (cards have data-blog-cat="...") ===== */
function filterBlogs(cat, btn) {
  document.querySelectorAll('#blogTabs .tab').forEach(t => t.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.querySelectorAll('#blogGrid .blog-card').forEach(card => {
    const c = card.getAttribute('data-blog-cat');
    card.style.display = (cat === 'all' || c === cat) ? '' : 'none';
  });
}

/* ===== CALLS — render only (callsData lives in data.js) ===== */
let currentCallsGroup = 'short';

function toggleAcc(groupId) {
  const group = document.getElementById(groupId);
  const isOpen = group.classList.contains('open');
  // close all
  document.querySelectorAll('.acc-group').forEach(g => g.classList.remove('open'));
  // open this one if it wasn't open, else leave closed
  if (!isOpen) group.classList.add('open');
}

function selectCall(type, groupId, btn) {
  // ensure parent group is open
  const group = document.getElementById(groupId);
  document.querySelectorAll('.acc-group').forEach(g => g.classList.remove('open'));
  group.classList.add('open');
  // clear all active child buttons
  document.querySelectorAll('.acc-child').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  renderCalls(type);
}

function filterCalls(type, btn, group) {
  selectCall(type, 'acc' + (group === 'short' ? 'Short' : 'Long'), btn);
}

function switchCallsGroup(group) {
  const id = group === 'short' ? 'accShort' : 'accLong';
  document.querySelectorAll('.acc-group').forEach(g => g.classList.remove('open'));
  document.getElementById(id).classList.add('open');
  const defaultType = group === 'short' ? 'intraday' : 'value';
  renderCalls(defaultType);
}

/* ===== PERFORMANCE — closed track record (performance.php) +
   live ongoing calls (calls.php), gated behind a WhatsApp CTA. ===== */
let currentPerfType = '';
let perfMode = 'achieved';                 // 'achieved' | 'live'
const PERF_WA_NUMBER = '917467094575';     // RootNivesh WhatsApp

function setPerfMode(mode, btn) {
  perfMode = mode;
  document.querySelectorAll('#perfModeTabs .perf-mode').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  const achievedWrap = document.getElementById('perfAchievedWrap');
  const liveWrap = document.getElementById('perfLiveWrap');
  if (achievedWrap) achievedWrap.style.display = (mode === 'achieved') ? '' : 'none';
  if (liveWrap) liveWrap.style.display = (mode === 'live') ? '' : 'none';
  // Re-run the active segment filter for the new mode.
  (mode === 'achieved' ? loadPerformance : loadLivePerf)(currentPerfType);
}

function filterPerformance(type, btn) {
  currentPerfType = type;
  document.querySelectorAll('#perfTabs .tab').forEach(t => t.classList.remove('active'));
  if (btn) btn.classList.add('active');
  (perfMode === 'achieved' ? loadPerformance : loadLivePerf)(type);
}

function loadPerformance(type) {
  type = type || '';
  const bodyEl = document.getElementById('perfTableBody');
  if (!bodyEl) return;

  fetch('/performance.php' + (type ? ('?type=' + encodeURIComponent(type)) : ''), { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => { renderPerfTable(d.calls || []); })
    .catch(() => {
      bodyEl.innerHTML = '<tr><td colspan="9" style="text-align:center; color:var(--grey); padding:24px">Could not load performance right now. Please try again shortly.</td></tr>';
    });
}

function perfWaUrl(c) {
  const msg = `Hi RootNivesh, I want the entry & target levels for your LIVE ${c.symbol} ${String(c.call_type).toUpperCase()} call. Please add me.`;
  return 'https://wa.me/' + PERF_WA_NUMBER + '?text=' + encodeURIComponent(msg);
}

function loadLivePerf(type) {
  type = type || '';
  const bodyEl = document.getElementById('perfLiveBody');
  if (!bodyEl) return;
  bodyEl.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--grey); padding:24px">Loading…</td></tr>';

  fetch('/calls.php?limit=100' + (type ? ('&type=' + encodeURIComponent(type)) : ''), { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => { renderLivePerf((d.calls || []).filter(c => c.status === 'open')); })
    .catch(() => {
      bodyEl.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--grey); padding:24px">Could not load live calls right now. Please try again shortly.</td></tr>';
    });
}

function renderLivePerf(calls) {
  const body = document.getElementById('perfLiveBody');
  if (!body) return;
  if (!calls.length) {
    body.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--grey); padding:24px">No live calls running in this segment right now. <a href="https://wa.me/' + PERF_WA_NUMBER + '" target="_blank" rel="noopener" style="color:var(--gold)">Join on WhatsApp →</a></td></tr>';
    return;
  }
  const fmtDate = iso => { const dt = new Date((iso || '').replace(' ', 'T')); return isNaN(dt) ? '' : dt.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: '2-digit' }); };
  body.innerHTML = calls.map(c => {
    const wa = perfWaUrl(c);
    // Entry & targets blurred behind a tap-to-WhatsApp lock — convert the viewer.
    const lockedCell = inner => `<td class="perf-locked" onclick="window.open('${wa}','_blank')" title="Get this level on WhatsApp"><span class="perf-blur">${inner}</span><span class="perf-lock-wa">💬</span></td>`;
    return `
    <tr>
      <td>${fmtDate(c.posted_at)}</td>
      <td><span class="perf-stock">${escapeHtml(c.symbol)}</span></td>
      <td style="text-transform:capitalize">${escapeHtml(c.call_type)}</td>
      <td><span class="perf-side ${c.action === 'BUY' ? 'buy' : 'sell'}">${escapeHtml(c.action)}</span></td>
      ${lockedCell('₹' + Number(c.entry_price).toLocaleString('en-IN'))}
      ${lockedCell(c.targets ? escapeHtml(c.targets) : '₹₹₹')}
      <td><a class="perf-wa-btn" href="${wa}" target="_blank" rel="noopener">💬 Get on WhatsApp</a></td>
    </tr>`;
  }).join('');
}


function renderPerfTable(calls) {
  const body = document.getElementById('perfTableBody');
  if (!body) return;
  if (!calls.length) {
    body.innerHTML = '<tr><td colspan="9" style="text-align:center; color:var(--grey); padding:24px">No closed calls in this segment yet.</td></tr>';
    return;
  }
  const fmtDate = iso => {
    const dt = new Date((iso || '').replace(' ', 'T'));
    return isNaN(dt) ? '—' : dt.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: '2-digit' });
  };
  const money = v => v == null || v === '' ? '—' : '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 2 });
  body.innerHTML = calls.map(c => {
    const pnl = c.pnl_pct == null ? null : parseFloat(c.pnl_pct);
    const win = pnl != null && pnl >= 0;
    const resultLabel = c.status === 'target_hit' ? 'Target Achieved' : c.status === 'stop_hit' ? 'Stop-loss' : 'Closed';

    return `
    <tr>
      <td>${fmtDate(c.exit_at || c.posted_at)}</td>
      <td><span class="perf-stock">${escapeHtml(c.symbol)}</span></td>
      <td style="text-transform:capitalize">${escapeHtml(c.call_type)}</td>
      <td><span class="perf-side ${c.action === 'BUY' ? 'buy' : 'sell'}">${escapeHtml(c.action)}</span></td>
      <td>${money(c.entry_price)}</td>
      <td>${c.targets ? escapeHtml(c.targets) : (c.target_price != null ? money(c.target_price) : '—')}</td>
      <td>${money(c.exit_price)}</td>
      <td><span class="perf-result ${win ? 'win' : 'loss'}">${resultLabel}</span></td>
      <td class="perf-pnl ${win ? 'pos' : 'neg'}">${pnl == null ? '—' : (pnl >= 0 ? '+' : '') + pnl.toFixed(2) + '%'}</td>
    </tr>`;
  }).join('');
}

/* Live OPEN calls from the DB (calls.php). Fetched once per Calls-page visit. */
let liveCalls = null;
function loadLiveCalls() {
  return fetch('/calls.php?limit=100', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => { liveCalls = (d.calls || []).filter(c => c.status === 'open'); })
    .catch(() => { liveCalls = []; });
}

function renderCalls(type) {
  const grid = document.getElementById('callsGrid');
  if (!grid) return;
  // Fetch on first need (e.g. a tab clicked before the page-level load finished).
  if (liveCalls === null) {
    grid.innerHTML = '<div class="call-empty">⟳ Loading live calls…</div>';
    loadLiveCalls().then(() => renderCalls(type));
    return;
  }
  const dbtypes = (typeof SNAV_TO_DBTYPES !== 'undefined' && SNAV_TO_DBTYPES[type]) || [type];
  const data = liveCalls.filter(c => dbtypes.includes(c.call_type));
  if (!data.length) {
    grid.innerHTML = `<div class="call-empty">No active ${escapeHtml(type)} calls right now.
      <span class="call-empty-cta" onclick="scrollToPlans()">Subscribe to get them live →</span></div>`;
    return;
  }
  const fmtDate = iso => { const dt = new Date((iso || '').replace(' ', 'T')); return isNaN(dt) ? '' : dt.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: '2-digit' }); };
  const visibleCount = 1; // first card open as proof; rest blurred behind subscribe.
  grid.innerHTML = data.map((c, i) => {
    const isBuy = c.action === 'BUY';
    const en = parseFloat(c.entry_price);
    const tg = c.target_price == null ? null : parseFloat(c.target_price);
    const sl = c.stop_loss == null ? null : parseFloat(c.stop_loss);
    let rr = '—', ret = '—', retPos = true;
    if (en > 0 && tg != null) {
      const reward = isBuy ? tg - en : en - tg;
      const g = reward / en * 100; retPos = g >= 0;
      ret = (g >= 0 ? '+' : '') + g.toFixed(2) + '%';
      if (sl != null) { const risk = isBuy ? en - sl : sl - en; if (risk > 0) rr = '1:' + (reward / risk).toFixed(2); }
    }
    const money = v => '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    const targetDisp = c.targets ? escapeHtml(c.targets) : (tg != null ? money(tg) : '—');
    const slDisp = c.stop_losses ? escapeHtml(c.stop_losses) : (sl != null ? money(sl) : '—');
    const locked = i >= visibleCount;
    const wrapperAttrs = locked
      ? ' class="call-card call-card-locked" role="button" tabindex="0" onclick="scrollToPlans()" onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();scrollToPlans();}"'
      : ' class="call-card"';
    const lockOverlay = locked
      ? `<div class="call-lock-overlay">
           <span class="call-lock-icon">🔒</span>
           <span class="call-lock-title">Subscribe to view stock name</span>
           <span class="call-lock-cta">View Plans →</span>
         </div>`
      : '';
    return `
    <div${wrapperAttrs}>
      <div class="call-card-inner">
        <div>
          <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap">
            <span class="${isBuy ? 'badge-buy' : 'badge-sell'}">${escapeHtml(c.action)}</span>
            <span style="font-size:11px; color:var(--grey); text-transform:capitalize">${escapeHtml(c.call_type)}</span>
          </div>
          <div class="call-stock">${escapeHtml(c.symbol)}</div>
          <div class="call-name">${escapeHtml(c.company_name || '')}</div>
          <div class="call-details">
            <div class="call-detail">Entry: <span>${money(en)}</span></div>
            <div class="call-detail">Target: <span style="color:var(--green)">${targetDisp}</span></div>
            <div class="call-detail">SL: <span style="color:var(--red)">${slDisp}</span></div>
            <div class="call-detail">R:R <span>${rr}</span></div>
            <div class="call-detail">Date: <span>${fmtDate(c.posted_at)}</span></div>
          </div>
        </div>
        <div class="call-return">
          <div class="r ${retPos ? 'badge-pos' : 'badge-neg'}">${ret}</div>
          <small>To Target</small>
        </div>
      </div>
      ${lockOverlay}
    </div>`;
  }).join('');
}

// Smooth-scroll to the plans section. Used by the locked-call overlay
// so visitors who tap a teaser jump straight to "where to subscribe".
function scrollToPlans() {
  const el = document.getElementById('plansSection');
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ================================================================
   NEW IPO  — NSE India primary-market endpoints via /proxy.php
   - Open IPOs:     /api/ipo-current-issue
   - Upcoming IPOs: /api/all-upcoming-issues?category=ipo
   - Closed:        derived from upcoming once close date passes
                    (NSE has no separate "closed" public endpoint, so
                    we cache last-seen and show placeholder for now)
   ================================================================ */
let currentIpoTab = 'open';

// IPO_ENDPOINTS, IPO_CLOSED_DAYS, IPO_LOOKUP_CACHE_TTL, IPO_DESCRIPTIONS live in data.js.

// Normalize the past-issues feed (different field names) to the shape
// renderIpoTable expects, and keep only rows whose end date is within
// the last IPO_CLOSED_DAYS days.
function normalizeClosedIpos(rows) {
  if (!Array.isArray(rows)) return [];
  const cutoff = new Date();
  cutoff.setDate(cutoff.getDate() - IPO_CLOSED_DAYS);
  cutoff.setHours(0, 0, 0, 0);

  // "21-APR-2026" -> Date
  const parseNseDate = s => {
    if (!s || typeof s !== 'string') return null;
    const m = s.match(/^(\d{1,2})-([A-Za-z]{3})-(\d{4})$/);
    if (!m) return null;
    const months = {JAN:0,FEB:1,MAR:2,APR:3,MAY:4,JUN:5,JUL:6,AUG:7,SEP:8,OCT:9,NOV:10,DEC:11};
    const mi = months[m[2].toUpperCase()];
    if (mi == null) return null;
    return new Date(Number(m[3]), mi, Number(m[1]));
  };

  return rows
    .map(r => ({
      symbol:           r.symbol,
      companyName:      r.companyName || r.company,
      issueStartDate:   r.ipoStartDate || r.issueStartDate,
      issueEndDate:     r.ipoEndDate   || r.issueEndDate,
      series:           r.series || (r.securityType === 'IV' ? 'InvIT' : (r.securityType === 'BE' ? 'SME' : 'Mainboard')),
      issuePrice:       r.priceRange || r.issuePrice || r.priceBand,
      noOfTime:         r.noOfTime,
      _endDate:         parseNseDate(r.ipoEndDate || r.issueEndDate)
    }))
    .filter(r => r._endDate && r._endDate >= cutoff)
    .sort((a, b) => b._endDate - a._endDate);
}

function fmtIpoDate(s) {
  // NSE returns "23-Apr-2026" — pass through as-is, that matches the screenshot style
  return s || '—';
}

function fmtIssuePrice(s) {
  if (!s) return '—';
  // NSE returns "Rs.130 to Rs.135" — convert to "₹130 - ₹135"
  return s.replace(/Rs\./g, '₹').replace(/\s+to\s+/g, ' - ');
}

function ipoSubscriptionLabel(item) {
  // Active IPOs have noOfTime as a string like "3.16"
  const t = parseFloat(item.noOfTime);
  if (!isNaN(t) && t > 0) return t.toFixed(2) + 'x';
  return '—';
}

/* IPO_DESCRIPTIONS lives in data.js. Add a row there to override
   what NSE / Chittorgarh return for a specific IPO symbol. */
function renderIpoCellFromInfo(sector, about) {
  // sector/about can originate from scraped third-party HTML (Chittorgarh) —
  // always escape before injecting into innerHTML.
  return (
    '<div style="max-width:360px; line-height:1.5">' +
      '<div style="font-size:11px; font-family:\'Space Mono\',monospace; color:var(--gold); ' +
                  'text-transform:uppercase; letter-spacing:1px; margin-bottom:4px">' +
        escapeHtml(sector || '—') +
      '</div>' +
      '<div style="font-size:12.5px; color:var(--grey2)">' +
        escapeHtml(about || '') +
      '</div>' +
    '</div>'
  );
}

function ipoBusinessCell(item) {
  const sym = (item.symbol || '').toUpperCase();
  const symAttr = escapeHtml(sym);
  const info = IPO_DESCRIPTIONS[sym];
  if (info) {
    return '<div data-ipo-business="' + symAttr + '">' + renderIpoCellFromInfo(info.sector, info.about) + '</div>';
  }
  // No manual entry — render an empty placeholder. Async lookup will populate it.
  const companyAttr = escapeHtml(item.companyName || item.company || '');
  return '<div data-ipo-business="' + symAttr + '" data-needs-lookup="1" data-ipo-company="' + companyAttr + '">' +
         '<span style="font-size:12px; color:var(--grey)">Loading…</span>' +
         '</div>';
}

/* ----- Auto-fill About Company from NSE for symbols that are
        already listed. Cached in localStorage for 24h.
        IPO_LOOKUP_CACHE_TTL lives in data.js. ---------------------- */
const IPO_LOOKUP_INFLIGHT = new Map();

function readLookupCache(sym) {
  try {
    const raw = localStorage.getItem('ipo-info:' + sym);
    if (!raw) return null;
    const obj = JSON.parse(raw);
    if (Date.now() - obj.t > IPO_LOOKUP_CACHE_TTL) return null;
    return obj.v;
  } catch (e) { return null; }
}

function writeLookupCache(sym, value) {
  try {
    localStorage.setItem('ipo-info:' + sym, JSON.stringify({ t: Date.now(), v: value }));
  } catch (e) { /* storage quota — ignore */ }
}

async function fetchNseIndustry(sym) {
  const inflight = IPO_LOOKUP_INFLIGHT.get('nse:' + sym);
  if (inflight) return inflight;
  const promise = (async () => {
    const url = '/proxy.php?url=' + encodeURIComponent('https://www.nseindia.com/api/quote-equity?symbol=' + encodeURIComponent(sym));
    const res = await fetch(url, { signal: AbortSignal.timeout(10000) });
    if (!res.ok) throw new Error('http ' + res.status);
    const data = await res.json();
    const ii = data && data.industryInfo;
    const info = data && data.info;
    if (!ii || !info) return null;
    const sectorTag  = (ii.sector || ii.macro || '').toUpperCase();
    const subSector  = ii.basicIndustry || ii.industry || '';
    const company    = info.companyName || sym;
    const about = company + ' — ' + (subSector ? 'operates in ' + subSector + '.' : 'sector classification not available.');
    return { sector: sectorTag, about: about };
  })().catch(() => null).finally(() => IPO_LOOKUP_INFLIGHT.delete('nse:' + sym));
  IPO_LOOKUP_INFLIGHT.set('nse:' + sym, promise);
  return promise;
}

/* ----- Chittorgarh fallback for unlisted IPOs ----------------------
   Chittorgarh.com aggregates every Indian IPO with a written
   business overview. We search by company name to find the IPO
   page (URL has a numeric ID), then extract the "About <Company>"
   section. Aggressive 24h caching to be polite to their server.    */
function _normName(s) {
  return (s || '')
    .toLowerCase()
    .replace(/&amp;/g, '&').replace(/&[a-z#0-9]+;/g, ' ')
    .replace(/\s*(limited|ltd\.?|pvt\.?|private)\s*$/i, '')
    .replace(/[^a-z0-9 ]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function _tokens(s) { return _normName(s).split(' ').filter(Boolean); }

async function fetchChittorgarhAbout(companyName, sym) {
  if (!companyName) return null;
  const inflight = IPO_LOOKUP_INFLIGHT.get('cg:' + sym);
  if (inflight) return inflight;
  const promise = (async () => {
    // 1. Search Chittorgarh for the company name.
    const searchUrl = 'https://www.chittorgarh.com/?s=' + encodeURIComponent(_normName(companyName));
    const searchProxied = '/proxy.php?url=' + encodeURIComponent(searchUrl);
    const sRes = await fetch(searchProxied, { signal: AbortSignal.timeout(12000) });
    if (!sRes.ok) return null;
    const sHtml = await sRes.text();

    // 2. Walk every IPO link, score by token overlap with the target name.
    const queryTokens = _tokens(companyName);
    if (queryTokens.length === 0) return null;
    const linkRe = /<a[^>]+href="(\/ipo\/[a-z0-9-]+ipo\/\d+\/?)"[^>]*>([^<]{1,120})<\/a>/gi;
    let bestUrl = null, bestScore = 0;
    let m;
    while ((m = linkRe.exec(sHtml)) !== null) {
      const labelTokens = _tokens(m[2]);
      if (!labelTokens.length) continue;
      const overlap = labelTokens.filter(t => queryTokens.includes(t)).length;
      // Require at least the first word of the query to match.
      if (overlap > bestScore && labelTokens.includes(queryTokens[0])) {
        bestScore = overlap;
        bestUrl   = m[1];
      }
    }
    if (!bestUrl) return null;

    // 3. Fetch the matched IPO page.
    const ipoUrl = 'https://www.chittorgarh.com' + bestUrl;
    const pageProxied = '/proxy.php?url=' + encodeURIComponent(ipoUrl);
    const pRes = await fetch(pageProxied, { signal: AbortSignal.timeout(12000) });
    if (!pRes.ok) return null;
    const pHtml = await pRes.text();

    // 4. Try a series of headings before falling back to meta description.
    const headingPatterns = [
      /<h2[^>]*>\s*About[^<]+<\/h2>([\s\S]{0,3500}?)(?=<h2|<div\s+class=)/i,
      /<h2[^>]*>\s*Company\s+Overview[^<]*<\/h2>([\s\S]{0,3500}?)(?=<h2|<div\s+class=)/i,
      /<h2[^>]*>\s*Issuer[^<]*<\/h2>([\s\S]{0,3500}?)(?=<h2|<div\s+class=)/i,
      /<h3[^>]*>\s*About[^<]+<\/h3>([\s\S]{0,3500}?)(?=<h[1-6]|<div\s+class=)/i
    ];
    let block = null;
    for (let i = 0; i < headingPatterns.length; i++) {
      const hm = pHtml.match(headingPatterns[i]);
      if (hm) { block = hm[0]; break; }
    }
    let text = '';
    if (block) {
      text = block.replace(/<[^>]+>/g, ' ');
    } else {
      // Last resort: meta description.
      const metaM = pHtml.match(/<meta\s+name="description"\s+content="([^"]{40,})"/i);
      if (metaM) text = metaM[1];
    }
    text = text
      .replace(/&amp;/g, '&').replace(/&nbsp;/g, ' ').replace(/&#39;/g, '\'')
      .replace(/&quot;/g, '"').replace(/&#x27;/g, '\'')
      .replace(/\s+/g, ' ').trim();
    if (!text || text.length < 40) return null;
    if (text.length > 320) text = text.slice(0, 317).replace(/\s\S*$/, '') + '…';
    return text;
  })().catch(() => null).finally(() => IPO_LOOKUP_INFLIGHT.delete('cg:' + sym));
  IPO_LOOKUP_INFLIGHT.set('cg:' + sym, promise);
  return promise;
}

async function autoFillIpoBusinessCells() {
  const placeholders = document.querySelectorAll('[data-needs-lookup="1"]');
  // Concurrency limit so we don't hammer either upstream.
  const queue = Array.from(placeholders);
  const workers = 3;
  async function worker() {
    while (queue.length) {
      const el = queue.shift();
      if (!el) continue;
      const sym = el.getAttribute('data-ipo-business');
      const company = el.getAttribute('data-ipo-company') || '';
      if (!sym) continue;
      el.removeAttribute('data-needs-lookup');

      // Tier 1 — NSE quote-equity (works for already-listed companies).
      let info = readLookupCache(sym);
      if (!info) {
        const nse = await fetchNseIndustry(sym);
        if (nse && nse.sector) {
          info = nse;
          writeLookupCache(sym, info);
        }
      }

      // Tier 2 — Chittorgarh (covers unlisted / SME IPOs that NSE doesn't).
      if (!info || !info.sector) {
        const cgKey = 'cg:' + sym;
        const cgCached = readLookupCache(cgKey);
        let about = (cgCached && cgCached.about) || null;
        if (!about) {
          about = await fetchChittorgarhAbout(company, sym);
          if (about) writeLookupCache(cgKey, { about: about });
        }
        if (about) {
          info = { sector: 'Company Overview', about: about };
        }
      }

      if (info && info.sector) {
        el.innerHTML = renderIpoCellFromInfo(info.sector, info.about);
      } else {
        el.innerHTML =
          '<a href="https://www.nseindia.com/get-quotes/equity?symbol=' + encodeURIComponent(sym) + '" ' +
          'target="_blank" rel="noopener" ' +
          'style="font-size:12px; color:var(--gold); text-decoration:none; white-space:nowrap">' +
          'View on NSE →</a>';
      }
    }
  }
  await Promise.all(Array.from({ length: workers }, worker));
}

function renderIpoTable(rows, tab) {
  const tbody = document.getElementById('ipoBody');
  if (!tbody) return;
  if (!rows || rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:var(--grey)">No IPOs to show in this tab right now.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map(r => `
    <tr>
      <td style="color:var(--white); font-weight:600">${escapeHtml(r.companyName || r.symbol || '—')}</td>
      <td><span style="text-transform:capitalize; color:var(--grey2)">${escapeHtml(r.series || 'Mainboard')}</span></td>
      <td>${escapeHtml(fmtIpoDate(r.issueStartDate))}</td>
      <td>${escapeHtml(fmtIpoDate(r.issueEndDate))}</td>
      <td>${escapeHtml(fmtIssuePrice(r.issuePrice || r.priceBand))}</td>
      <td>${ipoBusinessCell(r)}</td>
    </tr>`).join('');
}

async function fetchIpo(tab) {
  currentIpoTab = tab;
  const status = document.getElementById('ipoStatus');
  const tbody  = document.getElementById('ipoBody');
  if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px; color:var(--grey)">Loading IPO data from NSE…</td></tr>';

  // Use /ipo.php (cookie-handshake-aware) instead of /proxy.php — the plain
  // proxy was returning intermittent stale/empty data because NSE's API
  // requires a session-cookie handshake first. ipo.php replicates the
  // pattern from indices.php and gainers-losers.php.
  const url = '/ipo.php?tab=' + encodeURIComponent(tab) + '&cb=' + Date.now();
  try {
    const res = await fetch(url, { signal: AbortSignal.timeout(15000) });
    if (!res.ok) throw new Error('ipo.php ' + res.status);
    const payload = await res.json();
    const inner = payload && payload.data ? payload.data : null;
    let arr = Array.isArray(inner) ? inner : (inner && inner.data) || [];
    if (tab === 'closed') arr = normalizeClosedIpos(arr);
    renderIpoTable(arr, tab);
    autoFillIpoBusinessCells(); // async; updates cells in place when lookups land
    if (status) {
      const stale  = !!(payload && payload.stale);
      const at     = payload && payload.fetched_at
        ? new Date(payload.fetched_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
        : '';
      const dayCap = tab === 'closed' ? ' Showing the last ' + IPO_CLOSED_DAYS + ' days.' : '';
      status.textContent = (stale ? 'Stale — last fetched ' : 'Updated ') + at + ' • Source: NSE India.' + dayCap;
    }
  } catch (e) {
    renderIpoTable([], tab);
    if (status) status.textContent = 'Could not fetch live IPO data. Please refresh in a few seconds.';
  }
}

let ipoRefreshTimer = null;

function filterIpo(tab, btn) {
  document.querySelectorAll('#page-ipo .tabs .tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');

  // Stop any prior auto-refresh from the previous tab.
  if (ipoRefreshTimer) { clearInterval(ipoRefreshTimer); ipoRefreshTimer = null; }

  // 'allotment' has no public API — every IPO is handled by its registrar's
  // own portal — so we just swap to a static panel of registrar quick-links
  // and skip the NSE fetch entirely.
  const tablePanel = document.getElementById('ipoTablePanel');
  const allotPanel = document.getElementById('ipoAllotmentPanel');
  if (tab === 'allotment') {
    if (tablePanel) tablePanel.style.display = 'none';
    if (allotPanel) allotPanel.style.display = 'block';
    return;
  }
  if (tablePanel) tablePanel.style.display = 'block';
  if (allotPanel) allotPanel.style.display = 'none';
  fetchIpo(tab);
  // Keep the active IPO tab fresh during market hours — 60s refresh.
  ipoRefreshTimer = setInterval(() => {
    if (isMarketOpenIST()) fetchIpo(tab);
  }, 60 * 1000);
}

/* ================================================================
   PREMIUM CALL PLANS — data lives in data.js (PLANS, PLAN_DURATIONS,
   TYPE_LABELS, GST_RATE). Functions below render and price them.
   ================================================================ */
function fmtINR(n) { return '₹' + Math.round(n).toLocaleString('en-IN'); }
function fmtINRdec(n) { return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

function renderPlans() {
  const grid = document.getElementById('plansList');
  if (!grid) return;
  // First card open by default so visitors see something expanded;
  // subsequent cards collapsed to the summary header.
  grid.innerHTML = PLANS.map((p, i) => renderPlanCard(p, i === 0)).join('');
  PLANS.forEach(p => updatePlanPricing(p.id));
}

function togglePlanCard(planId) {
  const card = document.getElementById('plan-card-' + planId);
  if (!card) return;
  card.classList.toggle('open');
}

function renderPlanCard(p, openByDefault) {
  const featuresList = (p.features || []).map(f =>
    '<li>' + f + '</li>'
  ).join('');

  // Selector area varies by plan type ----------------------------------
  let selector, defaultSelection;

  if (p.type === 'subscription') {
    const types = Object.keys(p.pricing);
    const defaultType = types[0];
    defaultSelection = { type: defaultType, duration: '1' };
    // Per-duration savings vs paying monthly. The 1-month price is the
    // base rate; longer durations are progressively cheaper, so the
    // "save X%" is real (computed from actual prices, not fabricated).
    const monthly1 = p.pricing[defaultType][1];
    const opts = PLAN_DURATIONS.map(d => {
      const price = p.pricing[defaultType][d.months];
      const fullCost = monthly1 * d.months;
      const savedPct = d.months > 1 && fullCost > price
        ? Math.round((1 - price / fullCost) * 100)
        : 0;
      const tail = savedPct > 0 ? ' • Save ' + savedPct + '%' : '';
      const best = d.months === 12 ? ' ★ Best Value' : '';
      return '<option value="' + d.months + '">' + d.label + ' — ' + fmtINR(price) + tail + best + '</option>';
    }).join('');
    selector =
      '<div class="plan-control-group">' +
        '<label>Choose duration</label>' +
        '<select class="plan-duration" onchange="selectPlanDuration(\'' + p.id + '\', this.value)">' + opts + '</select>' +
      '</div>';
  } else {
    // 'service' or 'program' — pick a tier, no duration math.
    const t0 = p.tiers[0];
    defaultSelection = { tierId: t0.id };
    selector =
      '<div class="plan-control-group">' +
        '<label>Choose ' + (p.type === 'service' ? 'tier' : 'programme') + '</label>' +
        '<div class="plan-tier-toggle">' +
          p.tiers.map((t, i) =>
            '<button class="plan-tier-btn ' + (i === 0 ? 'active' : '') + '" ' +
                  'onclick="selectPlanTier(\'' + p.id + '\',\'' + t.id + '\',this)">' +
              t.name +
            '</button>'
          ).join('') +
        '</div>' +
      '</div>';
  }

  // Card markup — accordion: clickable summary header + collapsible content
  const dataAttrs = p.type === 'subscription'
    ? 'data-plan-type="subscription" data-type="' + defaultSelection.type + '" data-duration="1"'
    : 'data-plan-type="' + p.type + '" data-tier="' + defaultSelection.tierId + '"';
  const openClass = openByDefault ? ' open' : '';

  // Value label for the collapsed header — sells the depth of the
  // research, not the rupee figure. Falls back to a sensible default
  // if the plan didn't define one.
  const valueLabel = p.valueLabel ||
    (p.type === 'subscription' ? 'Research-Backed Calls'
   : p.type === 'service'      ? 'Premium Strategy'
   :                              'Personal Mastery');

  // Optional fancy chips (e.g. "Index-Based", "Research Delivery") and
  // small ribbon (e.g. "LAUNCH OFFER") next to the plan name.
  const chipsHTML = (p.chips && p.chips.length)
    ? '<div class="plan-chips-row">' +
        p.chips.map(c => '<span class="plan-chip">' + c + '</span>').join('') +
      '</div>'
    : '';
  const offerRibbon = p.offerLabel
    ? '<span class="plan-offer-ribbon">' + p.offerLabel + '</span>'
    : '';
  // Subscription plans get a dynamic discount badge (updates with selected
  // duration). Service / programme plans keep the static plan-level discount
  // because they have tiers, not durations.
  const isSubscription = p.type === 'subscription';
  const discountPct = (typeof p.discountPct === 'number' && p.discountPct > 0 && p.discountPct < 90)
    ? p.discountPct
    : 0;
  const discountBadgeHTML = isSubscription
    ? '<span class="plan-discount-badge" id="plan-disc-' + p.id + '" style="display:none">&mdash;</span>'
    : (discountPct ? '<span class="plan-discount-badge">' + discountPct + '% OFF</span>' : '');
  // MRP / savings rows: always rendered for subscriptions (populated dynamically),
  // rendered only when discount > 0 for service/programme plans.
  const mrpRowHTML = (isSubscription || discountPct)
    ? '<div class="plan-pricing-mrp-row" id="plan-mrp-row-' + p.id + '"' + (isSubscription ? ' style="display:none"' : '') + '>' +
        '<span class="plan-pricing-mrp-label">' + (isSubscription ? 'If paid monthly' : 'MRP') + '</span>' +
        '<span class="plan-pricing-mrp" id="plan-mrp-' + p.id + '">&mdash;</span>' +
      '</div>'
    : '';
  const savedRowHTML = (isSubscription || discountPct)
    ? '<p class="plan-pricing-saved" id="plan-saved-' + p.id + '"' + (isSubscription ? ' style="display:none"' : '') + '></p>'
    : '';

  return ''
    + '<div class="plan-card plan-card-' + p.type + openClass + '" id="plan-card-' + p.id + '" ' + dataAttrs + '>'

    // ── ALWAYS-VISIBLE HEADER (click to toggle) ──────────────────────
    +   '<button class="plan-card-toggle" onclick="togglePlanCard(\'' + p.id + '\')" aria-label="Toggle ' + p.name + ' plan details">'
    +     '<div class="plan-toggle-left">'
    +       '<span class="plan-toggle-icon">' + p.icon + '</span>'
    +       '<div class="plan-toggle-text">'
    +         '<span class="plan-toggle-name">' + p.name + ' ' + offerRibbon + '</span>'
    +         '<span class="plan-toggle-tagline">' + p.tagline + '</span>'
    +       '</div>'
    +     '</div>'
    +     '<div class="plan-toggle-right">'
    +       '<span class="plan-toggle-price">' + valueLabel + '</span>'
    +       '<span class="plan-toggle-chev">▾</span>'
    +     '</div>'
    +   '</button>'

    // ── COLLAPSIBLE BODY ─────────────────────────────────────────────
    +   '<div class="plan-collapse">'
    +     '<div class="plan-body">'
    +       chipsHTML
    +       '<p class="plan-desc">' + p.description + '</p>'
    +       (featuresList ? '<ul class="plan-features-list">' + featuresList + '</ul>' : '')
    +       '<div class="plan-controls">' + selector + '</div>'
    +     '</div>'
    +     '<div class="plan-pricing">'
    +       '<div class="plan-pricing-title">' + valueLabel + ' ' + discountBadgeHTML + '</div>'
    +       '<div class="plan-pricing-meta">'
    +         '<span class="plan-pricing-meta-name">' + p.name + '</span>'
    +         '<span class="plan-pricing-meta-dur"  id="plan-pricing-dur-'  + p.id + '">—</span>'
    +       '</div>'
    +       mrpRowHTML
    +       '<div class="plan-pricing-payable">'
    +         '<span>' + (p.type === 'service' ? 'Per Month' : p.type === 'program' ? 'One-time' : 'Total') + '</span>'
    +         '<span id="plan-payable-' + p.id + '">—</span>'
    +       '</div>'
    +       savedRowHTML
    +       '<p class="plan-pricing-note" id="plan-note-' + p.id + '"></p>'
    +       '<button class="btn btn-gold plan-cta" onclick="subscribeToPlan(\'' + p.id + '\')">'
    +         (p.type === 'program' ? 'Apply for Programme' : 'Get Started')
    +       '</button>'
    +       '<p class="plan-pricing-tag">+ 18% GST applicable as per SEBI norms</p>'
    +     '</div>'
    +   '</div>'

    + '</div>';
}

function selectPlanDuration(planId, months) {
  const card = document.getElementById('plan-card-' + planId);
  if (!card) return;
  card.setAttribute('data-duration', months);
  updatePlanPricing(planId);
}

function selectPlanTier(planId, tierId, btnEl) {
  const card = document.getElementById('plan-card-' + planId);
  if (!card) return;
  card.setAttribute('data-tier', tierId);
  card.querySelectorAll('.plan-tier-btn').forEach(b => b.classList.remove('active'));
  if (btnEl) btnEl.classList.add('active');
  updatePlanPricing(planId);
}

function updatePlanPricing(planId) {
  const card = document.getElementById('plan-card-' + planId);
  if (!card) return;
  const plan = PLANS.find(p => p.id === planId);
  if (!plan) return;

  const durEl     = document.getElementById('plan-pricing-dur-'   + planId);
  const payableEl = document.getElementById('plan-payable-' + planId);
  const noteEl    = document.getElementById('plan-note-'    + planId);
  const mrpEl     = document.getElementById('plan-mrp-'     + planId);
  const savedEl   = document.getElementById('plan-saved-'   + planId);

  const mrpRowEl = document.getElementById('plan-mrp-row-' + planId);
  const badgeEl  = document.getElementById('plan-disc-'    + planId);

  let basePrice, suffix = '';
  if (plan.type === 'subscription') {
    const type = card.getAttribute('data-type');
    const dur  = Number(card.getAttribute('data-duration'));
    basePrice = (plan.pricing[type] || {})[dur];
    if (basePrice == null) return;
    const dlabel = (PLAN_DURATIONS.find(d => String(d.months) === String(dur)) || {}).label || dur + ' Months';
    if (durEl)     durEl.textContent     = dlabel;
    if (payableEl) payableEl.textContent = fmtINR(basePrice);
    if (noteEl)    noteEl.textContent    = '';

    // Per-duration savings vs paying monthly. For 1-month, hide MRP/badge —
    // there's no comparison to make. For 3/6/12, show the real savings the
    // longer commitment unlocks. This is what makes longer subs visibly
    // cheaper instead of every duration showing the same fake 25%.
    const monthly1 = (plan.pricing[type] || {})[1];
    const fullCost = monthly1 != null ? monthly1 * dur : null;
    if (mrpEl && savedEl && mrpRowEl && badgeEl && fullCost && fullCost > basePrice) {
      const saved    = fullCost - basePrice;
      const savedPct = Math.round((saved / fullCost) * 100);
      mrpEl.textContent   = fmtINR(fullCost);
      savedEl.textContent = 'You save ' + fmtINR(saved) + ' (' + savedPct + '% off vs monthly)';
      mrpRowEl.style.display = '';
      savedEl.style.display  = '';
      badgeEl.textContent    = savedPct + '% OFF';
      badgeEl.style.display  = '';
    } else if (mrpRowEl && savedEl && badgeEl) {
      // 1-month: no comparison, show clean monthly rate only.
      mrpRowEl.style.display = 'none';
      savedEl.style.display  = 'none';
      badgeEl.style.display  = 'none';
    }
    return;
  }

  // Service / programme plans — keep the static plan-level discount logic.
  const tierId = card.getAttribute('data-tier');
  const tier   = (plan.tiers || []).find(t => t.id === tierId) || plan.tiers[0];
  if (!tier) return;
  basePrice = tier.price;
  suffix    = tier.suffix || '';
  if (durEl)     durEl.textContent     = tier.name;
  if (payableEl) payableEl.textContent = fmtINR(basePrice) + suffix;
  if (noteEl)    noteEl.textContent    = tier.note || '';

  const discountPct = (typeof plan.discountPct === 'number' && plan.discountPct > 0 && plan.discountPct < 90)
    ? plan.discountPct : 0;
  if (mrpEl && savedEl && discountPct) {
    const mrp   = Math.round(basePrice / (1 - discountPct / 100));
    const saved = mrp - basePrice;
    mrpEl.textContent   = fmtINR(mrp) + suffix;
    savedEl.textContent = 'You save ' + fmtINR(saved) + ' (' + discountPct + '% off)';
  }
}

function subscribeToPlan(planId) {
  const card = document.getElementById('plan-card-' + planId);
  const plan = PLANS.find(p => p.id === planId);
  if (!card || !plan) return;

  // Build a plan-specific WhatsApp greeting that includes the exact
  // selection (subscription duration / tier) and the price the visitor
  // saw, so the team can confirm the order in one message.
  let summary, priceLine = '';
  if (plan.type === 'subscription') {
    const type   = card.getAttribute('data-type');
    const dur    = card.getAttribute('data-duration');
    const dlabel = (PLAN_DURATIONS.find(d => String(d.months) === String(dur)) || {}).label || dur + ' Months';
    const price  = (plan.pricing[type] || {})[dur];
    summary   = plan.name + ' — ' + (TYPE_LABELS[type] || type) + ' • ' + dlabel;
    if (price != null) priceLine = '\nPrice: ' + fmtINR(price) + ' (+ 18% GST)';
  } else {
    const tierId = card.getAttribute('data-tier');
    const tier   = (plan.tiers || []).find(t => t.id === tierId) || plan.tiers[0];
    summary   = plan.name + ' — ' + tier.name;
    priceLine = '\nPrice: ' + fmtINR(tier.price) + (tier.suffix || '') + ' (+ 18% GST)';
  }

  const verb = plan.type === 'program' ? 'enrol in' : 'subscribe to';
  const message =
    'Hi RootNivesh, I\'d like to ' + verb + ':\n' +
    '*' + summary + '*' +
    priceLine +
    '\n\nPlease share the next steps to get started.';

  const number = (typeof WHATSAPP_NUMBER !== 'undefined') ? WHATSAPP_NUMBER : '917467094575';
  const url = 'https://wa.me/' + number + '?text=' + encodeURIComponent(message);
  window.open(url, '_blank', 'noopener');
}

/* Generic "send me details about X" entry point used by every
   Enroll / Subscribe / Request CTA across the site. Routes to
   Contact page, pre-fills Subject + Message so when the visitor
   hits Send, the email arriving at contact@rootnivesh.in carries
   the exact item they were interested in. */
function enquireFor(category, label, intent) {
  showPage('contact');
  setTimeout(() => {
    const msgEl = document.getElementById('cfMessage');
    const subjEl = document.getElementById('cfSubject');
    if (subjEl) {
      const map = {
        'Premium Plan':         'Subscription Enquiry',
        'Research Report':      'Research Report Query',
        'Learner Club Course':  'Learner Club',
      };
      const opt = map[category] || 'General Enquiry';
      const found = Array.from(subjEl.options).find(o => o.text === opt || o.value === opt);
      if (found) subjEl.value = found.value;
    }
    if (msgEl && !msgEl.value) {
      const verb = intent === 'subscribe' ? 'subscribe to'
                 : intent === 'enrol'     ? 'enroll in'
                 : intent === 'request'   ? 'receive the full'
                 : 'know more about';
      msgEl.value = "Hi, I'd like to " + verb + " " + category.toLowerCase() + ': ' + label + '. Please share next steps.';
    }
    const nameEl = document.getElementById('cfName');
    if (nameEl) try { nameEl.focus({ preventScroll: false }); } catch (e) {}
  }, 100);
}

/* ===== EMI CALCULATOR ===== */
function calcEMI() {
  const P = parseFloat(document.getElementById('loanAmount').value);
  const annualR = parseFloat(document.getElementById('interestRate').value);
  let n = parseFloat(document.getElementById('tenureValue').value);
  if (document.getElementById('tenureType').value === 'years') n *= 12;
  if (!P || !annualR || !n) return;
  const r = annualR / 12 / 100;
  const emi = P * r * Math.pow(1+r, n) / (Math.pow(1+r, n) - 1);
  const total = emi * n;
  const totalInt = total - P;
  const intPct = (totalInt / total * 100).toFixed(1);
  const prinPct = (100 - parseFloat(intPct)).toFixed(1);
  document.getElementById('emiVal').textContent = '₹' + fmt(emi);
  document.getElementById('totalInterestVal').textContent = '₹' + fmt(totalInt);
  document.getElementById('totalAmountVal').textContent = '₹' + fmt(total);
  document.getElementById('interestPctVal').textContent = intPct + '%';
  document.getElementById('emiBar').style.width = prinPct + '%';
  document.getElementById('principalPct').textContent = prinPct + '%';
  document.getElementById('interestPctDisp').textContent = intPct + '%';
  document.getElementById('emiResult').classList.add('show');
}
function fmt(n) {
  if (n >= 1e7) return (n/1e7).toFixed(2) + 'Cr';
  if (n >= 1e5) return (n/1e5).toFixed(2) + 'L';
  return Math.round(n).toLocaleString('en-IN');
}

/* ===== POSITION SIZING CALCULATOR ===== */
function calcPosition() {
  const capital = parseFloat(document.getElementById('capital').value);
  const riskPct = parseFloat(document.getElementById('riskPct').value);
  const entry = parseFloat(document.getElementById('entryPrice').value);
  const sl = parseFloat(document.getElementById('slPrice').value);
  const target = parseFloat(document.getElementById('targetPrice').value) || 0;
  if (!capital || !riskPct || !entry || !sl) return;
  const riskPerShare = Math.abs(entry - sl);
  const maxRisk = capital * riskPct / 100;
  const qty = Math.floor(maxRisk / riskPerShare);
  const invested = qty * entry;
  let rr = '-';
  let advice = '';
  if (target) {
    const reward = Math.abs(target - entry);
    rr = (reward / riskPerShare).toFixed(1) + ':1';
    const rrNum = reward / riskPerShare;
    if (rrNum >= 2) { advice = `✅ <strong>Good trade setup!</strong> Risk:Reward of ${rr} is favorable. Position of ${qty} shares risks ₹${Math.round(maxRisk).toLocaleString('en-IN')} for potential gain of ₹${Math.round(qty * reward).toLocaleString('en-IN')}.`; document.getElementById('posAdvice').style.background = 'rgba(46,204,113,0.08)'; document.getElementById('posAdvice').style.border = '1px solid rgba(46,204,113,0.25)'; document.getElementById('posAdvice').style.color = '#aaffcc'; }
    else { advice = `⚠️ <strong>Weak risk:reward of ${rr}.</strong> Consider a better target or entry price. Most traders require at least 2:1 R:R.`; document.getElementById('posAdvice').style.background = 'rgba(231,76,60,0.08)'; document.getElementById('posAdvice').style.border = '1px solid rgba(231,76,60,0.25)'; document.getElementById('posAdvice').style.color = '#ffaaaa'; }
  }
  document.getElementById('posQty').textContent = qty.toLocaleString('en-IN');
  document.getElementById('posInvest').textContent = '₹' + fmt(invested);
  document.getElementById('posRisk').textContent = '₹' + Math.round(maxRisk).toLocaleString('en-IN');
  document.getElementById('posRR').textContent = rr;
  const r = document.getElementById('posResult');
  const adv = document.getElementById('posAdvice');
  r.classList.add('show');
  if (advice) { adv.style.display = 'block'; adv.innerHTML = advice; } else { adv.style.display = 'none'; }
}

/* ===== SIP CALCULATOR ===== */
function calcSIP() {
  const monthly = parseFloat(document.getElementById('sipAmount').value);
  const annualR = parseFloat(document.getElementById('sipReturn').value);
  const years   = parseFloat(document.getElementById('sipYears').value);
  if (!monthly || !annualR || !years || monthly <= 0 || annualR < 0 || years <= 0) return;

  const n = years * 12;
  const r = annualR / 12 / 100;
  // Future value of an annuity (end-of-period contributions, then × (1+r) for start-of-period)
  const fv = monthly * (Math.pow(1 + r, n) - 1) / r * (1 + r);
  const invested = monthly * n;
  const gains    = fv - invested;
  const multiplier = fv / invested;

  document.getElementById('sipInvested').textContent   = '₹' + fmt(invested);
  document.getElementById('sipGains').textContent      = '₹' + fmt(gains);
  document.getElementById('sipMaturity').textContent   = '₹' + fmt(fv);
  document.getElementById('sipMultiplier').textContent = multiplier.toFixed(2) + 'x';

  const breakdown = document.getElementById('sipBreakdown');
  const gainsPct = (gains / fv * 100).toFixed(1);
  breakdown.style.display = 'block';
  breakdown.innerHTML =
    '<strong style="color:var(--gold)">Snapshot:</strong> investing ₹' + fmt(monthly) +
    ' every month for ' + years + ' years at ' + annualR + '% annual return ' +
    'compounds to <strong style="color:var(--white)">₹' + fmt(fv) + '</strong>. ' +
    'Of that, <strong style="color:var(--green)">' + gainsPct + '%</strong> is pure return — ' +
    'your contributions are only ₹' + fmt(invested) + '.';

  document.getElementById('sipResult').classList.add('show');
}

/* ===== CONTACT FORM ===== */
async function submitContact() {
  const get = id => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };
  const first = get('cfName');
  const last  = get('cfLast');
  const email = get('cfEmail');
  const phone = get('cfPhone');
  const subject = get('cfSubject');
  const msg = get('cfMessage');
  if (!first || !email || !msg) { alert('Please fill Name, Email, and Message.'); return; }

  const fullName = last ? (first + ' ' + last) : first;
  const fd = new FormData();
  fd.append('source', 'contact');
  fd.append('name', fullName);
  fd.append('email', email);
  if (phone)   fd.append('phone', phone);
  if (subject) fd.append('interest', subject);
  fd.append('message', msg);

  try {
    const res = await fetch('/contact.php', { method: 'POST', body: fd, signal: AbortSignal.timeout(15000) });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok !== true) throw new Error(data.error || 'send failed');
  } catch (e) {
    alert('Could not send right now. Please WhatsApp us at +91 74670 94575 or email contact@rootnivesh.in instead.');
    return;
  }
  document.getElementById('contactFormWrap').style.display = 'none';
  document.getElementById('formSuccess').classList.add('show');
}

/* ===== SCROLL FADE-IN ===== */
function observeFadeIns() {
  const obs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); } });
  }, { threshold: 0.1 });
  document.querySelectorAll('.fade-in').forEach(el => obs.observe(el));
}
