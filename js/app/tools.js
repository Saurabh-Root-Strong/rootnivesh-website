/* ============================================================
   tools.js — RootNivesh financial & trading calculators.

   Pure compute + DOM rendering. The only network dependency is
   the shared /proxy.php (Yahoo Finance FX) used by the live
   currency converter; everything else runs entirely client-side.

   Loaded after core.js. None of these functions run at load time —
   each fires from a button in the Tools page.

   Tools (high India search intent):
     calcEMI            — loan EMI / interest
     calcBrokerage      — brokerage + all statutory charges, net P&L, breakeven
     calcOptions        — F&O option payoff + multi-leg strategy builder
     calcAverage        — stock / share average price (cost averaging)
     calcSIP            — monthly SIP future value
     returns (CAGR/XIRR/SIP-vs-Lumpsum)
     convertCurrency    — live FX via Yahoo Finance
   ============================================================ */

/* ---- shared formatters ---- */
function fmt(n) {
  if (!isFinite(n)) return '0';
  const a = Math.abs(n);
  if (a >= 1e7) return (n / 1e7).toFixed(2) + 'Cr';
  if (a >= 1e5) return (n / 1e5).toFixed(2) + 'L';
  return Math.round(n).toLocaleString('en-IN');
}
function inr(n)  { return '₹' + Math.round(n).toLocaleString('en-IN'); }
function inr2(n) { return '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function toolNum(id) { const el = document.getElementById(id); return el ? parseFloat(el.value) : NaN; }

/* ============================================================
   EMI CALCULATOR
   ============================================================ */
function calcEMI() {
  const P = toolNum('loanAmount');
  const annualR = toolNum('interestRate');
  let n = toolNum('tenureValue');
  if (document.getElementById('tenureType').value === 'years') n *= 12;
  if (!P || !annualR || !n) return;
  const r = annualR / 12 / 100;
  const emi = P * r * Math.pow(1 + r, n) / (Math.pow(1 + r, n) - 1);
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

/* ============================================================
   BROKERAGE + NET P&L / BREAKEVEN CALCULATOR
   Indian equity & F&O statutory charges. Rates reflect the
   post-1-Oct-2024 STT structure. Exchange/SEBI rates are NSE
   approximations and can change — shown as an estimate.
   ============================================================ */
const BROKER_RATES = {
  // segment: { sttBuy, sttSell, exch, stamp, dp(flat ₹, delivery sell only) }
  delivery: { sttBuy: 0.001,    sttSell: 0.001,    exch: 0.0000297, stamp: 0.00015, dp: 15.93 },
  intraday: { sttBuy: 0,        sttSell: 0.00025,  exch: 0.0000297, stamp: 0.00003, dp: 0 },
  futures:  { sttBuy: 0,        sttSell: 0.0002,   exch: 0.0000173, stamp: 0.00002, dp: 0 },
  options:  { sttBuy: 0,        sttSell: 0.001,    exch: 0.0003503, stamp: 0.00003, dp: 0 },
};
function brokerageSegmentDefault() {
  // Delivery brokerage is ₹0 at discount brokers; everything else flat ₹20/order.
  const seg = document.getElementById('brkSegment').value;
  document.getElementById('brkPerOrder').value = seg === 'delivery' ? 0 : 20;
}
function calcBrokerage() {
  const seg   = document.getElementById('brkSegment').value;
  const buy   = toolNum('brkBuy');
  const sell  = toolNum('brkSell');
  const qty   = toolNum('brkQty');
  let perOrder = toolNum('brkPerOrder'); if (!isFinite(perOrder)) perOrder = 0;
  if (!buy || !sell || !qty) return;

  const R = BROKER_RATES[seg];
  const buyTo  = buy * qty;
  const sellTo = sell * qty;
  const turnover = buyTo + sellTo;

  const brokerage = perOrder * 2;                       // buy + sell order
  const stt   = buyTo * R.sttBuy + sellTo * R.sttSell;
  const exch  = turnover * R.exch;
  const sebi  = turnover * 0.000001;                    // ₹10 per crore
  const gst   = (brokerage + exch + sebi) * 0.18;
  const stamp = buyTo * R.stamp;
  const dp    = R.dp;
  const charges = brokerage + stt + exch + sebi + gst + stamp + dp;

  const gross = sellTo - buyTo;
  const net   = gross - charges;
  const beMove = charges / qty;                         // price move needed to break even
  const bePrice = buy + beMove;
  const netClass = net >= 0 ? 'var(--green)' : '#ff6b6b';

  document.getElementById('brkTurnover').textContent = inr(turnover);
  document.getElementById('brkCharges').textContent  = inr2(charges);
  document.getElementById('brkGross').textContent    = inr2(gross);
  document.getElementById('brkNet').textContent      = inr2(net);
  document.getElementById('brkNet').style.color      = netClass;

  const row = (k, v) => `<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)"><span style="color:var(--grey)">${k}</span><span>${v}</span></div>`;
  document.getElementById('brkBreakdown').innerHTML =
    row('Brokerage', inr2(brokerage)) +
    row('STT / CTT', inr2(stt)) +
    row('Exchange txn charges', inr2(exch)) +
    row('GST (18%)', inr2(gst)) +
    row('SEBI charges', inr2(sebi)) +
    row('Stamp duty', inr2(stamp)) +
    (dp ? row('DP charges', inr2(dp)) : '') +
    `<div style="display:flex;justify-content:space-between;padding:8px 0 0;font-weight:700"><span>Total charges</span><span style="color:var(--gold)">${inr2(charges)}</span></div>` +
    `<div style="margin-top:10px;font-size:12px;color:var(--grey2)">Breakeven: price must move <strong style="color:var(--white)">₹${beMove.toFixed(2)}</strong> (to ≈ <strong style="color:var(--white)">₹${bePrice.toFixed(2)}</strong>) just to cover costs.</div>` +
    `<div style="margin-top:6px;font-size:11px;color:var(--grey)">Statutory rates are estimates (post-Oct-2024 STT) and vary by broker/exchange. Verify on your contract note.</div>`;
  document.getElementById('brkResult').classList.add('show');
}

/* ============================================================
   STOCK AVERAGE CALCULATOR  (cost averaging)
   ============================================================ */
let avgRowCount = 0;
function addAvgRow(price, qty) {
  avgRowCount++;
  const wrap = document.getElementById('avgRows');
  const div = document.createElement('div');
  div.className = 'avg-row';
  div.style.cssText = 'display:grid;grid-template-columns:1fr 1fr auto;gap:10px;margin-bottom:10px;align-items:end';
  div.innerHTML =
    `<div class="form-group" style="margin:0"><label>Buy Price (₹)</label><input type="number" class="avg-price" placeholder="e.g. 250" value="${price != null ? price : ''}"></div>` +
    `<div class="form-group" style="margin:0"><label>Quantity</label><input type="number" class="avg-qty" placeholder="e.g. 100" value="${qty != null ? qty : ''}"></div>` +
    `<button type="button" class="calc-btn" style="background:transparent;border:1px solid var(--border);color:var(--grey);max-width:46px;padding:10px" onclick="this.parentElement.remove()" title="Remove">✕</button>`;
  wrap.appendChild(div);
}
function calcAverage() {
  const prices = document.querySelectorAll('#avgRows .avg-price');
  const qtys   = document.querySelectorAll('#avgRows .avg-qty');
  let totQty = 0, totCost = 0;
  for (let i = 0; i < prices.length; i++) {
    const p = parseFloat(prices[i].value), q = parseFloat(qtys[i].value);
    if (p > 0 && q > 0) { totQty += q; totCost += p * q; }
  }
  if (totQty === 0) return;
  const avg = totCost / totQty;
  document.getElementById('avgPrice').textContent = '₹' + avg.toFixed(2);
  document.getElementById('avgQty').textContent   = totQty.toLocaleString('en-IN');
  document.getElementById('avgInvest').textContent = '₹' + fmt(totCost);
  document.getElementById('avgResult').classList.add('show');
}

/* ============================================================
   OPTIONS PAYOFF + STRATEGY BUILDER  (F&O, expiry payoff)
   ============================================================ */
let optLegCount = 0;
function addOptLeg(side, type, strike, premium, lots) {
  optLegCount++;
  const wrap = document.getElementById('optLegs');
  const div = document.createElement('div');
  div.className = 'opt-leg';
  div.style.cssText = 'display:grid;grid-template-columns:repeat(4,1fr) auto;gap:8px;margin-bottom:8px;align-items:end';
  div.innerHTML =
    `<div class="form-group" style="margin:0"><label>Action</label><select class="opt-side"><option value="buy"${side==='sell'?'':' selected'}>Buy</option><option value="sell"${side==='sell'?' selected':''}>Sell</option></select></div>` +
    `<div class="form-group" style="margin:0"><label>Type</label><select class="opt-type"><option value="call"${type==='put'?'':' selected'}>Call</option><option value="put"${type==='put'?' selected':''}>Put</option></select></div>` +
    `<div class="form-group" style="margin:0"><label>Strike</label><input type="number" class="opt-strike" placeholder="e.g. 24000" value="${strike != null ? strike : ''}"></div>` +
    `<div class="form-group" style="margin:0"><label>Premium</label><input type="number" class="opt-prem" placeholder="e.g. 120" value="${premium != null ? premium : ''}"></div>` +
    `<button type="button" class="calc-btn" style="background:transparent;border:1px solid var(--border);color:var(--grey);max-width:46px;padding:10px" onclick="this.parentElement.remove()" title="Remove leg">✕</button>`;
  wrap.appendChild(div);
}
function _optLegs() {
  const sides   = document.querySelectorAll('#optLegs .opt-side');
  const types   = document.querySelectorAll('#optLegs .opt-type');
  const strikes = document.querySelectorAll('#optLegs .opt-strike');
  const prems   = document.querySelectorAll('#optLegs .opt-prem');
  const legs = [];
  for (let i = 0; i < strikes.length; i++) {
    const k = parseFloat(strikes[i].value), p = parseFloat(prems[i].value);
    if (k > 0 && p >= 0) legs.push({ side: sides[i].value, type: types[i].value, strike: k, premium: p });
  }
  return legs;
}
function _legPnL(leg, S, lotSize) {
  const intrinsic = leg.type === 'call' ? Math.max(S - leg.strike, 0) : Math.max(leg.strike - S, 0);
  const per = leg.side === 'buy' ? (intrinsic - leg.premium) : (leg.premium - intrinsic);
  return per * lotSize;
}
function calcOptions() {
  const legs = _optLegs();
  const lotSize = toolNum('optLotSize') || 1;
  if (!legs.length) return;

  const strikes = legs.map(l => l.strike);
  const lo = Math.min(...strikes) * 0.85;
  const hi = Math.max(...strikes) * 1.15;
  const steps = 240;
  const pts = [];
  for (let i = 0; i <= steps; i++) {
    const S = lo + (hi - lo) * i / steps;
    let pnl = 0;
    legs.forEach(l => { pnl += _legPnL(l, S, lotSize); });
    pts.push({ S, pnl });
  }
  // Net premium (debit negative, credit positive), per full position.
  let netPrem = 0;
  legs.forEach(l => { netPrem += (l.side === 'buy' ? -l.premium : l.premium) * lotSize; });

  const pnls = pts.map(p => p.pnl);
  let maxP = Math.max(...pnls), minP = Math.min(...pnls);
  // Boundary slope → unlimited detection.
  const rising  = pnls[pnls.length - 1] > pnls[pnls.length - 2] + 1e-6;
  const falling = pnls[0] > pnls[1] + 1e-6; // payoff high at far-left edge (keeps rising as S→0)
  const maxLabel = rising  ? 'Unlimited' : inr(maxP);
  const minLabel = falling ? 'Unlimited loss' : inr(minP);

  // Breakevens — zero crossings (interpolated).
  const bes = [];
  for (let i = 1; i < pts.length; i++) {
    const a = pts[i - 1], b = pts[i];
    if ((a.pnl <= 0 && b.pnl > 0) || (a.pnl >= 0 && b.pnl < 0)) {
      const t = a.pnl / (a.pnl - b.pnl);
      bes.push(a.S + (b.S - a.S) * t);
    }
  }

  document.getElementById('optNetPrem').textContent = (netPrem >= 0 ? 'Credit ' : 'Debit ') + inr(Math.abs(netPrem));
  document.getElementById('optMaxP').textContent = maxLabel;
  document.getElementById('optMaxL').textContent = minLabel;
  document.getElementById('optBE').textContent = bes.length ? bes.map(b => Math.round(b)).join(', ') : '—';

  // Payoff at a chosen spot.
  const tSpot = toolNum('optSpot');
  const spotBox = document.getElementById('optSpotResult');
  if (isFinite(tSpot) && tSpot > 0) {
    let pnl = 0; legs.forEach(l => { pnl += _legPnL(l, tSpot, lotSize); });
    spotBox.innerHTML = `At expiry spot <strong style="color:var(--white)">${Math.round(tSpot)}</strong>, this strategy ${pnl >= 0 ? 'makes' : 'loses'} <strong style="color:${pnl >= 0 ? 'var(--green)' : '#ff6b6b'}">${inr(Math.abs(pnl))}</strong>.`;
    spotBox.style.display = 'block';
  } else { spotBox.style.display = 'none'; }

  document.getElementById('optChart').innerHTML = _optPayoffSvg(pts, bes);
  document.getElementById('optResult').classList.add('show');
}
function _optPayoffSvg(pts, bes) {
  const W = 600, H = 240, pad = 28;
  const xs = pts.map(p => p.S), ys = pts.map(p => p.pnl);
  const minX = Math.min(...xs), maxX = Math.max(...xs);
  const minY = Math.min(...ys), maxY = Math.max(...ys);
  const padY = (maxY - minY) * 0.1 || 1;
  const y0 = minY - padY, y1 = maxY + padY;
  const sx = S => pad + (S - minX) / (maxX - minX) * (W - 2 * pad);
  const sy = v => H - pad - (v - y0) / (y1 - y0) * (H - 2 * pad);
  const zeroY = sy(0);
  // Split the line into profit (green) and loss (red) by clipping at y=0 visually via two polylines.
  const path = pts.map((p, i) => (i ? 'L' : 'M') + sx(p.S).toFixed(1) + ' ' + sy(p.pnl).toFixed(1)).join(' ');
  const beMarks = bes.map(b => `<line x1="${sx(b).toFixed(1)}" y1="${pad}" x2="${sx(b).toFixed(1)}" y2="${H - pad}" stroke="rgba(229,165,10,0.5)" stroke-dasharray="3 3"/><text x="${sx(b).toFixed(1)}" y="${H - 8}" fill="var(--gold)" font-size="10" text-anchor="middle">${Math.round(b)}</text>`).join('');
  return `<svg viewBox="0 0 ${W} ${H}" width="100%" preserveAspectRatio="xMidYMid meet" style="background:rgba(255,255,255,0.02);border-radius:8px">
    <rect x="${pad}" y="${pad}" width="${W - 2 * pad}" height="${zeroY - pad}" fill="rgba(46,204,113,0.06)"/>
    <rect x="${pad}" y="${zeroY}" width="${W - 2 * pad}" height="${H - pad - zeroY}" fill="rgba(231,76,60,0.06)"/>
    <line x1="${pad}" y1="${zeroY.toFixed(1)}" x2="${W - pad}" y2="${zeroY.toFixed(1)}" stroke="rgba(255,255,255,0.25)"/>
    ${beMarks}
    <path d="${path}" fill="none" stroke="var(--gold)" stroke-width="2"/>
    <text x="${pad}" y="${pad - 8}" fill="var(--grey)" font-size="10">P&amp;L</text>
    <text x="${W - pad}" y="${H - 8}" fill="var(--grey)" font-size="10" text-anchor="end">Spot at expiry →</text>
  </svg>`;
}

/* ============================================================
   SIP CALCULATOR
   ============================================================ */
function calcSIP() {
  const monthly = toolNum('sipAmount');
  const annualR = toolNum('sipReturn');
  const years   = toolNum('sipYears');
  if (!monthly || !annualR || !years || monthly <= 0 || annualR < 0 || years <= 0) return;
  const n = years * 12;
  const r = annualR / 12 / 100;
  const fv = monthly * (Math.pow(1 + r, n) - 1) / r * (1 + r);
  const invested = monthly * n;
  const gains = fv - invested;
  document.getElementById('sipInvested').textContent   = '₹' + fmt(invested);
  document.getElementById('sipGains').textContent      = '₹' + fmt(gains);
  document.getElementById('sipMaturity').textContent   = '₹' + fmt(fv);
  document.getElementById('sipMultiplier').textContent = (fv / invested).toFixed(2) + 'x';
  const gainsPct = (gains / fv * 100).toFixed(1);
  const breakdown = document.getElementById('sipBreakdown');
  breakdown.style.display = 'block';
  breakdown.innerHTML =
    '<strong style="color:var(--gold)">Snapshot:</strong> investing ₹' + fmt(monthly) +
    ' every month for ' + years + ' years at ' + annualR + '% annual return ' +
    'compounds to <strong style="color:var(--white)">₹' + fmt(fv) + '</strong>. ' +
    'Of that, <strong style="color:var(--green)">' + gainsPct + '%</strong> is pure return — ' +
    'your contributions are only ₹' + fmt(invested) + '.';
  document.getElementById('sipResult').classList.add('show');
}

/* ============================================================
   RETURNS — CAGR / XIRR / SIP-vs-LUMPSUM (sub-tabbed)
   ============================================================ */
function returnsTab(which, btn) {
  ['cagr', 'xirr', 'lump'].forEach(t => {
    const el = document.getElementById('ret-' + t);
    if (el) el.style.display = t === which ? 'block' : 'none';
  });
  document.querySelectorAll('#ret-tabs .tab').forEach(t => t.classList.remove('active'));
  if (btn) btn.classList.add('active');
}
function calcCAGR() {
  const initial = toolNum('cagrInit');
  const final   = toolNum('cagrFinal');
  const years   = toolNum('cagrYears');
  if (!initial || !final || !years || initial <= 0 || years <= 0) return;
  const cagr = (Math.pow(final / initial, 1 / years) - 1) * 100;
  const abs  = (final / initial - 1) * 100;
  document.getElementById('cagrVal').textContent = cagr.toFixed(2) + '%';
  document.getElementById('cagrAbs').textContent = abs.toFixed(2) + '%';
  document.getElementById('cagrMult').textContent = (final / initial).toFixed(2) + 'x';
  document.getElementById('cagrResult').classList.add('show');
}
function calcLumpVsSip() {
  const monthly = toolNum('lvMonthly');
  const lump    = toolNum('lvLump');
  const annualR = toolNum('lvReturn');
  const years   = toolNum('lvYears');
  if (!annualR || !years || years <= 0) return;
  const n = years * 12, r = annualR / 12 / 100;
  const sipFv  = monthly > 0 ? monthly * (Math.pow(1 + r, n) - 1) / r * (1 + r) : 0;
  const lumpFv = lump > 0 ? lump * Math.pow(1 + annualR / 100, years) : 0;
  const sipInv = monthly * n, lumpInv = lump;
  document.getElementById('lvSipFv').textContent   = '₹' + fmt(sipFv);
  document.getElementById('lvSipInv').textContent  = 'Invested ₹' + fmt(sipInv);
  document.getElementById('lvLumpFv').textContent  = '₹' + fmt(lumpFv);
  document.getElementById('lvLumpInv').textContent = 'Invested ₹' + fmt(lumpInv);
  const verdict = document.getElementById('lvVerdict');
  if (sipFv && lumpFv) {
    const winner = sipFv > lumpFv ? 'SIP' : 'Lumpsum';
    const diff = Math.abs(sipFv - lumpFv);
    verdict.style.display = 'block';
    verdict.innerHTML = `<strong style="color:var(--gold)">${winner}</strong> ends higher by <strong style="color:var(--white)">₹${fmt(diff)}</strong> at ${annualR}% over ${years} years. Lumpsum wins when you have the capital upfront and markets trend up; SIP wins by averaging through volatility.`;
  } else { verdict.style.display = 'none'; }
  document.getElementById('lvResult').classList.add('show');
}
let xirrRowCount = 0;
function addXirrRow(date, amount) {
  xirrRowCount++;
  const wrap = document.getElementById('xirrRows');
  const div = document.createElement('div');
  div.style.cssText = 'display:grid;grid-template-columns:1fr 1fr auto;gap:10px;margin-bottom:10px;align-items:end';
  div.innerHTML =
    `<div class="form-group" style="margin:0"><label>Date</label><input type="date" class="xirr-date" value="${date || ''}"></div>` +
    `<div class="form-group" style="margin:0"><label>Amount (₹) — invest −, redeem +</label><input type="number" class="xirr-amt" placeholder="e.g. -10000" value="${amount != null ? amount : ''}"></div>` +
    `<button type="button" class="calc-btn" style="background:transparent;border:1px solid var(--border);color:var(--grey);max-width:46px;padding:10px" onclick="this.parentElement.remove()" title="Remove">✕</button>`;
  wrap.appendChild(div);
}
function _xirr(cashflows) {
  // Newton-Raphson on annualised XIRR. cashflows: [{t: days, amt}], t relative to first date.
  const f = (rate) => cashflows.reduce((s, cf) => s + cf.amt / Math.pow(1 + rate, cf.t / 365), 0);
  const df = (rate) => cashflows.reduce((s, cf) => s - (cf.t / 365) * cf.amt / Math.pow(1 + rate, cf.t / 365 + 1), 0);
  let rate = 0.1;
  for (let i = 0; i < 100; i++) {
    const v = f(rate), d = df(rate);
    if (Math.abs(d) < 1e-10) break;
    const next = rate - v / d;
    if (!isFinite(next)) break;
    if (Math.abs(next - rate) < 1e-7) return next;
    rate = next <= -0.9999 ? -0.9999 : next;
  }
  return rate;
}
function calcXIRR() {
  const dates = document.querySelectorAll('#xirrRows .xirr-date');
  const amts  = document.querySelectorAll('#xirrRows .xirr-amt');
  const rows = [];
  for (let i = 0; i < dates.length; i++) {
    const d = dates[i].value, a = parseFloat(amts[i].value);
    if (d && isFinite(a) && a !== 0) rows.push({ date: new Date(d), amt: a });
  }
  const out = document.getElementById('xirrOut');
  if (rows.length < 2) { out.innerHTML = '<span style="color:#ff6b6b">Add at least two cashflows (an investment and a redemption).</span>'; out.style.display = 'block'; return; }
  rows.sort((a, b) => a.date - b.date);
  const hasNeg = rows.some(r => r.amt < 0), hasPos = rows.some(r => r.amt > 0);
  if (!hasNeg || !hasPos) { out.innerHTML = '<span style="color:#ff6b6b">Need at least one investment (negative) and one redemption / current value (positive).</span>'; out.style.display = 'block'; return; }
  const t0 = rows[0].date;
  const cf = rows.map(r => ({ t: (r.date - t0) / 86400000, amt: r.amt }));
  const rate = _xirr(cf) * 100;
  document.getElementById('xirrVal').textContent = rate.toFixed(2) + '%';
  out.style.display = 'none';
  document.getElementById('xirrResult').classList.add('show');
}

/* ============================================================
   CURRENCY CONVERTER — live mid-market rate via Yahoo Finance
   (reuses the whitelisted /proxy.php). Static fallback if the
   network call fails.
   ============================================================ */
const FX_FALLBACK = { USDINR: 86.5, EURINR: 94, GBPINR: 110, JPYINR: 0.57, AEDINR: 23.5, AUDINR: 56, CADINR: 61, SGDINR: 64, CHFINR: 97, CNYINR: 12 };
async function _fxRate(from, to) {
  if (from === to) return 1;
  const sym = from + to + '=X';
  try {
    const yahoo = 'https://query1.finance.yahoo.com/v8/finance/chart/' + encodeURIComponent(sym) + '?interval=1d&range=1d';
    const res = await fetch(PROXY + encodeURIComponent(yahoo), { signal: AbortSignal.timeout(10000) });
    if (res.ok) {
      const data = await res.json();
      const meta = data && data.chart && data.chart.result && data.chart.result[0] && data.chart.result[0].meta;
      if (meta && meta.regularMarketPrice > 0) return meta.regularMarketPrice;
    }
  } catch (e) {}
  // Fallback via INR cross.
  const f2i = from === 'INR' ? 1 : FX_FALLBACK[from + 'INR'];
  const t2i = to === 'INR' ? 1 : FX_FALLBACK[to + 'INR'];
  if (f2i && t2i) return f2i / t2i;
  return null;
}
async function convertCurrency() {
  const from = document.getElementById('fxFrom').value;
  const to   = document.getElementById('fxTo').value;
  const amt  = toolNum('fxAmount');
  const out  = document.getElementById('fxResult');
  if (!isFinite(amt)) return;
  out.classList.add('show');
  document.getElementById('fxConverted').textContent = '…';
  document.getElementById('fxRate').textContent = 'Fetching live rate…';
  const rate = await _fxRate(from, to);
  if (rate == null) { document.getElementById('fxConverted').textContent = '—'; document.getElementById('fxRate').textContent = 'Rate unavailable. Try again shortly.'; return; }
  const converted = amt * rate;
  document.getElementById('fxConverted').textContent = converted.toLocaleString('en-IN', { maximumFractionDigits: 2 }) + ' ' + to;
  document.getElementById('fxRate').textContent = '1 ' + from + ' = ' + rate.toFixed(4) + ' ' + to + ' · live mid-market rate (Yahoo Finance)';
}

/* ---- Seed the dynamic-row tools with a couple of starter rows once the
        Tools page DOM exists. Safe to call on DOMContentLoaded. ---- */
function initToolRows() {
  if (document.getElementById('avgRows') && !document.querySelector('#avgRows .avg-row')) {
    addAvgRow(250, 100); addAvgRow(220, 150);
  }
  if (document.getElementById('optLegs') && !document.querySelector('#optLegs .opt-leg')) {
    addOptLeg('buy', 'call', 24000, 120, 1);
  }
  if (document.getElementById('xirrRows') && !document.querySelector('#xirrRows .xirr-date')) {
    addXirrRow('', -100000); addXirrRow('', 130000);
  }
}
