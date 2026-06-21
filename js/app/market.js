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
    fetchTopMovers();
    fetchFiiDiiHistory(); // FII/DII publishes T+1 evening but we still poll so the new day's data appears within 60s of the 7:30 PM cutoff
  }, 60 * 1000);
  // Hero Nifty/Sensex tiles get their own faster cadence (15s) so the headline
  // index prints feel near-live. Server cache (indices.php) is also 15s in
  // market hours, so this is the tightest the data updates without hammering
  // NSE/BSE harder than ~4 req/min.
  setInterval(() => {
    if (!isMarketOpenIST()) return;
    fetchIndices();
  }, 15 * 1000);
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
   Hits /indices.php (NSE/BSE direct, Yahoo fallback), cached server-side.
   Server cache + client poll are both 15s in market hours → near-live tiles. */
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
  if (typeof initToolRows === 'function') initToolRows();
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

