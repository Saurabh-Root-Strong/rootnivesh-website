-- =============================================================
-- seed_blog.sql — 7 ready-to-publish blog posts for RootNivesh.
-- Run ONCE in phpMyAdmin (SQL tab) on the live database.
-- Safe to re-run: INSERT IGNORE skips rows whose slug already exists.
-- Covers are Unsplash CDN URLs (verified reachable). Swap any cover
-- later from the admin Blog editor (upload from device).
-- Body uses the site's lite-markdown: "## " = section heading (builds
-- the In-this-article TOC), "### " = sub-heading, blank line = new
-- paragraph, **text** = bold.
-- =============================================================

INSERT IGNORE INTO posts
  (slug, title, category, excerpt, cover_image, body, author, read_minutes, status, published_at)
VALUES

-- 1 -----------------------------------------------------------
('position-sizing-2-percent-rule',
 'Position Sizing: The 2% Rule That Keeps You in the Game',
 'strategy',
 'Most blow-ups are not bad calls — they are oversized ones. Here is the simple risk-per-trade math that lets a losing streak pass without wrecking your capital.',
 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=1200&q=80&auto=format&fit=crop',
 '## Why position sizing beats stock picking

A trader with a mediocre strategy and strict risk control will outlast a brilliant analyst who bets the farm on conviction. **Survival is the edge.** Position sizing decides how long you stay at the table.

## The 2% rule, stated simply

Never risk more than **2% of your trading capital** on a single idea. Risk is not the money you deploy — it is the distance from your entry to your stop-loss.

### Worked example

On a Rs 5,00,000 account, 2% is Rs 10,000 of risk per trade. If you buy a stock at Rs 500 with a stop at Rs 480, your risk is Rs 20 per share. Rs 10,000 divided by Rs 20 gives **500 shares** — regardless of how confident you feel.

## What this protects you from

A run of five straight losses at 2% each draws your account down roughly 10%, not 50%. You live to trade the sixth setup, which is where the recovery usually comes from.

## The discipline that makes it work

Set the stop **before** you enter, size from the stop, and never widen it once the trade is live. The rule only works if the stop is real.',
 'RootNivesh Research', 6, 'published', '2026-06-15 10:00:00'),

-- 2 -----------------------------------------------------------
('reading-option-chain-oi-iv-max-pain',
 'Reading an Option Chain: OI, IV and Max Pain Explained',
 'education',
 'Open interest, implied volatility and max pain look intimidating on the NSE option chain. Decode what each column is actually telling you before your next F&O trade.',
 'https://images.unsplash.com/photo-1612178991541-b48cc8e92a4d?w=1200&q=80&auto=format&fit=crop',
 '## The option chain is a positioning map

Every row on the Nifty or Bank Nifty option chain is a record of where traders have placed bets. Read it as a map of supply and demand, not a crystal ball.

## Open Interest (OI)

OI is the number of contracts still open at a strike. **Rising OI with rising price** signals fresh longs; rising OI with falling price signals fresh shorts. Heavy call OI above spot acts as resistance; heavy put OI below acts as support.

## Implied Volatility (IV)

IV is the market''s expectation of future movement, baked into the option price. **High IV means expensive options** — good for sellers, costly for buyers. IV usually spikes before events and collapses after, the move known as IV crush.

## Max Pain

Max pain is the strike where the largest number of options expire worthless, inflicting maximum loss on buyers. Price often drifts toward it near expiry, but treat it as **context, not a guarantee** — in trending markets it fails.

## Putting it together

Use OI for support and resistance, IV to judge whether you should be a buyer or a seller, and max pain only as a soft magnet near expiry. No single column is a signal on its own.',
 'RootNivesh Research', 7, 'published', '2026-06-16 11:30:00'),

-- 3 -----------------------------------------------------------
('fii-dii-flows-what-they-tell-you',
 'FII vs DII Flows: What the Daily Numbers Actually Tell You',
 'markets',
 'Every evening the provisional FII and DII cash figures hit the headlines. Here is how to read them without falling for the day-trader myths around them.',
 'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?w=1200&q=80&auto=format&fit=crop',
 '## Two very different players

**FIIs** (foreign institutional investors) chase global risk appetite, the rupee and relative valuations. **DIIs** (domestic mutual funds and insurers) deploy steady SIP inflows. They often sit on opposite sides of the same tape.

## The provisional cash figure

The number you see after market close is the **provisional cash-segment** buy/sell. It is revised the next day and excludes the much larger F&O positioning, so treat it as a sentiment gauge, not the full story.

## What a single day means

Not much. One day of FII selling against strong DII buying is noise. The signal is in the **trend** — a multi-week stretch of consistent FII outflow alongside a weakening rupee is what historically pressures large-caps.

## A common misread

"FIIs are buying, so go long" ignores their F&O hedges. FIIs frequently buy cash while shorting index futures. Reading only the cash line can point you the wrong way.

## How to use it

Track the rolling sum, watch FII and DII together, and pair the flow with the rupee and index futures positioning. Flow is a backdrop, not a trade trigger.',
 'RootNivesh Research', 7, 'published', '2026-06-17 18:00:00'),

