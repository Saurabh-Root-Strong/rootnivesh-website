/* ===== PAGE NAVIGATION ===== */
function showPage(id) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById('page-' + id).classList.add('active');
  document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
  window.scrollTo(0, 0);
  initPage(id);
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
}
function megaGoTool(type) {
  closeMegas();
  showPage('tools');
  const el = document.getElementById('snav-' + type);
  if (el) snavSelectTool(type, el);
}

function initPage(id) {
  if (id === 'reports') renderReports('all');
  if (id === 'learner') renderCourses('all');
  if (id === 'calls') { renderCalls('intraday'); }
  if (id === 'performance') { renderPerfTable('all'); }
}

function toggleMenu() {
  document.getElementById('mobileMenu').classList.toggle('open');
}

/* ===== SIDEBAR NAV — CALLS ===== */
const callsMeta = {
  intraday:   { title:'📈 Intraday Calls',          crumb:'INTRADAY',   desc:'Same-day trades with precise entry, target & stop-loss levels.' },
  swing:      { title:'🔄 Swing Calls',              crumb:'SWING',      desc:'7–14 day momentum trades capturing short-term price swings.' },
  positional: { title:'📊 Positional Calls',         crumb:'POSITIONAL', desc:'1–3 month trend-following calls with larger profit targets.' },
  value:      { title:'💎 Value Based Investing',    crumb:'VALUE',      desc:'Fundamentally strong stocks picked below intrinsic value.' },
  monthly:    { title:'📅 Monthly Based Investing',  crumb:'MONTHLY',    desc:'SIP, ETF & index fund ideas for systematic long-term compounding.' },
};

function snavSelectCall(type, el) {
  document.querySelectorAll('#page-calls .snav-item').forEach(i => i.classList.remove('active'));
  el.classList.add('active');
  const m = callsMeta[type];
  document.getElementById('callsTitle').textContent = m.title;
  document.getElementById('callsBreadcrumb').textContent = m.crumb;
  document.getElementById('callsDesc').textContent = m.desc;
  renderCalls(type);
}

