/* ============================================================
   data.js — pure static data for the RootNivesh site.

   All the things you might want to edit casually live here:
   subscription prices, course list, sample reports, ticker
   symbols, page titles, IPO endpoints, etc.

   This file MUST load before script.js — index.html does that.

   No side effects. No DOM access. Just `const` declarations
   that script.js reads from.
   ============================================================ */


/* ----------- URL routing -------------------------------------------------- */
const PAGE_TO_PATH = {
  home:     '/',
  ipo:      '/ipo',
  learner:  '/learner',
  calls:    '/calls',
  reports:  '/reports',
  tools:    '/tools',
  about:    '/about',
  contact:  '/contact'
};

const PAGE_TITLES = {
  home:    'RootNivesh Research | SEBI Registered Research Analyst',
  ipo:     'New IPO | RootNivesh Research',
  learner: 'Learner Club | RootNivesh Research',
  calls:   'Trading & Investment Calls | RootNivesh Research',
  reports: 'Research Reports | RootNivesh Research',
  tools:   'Smart Calculators | RootNivesh Research',
  about:   'About Us | RootNivesh Research',
  contact: 'Contact | RootNivesh Research'
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
const TOOL_TYPES = ['emi', 'pos', 'sip'];


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

const PLANS = [
  {
    id: 'starter',
    name: 'Starter',
    icon: '🎯',
    tagline: 'Equity calls • WhatsApp delivery',
    type: 'subscription',
    valueLabel: 'Basic Analysis',
    description: 'Beginner-friendly entry plan. 2–3 high-quality cash-segment equity calls every week, focused on swing and short-term trades, delivered on WhatsApp.',
    features: [
      '2–3 equity calls per week',
      'Swing + short-term trades',
      'Basic risk-management guidance',
      'WhatsApp + email delivery'
    ],
    pricing: {
      cash: { 1: 2999, 3: 7999, 6: 14999, 12: 24999 }
    }
  },
  {
    id: 'pro-fno',
    name: 'Pro F&O',
    icon: '🔥',
    tagline: 'Derivatives Edge — serious traders',
    type: 'subscription',
    valueLabel: 'Deep Quant Research',
    description: 'Quant-driven futures & options playbook. Intraday + positional F&O calls, option strategies for BTST and expiry setups, with tight risk-reward discipline.',
    features: [
      'Intraday + positional F&O calls',
      'Option strategies (BTST, expiry setups)',
      'Defined risk : reward on every trade',
      'Faster, real-time WhatsApp updates'
    ],
    pricing: {
      fno: { 1: 5999, 3: 15999, 6: 29999, 12: 54999 }
    }
  },
  {
    id: 'elite',
    name: 'Elite',
    icon: '💎',
    tagline: 'Dedicated advisory — multi-market',
    type: 'service',
    valueLabel: 'Bespoke Advisory',
    description: 'Personalised, dedicated trade assistance. Calls customised to your capital, real portfolio tracking, and coverage across Equity, F&O, Forex and Commodity markets.',
    features: [
      'Dedicated relationship manager',
      'Calls customised to your capital',
      'Portfolio tracking & rebalancing',
      'Multi-market: Equity / F&O / Forex / Commodity'
    ],
    tiers: [
      { id: 'standard', name: 'Standard', price: 9999,  suffix: '/month', note: 'Core advisory access' },
      { id: 'premium',  name: 'Premium',  price: 19999, suffix: '/month', note: 'Faster turnaround + portfolio review' },
      { id: 'ultra',    name: 'Ultra',    price: 34999, suffix: '/month', note: 'Concierge service across all markets' }
    ]
  },
  {
    id: 'mentorship',
    name: 'Mentorship',
    icon: '🎓',
    tagline: '1-on-1 coaching • premium segment',
    type: 'program',
    valueLabel: 'Lifetime Mastery',
    description: 'High-touch personal coaching. Strategy building, trader psychology, risk mastery and a lifetime framework you can apply across markets — not just signals you blindly follow.',
    features: [
      'Personal 1:1 coaching sessions',
      'Live strategy building',
      'Trader psychology + risk mastery',
      'Lifetime framework, not just signals'
    ],
    tiers: [
      { id: 'beginner', name: 'Beginner Program',     price: 15000, suffix: '',  note: 'Foundation in research-driven trading' },
      { id: 'advanced', name: 'Advanced Trading',     price: 25000, suffix: '',  note: 'Quant + technical + risk integration' },
      { id: 'mastery',  name: 'Complete Mastery',     price: 45000, suffix: '+', note: 'All-markets framework, lifetime access' }
    ]
  }
];

const PLAN_DURATIONS = [
  { months: 1,  label: '1 Month'   },
  { months: 3,  label: '3 Months'  },
  { months: 6,  label: '6 Months'  },
  { months: 12, label: '12 Months' }
];

const TYPE_LABELS = { cash: 'Cash', fno: 'F&O' };


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
