/* ============================================================
   data.js — pure static data for the RootNivesh site.

   All the things you might want to edit casually live here:
   subscription prices, course list, sample reports, ticker
   symbols, page titles, IPO endpoints, etc.

   This file MUST load before the app layers in js/app/ — index.html
   loads it first.

   No side effects. No DOM access. Just `const` declarations
   that the js/app/ layers read from.
   ============================================================ */


/* ----------- Author / compliance identity --------------------------------
   Shown in the author box at the bottom of every blog article (E-E-A-T:
   credentials matter most for finance/YMYL ranking). Edit in ONE place.
   Replace SITE_SEBI_REG with the real INH number the day it is allotted. */
const SITE_ANALYST   = 'RootNivesh Research';
const SITE_SEBI_REG  = 'INH000XXXXX';            // <-- real SEBI Reg No. when allotted
const SITE_ANALYST_BIO =
  'RootNivesh Research is a SEBI Registered Research Analyst based in Mumbai, ' +
  'publishing quant-backed, data-driven analysis of Indian equities, F&O and IPOs. ' +
  'Research only — not investment advice. Markets carry risk; read the full disclaimer.';

/* ----------- URL routing -------------------------------------------------- */
const PAGE_TO_PATH = {
  home:       '/',
  ipo:        '/ipo',
  learner:    '/learner',
  calls:      '/membership',
  blog:       '/blog',
  performance:'/performance',
  reports:    '/reports',
  tools:      '/tools',
  about:      '/about',
  contact:    '/contact',
  privacy:    '/privacy',
  terms:      '/terms',
  disclaimer: '/disclaimer'
};

const PAGE_TITLES = {
  home:       'RootNivesh Research | SEBI Registered Research Analyst',
  ipo:        'New IPO | RootNivesh Research',
  learner:    'Learner Club | RootNivesh Research',
  calls:      'Trading & Investment Calls | RootNivesh Research',
  blog:       'Stock Market Blog — Investing, Trading & Personal Finance | RootNivesh Research',
  performance:'Calls Performance & Track Record | RootNivesh Research',
  reports:    'Research Reports | RootNivesh Research',
  tools:      'Free Stock Market Calculators — Brokerage, Options, SIP, CAGR & Currency | RootNivesh',
  about:      'About Us | RootNivesh Research',
  contact:    'Contact | RootNivesh Research',
  privacy:    'Privacy Policy | RootNivesh Research',
  terms:      'Terms of Use | RootNivesh Research',
  disclaimer: 'Disclaimer | RootNivesh Research'
};

/* Per-route meta description. Keeps each History-API "page" from sharing the
   homepage description/canonical, which otherwise collapses deep-link ranking. */
const PAGE_DESCRIPTIONS = {
  home:       'SEBI Registered Research Analyst (Mumbai). Quant-backed stock market research, intraday & swing calls, F&O strategies, IPO analysis and portfolio reviews.',
  ipo:        'Live NSE IPO tracker — Open, Upcoming and recently Closed mainboard & SME IPOs with price bands, dates and company overviews.',
  learner:    'Learner Club — structured stock market learning from a SEBI Registered Research Analyst.',
  calls:      'Intraday, swing, positional and value investing calls with clear entry, target and stop-loss levels from a SEBI Registered Research Analyst.',
  blog:       'Indian stock market blog by a SEBI Registered Research Analyst — investing, SIP & mutual funds, F&O and swing trading strategies, factor & quant research, and personal finance. Practical, data-driven, no tips.',
  performance:'Transparent track record — win-rate, average return and a closed-trade log of every public call from RootNivesh Research. Past performance is not indicative of future results.',
  reports:    'In-depth equity research reports with target prices, upside and time horizons — data-driven, no-tip-style analysis.',
  tools:      'Free online calculators for Indian traders & investors — brokerage & net P&L, options profit/payoff & strategy builder, stock average, SIP, CAGR, XIRR, SIP-vs-lumpsum and live currency converter.',
  about:      'About RootNivesh Research — a SEBI Registered Research Analyst based in Mumbai providing quant-backed market research.',
  contact:    'Contact RootNivesh Research for research subscriptions, queries and SEBI-compliant advisory.',
  privacy:    'How RootNivesh Research collects, uses and protects your personal data.',
  terms:      'Terms of use governing the RootNivesh Research website and services.',
  disclaimer: 'Market-risk disclaimer and SEBI Research Analyst disclosures for RootNivesh Research.'
};


