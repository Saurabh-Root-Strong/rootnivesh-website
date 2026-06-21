-- =============================================================
-- seed_blog_2.sql — 8 more posts: SIP, compounding, long-term
-- investing, personal finance, index funds, factor investing.
-- Run ONCE in phpMyAdmin (SQL tab). Safe to re-run (INSERT IGNORE).
-- Requires the new categories 'investing' and 'personal-finance'
-- (already added to the admin whitelist + blog filter chips).
-- Body = site lite-markdown: "## " heading (builds TOC), "### "
-- sub-heading, blank line = paragraph, **text** = bold.
-- =============================================================

INSERT IGNORE INTO posts
  (slug, title, category, excerpt, cover_image, body, author, read_minutes, status, published_at)
VALUES

-- 1 -----------------------------------------------------------
('power-of-compounding-sip-explained',
 'The Power of Compounding: How a Small SIP Becomes a Crore',
 'investing',
 'Compounding is the closest thing to magic in finance — but only if you give it time. The exact maths of how a modest monthly SIP snowballs into serious wealth.',
 'https://images.unsplash.com/photo-1518186285589-2f7649de83e0?w=1200&q=80&auto=format&fit=crop',
 '## Why Einstein called it the eighth wonder

Compounding means your returns start earning their own returns. Early on it feels painfully slow; later it becomes unstoppable. **The whole game is time, not timing.**

## The maths, made concrete

Invest Rs 10,000 a month at a 12% annual return. After 10 years you have roughly Rs 23 lakh. Keep going to 20 years and it is about Rs 1 crore. To 30 years, **around Rs 3.5 crore.** You tripled the time and the wealth grew far more than three times — that is the curve bending upward.

## The cost of starting late

A 25-year-old investing Rs 5,000 a month often ends up wealthier at 60 than a 35-year-old investing Rs 10,000 a month. **The early saver put in less money but bought more time.** Those first ten years do the heaviest lifting.

## What quietly kills compounding

### Stopping during crashes

Pausing your SIP when markets fall cancels the discounted units you most want to buy. **Volatility is the price of the return, not a reason to quit.**

### High fees

A 2% annual cost does not sound like much, but over 30 years it can eat a quarter of your final corpus. Prefer low-cost funds and let the curve work for you.

## The one habit that matters

Automate the SIP, raise it a little each year as your income grows, and **leave it alone.** The investors who win are rarely the smartest — they are the most patient.',
 'RootNivesh Research', 6, 'published', '2026-06-07 09:00:00'),

-- 2 -----------------------------------------------------------
('sip-vs-lumpsum-which-is-better',
 'SIP vs Lumpsum: Which Actually Wins in Indian Markets?',
 'investing',
 'Got a bonus to invest — all at once, or spread it out? The honest, data-driven answer depends on three things, and it is not the one most people assume.',
 'https://images.unsplash.com/photo-1554260570-9140fd3b7614?w=1200&q=80&auto=format&fit=crop',
 '## The question everyone gets wrong

People treat SIP versus lumpsum as a moral choice. It is really a **maths and behaviour** question, and the right answer changes with your situation.

## What the data says

Because markets rise more often than they fall, a **lumpsum invested early statistically beats a SIP** over long horizons — your money spends more time in the market. The catch is the risk of investing the whole amount right before a correction.

## Where SIP genuinely wins

### Rupee-cost averaging

A SIP buys more units when prices are low and fewer when high, smoothing your entry. In a **sideways or choppy market**, that averaging can beat a single ill-timed lumpsum.

### Behaviour

Most people do not have a lumpsum lying idle — they have a monthly salary. A SIP turns investing into a habit you never have to think about, which is worth more than a small theoretical edge.

## A practical rule

If you have a windfall and a long horizon, **stagger it over 6 to 12 months (a STP)** to balance the time-in-market advantage against bad-timing risk. For regular income, run a plain monthly SIP and forget about it.

## The real takeaway

The best plan is the one you will actually stick to. **A consistent SIP you never stop beats a perfect lumpsum you agonise over and delay.**',
 'RootNivesh Research', 6, 'published', '2026-06-08 10:30:00'),