-- 4 -----------------------------------------------------------
('backtesting-101-why-retail-backtests-lie',
 'Backtesting 101: Why Most Retail Backtests Lie',
 'quant',
 'A backtest that shows 90% win-rate is almost always broken. The four biases that quietly inflate results — and how a disciplined research process removes them.',
 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&q=80&auto=format&fit=crop',
 '## A beautiful equity curve proves nothing

If a strategy looks too good, the bias is in the test, not the alpha. Honest backtesting is mostly about hunting for the ways you fooled yourself.

## Lookahead bias

Using information that was not available at decision time. **Computing a signal on today''s close and acting at today''s open** is the classic offender. Every input must be known strictly before the trade.

## Survivorship bias

Testing only on stocks that exist today silently deletes the ones that were delisted or went to zero. The universe must be the universe **as it was on each historical date.**

## Overfitting

Tune enough parameters and any noise becomes a "system". A rule with ten conditions that perfectly fits the past usually **fails out-of-sample.** Fewer knobs, tested on data the model never saw, is the cure.

## Ignoring costs

Brokerage, STT, slippage and impact turn many paper-profitable systems into losers. **Always backtest net of costs**, not gross.

## The honest workflow

Define the rule, test on in-sample data, validate on untouched out-of-sample data, and only then size small and trade live. If the edge survives all four checks, it might be real.',
 'RootNivesh Research', 8, 'published', '2026-06-18 09:30:00'),

-- 5 -----------------------------------------------------------
('swing-trading-setups-entry-stop-target',
 'Swing Trading Setups: Entry, Stop-Loss and Target Discipline',
 'strategy',
 'A clean swing trade is three decisions made before you click buy. The framework we use to define entry, protective stop and a realistic target on every call.',
 'https://images.unsplash.com/photo-1543286386-713bdd548da4?w=1200&q=80&auto=format&fit=crop',
 '## Plan the trade, then trade the plan

A swing trade lasts a few days to a few weeks. The entire edge comes from deciding entry, stop and target **before** emotion enters the picture.

## The entry

Enter on a trigger, not a hunch. A breakout above a multi-day base with rising volume, or a pullback to a rising moving average that holds, are two clean templates. **No trigger, no trade.**

## The stop-loss

Place the stop where your thesis is proven wrong — below the breakout base or the swing low — not at an arbitrary rupee amount. If that stop is too far for your risk budget, the **position is too big**, so size down.

## The target

Define a reward that is at least **twice your risk** (a 1:2 reward-to-risk). For a Rs 20 stop, aim for at least Rs 40 of upside. Trades that do not offer 1:2 are usually not worth taking.

## Managing the live trade

Once price moves halfway to target, trail the stop to break-even to protect capital. **Book partial profit at the first target** and let the rest run to the second. Discipline on exits matters more than a perfect entry.',
 'RootNivesh Research', 6, 'published', '2026-06-19 10:15:00'),

-- 6 -----------------------------------------------------------
('nifty-banknifty-expiry-weekly-vs-monthly',
 'Nifty & Bank Nifty Expiry: Weekly vs Monthly, Explained',
 'education',
 'Expiry day moves the index more than most news. Understand how weekly and monthly expiries work, why volatility clusters near the close, and how to stay out of trouble.',
 'https://images.unsplash.com/photo-1565514020179-026b92b84bb6?w=1200&q=80&auto=format&fit=crop',
 '## Why expiry matters

On expiry, all open weekly options for that index settle. The scramble to square off and hedge concentrates volume and can whip the index hard, especially in the **final hour.**

## Weekly vs monthly

Weekly options expire every week and decay fast, which makes them cheap lottery tickets that usually expire worthless. **Monthly options** carry more time value and are steadier. Index weekly expiry days are now set by the exchange schedule — always confirm the current expiry day on the NSE calendar rather than assuming.

## Theta and the time-decay trap

Option buyers fight **theta** — the daily erosion of time value — which accelerates into expiry. Buying weekly options on expiry morning and hoping for a move is one of the fastest ways retail loses money.

## How expiry distorts the chart

Pinning toward heavy-OI strikes, sudden IV crush and thin liquidity in far strikes all make expiry-day price action **unreliable for fresh swing entries.**

## A simple rule for beginners

Until you understand option Greeks, avoid initiating new positions on expiry day. Let the volatility pass and trade the cleaner trend the following session.',
 'RootNivesh Research', 7, 'published', '2026-06-20 12:00:00'),

-- 7 -----------------------------------------------------------
('sector-rotation-how-money-moves',
 'Sector Rotation: How Money Moves Across Nifty Sectors',
 'markets',
 'Markets rarely rise as one block. Capital rotates from one sector to the next, and spotting the handoff early is where a lot of relative-return edge lives.',
 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&q=80&auto=format&fit=crop',
 '## The index hides the real action

A flat Nifty can mask a furious rotation underneath — banks bleeding while IT and pharma rally. **Tracking sectors, not just the index, is where leadership shows up first.**

## What drives rotation

Money flows toward sectors with improving earnings, supportive policy or a turning rate cycle. Falling interest rates tend to favour **rate-sensitives** like banks, autos and real estate; a weak rupee helps **exporters** like IT and pharma.

## Relative strength is the tell

Compare each sector index against the Nifty. A sector making new relative highs while the broader market consolidates is **absorbing fresh money** — that is the leadership you want to be aligned with.

## The rotation clock

Early-cycle favours financials and industrials, mid-cycle favours technology, and late-cycle defensives like FMCG and pharma take over. The market is never perfectly on schedule, but the **sequence rhymes.**

## Using it in practice

Anchor your watchlist to the two or three strongest sectors and avoid fighting a sector in clear downtrend, however cheap a single stock inside it looks. **Trade with the rotation, not against it.**',
 'RootNivesh Research', 8, 'published', '2026-06-21 09:00:00');