/* Maps a Calls-page sidebar bucket to the DB call_type(s) it should show.
   DB enum: intraday | swing | positional | longterm | fno. */
const SNAV_TO_DBTYPES = {
  intraday:   ['intraday'],
  swing:      ['swing'],
  positional: ['positional', 'fno'],
  value:      ['longterm'],
  monthly:    ['longterm'],
};


/* ----------- Calls page sidebar metadata ---------------------------------- */
const callsMeta = {
  intraday:   { title:'📈 Intraday Calls',          crumb:'INTRADAY',   desc:'Same-day trades with precise entry, target & stop-loss levels.' },
  swing:      { title:'🔄 Swing Calls',              crumb:'SWING',      desc:'7–14 day momentum trades capturing short-term price swings.' },
  positional: { title:'📊 Positional Calls',         crumb:'POSITIONAL', desc:'1–3 month trend-following calls with larger profit targets.' },
  value:      { title:'💎 Value Based Investing',    crumb:'VALUE',      desc:'Fundamentally strong stocks picked below intrinsic value.' },
  monthly:    { title:'📅 Monthly Based Investing',  crumb:'MONTHLY',    desc:'SIP, ETF & index fund ideas for systematic long-term compounding.' },
};


/* ----------- Backend / proxy config --------------------------------------- */
const PROXY = '/proxy.php?url=';
const TOOL_TYPES = ['brokerage', 'sip', 'options', 'emi', 'currency', 'returns', 'average'];

/* F&O index lot sizes — fallback used if /lots.php is unreachable. The live
   values (auto-refreshed weekly from NSE) come from lots.php; this just keeps
   the dropdown usable offline. INDEX_ORDER fixes the dropdown order. */
const INDEX_LOTS_FALLBACK = {
  NIFTY:      { name: 'Nifty 50',      lot: 65 },
  BANKNIFTY:  { name: 'Bank Nifty',    lot: 30 },
  FINNIFTY:   { name: 'Fin Nifty',     lot: 60 },
  MIDCPNIFTY: { name: 'Midcap Nifty',  lot: 120 },
  NIFTYNXT50: { name: 'Nifty Next 50', lot: 25 },
  SENSEX:     { name: 'Sensex',        lot: 20 },
  BANKEX:     { name: 'Bankex',        lot: 30 },
  SENSEX50:   { name: 'Sensex 50',     lot: 60 },
};
const INDEX_ORDER = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY', 'NIFTYNXT50', 'SENSEX', 'BANKEX', 'SENSEX50'];

/* Broker brokerage models — offline fallback for /brokers.php. Order also sets
   the dropdown order (most-searched first). Rule types: zero | flat{flat} |
   pct{pct} | minpctcap{pct,cap}. */
const BROKER_ORDER = ['zerodha', 'groww', 'angelone', 'upstox', 'dhan', 'fyers', 'fivepaisa', 'paytmmoney', 'icicidirect', 'hdfcsec', 'kotak', 'sbisec', 'motilal'];
const _mc = (pct, cap) => ({ type: 'minpctcap', pct: pct, cap: cap || 20 });
const _z = { type: 'zero' }, _f20 = { type: 'flat', flat: 20 };
const BROKERS_FALLBACK = {
  zerodha:    { name: 'Zerodha',          plan: '',                delivery: _z,        intraday: _mc(0.03), futures: _mc(0.03), options: _f20 },
  groww:      { name: 'Groww',            plan: '',                delivery: _mc(0.1),  intraday: _mc(0.1),  futures: _f20,       options: _f20 },
  angelone:   { name: 'Angel One',        plan: '',                delivery: _mc(0.1),  intraday: _mc(0.03), futures: _mc(0.03), options: _f20 },
  upstox:     { name: 'Upstox',           plan: '',                delivery: _mc(2.5),  intraday: _mc(0.05), futures: _mc(0.05), options: _f20 },
  dhan:       { name: 'Dhan',             plan: '',                delivery: _z,        intraday: _mc(0.03), futures: _mc(0.03), options: _f20 },
  fyers:      { name: 'Fyers',            plan: '',                delivery: _z,        intraday: _mc(0.03), futures: _mc(0.03), options: _f20 },
  fivepaisa:  { name: '5paisa',           plan: '',                delivery: _f20,      intraday: _f20,      futures: _f20,       options: _f20 },
  paytmmoney: { name: 'Paytm Money',      plan: '',                delivery: _mc(2.5),  intraday: _f20,      futures: _f20,       options: _f20 },
  icicidirect:{ name: 'ICICI Direct',     plan: 'Neo plan',        delivery: _z,        intraday: _f20,      futures: _f20,       options: _f20 },
  hdfcsec:    { name: 'HDFC Securities',  plan: 'Sky plan',        delivery: _mc(0.10), intraday: _f20,      futures: _f20,       options: _f20 },
  kotak:      { name: 'Kotak Securities', plan: 'Trade Free plan', delivery: _z,        intraday: _f20,      futures: _f20,       options: _f20 },
  sbisec:     { name: 'SBI Securities',   plan: '',                delivery: _mc(0.50), intraday: _mc(0.05), futures: _f20,       options: _f20 },
  motilal:    { name: 'Motilal Oswal',    plan: '',                delivery: _mc(0.20), intraday: _mc(0.02), futures: _f20,       options: _f20 },
};