-- 3 -----------------------------------------------------------
('emergency-fund-how-much-where-to-keep',
 'Emergency Fund: How Much You Need and Where to Park It',
 'personal-finance',
 'Before a single rupee goes into stocks, you need a cash buffer. How big it should be, where to keep it, and why it is the foundation every portfolio stands on.',
 'https://images.unsplash.com/photo-1567427017947-545c5f8d16ad?w=1200&q=80&auto=format&fit=crop',
 '## The investment you make before investing

An emergency fund is not exciting, but it is what stops a job loss or medical bill from forcing you to **sell your investments at the worst possible time.** It is the floor under everything else.

## How much

The standard guidance is **3 to 6 months of essential expenses** — rent, EMIs, food, utilities, insurance. Lean toward six months if your income is variable, you are self-employed, or you are the sole earner.

## Where to park it

The job of this money is **safety and instant access, not returns.** Good homes:

### A separate savings account

Boring, fully liquid, and out of sight from your spending account so you are not tempted.

### Liquid or overnight mutual funds

Slightly better returns than a savings account, redeemable in a day, with very low risk. A sensible place for the bulk of the buffer.

## Where NOT to park it

Not in stocks, equity funds or anything that can fall 30% exactly when you need it. **An emergency fund that can crash is not an emergency fund.**

## Build it first, then invest

If you have no buffer yet, pause aggressive investing and **fill the emergency fund first.** Once it is in place, you can take real risk in the market with a calm head — because a bad month in life no longer means a forced sale at a loss.',
 'RootNivesh Research', 5, 'published', '2026-06-09 11:00:00'),

-- 4 -----------------------------------------------------------
('50-30-20-budget-for-indian-salaries',
 'The 50/30/20 Budget: A Simple Money Plan for Indian Salaries',
 'personal-finance',
 'You cannot invest what you do not save. The clean, no-spreadsheet framework that splits your take-home pay so saving happens automatically every month.',
 'https://images.unsplash.com/photo-1579532537598-459ecdaf39cc?w=1200&q=80&auto=format&fit=crop',
 '## Wealth starts with the savings rate

How much you earn matters less than **how much you keep.** A high earner who spends it all stays broke; a steady saver compounds quietly into wealth. A budget is just a system to protect your savings rate.

## The 50/30/20 split

Take your monthly take-home pay and divide it three ways.

### 50% — Needs

Rent or home EMI, groceries, utilities, transport, insurance premiums, school fees. The non-negotiables.

### 30% — Wants

Eating out, OTT subscriptions, travel, gadgets, the lifestyle. Allowed, but capped — this is the line most budgets quietly blow through.

### 20% — Save and invest

SIPs, retirement, debt repayment beyond the minimum. **Treat this like a bill you pay yourself first**, on salary day, before anything else moves.

## The trick that makes it work

**Automate the 20% on the day your salary arrives.** Auto-debit the SIP and any extra savings first. You then spend whatever is left guilt-free, knowing the important part is already done.

## Adjust to reality

In a high-rent city the needs slice may run to 60%, which is fine — the point is awareness, not rigid rules. As your income grows, **bank the raises**: push the extra into the 20% instead of inflating the 30%. That single habit is what turns a salary into a portfolio.',
 'RootNivesh Research', 6, 'published', '2026-06-10 09:30:00'),

-- 5 -----------------------------------------------------------
('what-is-an-index-fund-and-why-it-works',
 'What Is an Index Fund — and Why It Quietly Beats Most Pros',
 'education',
 'Most actively managed funds fail to beat the index over time. Here is what an index fund actually is, why it works, and where it fits in an Indian portfolio.',
 'https://images.unsplash.com/photo-1640340434855-6084b1f4901c?w=1200&q=80&auto=format&fit=crop',
 '## The simplest powerful idea in investing

An index fund does not try to pick winners. It simply **buys the whole market** — say all 50 Nifty stocks in their exact weights — and charges a tiny fee to do it. No star manager, no stock-picking drama.

## Why owning everything beats trying to be clever

Over long periods, **the majority of active fund managers underperform their benchmark** after fees. Markets are competitive; consistently beating the index is genuinely hard. By owning the index, you guarantee the market return at minimal cost — which quietly puts you ahead of most who tried to beat it.

## The fee difference is enormous

An active fund may charge 1.5 to 2% a year; an index fund often charges a fraction of that. Compounded over decades, that gap can mean **lakhs of rupees of difference** in your final corpus for identical market exposure.

## How it fits an Indian portfolio

### The core

A broad index fund (Nifty 50 or a total-market index) makes an excellent **low-maintenance core** that you hold for years.

### The satellites

Around that core you can add specific bets — sectors, factors, individual stocks — if you have an edge. But the core does the heavy lifting.

## The honest caveat

An index fund still falls in a crash — it is not safe, it is **diversified and cheap.** Its power comes from low cost and the discipline to hold through downturns. For most investors, most of the time, that is the smartest default there is.',
 'RootNivesh Research', 7, 'published', '2026-06-11 10:00:00'),

