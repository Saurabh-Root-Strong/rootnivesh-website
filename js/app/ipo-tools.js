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

/* ================================================================
   GMP (Grey Market Premium) — /gmp.php
   The grey market is unofficial; NSE has no GMP feed. gmp.php scrapes
   the IPO trackers, derives a rating from the estimated listing gain,
   and caches. Here we just join it onto the NSE rows by company name.
   ================================================================ */
let GMP_MAP  = null;   // key -> row
let GMP_META = null;   // { source, fetched_at, stale }
let GMP_AT   = 0;      // when we last loaded it, so the 60s table refresh re-pulls
const GMP_CLIENT_TTL = 10 * 60 * 1000;   // 10 min (server caches 30 min anyway)

/* Must mirror gmp_key() in gmp.php, so both sides of the join agree. */
function gmpKey(name) {
  return String(name || '')
    .toLowerCase()
    .replace(/\b(limited|ltd|private|pvt|india|ipo|the)\b/g, ' ')
    .replace(/\((?:bse|nse)?\s*sme\)|\(mainboard\)|\(eq\)/g, ' ')
    .replace(/[^a-z0-9 ]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

async function loadGmp() {
  if (GMP_MAP && (Date.now() - GMP_AT) < GMP_CLIENT_TTL) return GMP_MAP;
  try {
    const res = await fetch('/gmp.php?cb=' + Date.now(), { signal: AbortSignal.timeout(15000) });
    if (!res.ok) throw new Error('gmp.php ' + res.status);
    const p = await res.json();
    GMP_MAP  = new Map((p.rows || []).map(r => [r.key, r]));
    GMP_META = { source: p.source, fetched_at: p.fetched_at, stale: !!p.stale };
    GMP_AT   = Date.now();
  } catch (e) {
    GMP_MAP  = new Map();   // empty map => every cell renders "—", page still fine
    GMP_META = null;
    GMP_AT   = Date.now();  // don't hammer a dead endpoint on every 60s refresh
  }
  return GMP_MAP;
}

/* Exact key first; else best token-overlap match. NSE says "Laser Power &
   Infra Limited", the tracker says "Laser Power & Infra" — after gmpKey()
   both are "laser power infra", so exact hits most of the time. The fuzzy
   pass catches the rest (abbreviations, dropped words), but only on a
   strong overlap and a shared first token, so we never show one company's
   GMP against another's row. */
function gmpFor(companyName) {
  if (!GMP_MAP || GMP_MAP.size === 0) return null;
  const k = gmpKey(companyName);
  if (!k) return null;
  if (GMP_MAP.has(k)) return GMP_MAP.get(k);

  const qt = k.split(' ').filter(Boolean);
  if (!qt.length) return null;
  let best = null, bestScore = 0;
  GMP_MAP.forEach((row, rk) => {
    const rt = rk.split(' ').filter(Boolean);
    if (!rt.length || rt[0] !== qt[0]) return;             // first token must agree
    const hits = qt.filter(t => rt.includes(t)).length;
    const score = hits / Math.max(qt.length, rt.length);   // Jaccard-ish
    if (score > bestScore) { bestScore = score; best = row; }
  });
  return bestScore >= 0.6 ? best : null;
}

function gmpCell(companyName) {
  const g = gmpFor(companyName);
  if (!g || g.gmp === null) return '<span style="color:var(--grey)">—</span>';
  const up   = g.gmp > 0;
  const flat = g.gmp === 0;
  const col  = flat ? 'var(--grey2)' : (up ? '#22c55e' : '#ef4444');
  const sign = up ? '+' : '';
  const sub  = (g.est_gain_pct !== null && !flat)
    ? `<div style="font-size:10px; color:var(--grey); font-family:'Space Mono',monospace">${sign}${g.est_gain_pct}%</div>`
    : '';
  return `<span style="color:${col}; font-weight:700; white-space:nowrap">${sign}₹${escapeHtml(String(g.gmp))}</span>${sub}`;
}

function gmpRatingCell(companyName) {
  const g = gmpFor(companyName);
  if (!g || !g.rating_stars) return '<span style="color:var(--grey)">—</span>';
  const stars = '★'.repeat(g.rating_stars) + '☆'.repeat(5 - g.rating_stars);
  const col = g.rating_stars >= 4 ? '#22c55e'
            : g.rating_stars === 3 ? 'var(--gold)'
            : g.rating_stars === 2 ? 'var(--grey2)' : '#ef4444';
  return `<span title="Derived from grey-market listing gain — not a recommendation" style="color:${col}; white-space:nowrap">${stars}</span>` +
         `<div style="font-size:10px; color:var(--grey); font-family:'Space Mono',monospace">${escapeHtml(g.rating_label)}</div>`;
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
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:40px; color:var(--grey)">No IPOs to show in this tab right now.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map(r => {
    const name = r.companyName || r.symbol || '—';
    return `
    <tr>
      <td style="color:var(--white); font-weight:600">${escapeHtml(name)}</td>
      <td><span style="text-transform:capitalize; color:var(--grey2)">${escapeHtml(r.series || 'Mainboard')}</span></td>
      <td style="text-align:center">${gmpCell(name)}</td>
      <td style="text-align:center">${gmpRatingCell(name)}</td>
      <td>${escapeHtml(fmtIpoDate(r.issueStartDate))}</td>
      <td>${escapeHtml(fmtIpoDate(r.issueEndDate))}</td>
      <td>${escapeHtml(fmtIssuePrice(r.issuePrice || r.priceBand))}</td>
      <td>${ipoBusinessCell(r)}</td>
    </tr>`;
  }).join('');
}