/* ----------- Live ticker (Yahoo Finance via /proxy.php) ------------------- */
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


/* ----------- FII / DII sample data ---------------------------------------- */
/* Update these numbers when you have fresh end-of-day NSE figures. */
const SAMPLE_LATEST = {
  fii: { buy: 14832.45, sell: 13120.30, net: 1712.15 },
  dii: { buy: 10234.80, sell:  8940.25, net: 1294.55 }
};


/* ----------- IPO sources -------------------------------------------------- */
const IPO_ENDPOINTS = {
  open:     'https://www.nseindia.com/api/ipo-current-issue',
  upcoming: 'https://www.nseindia.com/api/all-upcoming-issues?category=ipo',
  closed:   'https://www.nseindia.com/api/public-past-issues'
};
const IPO_CLOSED_DAYS = 60;
const IPO_LOOKUP_CACHE_TTL = 24 * 60 * 60 * 1000; // 24h

/* Manual overrides — only used when both NSE quote-equity AND
   Chittorgarh fall short. Key = uppercase NSE symbol. */
const IPO_DESCRIPTIONS = {};


/* ----------- Premium plans — RootNivesh upsell funnel ---------------------
   Four tiers covering the full client journey:
     1. Starter      — equity calls, beginners, lowest-friction entry
     2. Pro F&O      — derivatives for serious traders
     3. Elite        — dedicated 1-on-1 advisory tiers
     4. Mentorship   — high-ticket coaching programmes
   ---
   Each plan has a `type` field that tells renderPlans() how to lay it out:
     "subscription"  → 1/3/6/12-month duration selector
     "service"       → tier picker (Standard / Premium / Ultra) at monthly rates
     "program"       → program picker (Beginner / Advanced / Mastery) one-time
   --------------------------------------------------------------------- */
const GST_RATE = 0.18;

// WhatsApp business number used by the "Get Started" CTAs across plans.
// Format: country code + number, no plus or spaces (wa.me requirement).
const WHATSAPP_NUMBER = '917467094575';