/* ===== SIDEBAR NAV — TOOLS ===== */
function snavSelectTool(type, el) {
  document.querySelectorAll('#page-tools .snav-item').forEach(i => i.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('toolContent-emi').style.display = type === 'emi' ? 'block' : 'none';
  document.getElementById('toolContent-pos').style.display = type === 'pos' ? 'block' : 'none';
}

/* called from navbar dropdown */
function setTool(which) {
  showPage('tools');
  const type = which === 'emi' ? 'emi' : 'pos';
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
function submitMiniForm() {
  const name = document.getElementById('mfName').value.trim();
  const phone = document.getElementById('mfPhone').value.trim();
  const email = document.getElementById('mfEmail').value.trim();
  const interest = document.getElementById('mfInterest').value;
  if (!name || !phone || !email || !interest) {
    alert('Please fill Name, Phone, Email and select your interest.'); return;
  }
  document.getElementById('miniFormFields').style.display = 'none';
  const s = document.getElementById('miniFormSuccess');
  s.style.display = 'block'; s.classList.add('show');
}


/* ================================================================
   LIVE MARKET TICKER  — Yahoo Finance v8/chart via local PHP proxy
   Data is 15-min delayed (Yahoo's standard for free quotes).
   Server-side proxy at /proxy.php avoids CORS and rate-limit issues.
   ================================================================ */
const PROXY = '/proxy.php?url=';

const TICKER_SYMBOLS = [
  { yahoo: '^NSEI',         label: 'NIFTY 50' },
  { yahoo: '^BSESN',        label: 'SENSEX' },
  { yahoo: '^NSEBANK',      label: 'BANKNIFTY' },
  { yahoo: 'RELIANCE.NS',   label: 'RELIANCE' },
  { yahoo: 'TCS.NS',        label: 'TCS' },
  { yahoo: 'INFY.NS',       label: 'INFOSYS' },
  { yahoo: 'HDFCBANK.NS',   label: 'HDFCBANK' },
  { yahoo: 'ICICIBANK.NS',  label: 'ICICIBANK' },
  { yahoo: 'WIPRO.NS',      label: 'WIPRO' },
  { yahoo: 'BAJFINANCE.NS', label: 'BAJFINANCE' },
  { yahoo: 'TATAMOTORS.NS', label: 'TATAMOTORS' },
  { yahoo: 'SBIN.NS',       label: 'SBIN' },
  { yahoo: 'AXISBANK.NS',   label: 'AXISBANK' },
  { yahoo: 'LT.NS',         label: 'L&T' },
  { yahoo: 'MARUTI.NS',     label: 'MARUTI' }
];

function isMarketOpen() {
  const ist = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Kolkata' }));
  const day = ist.getDay();
  const mins = ist.getHours() * 60 + ist.getMinutes();
  return day >= 1 && day <= 5 && mins >= 555 && mins <= 930; // Mon-Fri, 9:15-15:30 IST
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
        status.textContent = open ? 'NSE • LIVE (15min delay)' : 'NSE • CLOSED';
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
  setInterval(refreshTicker, 60000); // refresh every 60 seconds
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
  return '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: 2 }) + ' Cr';
}

function applyFiiDii(fii, dii) {
  // FII
  const fiiNet = parseFloat(fii.netValue || fii.NET_VALUE || 0);
  const fiiBuy = parseFloat(fii.buyValue  || fii.BUY_VALUE  || 0);
  const fiiSell= parseFloat(fii.sellValue || fii.SELL_VALUE || 0);
  const fiiPos = fiiNet >= 0;
  document.getElementById('fiiNet').textContent   = fmtCr(fiiNet);
  document.getElementById('fiiNet').className     = 'fiidii-net ' + (fiiPos ? 'pos' : 'neg');
  document.getElementById('fiiNetLabel').textContent = fiiPos ? 'Net Buy' : 'Net Sell';
  document.getElementById('fiiBuy').textContent  = fmtCrAbs(fiiBuy);
  document.getElementById('fiiSell').textContent = fmtCrAbs(fiiSell);
  const fiiBadge = document.getElementById('fiiBadge');
  fiiBadge.textContent = fiiPos ? 'NET BUY' : 'NET SELL';
  fiiBadge.className   = 'fiidii-net-badge ' + (fiiPos ? 'buy-badge' : 'sell-badge');
  const fiiBuyPct = fiiBuy + fiiSell > 0 ? (fiiBuy / (fiiBuy + fiiSell) * 100) : 50;
  document.getElementById('fiiBarBuy').style.width = fiiBuyPct + '%';

  // DII
  const diiNet = parseFloat(dii.netValue || dii.NET_VALUE || 0);
  const diiBuy = parseFloat(dii.buyValue  || dii.BUY_VALUE  || 0);
  const diiSell= parseFloat(dii.sellValue || dii.SELL_VALUE || 0);
  const diiPos = diiNet >= 0;
  document.getElementById('diiNet').textContent   = fmtCr(diiNet);
  document.getElementById('diiNet').className     = 'fiidii-net ' + (diiPos ? 'pos' : 'neg');
  document.getElementById('diiNetLabel').textContent = diiPos ? 'Net Buy' : 'Net Sell';
  document.getElementById('diiBuy').textContent  = fmtCrAbs(diiBuy);
  document.getElementById('diiSell').textContent = fmtCrAbs(diiSell);
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

function fetchFiiDii() {
  // NSE FII/DII API requires session cookies that a simple proxy can't supply,
  // so we render representative sample figures with a clear date stamp.
  // Update the numbers here when you have fresh NSE data, or wire up a
  // dedicated cookie-aware backend later.
  applyFiiDii(
    { buyValue: '14832.45', sellValue: '13120.30', netValue:  '1712.15' },
    { buyValue: '10234.80', sellValue:  '8940.25', netValue:  '1294.55' }
  );
  const today = new Date().toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
  document.getElementById('fiidiiDate').textContent = 'Sample data — ' + today;
}

function initFiiDii() {
  fetchFiiDii();
  fetchFiiDiiHistory();
}

/* ---- 30-day historical table ---- */
function genFallbackHistory() {
  const rows = [];
  const today = new Date();
  let seed = 1234;
  function rnd() { seed = (seed * 16807 + 0) % 2147483647; return (seed % 10000) / 100; }
  for (let i = 29; i >= 0; i--) {
    const d = new Date(today); d.setDate(d.getDate() - i);
    if (d.getDay() === 0 || d.getDay() === 6) continue;
    const fiiNet = (rnd() - 50) * 80;
    const diiNet = (rnd() - 40) * 60;
    rows.push({
      date: d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }),
      fii: fiiNet.toFixed(2),
      dii: diiNet.toFixed(2),
    });
  }
  return rows.slice(-22);
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
      <td class="td-date">${r.date}</td>
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

function fetchFiiDiiHistory() {
  // NSE historical endpoint requires session cookies — render sample series.
  renderHistTable(genFallbackHistory());
  document.getElementById('histLoadStatus').textContent = 'Sample data';
}

function toggleHistTable() {
  /* table is always visible — this was an earlier toggle, no-op now */
}

window.addEventListener('DOMContentLoaded', () => {
  renderReports('all');
  renderCourses('all');
  renderCalls('intraday');
  renderPerfTable('all');
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

/* ===== REPORTS DATA ===== */
const reportsData = [
  {cat:'largecap', tag:'buy', title:'Reliance Industries Ltd', ticker:'RELIANCE', desc:'Jio Financial value unlock, O2C recovery, and new energy ramp-up. Strong FCF generation supports premium valuation.', target:'₹3,250', upside:'+18%', horizon:'12M', date:'Apr 5, 2025'},
  {cat:'largecap', tag:'buy', title:'HDFC Bank Limited', ticker:'HDFCBANK', desc:'Post-merger NIM expansion expected, strong CASA ratio and retail loan growth. Valuations still reasonable vs history.', target:'₹1,950', upside:'+22%', horizon:'12M', date:'Apr 3, 2025'},
  {cat:'largecap', tag:'hold', title:'Infosys Limited', ticker:'INFY', desc:'Near-term headwinds from deal ramp-downs. Await guidance upgrade. Strong balance sheet and dividend yield provide support.', target:'₹1,760', upside:'+8%', horizon:'6M', date:'Apr 1, 2025'},
  {cat:'midcap', tag:'buy', title:'Dixon Technologies', ticker:'DIXON', desc:'Electronics PLI beneficiary. Strong order book in mobile, LED, and consumer electronics. Scalable business model.', target:'₹16,500', upside:'+28%', horizon:'18M', date:'Mar 28, 2025'},
  {cat:'midcap', tag:'buy', title:'Chalet Hotels Ltd', ticker:'CHALET', desc:'India hospitality upcycle. Premium hotel RevPAR growth of 15%+ YoY. Debt reduction on track.', target:'₹1,050', upside:'+32%', horizon:'18M', date:'Mar 25, 2025'},
  {cat:'midcap', tag:'sell', title:'PVR Inox Ltd', ticker:'PVRINOX', desc:'OTT competition intensifying. Footfall recovery slower than expected. Debt levels remain elevated.', target:'₹1,200', upside:'-12%', horizon:'12M', date:'Mar 22, 2025'},
  {cat:'smallcap', tag:'buy', title:'Bikaji Foods', ticker:'BIKAJI', desc:'Organized snacks category growing rapidly. Pan-India distribution expansion. Margin improvement ahead.', target:'₹820', upside:'+35%', horizon:'24M', date:'Mar 18, 2025'},
  {cat:'smallcap', tag:'buy', title:'Ami Organics', ticker:'AMIORG', desc:'Specialty chemicals with strong pharma API pipeline. Export diversification into Europe.', target:'₹1,450', upside:'+40%', horizon:'24M', date:'Mar 15, 2025'},
  {cat:'sectoral', tag:'hold', title:'Nifty IT Sector', ticker:'NIFTYIT', desc:'Mixed signals — US macro uncertainty vs long-term AI spends. Prefer Tier-2 IT over large caps at current valuations.', target:'Index View', upside:'Neutral', horizon:'6M', date:'Apr 2, 2025'},
  {cat:'sectoral', tag:'buy', title:'Banking Sector Outlook', ticker:'BANKNIFTY', desc:'Rate cut cycle beginning. NIMs to stabilize. Credit growth at 14–16%. PSU banks offer value; HDFC/ICICI for quality.', target:'Overweight', upside:'+15–25%', horizon:'12M', date:'Mar 30, 2025'},
];
function renderReports(cat) {
  const data = cat === 'all' ? reportsData : reportsData.filter(r => r.cat === cat);
  document.getElementById('reportsGrid').innerHTML = data.map(r => `
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
        <button class="btn btn-outline" style="padding:6px 14px; font-size:12px">Read Full Report →</button>
      </div>
    </div>`).join('');
}
function filterReports(cat, btn) {
  document.querySelectorAll('#reportTabs .tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  renderReports(cat);
}

/* ===== COURSES DATA ===== */
const coursesData = [
  {level:'beginner', icon:'📈', title:'Stock Market Basics', desc:'What is a stock? How does BSE/NSE work? Types of orders, demat accounts, and how to start investing.', lessons:'12 Lessons', duration:'6 Hours', badge:'Free'},
  {level:'beginner', icon:'💰', title:'Mutual Funds & SIP', desc:'Understanding mutual fund categories, SIP vs lumpsum, NAV, expense ratio, and picking the right fund.', lessons:'8 Lessons', duration:'4 Hours', badge:'Free'},
  {level:'intermediate', icon:'📊', title:'Fundamental Analysis', desc:'P/E, P/B, ROE, ROCE, DCF valuation, reading annual reports, and building a financial model.', lessons:'20 Lessons', duration:'14 Hours', badge:'Premium'},
  {level:'intermediate', icon:'📉', title:'Technical Analysis', desc:'Chart patterns, candlesticks, RSI, MACD, moving averages, volume analysis, and support/resistance.', lessons:'18 Lessons', duration:'12 Hours', badge:'Premium'},
  {level:'advanced', icon:'🎲', title:'Options Strategies', desc:'Greeks, spreads, straddles, iron condor, theta decay, and building income-generating options portfolios.', lessons:'24 Lessons', duration:'18 Hours', badge:'Premium'},
  {level:'advanced', icon:'🏗️', title:'Portfolio Construction', desc:'Asset allocation, rebalancing, factor investing, risk-adjusted returns, and building a multi-cap portfolio.', lessons:'16 Lessons', duration:'10 Hours', badge:'Premium'},
];
function renderCourses(level) {
  const data = level === 'all' ? coursesData : coursesData.filter(c => c.level === level);
  document.getElementById('coursesGrid').innerHTML = data.map(c => `
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
        <button class="btn btn-gold" style="width:100%; justify-content:center" onclick="showPage('contact')">
          ${c.badge === 'Free' ? 'Start Free →' : 'Enroll Now →'}
        </button>
      </div>
    </div>`).join('');
}
function filterCourses(level, btn) {
  document.querySelectorAll('#page-learner .tabs .tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  renderCourses(level);
}

/* ===== CALLS DATA — STRUCTURED ===== */
const callsData = {
  intraday: [
    {stock:'NIFTY 50', name:'Index F&O — Intraday', action:'buy', entry:'24,250', target:'24,480', sl:'24,160', ret:'+9.6%', date:'Apr 9', horizon:'Same Day', rr:'2.6:1'},
    {stock:'ICICIBANK', name:'ICICI Bank Ltd', action:'buy', entry:'₹1,240', target:'₹1,265', sl:'₹1,228', ret:'+2.0%', date:'Apr 2', horizon:'Same Day', rr:'2.1:1'},
    {stock:'RELIANCE', name:'Reliance Industries', action:'sell', entry:'₹2,900', target:'₹2,840', sl:'₹2,935', ret:'+2.1%', date:'Mar 27', horizon:'Same Day', rr:'1.7:1'},
    {stock:'HDFCBANK', name:'HDFC Bank Ltd', action:'buy', entry:'₹1,590', target:'₹1,618', sl:'₹1,578', ret:'+1.8%', date:'Mar 26', horizon:'Same Day', rr:'2.3:1'},
  ],
  swing: [
    {stock:'TATAMOTORS', name:'Tata Motors Ltd', action:'buy', entry:'₹838', target:'₹920', sl:'₹805', ret:'+15.3%', date:'Apr 8', horizon:'7–14 Days', rr:'2.5:1'},
    {stock:'DIXON', name:'Dixon Technologies', action:'buy', entry:'₹12,850', target:'₹14,200', sl:'₹12,100', ret:'+10.5%', date:'Apr 1', horizon:'10 Days', rr:'1.8:1'},
    {stock:'WIPRO', name:'Wipro Ltd', action:'buy', entry:'₹490', target:'₹528', sl:'₹472', ret:'+7.8%', date:'Mar 28', horizon:'12 Days', rr:'2.1:1'},
    {stock:'SBIN', name:'State Bank of India', action:'buy', entry:'₹720', target:'₹780', sl:'₹692', ret:'+8.3%', date:'Mar 25', horizon:'8 Days', rr:'2.1:1'},
  ],
  positional: [
    {stock:'HCLTECH', name:'HCL Technologies', action:'buy', entry:'₹1,650', target:'₹1,920', sl:'₹1,550', ret:'+16.4%', date:'Apr 4', horizon:'1–3 Months', rr:'2.7:1'},
    {stock:'ASIANPAINT', name:'Asian Paints Ltd', action:'buy', entry:'₹2,850', target:'₹3,200', sl:'₹2,700', ret:'+12.3%', date:'Mar 20', horizon:'2 Months', rr:'2.3:1'},
    {stock:'MARUTI', name:'Maruti Suzuki India', action:'buy', entry:'₹11,800', target:'₹13,500', sl:'₹11,100', ret:'+14.4%', date:'Mar 15', horizon:'3 Months', rr:'2.4:1'},
    {stock:'BAJFINANCE', name:'Bajaj Finance Ltd', action:'buy', entry:'₹6,800', target:'₹7,800', sl:'₹6,400', ret:'+14.7%', date:'Mar 10', horizon:'2 Months', rr:'2.5:1'},
  ],
  value: [
    {stock:'HDFCBANK', name:'HDFC Bank Limited', action:'buy', entry:'₹1,595', target:'₹2,100', sl:'₹1,350', ret:'+31.7%', date:'Jan 15', horizon:'18–24 Months', rr:'2.1:1'},
    {stock:'NESTLEIND', name:'Nestle India Ltd', action:'buy', entry:'₹2,350', target:'₹2,950', sl:'₹2,050', ret:'+25.5%', date:'Feb 10', horizon:'24 Months', rr:'2.0:1'},
    {stock:'AMIORG', name:'Ami Organics Ltd', action:'buy', entry:'₹1,040', target:'₹1,600', sl:'₹880', ret:'+53.8%', date:'Dec 5', horizon:'24 Months', rr:'3.5:1'},
    {stock:'BIKAJI', name:'Bikaji Foods Intl', action:'buy', entry:'₹608', target:'₹900', sl:'₹520', ret:'+48.0%', date:'Nov 20', horizon:'30 Months', rr:'3.3:1'},
  ],
  monthly: [
    {stock:'NIFTYBEES', name:'Nifty 50 ETF — SIP', action:'buy', entry:'₹240/unit', target:'₹320', sl:'N/A', ret:'+33.3%', date:'Jan 1', horizon:'12 Months SIP', rr:'Long Term'},
    {stock:'JUNIORBEES', name:'Nifty Next 50 ETF', action:'buy', entry:'₹65/unit', target:'₹95', sl:'N/A', ret:'+46.2%', date:'Jan 1', horizon:'12 Months SIP', rr:'Long Term'},
    {stock:'PPFAS', name:'Parag Parikh Flexi Cap', action:'buy', entry:'₹5,000/month', target:'Wealth Creation', sl:'N/A', ret:'+26.8% XIRR', date:'Ongoing', horizon:'3–5 Years', rr:'Long Term'},
    {stock:'MIRAE', name:'Mirae Asset Large Cap', action:'buy', entry:'₹3,000/month', target:'Wealth Creation', sl:'N/A', ret:'+21.4% XIRR', date:'Ongoing', horizon:'5+ Years', rr:'Long Term'},
  ],
};

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

function renderCalls(type) {
  const data = callsData[type] || [];
  const isLong = (type === 'value' || type === 'monthly');
  document.getElementById('callsGrid').innerHTML = data.map(c => `
    <div class="call-card">
      <div>
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap">
          <span class="${c.action === 'buy' ? 'badge-buy' : 'badge-sell'}">${c.action.toUpperCase()}</span>
          <span style="font-size:11px; color:var(--grey); text-transform:capitalize">${type}</span>
          <span style="font-size:11px; color:var(--grey); background:rgba(255,255,255,0.05); padding:2px 8px; border-radius:10px;">⏱ ${c.horizon}</span>
        </div>
        <div class="call-stock">${c.stock}</div>
        <div class="call-name">${c.name}</div>
        <div class="call-details">
          <div class="call-detail">Entry: <span>${c.entry}</span></div>
          <div class="call-detail">Target: <span style="color:var(--green)">${c.target}</span></div>
          <div class="call-detail">SL: <span style="color:var(--red)">${c.sl}</span></div>
          <div class="call-detail">R:R <span>${c.rr}</span></div>
          <div class="call-detail">Date: <span>${c.date}</span></div>
        </div>
      </div>
      <div class="call-return">
        <div class="r ${parseFloat(c.ret) >= 0 ? 'badge-pos' : 'badge-neg'}">${c.ret}</div>
        <small>Return</small>
      </div>
    </div>`).join('');
}

/* ===== PERFORMANCE DATA ===== */
const perfData = [
  {stock:'TATAMOTORS', type:'equity', entry:'780', target:'920', sl:'740', exit:'915', pnl:'+17.3%', status:'Target Hit', date:'Apr 8'},
  {stock:'ICICIBANK', type:'intraday', entry:'1,220', target:'1,260', sl:'1,205', exit:'1,258', pnl:'+3.1%', status:'Target Hit', date:'Apr 7'},
  {stock:'HCLTECH', type:'equity', entry:'1,650', target:'1,850', sl:'1,580', exit:'1,840', pnl:'+11.5%', status:'Target Hit', date:'Apr 4'},
  {stock:'NIFTY CE 24000', type:'options', entry:'180', target:'320', sl:'110', exit:'315', pnl:'+75.0%', status:'Target Hit', date:'Apr 2'},
  {stock:'BAJFINANCE', type:'equity', entry:'6,800', target:'7,500', sl:'6,500', exit:'6,490', pnl:'-4.6%', status:'SL Hit', date:'Mar 31'},
  {stock:'WIPRO', type:'intraday', entry:'490', target:'502', sl:'484', exit:'501', pnl:'+2.2%', status:'Target Hit', date:'Mar 28'},
  {stock:'SBIN', type:'equity', entry:'720', target:'820', sl:'685', exit:'815', pnl:'+13.2%', status:'Target Hit', date:'Mar 25'},
  {stock:'NIFTY PE 23500', type:'options', entry:'150', target:'280', sl:'90', exit:'88', pnl:'-41.3%', status:'SL Hit', date:'Mar 22'},
  {stock:'ASIAN PAINTS', type:'equity', entry:'2,850', target:'3,200', sl:'2,720', exit:'3,190', pnl:'+12.0%', status:'Target Hit', date:'Mar 20'},
  {stock:'MARUTI', type:'equity', entry:'11,800', target:'13,200', sl:'11,200', exit:'13,100', pnl:'+11.0%', status:'Target Hit', date:'Mar 15'},
];
function renderPerfTable(type) {
  const data = type === 'all' ? perfData : perfData.filter(p => p.type === type);
  document.getElementById('perfBody').innerHTML = data.map(p => `
    <tr>
      <td style="color:var(--white); font-weight:600">${p.stock}</td>
      <td><span style="text-transform:capitalize; color:var(--grey2)">${p.type}</span></td>
      <td>${p.entry}</td>
      <td style="color:var(--green)">${p.target}</td>
      <td style="color:var(--red)">${p.sl}</td>
      <td style="color:var(--white)">${p.exit}</td>
      <td class="${parseFloat(p.pnl) >= 0 ? 'badge-pos' : 'badge-neg'}">${p.pnl}</td>
      <td><span class="${p.status === 'Target Hit' ? 'badge-buy' : 'badge-sell'}">${p.status}</span></td>
      <td>${p.date}</td>
    </tr>`).join('');
}
function filterPerf(type, btn) {
  document.querySelectorAll('#page-performance .tabs .tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  renderPerfTable(type);
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

/* ===== CONTACT FORM ===== */
function submitContact() {
  const name = document.getElementById('cfName').value;
  const email = document.getElementById('cfEmail').value;
  const msg = document.getElementById('cfMessage').value;
  if (!name || !email || !msg) { alert('Please fill Name, Email, and Message.'); return; }
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