-- 6 -----------------------------------------------------------
('long-term-investing-time-in-the-market',
 'Long-Term Investing: Why Time in the Market Beats Timing It',
 'investing',
 'Everyone wants to buy the bottom and sell the top. The data shows that trying to time the market costs most investors far more than the crashes they fear.',
 'https://images.unsplash.com/photo-1638913662252-70efce1e60a7?w=1200&q=80&auto=format&fit=crop',
 '## The most expensive instinct in investing

Selling to avoid a crash feels smart. But to win at timing you must be right **twice** — when to get out and when to get back in — and almost nobody is. The cost of getting it wrong is brutal.

## The missed-best-days problem

Market returns are wildly concentrated in a handful of days, and **the best days cluster right next to the worst ones**, usually during the scariest part of a sell-off. Sit in cash to dodge the crash and you almost always miss the violent recovery that follows. Missing just the ten best days over a couple of decades can cut your total return dramatically.

## Why this happens

Recoveries are sudden and unscheduled. By the time it feels safe to return, the rebound has already happened. **The investor who simply stayed put captured it without doing anything.**

## What to do instead

### Stay invested

If your money is for a goal a decade away, a 20% drop along the way is noise, not news. **Keep the SIP running**, especially when it hurts.

### Diversify and rebalance

Spread across assets so no single crash is fatal, and rebalance occasionally. That is the disciplined version of buy-low-sell-high, on autopilot.

## The mindset that wins

Treat market falls as **sales, not disasters.** The long-term investor does not need to predict the future — only to stay in the game long enough for compounding to do its work.',
 'RootNivesh Research', 7, 'published', '2026-06-12 09:00:00'),

-- 7 -----------------------------------------------------------
('factor-investing-value-momentum-quality',
 'Factor Investing 101: Value, Momentum and Quality Explained',
 'quant',
 'Decades of research show that certain stock characteristics have earned higher returns over time. A plain-English tour of the factors that power quant investing.',
 'https://images.unsplash.com/photo-1556742502-ec7c0e9f34b1?w=1200&q=80&auto=format&fit=crop',
 '## Beyond just picking stocks

Factor investing asks a deeper question: **what shared traits make groups of stocks outperform?** Decades of academic and market data point to a handful of persistent factors that have historically been rewarded.

## The main factors

### Value

Buying stocks that are cheap relative to earnings, book value or cash flow. The idea is simple — **pay less for each rupee of fundamentals** — and over long horizons cheapness has tended to pay off, though it can lag for years.

### Momentum

Stocks that have outperformed recently tend to keep outperforming for a while. Momentum captures the market''s tendency to **trend** before it reverses — powerful, but it needs discipline because reversals can be sharp.

### Quality

Companies with strong profitability, low debt and stable earnings. Quality tends to **cushion drawdowns** and compound steadily, often shining when markets get nervous.

### Low volatility and size

Lower-volatility stocks have delivered surprisingly strong risk-adjusted returns, and smaller companies have historically carried a return premium for the extra risk.

## Why factors beat hunches

A factor is a **rule you can test, measure and repeat** — the opposite of a tip. It removes emotion and lets you know exactly why you own something.

## The honest caveat

No factor works every year; each goes through long stretches of underperformance, which is precisely why the premium survives. **The edge is in disciplined exposure across factors and the patience to hold through the lean periods** — the core of how we build quant-backed research.',
 'RootNivesh Research', 8, 'published', '2026-06-13 10:30:00'),

-- 8 -----------------------------------------------------------
('risk-vs-return-sharpe-ratio-explained',
 'Risk vs Return: Standard Deviation and the Sharpe Ratio, Simply',
 'education',
 'A 20% return means nothing until you know the risk taken to earn it. The two numbers that let you compare any two investments on a level playing field.',
 'https://images.unsplash.com/photo-1642543492481-44e81e3914a7?w=1200&q=80&auto=format&fit=crop',
 '## Return alone is a half-truth

Two funds both returned 15% last year. One did it smoothly; the other swung wildly and nearly halved on the way. **They are not the same investment.** To judge fairly, you have to measure the risk behind the return.

## Standard deviation: measuring the bumps

Standard deviation tells you how much an investment''s returns **bounce around their average.** A low number means a steady ride; a high number means big swings in both directions. It is the most common stand-in for risk.

## The Sharpe ratio: return per unit of risk

The Sharpe ratio divides an investment''s return (above the risk-free rate) by its standard deviation. In plain terms, it answers: **how much reward did you get for each unit of risk you took?**

A higher Sharpe ratio means a more efficient investment. A fund returning 15% with low volatility can have a **better Sharpe ratio** than one returning 20% with stomach-churning swings — and be the smarter holding.

## Why this matters to you

### Comparing fairly

Sharpe lets you line up two very different investments and see which one **worked harder for its risk.**

### Knowing your own limit

A high-return, high-volatility fund is only good if you can hold it through the drops without panic-selling. **The best risk-adjusted return is the one you can actually stay invested in.**

## The takeaway

Chase risk-adjusted returns, not headline returns. The investor who understands the risk behind a number is far harder to fool — and far harder to scare out of a good long-term plan.',
 'RootNivesh Research', 7, 'published', '2026-06-14 11:00:00');