// Each plan carries a `discountPct` so the displayed MRP (strike-through)
// is computed as: mrp = price / (1 - discountPct/100). 50% across all
// plans keeps the maths clean (mrp = price × 2) and the offer credible.
// `chips` are short fancy descriptors shown as pills on each card.
// `offerLabel` overlays a launch-style ribbon on the card header.
const PLANS = [
  {
    id: 'starter',
    name: 'Starter',
    icon: '🎯',
    tagline: 'Equity calls • WhatsApp delivery',
    type: 'subscription',
    valueLabel: 'Smart Equity Picks',
    chips: ['Index-Based', 'Equity Delivery', 'WhatsApp Calls', 'Beginner Friendly'],
    discountPct: 25,
    offerLabel: 'LAUNCH OFFER',
    description: 'Beginner-friendly entry plan. 2–3 high-quality index-based and large-cap cash-segment calls every week, delivered on WhatsApp with research-grade reasoning behind every pick.',
    features: [
      '2–3 index-based equity calls per week',
      'Cash-segment delivery, no leverage stress',
      'Research-backed entry, target & stop-loss',
      'Instant WhatsApp + email delivery'
    ],
    pricing: {
      cash: { 1: 299, 3: 799, 6: 1499, 12: 2799 }
    }
  },
  {
    id: 'pro-fno',
    name: 'Pro F&O',
    icon: '🔥',
    tagline: 'Index derivatives — serious traders',
    popular: true,
    type: 'subscription',
    valueLabel: 'Derivatives Edge',
    chips: ['Index Derivatives', 'Options Edge', 'Real-Time Alerts', 'BTST + Expiry'],
    discountPct: 40,
    offerLabel: 'LAUNCH OFFER',
    description: 'Quant-driven Nifty / BankNifty futures & options playbook. Intraday + positional F&O calls, option spreads for BTST and expiry setups, with tight risk-reward discipline on every trade.',
    features: [
      'Index derivatives + stock F&O calls',
      'Option spreads (BTST, expiry plays)',
      'Defined risk : reward on every trade',
      'Real-time WhatsApp alerts during market hours'
    ],
    pricing: {
      fno: { 1: 599, 3: 1599, 6: 2999, 12: 5499 }
    }
  },
  {
    id: 'deep-quant',
    name: 'Deep Quant Research',
    icon: '🧠',
    tagline: 'Research delivery — institutional depth',
    type: 'subscription',
    valueLabel: 'Research-Delivery',
    chips: ['Research Delivery', 'Multi-Factor Quant', '4,000+ Stocks Screened', 'Cross-Segment'],
    discountPct: 50,
    offerLabel: 'FLAGSHIP',
    description: 'Our flagship research stream. Multi-factor quant screening across 4,000+ NSE/BSE names is fused with deep fundamental analysis, multi-timeframe technicals, and sector-macro context — delivered as high-conviction research-backed calls.',
    features: [
      'Research-delivery: full thesis with every call',
      'Multi-factor quant screening (momentum, quality, value)',
      'Sector + macro context layered on every pick',
      'Cash + F&O + positional coverage'
    ],
    pricing: {
      research: { 1: 799, 3: 2199, 6: 3999, 12: 7499 }
    }
  }
];

/* Trust strip shown on every plan card — honest, SEBI-compliant reassurances
   (no return/profit guarantees). These convert by removing risk and friction,
   not by promising outcomes. */
const PLAN_TRUST_POINTS = [
  'SEBI Registered Research Analyst — fully compliant',
  'Every call with entry, target & stop-loss — never a blind tip',
  'Transparent public track record you can verify',
  'Instant WhatsApp delivery — never miss an entry',
  'Cancel anytime — no lock-in, no questions'
];

const PLAN_DURATIONS = [
  { months: 1,  label: '1 Month'   },
  { months: 3,  label: '3 Months'  },
  { months: 6,  label: '6 Months'  },
  { months: 12, label: '12 Months' }
];

const TYPE_LABELS = { cash: 'Cash', fno: 'F&O', research: 'Research' };


/* ----------- Sample research reports (homepage / Reports page) ------------ */
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


/* ----------- Learner Club courses ----------------------------------------- */
const coursesData = [
  {level:'beginner', icon:'📈', title:'Stock Market Basics', desc:'What is a stock? How does BSE/NSE work? Types of orders, demat accounts, and how to start investing.', lessons:'12 Lessons', duration:'6 Hours', badge:'Free'},
  {level:'beginner', icon:'💰', title:'Mutual Funds & SIP', desc:'Understanding mutual fund categories, SIP vs lumpsum, NAV, expense ratio, and picking the right fund.', lessons:'8 Lessons', duration:'4 Hours', badge:'Free'},
  {level:'intermediate', icon:'📊', title:'Fundamental Analysis', desc:'P/E, P/B, ROE, ROCE, DCF valuation, reading annual reports, and building a financial model.', lessons:'20 Lessons', duration:'14 Hours', badge:'Premium'},
  {level:'intermediate', icon:'📉', title:'Technical Analysis', desc:'Chart patterns, candlesticks, RSI, MACD, moving averages, volume analysis, and support/resistance.', lessons:'18 Lessons', duration:'12 Hours', badge:'Premium'},
  {level:'advanced', icon:'🎲', title:'Options Strategies', desc:'Greeks, spreads, straddles, iron condor, theta decay, and building income-generating options portfolios.', lessons:'24 Lessons', duration:'18 Hours', badge:'Premium'},
  {level:'advanced', icon:'🏗️', title:'Portfolio Construction', desc:'Asset allocation, rebalancing, factor investing, risk-adjusted returns, and building a multi-cap portfolio.', lessons:'16 Lessons', duration:'10 Hours', badge:'Premium'},
];


/* ----------- Calls — sample track record ---------------------------------- */
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