async function fetchIpo(tab) {
  currentIpoTab = tab;
  const status = document.getElementById('ipoStatus');
  const tbody  = document.getElementById('ipoBody');
  if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:40px; color:var(--grey)">Loading IPO data from NSE…</td></tr>';

  // Use /ipo.php (cookie-handshake-aware) instead of /proxy.php — the plain
  // proxy was returning intermittent stale/empty data because NSE's API
  // requires a session-cookie handshake first. ipo.php replicates the
  // pattern from indices.php and gainers-losers.php.
  const url = '/ipo.php?tab=' + encodeURIComponent(tab) + '&cb=' + Date.now();
  try {
    // GMP is a second, independent upstream. Fetch both in parallel and let the
    // GMP side fail soft — a dead GMP scraper must never blank the NSE table.
    const [res] = await Promise.all([
      fetch(url, { signal: AbortSignal.timeout(15000) }),
      loadGmp()
    ]);
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
      const gmpSrc = (GMP_META && GMP_META.source)
        ? ' • GMP: ' + GMP_META.source + (GMP_META.stale ? ' (stale)' : '')
        : '';
      status.textContent = (stale ? 'Stale — last fetched ' : 'Updated ') + at + ' • Source: NSE India.' + dayCap + gmpSrc;
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

  // Shared trust strip — honest reassurances that remove buyer risk/friction.
  const trustHTML = (typeof PLAN_TRUST_POINTS !== 'undefined')
    ? '<ul class="plan-trust-list">' +
        PLAN_TRUST_POINTS.map(t => '<li><span class="plan-trust-tick">✓</span>' + t + '</li>').join('') +
      '</ul>'
    : '';

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

  const popularRibbon = p.popular
    ? '<span class="plan-popular-badge">★ MOST POPULAR</span>'
    : '';

  return ''
    + '<div class="plan-card plan-card-' + p.type + (p.popular ? ' plan-card-popular' : '') + openClass + '" id="plan-card-' + p.id + '" ' + dataAttrs + '>'

    // ── ALWAYS-VISIBLE HEADER (click to toggle) ──────────────────────
    +   '<button class="plan-card-toggle" onclick="togglePlanCard(\'' + p.id + '\')" aria-label="Toggle ' + p.name + ' plan details">'
    +     '<div class="plan-toggle-left">'
    +       '<span class="plan-toggle-icon">' + p.icon + '</span>'
    +       '<div class="plan-toggle-text">'
    +         '<span class="plan-toggle-name">' + p.name + ' ' + offerRibbon + ' ' + popularRibbon + '</span>'
    +         '<span class="plan-toggle-tagline">' + p.tagline + '</span>'
    +       '</div>'
    +     '</div>'
    +     '<div class="plan-toggle-right">'
    +       '<span class="plan-toggle-chev">▾</span>'
    +     '</div>'
    +   '</button>'

    // ── COLLAPSIBLE BODY ─────────────────────────────────────────────
    +   '<div class="plan-collapse">'
    +     '<div class="plan-body">'
    +       chipsHTML
    +       '<p class="plan-desc">' + p.description + '</p>'
    +       (featuresList ? '<ul class="plan-features-list">' + featuresList + '</ul>' : '')
    +       trustHTML
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
    +         (p.type === 'program' ? 'Apply for Programme →' : 'Get Instant Access →')
    +       '</button>'
    +       '<p class="plan-cta-reassure">⚡ Activated on WhatsApp within minutes • Cancel anytime</p>'
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
