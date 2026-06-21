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
let perfDateFrom = '';                     // 'YYYY-MM-DD' or '' (no bound)
let perfDateTo = '';
const PERF_WA_NUMBER = '917467094575';     // RootNivesh WhatsApp

// Local YYYY-MM-DD (avoid toISOString UTC off-by-one in IST).
function perfYmd(d) {
  const z = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
  return z.toISOString().slice(0, 10);
}
function perfReloadActive() {
  (perfMode === 'achieved' ? loadPerformance : loadLivePerf)(currentPerfType);
}
// Date-range chip handler: All / Today / This Week / Custom.
function setPerfRange(range, btn) {
  document.querySelectorAll('#perfDateBar .perf-date').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  const custom = document.getElementById('perfCustomWrap');
  if (custom) custom.style.display = (range === 'custom') ? '' : 'none';
  const today = new Date();
  if (range === 'all') {
    perfDateFrom = ''; perfDateTo = '';
  } else if (range === 'today') {
    perfDateFrom = perfDateTo = perfYmd(today);
  } else if (range === 'week') {
    const dow = (today.getDay() + 6) % 7;            // Mon=0 … Sun=6
    const mon = new Date(today); mon.setDate(today.getDate() - dow);
    perfDateFrom = perfYmd(mon); perfDateTo = perfYmd(today);
  } else if (range === 'custom') {
    // Wait for both inputs via applyPerfCustom(); don't reload yet.
    return;
  }
  perfReloadActive();
}
function applyPerfCustom() {
  const f = document.getElementById('perfFrom');
  const t = document.getElementById('perfTo');
  perfDateFrom = (f && f.value) ? f.value : '';
  perfDateTo   = (t && t.value) ? t.value : '';
  perfReloadActive();
}
// Client-side date-in-range test (used for Live calls, by posted date).
function inPerfRange(iso) {
  if (!perfDateFrom && !perfDateTo) return true;
  const d = (iso || '').slice(0, 10);
  if (perfDateFrom && d < perfDateFrom) return false;
  if (perfDateTo && d > perfDateTo) return false;
  return true;
}

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

  const qs = [];
  if (type) qs.push('type=' + encodeURIComponent(type));
  if (perfDateFrom) qs.push('from=' + perfDateFrom);
  if (perfDateTo) qs.push('to=' + perfDateTo);
  // cache:'no-store' so a freshly updated call shows immediately (no stale 30s copy).
  fetch('/performance.php' + (qs.length ? '?' + qs.join('&') : ''), { credentials: 'same-origin', cache: 'no-store' })
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

  fetch('/calls.php?limit=100' + (type ? ('&type=' + encodeURIComponent(type)) : ''), { credentials: 'same-origin', cache: 'no-store' })
    .then(r => r.json())
    .then(d => { renderLivePerf((d.calls || []).filter(c => c.status === 'open' && inPerfRange(c.posted_at))); })
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
    // Server already redacts the raw levels; we only get achieved targets + booked %.
    const hit = parseInt(c.targets_hit || 0, 10) || 0;
    const total = parseInt(c.targets_total || 0, 10) || 0;

    // Progress cell: how many targets are booked (percentage intentionally hidden).
    let progress;
    if (c.booked_pct != null) {
      progress = `<span class="perf-result win">✅ ${hit} target${hit > 1 ? 's' : ''} hit</span>`;
    } else {
      progress = `<span class="perf-running">🟢 Active</span>`;
    }

    // Targets cell: achieved targets shown as proof, remaining ones locked behind WhatsApp.
    let targetsCell;
    if (hit > 0 && c.targets_achieved) {
      const remaining = Math.max(0, total - hit);
      const lock = remaining > 0
        ? ` <span class="perf-locked" onclick="window.open('${wa}','_blank')" title="Get the remaining targets on WhatsApp"><span class="perf-blur">+${remaining} more</span><span class="perf-lock-wa">💬</span></span>`
        : '';
      targetsCell = `<td><span style="color:var(--green)">${escapeHtml(c.targets_achieved)} ✅</span>${lock}</td>`;
    } else {
      targetsCell = `<td class="perf-locked" onclick="window.open('${wa}','_blank')" title="Get the targets on WhatsApp"><span class="perf-blur">₹₹₹₹</span><span class="perf-lock-wa">💬</span></td>`;
    }

    return `
    <tr>
      <td>${fmtDate(c.posted_at)}</td>
      <td><span class="perf-stock">${escapeHtml(c.symbol)}</span></td>
      <td style="text-transform:capitalize">${escapeHtml(c.call_type)}</td>
      <td><span class="perf-side ${c.action === 'BUY' ? 'buy' : 'sell'}">${escapeHtml(c.action)}</span></td>
      <td>${progress}</td>
      ${targetsCell}
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
      <td>${fmtDate(c.posted_at || c.exit_at)}</td>
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
  const waUrl = c => 'https://wa.me/' + PERF_WA_NUMBER + '?text=' +
    encodeURIComponent(`Hi RootNivesh, I want the entry & target levels for your LIVE ${c.symbol} ${String(c.call_type).toUpperCase()} call. Please add me.`);
  grid.innerHTML = data.map((c) => {
    const isBuy = c.action === 'BUY';
    // Levels are redacted server-side; only achieved targets + booked % arrive.
    const hit = parseInt(c.targets_hit || 0, 10) || 0;
    const total = parseInt(c.targets_total || 0, 10) || 0;
    const bookedPct = c.booked_pct == null ? null : parseFloat(c.booked_pct);
    const wa = waUrl(c);

    const targetCell = (hit > 0 && c.targets_achieved)
      ? `<span style="color:var(--green)">${escapeHtml(c.targets_achieved)} ✅</span>${total - hit > 0 ? ` <span class="call-wa-lock" onclick="window.open('${wa}','_blank')">🔒 +${total - hit} more 💬</span>` : ''}`
      : `<span class="call-wa-lock" onclick="window.open('${wa}','_blank')">🔒 on WhatsApp 💬</span>`;
    const ret = bookedPct != null ? (bookedPct >= 0 ? '+' : '') + bookedPct.toFixed(2) + '%' : 'LIVE';

    return `
    <div class="call-card">
      <div class="call-card-inner">
        <div>
          <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap">
            <span class="${isBuy ? 'badge-buy' : 'badge-sell'}">${escapeHtml(c.action)}</span>
            <span style="font-size:11px; color:var(--grey); text-transform:capitalize">${escapeHtml(c.call_type)}</span>
          </div>
          <div class="call-stock">${escapeHtml(c.symbol)}</div>
          <div class="call-name">${escapeHtml(c.company_name || '')}</div>
          <div class="call-details">
            <div class="call-detail">Entry: <span class="call-wa-lock" onclick="window.open('${wa}','_blank')">🔒 💬</span></div>
            <div class="call-detail">Target: <span>${targetCell}</span></div>
            <div class="call-detail">SL: <span class="call-wa-lock" onclick="window.open('${wa}','_blank')">🔒 💬</span></div>
            <div class="call-detail">Date: <span>${fmtDate(c.posted_at)}</span></div>
          </div>
        </div>
        <div class="call-return">
          <div class="r ${bookedPct == null || bookedPct >= 0 ? 'badge-pos' : 'badge-neg'}">${ret}</div>
          <small>${bookedPct != null ? 'Booked' : 'Status'}</small>
        </div>
      </div>
      <a class="call-wa-btn" href="${wa}" target="_blank" rel="noopener">💬 Get entry &amp; targets on WhatsApp</a>
    </div>`;
  }).join('');
}

// Smooth-scroll to the plans section. Used by the locked-call overlay
// so visitors who tap a teaser jump straight to "where to subscribe".
function scrollToPlans() {
  const el = document.getElementById('plansSection');
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

