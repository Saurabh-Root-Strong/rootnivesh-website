-- =============================================================
-- seed_blog_3.sql — 10 NEW long-form, search-targeted posts.
-- Run ONCE in phpMyAdmin (SQL tab). Safe to re-run: INSERT IGNORE
-- skips any slug that already exists.
--
-- EDITORIAL / SEO NOTES
-- ---------------------
-- These 10 are built as FOUR TOPIC CLUSTERS rather than ten loose
-- articles, because clusters are what earn topical authority:
--
--   1. IPO cluster (3 posts)  -> feeds and is fed by /ipo, the tool
--      page that now carries live GMP + rating. "ipo gmp", "ipo
--      allotment status" and "sme ipo" are among the highest-volume
--      recurring retail queries in India, and we own a tool that
--      answers them. Ranking the posts pulls traffic INTO the tool.
--   2. F&O reality cluster (2) -> "why do traders lose money" and
--      SEBI's 2024-25 rule overhaul. High volume, high trust, and it
--      positions a Research Analyst honestly rather than as a tipster.
--   3. Tax cluster (2) -> capital gains + F&O ITR. Peak season is
--      July-September, which is exactly now. Perennial, high intent.
--   4. Fundamentals cluster (3) -> valuation ratios, balance sheet,
--      corporate actions. Evergreen, links the beginner audience to
--      the research side of the business.
--
-- Every post links internally (the blog renderer now supports
-- [text](/path) links), which is what turns a blog into a ranking
-- system instead of ten dead ends.
--
-- Facts current as of July 2026: STCG 20% / LTCG 12.5% over Rs 1.25
-- lakh (Budget 2024, unchanged since); SEBI FY25 study - 91% of
-- individual F&O traders lost money, aggregate net loss Rs 1.05 lakh
-- crore; index derivatives contract size Rs 15-20 lakh; one weekly
-- expiry per exchange.
--
-- Body uses the site lite-markdown: "## " heading, "### " sub-heading,
-- blank line = paragraph, **bold**, [text](/path) = link.
-- =============================================================

INSERT IGNORE INTO posts
  (slug, title, category, excerpt, cover_image, body, author, read_minutes, status, published_at)
VALUES

-- 1 — IPO CLUSTER -------------------------------------------------
('ipo-gmp-grey-market-premium-meaning',
 'IPO GMP Explained: What Grey Market Premium Really Tells You (and What It Does Not)',
 'education',
 'GMP is the number every IPO investor checks and almost nobody understands. What the grey market actually is, how the premium is set, how well it predicts listing gains — and why SEBI does not regulate a rupee of it.',
 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=1200&q=80&auto=format&fit=crop',
 '## What GMP actually means

**GMP, or Grey Market Premium, is the price people are privately willing to pay for an IPO share before it lists on the exchange.** If an IPO is priced at Rs 214 and the GMP is Rs 35, buyers in that unofficial market are paying roughly Rs 249 for a share that has not started trading yet.

That single number now drives an enormous amount of retail behaviour. People decide whether to apply, how many lots to bid for, and whether to sell on listing day, largely on the basis of GMP. So it is worth understanding exactly what it is — and what it is not.

## The grey market is not a market

Here is the part most articles skip. **The grey market has no exchange, no clearing house, no regulator and no legal recourse.** There is no order book you can inspect. There is no settlement guarantee. If the person on the other side of your trade walks away, nothing happens to them.

It is a network of dealers, largely concentrated in a handful of trading hubs, quoting prices to one another over the phone and on messaging apps. The "price" you see published on IPO tracking websites is a survey of those quotes.

SEBI does not regulate it. It does not recognise it. Grey market transactions are settled on trust between the parties, which is precisely why a Research Analyst can report the number as information but cannot advise you to transact in it. **We publish GMP on our [IPO page](/ipo) as market colour. We do not deal in the grey market, and neither should you.**

## How the premium gets set

GMP is a sentiment gauge more than a valuation. It moves on:

### Subscription momentum

When the QIB and HNI books fill fast, dealers mark the premium up. A heavily oversubscribed issue means fewer shares per applicant, which means scarcity, which means people will pay more for the ones that do get allotted.

### The mood of the broader market

The same company at the same price would carry a very different GMP in a raging bull market than in a correction. GMP is downstream of the Nifty far more than most people realise.

### Float and lot size

A small SME issue with a tiny free float can be moved by a handful of dealers. **A thin market produces a loud number.** This is why some SME IPO premiums look absurd next to their fundamentals.

### Deliberate signalling

Because a high GMP attracts retail applications, there are parties who benefit from that number looking strong. Treat an unusually enthusiastic premium on an otherwise unremarkable issue with suspicion, not excitement.

## Does GMP actually predict the listing price?

Directionally, often. Precisely, no.

A strongly positive GMP has historically correlated with a positive listing. A negative or zero GMP has often preceded a flat or discounted listing. That relationship is real, and it is why the number is worth watching at all.

But the correlation is loose and it breaks exactly when it matters most. **GMP is a snapshot of sentiment days before listing, and sentiment is the most perishable input in finance.** A weak global cue on listing morning, a bad set of results from a peer, a sudden move in the index — any of these can wipe out a premium that looked bankable the previous evening.

There is also a survivorship problem in how GMP is discussed. Nobody writes a triumphant post about the IPO whose GMP said plus 40 percent and which listed at par. The hits get remembered and the misses get quietly forgotten, which makes the track record look far better than it is.

## How to use GMP without being used by it

**Use it as a thermometer, not a thesis.**

A high GMP tells you the market is currently excited about this issue. That is genuinely useful context. It tells you nothing whatsoever about whether the business is good, whether the valuation is sane, or whether you should own the stock a year from now.

Before GMP, read the prospectus. Our guide on [how to read an IPO prospectus](/blog/how-to-read-an-ipo-prospectus-7-things-that-actually-matter) covers the seven sections that actually decide whether an issue is worth your money. Look at what the company earns, what it is charging you for those earnings, whether the promoters are selling their own stake through the offer, and what the money is being used for.

Then, and only then, glance at the premium to gauge the mood you are walking into.

## The rating we show, and what it is not

On our [IPO page](/ipo) each issue carries a star rating next to its GMP. That rating is derived arithmetically from the estimated listing gain — the premium expressed as a percentage of the issue price. It is a restatement of grey market sentiment in a form that is quicker to scan.

**It is not a recommendation to subscribe.** It is not our research view on the company. A five-star sentiment reading on a poor business is a warning sign, not a green light.

## The uncomfortable summary

Grey Market Premium is the most watched and least understood number in the Indian primary market. It is unofficial, unregulated, thinly traded, easily influenced and highly perishable. It is still worth knowing, because ignoring the mood of a market you are about to enter is its own kind of blindness.

Know the number. Do not obey it.

**Disclaimer: Grey market data is sourced from public IPO trackers and is provided for information only. Root Nivesh does not deal, trade or transact in the grey market. Investments in securities are subject to market risk. Read all offer documents carefully before investing.**',
 'RootNivesh Research', 9, 'published', '2026-07-11 09:30:00'),

-- 2 ---------------------------------------------------------------
('ipo-allotment-status-how-to-check',
 'IPO Allotment Status: How to Check It on BSE, NSE and the Registrar',
 'education',
 'Applied for an IPO and waiting? Here is exactly where allotment status is published, what each registrar portal needs from you, why you may get zero shares despite a full payment, and what happens to your blocked money.',
 'https://images.unsplash.com/photo-1633158829585-23ba8f7c8caf?w=1200&q=80&auto=format&fit=crop',
 '## Where allotment status actually lives

There is no single national IPO allotment website, and this confuses almost everyone applying for the first time.

**Allotment is processed by the issue registrar, not by the exchange and not by your broker.** The registrar is the agency the company appoints to run the application and allotment process — in India that is usually Link Intime, KFin Technologies (formerly Karvy), Bigshare, Cameo or MUFG Intime. Each has its own status page.

You have three practical routes, and any of them will do.

### Route 1 — The registrar portal

The most authoritative source. Go to the registrar handling that specific IPO, select the issue from a dropdown, then identify yourself by any one of:

- **PAN** — easiest, works every time
- **Application number** — from your broker or bank confirmation
- **DP ID / Client ID** — your 16-digit demat identifier
- **Bank account number** — for ASBA applications

The catch is knowing which registrar handles which issue. It is printed on the prospectus and on the issue page.

### Route 2 — BSE

BSE publishes a combined allotment status page that covers issues listed on it. Choose **Equity** as the issue type, pick the company, and enter your application number and PAN.

### Route 3 — NSE

NSE lets you verify your bid rather than the allotment itself, which is subtly different but useful — it confirms the exchange received your application at the price and quantity you intended.

We keep direct links to all of these on the **Allotment Status** tab of our [IPO page](/ipo), so you do not have to hunt for the right registrar each time.

## The timeline you are actually waiting on

Under the T+3 listing framework now standard in India:

- **Day 0** — Issue closes
- **Day 1** — Basis of allotment is finalised
- **Day 2** — Allotment status goes live; refunds initiated; shares credited to demat
- **Day 3** — The stock lists and starts trading

**Allotment status typically appears one to two days after the issue closes**, usually in the evening. If you check the morning after closing and see nothing, you are simply early.

## Why you got zero shares

This is the single most common question, and the answer is usually not a mistake.

**In an oversubscribed IPO, retail allotment is a lottery.** If the retail portion is subscribed five times, only about one in five applicants at the minimum lot gets anything. SEBI rules require that the maximum number of applicants receive at least one lot, so the system does not hand large chunks to a few and nothing to everyone else. It runs a computerised draw.

The practical consequences are worth internalising:

**Applying for more lots does not improve your odds in the retail category.** In a heavily oversubscribed issue, one lot and six lots have the same probability of the draw landing on you. Bidding bigger only helps in the HNI category, which is a different game with different capital requirements.

**Applying at the cut-off price does improve your odds.** Bidding below the final issue price gets your application rejected outright. Cut-off simply means "I will pay whatever the final price turns out to be", and it removes that risk.

**Multiple applications from the same PAN get rejected.** All of them, including the first. One PAN, one application. Family members with their own PAN and their own demat account may each apply legitimately.

## What happens to your money

If you applied through ASBA — which is now effectively mandatory — **your money was never debited.** It was *blocked* in your bank account. You could see the balance but not spend it.

On allotment day one of two things happens. If you were allotted shares, the exact amount is debited and the rest unblocked. If you got nothing, the entire block is released, usually within one to two working days.

If the block has not lifted three working days after allotment, raise it with your bank first, not the registrar — a stuck ASBA mandate is nearly always a banking system issue.

## Small things that cost people allotments

**A dormant or frozen demat account.** Shares cannot be credited to an account that is not active. Check before applying, not after.

**A PAN and demat name mismatch.** If the name on the PAN does not exactly match the demat records, the application can be rejected at verification.

**Insufficient balance at the time of blocking.** The full application amount must be available. A partially funded account gets rejected.

**Applying after the cut-off time on the closing day.** Brokers close their windows earlier than the exchange does — often by several hours — to allow for processing.

## After allotment

If you were allotted, the shares hit your demat before listing and you are free to sell from the first tick. Whether you should is a separate question entirely, and one that GMP will happily answer wrongly for you — see our piece on [what Grey Market Premium really tells you](/blog/ipo-gmp-grey-market-premium-meaning).

If you were not allotted, nothing is wrong with you or your application. The draw did not land on you. That is the entire mechanism.

**Disclaimer: Investments in securities are subject to market risk. Read all offer documents carefully before investing. Past listing performance is not indicative of future results.**',
 'RootNivesh Research', 8, 'published', '2026-07-10 10:00:00'),

-- 3 ---------------------------------------------------------------
('sme-ipo-vs-mainboard-ipo-difference',
 'SME IPO vs Mainboard IPO: The Differences That Actually Cost Retail Money',
 'education',
 'SME IPOs post eye-watering listing gains and quietly destroy capital. The lot size, the disclosure gap, the liquidity trap and the regulatory reality — before you put a lakh into one.',
 'https://images.unsplash.com/photo-1591696205602-2f950c417cb9?w=1200&q=80&auto=format&fit=crop',
 '## Two very different products with the same name

When people say "IPO" they usually picture a mainboard issue — a large, well-covered company listing on the main NSE or BSE platform. But a growing share of the issues hitting the market are **SME IPOs**, listed on the NSE Emerge and BSE SME platforms.

They look the same on a screen. They are not the same product. Understanding the difference is the difference between an informed risk and an accident.

## The lot size gap is the first shock

**A mainboard IPO is designed so a retail investor can apply with roughly Rs 14,000 to Rs 15,000.** One lot. If it does badly, you have lost a manageable amount.

**An SME IPO lot typically costs Rs 1 lakh to Rs 2 lakh.** That is the minimum ticket. There is no smaller size available to you.

This single fact changes everything about position sizing. A retail investor who would never dream of putting a lakh into one unproven small-cap stock will do exactly that in an SME IPO, because the application process makes it feel like a normal IPO rather than a concentrated bet. **The lot size is not a detail. It is the risk.**

## The disclosure gap

Mainboard issues face the full weight of SEBI disclosure. The prospectus runs to hundreds of pages. Analysts cover it. Institutions price it. Journalists dig into it.

SME issues operate under a lighter framework by design — the entire point of the platform is to let genuinely small companies raise capital without a compliance burden that would swallow them. That is a legitimate policy goal.

But it means:

- **Fewer eyes.** Little to no independent analyst coverage.
- **Thinner history.** Shorter track records, smaller audits.
- **Half-yearly, not quarterly, reporting** for many SME-listed companies.
- **Less institutional participation** to discipline the pricing.

You are underwriting a young business with less information and fewer professionals checking the work. That is not automatically bad. It is definitively riskier.

## The liquidity trap

This is the part that catches people, and it catches them on the way out rather than the way in.

**SME stocks trade in fixed lot sizes even after listing.** You cannot sell a single share. You sell a lot. And the daily traded volume on many SME counters is a rounding error compared with a mainboard stock.

The practical result: on a bad day, there may be no bid anywhere near the last traded price. The stock hits a lower circuit and stays there. Your position is worth what the screen says only in the sense that you cannot get that price for it.

**A listing gain you cannot exit is not a gain.** It is a number on a screen.

## Why the returns look spectacular

Every so often an SME IPO lists at two or three times its issue price and the number goes around social media. Those events are real. They are also profoundly unrepresentative.

Three things make SME listing pops both larger and less meaningful than they appear:

**Tiny float.** A small number of shares changing hands can move the price enormously. The same demand against a mainboard float would barely register.

**Retail-heavy books.** With less institutional pricing discipline, issues can be priced into an enthusiastic market and then rise into an even more enthusiastic one.

**Selection bias in what you hear about.** The SME that doubled gets a screenshot. The one that drifted 40 percent below issue price over the following year does not.

SEBI has repeatedly flagged manipulation and inflated financials in segments of the SME market, and has tightened norms in response. That regulatory attention exists for a reason.

## How to think about an SME IPO properly

If you are still interested — and there are genuinely good businesses on these platforms — apply the same discipline you would to any concentrated bet:

**Size it as the bet it is.** A Rs 1.5 lakh SME application is not "an IPO application". It is a single-stock position of Rs 1.5 lakh in an illiquid micro-cap. Would you take that position in the secondary market? If not, do not take it here.

**Read the prospectus properly.** Our guide to [reading an IPO prospectus](/blog/how-to-read-an-ipo-prospectus-7-things-that-actually-matter) applies with more force here, not less, precisely because nobody else is doing it for you.

**Check who is selling.** If the offer is dominated by an Offer for Sale — promoters cashing out — rather than fresh issue capital going into the business, ask why the people who know the company best are reducing their stake.

**Ignore the grey market.** SME premiums are set in the thinnest, most easily influenced corner of an already unregulated market. See [what GMP really tells you](/blog/ipo-gmp-grey-market-premium-meaning).

**Assume you cannot exit quickly.** Then decide if you still want it.

## The honest summary

SME IPOs are a legitimate route for small companies to raise capital, and a legitimate place for informed investors to take risk with money they can afford to lock up.

They are not a lottery ticket, they are not a smaller version of a mainboard IPO, and the Rs 1 lakh-plus minimum ticket makes them a far more concentrated commitment than most applicants realise at the moment they click apply.

You can see which issues are SME and which are Mainboard, clearly labelled, on our [IPO page](/ipo).

**Disclaimer: Investments in securities are subject to market risk. SME securities carry elevated liquidity and disclosure risk. Read all offer documents carefully before investing.**',
 'RootNivesh Research', 9, 'published', '2026-07-09 09:45:00'),

-- 4 — F&O REALITY CLUSTER -----------------------------------------
('why-91-percent-fno-traders-lose-money-sebi-data',
 'Why 91% of F&O Traders Lose Money: What SEBI''s Own Data Actually Shows',
 'strategy',
 'SEBI studied 96 lakh derivative traders and found nine in ten losing money — Rs 1.05 lakh crore of net losses in one year. The numbers, the reasons behind them, and the four that describe the survivors.',
 'https://images.unsplash.com/photo-1612178991541-b48cc8e92a4d?w=1200&q=80&auto=format&fit=crop',
 '## The number, and where it comes from

This is not a motivational statistic invented by a blogger. **It is SEBI''s own finding, drawn from the books of the 13 largest brokers covering roughly 96 lakh unique F&O traders.**

The headline results from the study covering FY 2024-25:

- **Over 91% of individual traders lost money in equity derivatives.**
- **Aggregate net losses reached about Rs 1.05 lakh crore**, up roughly 41% from the previous year.
- **The average loss per losing trader was around Rs 1.1 lakh.**

Read that middle number again. In a single financial year, individual traders as a group handed over more than a lakh crore rupees. That is not a market correction. That is a structural transfer.

And the ratio has been stubbornly consistent across every year SEBI has studied. It is not a bad-luck year. It is the base rate.

## Why the ratio is so brutal

### The cost floor most traders never calculate

Every round trip costs you: brokerage, exchange transaction charges, STT, stamp duty, GST on the brokerage, and — the invisible one — **the bid-ask spread you cross to get filled.**

For an intraday options trader taking several trades a day, these costs can exceed the entire statistical edge of the strategy. **You can be right slightly more often than you are wrong and still bleed to death through friction.** Most retail strategies are not edge-negative because the idea is stupid. They are edge-negative because the idea was never large enough to clear the cost of expressing it.

We have measured this in our own research repeatedly: a signal that looks profitable on paper vanishes the moment realistic costs are applied.

### Options decay while you deliberate

A long option is a wasting asset. Every day you hold it, time value bleeds out — and on expiry day it bleeds out violently. Buying cheap out-of-the-money options on expiry day, the single most popular retail trade in India, is the mathematical equivalent of buying a lottery ticket whose price rises as your odds fall.

**Most of those options expire worthless. That is not an opinion, it is what the payoff structure is designed to do.**

### Leverage turns a normal drawdown into a wipeout

Derivatives let you control a large notional position with a small margin. That cuts both ways with perfect symmetry, and human psychology is not symmetric. A leveraged loss triggers a margin call at exactly the moment your judgement is worst.

### Overtrading

SEBI''s data shows the heaviest traders lose the most, not the least. Activity is not skill. Each additional trade is another payment of the cost floor in exchange for another draw from a distribution whose mean is negative.

## What the profitable 9% look like

The data does not fully describe them, but the shape is visible in the study and in every serious body of trading research:

**They trade less.** Fewer positions, held with more conviction, sized correctly.

**They are not buying expiry-day lottery tickets.** Consistently profitable participants skew toward strategies with a defined, structural edge — often hedged, often institutional in character.

**They size positions so a losing streak is survivable.** They risk a small, fixed fraction of capital per trade, so that being wrong six times in a row is an inconvenience rather than an ending. This is the single most transferable habit in trading, and it is a risk question rather than a forecasting one — see [risk vs return and the Sharpe ratio](/blog/risk-vs-return-standard-deviation-and-the-sharpe-ratio-simply).

**They know their cost per trade to the rupee.** If you cannot state what a round trip costs you, you cannot know whether your strategy has an edge.

## The uncomfortable implication

If nine in ten lose, the question is not "how do I win at this" but **"do I have any business being here at all".**

For most retail participants, the honest answer is no — not because they are stupid, but because they are competing, on cost and speed and information, with dedicated firms whose entire existence is this. There is no shame in that. There is only expense in ignoring it.

The capital that flows out of F&O and into a disciplined equity or index-fund plan is not being timid. It is being routed to where an ordinary investor actually has an edge: **time, patience and compounding** — which is a game institutions cannot take away from you. Our piece on [why time in the market beats timing](/blog/long-term-investing-why-time-in-the-market-beats-timing-it) makes that case with the numbers.

## If you are going to trade anyway

Then trade like the minority, not the majority:

- Write down your cost per round trip. Then demand an edge bigger than it.
- Risk a fixed, small fraction of capital per trade. Never a fraction of your conviction.
- Stop trading expiry-day options for the thrill of it.
- Keep a log. The market will lie to you about your own record; a spreadsheet will not.
- Judge yourself on process over a year, not on P&L over a week.

**Disclaimer: Derivatives trading involves substantial risk of loss and is not suitable for all investors. This article is for education only and is not a recommendation to buy, sell or trade any security. Investments in securities are subject to market risk.**',
 'RootNivesh Research', 10, 'published', '2026-07-08 09:15:00'),

-- 5 ---------------------------------------------------------------
('sebi-new-fno-rules-lot-size-weekly-expiry',
 'SEBI''s New F&O Rules: Bigger Lots, One Weekly Expiry — What Changed for Retail',
 'markets',
 'Contract sizes tripled, weekly expiries were cut to one per exchange, and upfront premium became mandatory. A plain-English guide to the derivatives overhaul and what it means for a small trading account.',
 'https://images.unsplash.com/photo-1526304640581-d334cdbbf45e?w=1200&q=80&auto=format&fit=crop',
 '## Why SEBI moved

The regulator did not act on a hunch. **Its own study found that more than nine in ten individual F&O traders were losing money**, with aggregate losses running into lakhs of crores and the heaviest concentration of damage on expiry-day options.

The response was a package of measures aimed squarely at reducing speculative churn in index derivatives. Some of it is unpopular. All of it is comprehensible once you see the problem it is aimed at.

## What actually changed

### 1. Contract size raised to roughly Rs 15-20 lakh

This is the change with the largest practical effect. **The minimum notional value of an index derivatives contract was raised to the Rs 15-20 lakh band, the first revision in about nine years.**

Concretely, one lot now represents about three times the exposure it used to. A trader who previously traded three lots comfortably may now be able to afford one — or none.

**Intent:** ensure that anyone taking leveraged derivative exposure has the capital to absorb it. **Effect:** a meaningful slice of small accounts is priced out of index options entirely.

### 2. One weekly expiry per exchange

Previously there was a weekly expiry almost every day of the week across the various indices — an effectively continuous expiry-day casino.

**Now each exchange offers weekly contracts on only one benchmark index.** NSE discontinued weekly expiries on Bank Nifty, Nifty Financial Services, Nifty Midcap Select and Nifty Next 50.

**Intent:** remove the daily expiry-day lottery. **Effect:** far fewer of the cheap, high-gamma, near-zero-probability options that retail traders were buying in enormous volume.

### 3. Upfront option premium mandatory

Brokers must collect the option premium from the buyer upfront, closing off intraday leverage on premium that some were extending.

### 4. Extra margin on expiry-day short options

An additional Extreme Loss Margin applies to short index options on expiry day — the window where a quiet position can be destroyed in minutes by a sharp move.

### 5. Calendar spread benefit removed on expiry day

Traders could previously offset margin between a near-month and far-month position. On expiry day this offset no longer applies, because the two legs stop behaving as a hedge when one of them is about to disappear.

### 6. Intraday position limit monitoring

Exchanges now snapshot open positions several times a day rather than checking only at end of day, closing a window in which limits could be exceeded intraday and squared off before anyone noticed.

## What this means for a small account

**If your trading capital is under a few lakh rupees, index options are now substantially harder to access — and that is deliberate.**

The reasonable reactions, in rough order of sense:

**Accept the message.** The rules exist because the data said this segment was destroying retail capital at scale. If the new lot size prices you out, the rules have arguably done you a favour. Read [what SEBI''s loss data actually shows](/blog/why-91-percent-fno-traders-lose-money-sebi-data) before you go looking for a workaround.

**Do not chase leverage elsewhere.** The instinct after being priced out of one leveraged product is to find another. That instinct has a poor record.

**Consider whether you were trading a strategy or a habit.** If a larger lot size destroys your approach entirely, the approach was a function of cheap optionality rather than an edge.

**Redirect to where a retail investor is actually advantaged.** Nobody can compete with you on holding a good business for ten years. Everybody can outcompete you on a two-hour options trade. Our [index fund](/blog/what-is-an-index-fund-and-why-it-quietly-beats-most-pros) and [SIP](/blog/sip-vs-lumpsum-which-actually-wins-in-indian-markets) pieces cover the alternative honestly.

## What did NOT change

Stock F&O lot sizes and the equity cash market are untouched. Hedging remains available to those who need it. Institutions and genuine hedgers were never the target — **retail speculation on expiry day was.**

## The bigger picture

There is a reasonable debate about whether it is a regulator''s job to protect adults from their own trades. But the framing misses something. SEBI is not banning derivatives. It is raising the price of admission to a game whose own scoreboard showed nine in ten participants losing.

You can still play. You simply have to bring enough capital to survive being wrong — which was always the actual requirement, only now it is written down.

**Disclaimer: Derivatives trading involves substantial risk of loss. This article is for education only and does not constitute investment advice. Rules and thresholds are subject to change by SEBI and the exchanges; always verify current norms with your broker.**',
 'RootNivesh Research', 9, 'published', '2026-07-07 10:30:00'),

-- 6 — TAX CLUSTER -------------------------------------------------
('capital-gains-tax-on-shares-stcg-ltcg-india',
 'Capital Gains Tax on Shares in India: STCG, LTCG and the Rules After Budget 2024',
 'personal-finance',
 'Short-term gains at 20%, long-term at 12.5% above Rs 1.25 lakh, indexation gone. The current rates, the holding-period line, worked examples and the loss set-off rules most investors never use.',
 'https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?w=1200&q=80&auto=format&fit=crop',
 '## The rates, current as of FY 2026-27

Budget 2024 reset equity taxation, and those rates have carried forward unchanged. For **listed equity shares and equity mutual funds**:

### Short-Term Capital Gains (STCG)

**Holding period: 12 months or less. Tax rate: 20%** under Section 111A.

This rose from 15% with effect from 23 July 2024. There is no basic exemption applied to it — 20% from the first rupee of gain.

### Long-Term Capital Gains (LTCG)

**Holding period: more than 12 months. Tax rate: 12.5%**, on gains **above Rs 1.25 lakh in a financial year**.

The first Rs 1.25 lakh of long-term equity gains each year is tax-free. The rate rose from 10%, and **the indexation benefit was removed** for these assets.

## The one line that decides everything

**Twelve months.** That is the entire boundary between paying 20% and paying 12.5%.

Sell on day 365 and you are short-term. Sell on day 367 and you are long-term, with a Rs 1.25 lakh cushion on top. **The holding period is measured from the date of purchase to the date of sale, not by financial year.**

This is the cheapest tax planning available to an equity investor and most people ignore it entirely. If you are sitting on a gain at eleven months, ask yourself whether the reason to sell now is strong enough to pay an extra 7.5 percentage points of tax for the privilege.

## Worked example

You buy 200 shares at Rs 500 (Rs 1,00,000) and sell at Rs 800 (Rs 1,60,000). **Gain: Rs 60,000.**

**Sold within 12 months (STCG):** 20% of Rs 60,000 = **Rs 12,000 tax.**

**Sold after 12 months (LTCG):** the gain is below the Rs 1.25 lakh annual exemption, so — assuming no other long-term gains that year — **Rs 0 tax.**

Same trade. Same profit. **Rs 12,000 of difference, decided by a calendar.**

Now scale it. On a Rs 5,00,000 long-term gain: the first Rs 1.25 lakh is exempt, the remaining Rs 3.75 lakh is taxed at 12.5% = **Rs 46,875**. The same gain realised short-term would cost 20% of Rs 5,00,000 = **Rs 1,00,000.**

## Harvesting the Rs 1.25 lakh exemption

The exemption is **per financial year and it does not carry forward.** If you do not use it, it is gone on 31 March.

A common and entirely legitimate practice is to sell enough long-held shares each year to realise roughly Rs 1.25 lakh of long-term gain, pay zero tax on it, and — if you still want the position — buy it back. Your cost basis resets higher, which reduces the taxable gain when you eventually exit for real.

Do this deliberately, keep records, and be aware that the repurchase carries market risk in the gap. It is a tax technique, not a free lunch.

## Setting off losses — the part people leave on the table

**Short-term capital loss** can be set off against **both** short-term and long-term capital gains.

**Long-term capital loss** can be set off **only** against long-term capital gains.

**Unabsorbed losses can be carried forward for eight assessment years** — but **only if you file your income tax return by the due date.** Miss the deadline and you forfeit the carry-forward permanently.

That last sentence is worth more than most tax articles. A trader with a loss year who does not file on time has thrown away a shield that could have covered eight years of future gains.

## What about dividends?

**Dividends are taxed at your slab rate** as income from other sources, and TDS applies above the threshold. The old Dividend Distribution Tax regime is gone — the tax now sits with you, not the company.

This matters for how you evaluate a high-dividend stock: a 4% dividend yield taxed at a 30% slab is an effective 2.8%. Our piece on [dividends, bonuses, splits and buybacks](/blog/dividend-bonus-split-buyback-difference) covers what each actually does to your holding.

## What is NOT capital gains

**F&O income is business income, not capital gains.** Different rules, different forms, different audit thresholds. If you traded derivatives this year, read [F&O income and ITR filing](/blog/fno-income-itr-filing-turnover-audit) instead — the capital gains rules on this page do not apply to it.

**Intraday equity is speculative business income**, also outside capital gains.

## The practical checklist

- Know your holding period on every position before you sell.
- Use the Rs 1.25 lakh long-term exemption every single year.
- File on time, especially in a loss year, to preserve the carry-forward.
- Download the capital gains statement your broker provides — it does the FIFO matching for you.
- Reconcile against the AIS on the income tax portal before filing.

**Disclaimer: This article is for general education and reflects rates as understood in July 2026. Tax law changes and individual circumstances differ. Consult a qualified chartered accountant or tax adviser before acting. Root Nivesh is a SEBI Registered Research Analyst and does not provide tax advice.**',
 'RootNivesh Research', 9, 'published', '2026-07-06 09:00:00'),

-- 7 ---------------------------------------------------------------
('fno-income-itr-filing-turnover-audit',
 'F&O Income and ITR: Turnover, Audit and Which Form to File',
 'personal-finance',
 'F&O profit is business income, not capital gains — and that changes everything. How turnover is actually computed, when a tax audit kicks in, which ITR form applies, and why filing on time matters most in a loss year.',
 'https://images.unsplash.com/photo-1526628953301-3e589a6a8b74?w=1200&q=80&auto=format&fit=crop',
 '## The classification that changes everything

**Income from Futures and Options is treated as non-speculative business income.** It is not capital gains. It is not "other income".

That single classification pulls a whole set of consequences behind it:

- You file **ITR-3**, not ITR-2.
- You can **deduct business expenses** against it.
- **Turnover** must be computed — and it is not what you think it is.
- A **tax audit** may become mandatory.
- Losses can be **carried forward for eight years** — but only if you file on time.

Intraday equity, by contrast, is **speculative** business income. Both are business income; they are reported separately and their losses are ring-fenced differently. Delivery-based equity remains capital gains — see [capital gains tax on shares](/blog/capital-gains-tax-on-shares-stcg-ltcg-india).

## Turnover is not your trade value

This is where most people get it badly wrong, and the error is usually in the terrifying direction.

**Turnover for F&O is not the notional value of your contracts.** If you bought one Nifty lot worth Rs 18 lakh and sold it the next day, your turnover is not Rs 18 lakh.

The widely followed approach under the ICAI guidance note:

**For futures:** turnover is the **sum of absolute profits and losses** on each trade. A Rs 4,000 profit and a Rs 6,000 loss give a turnover of Rs 10,000 — not the crores of notional value that passed through.

**For options:** the **absolute profit or loss** is counted. Practice on including premium received on sale has varied over the years; this is precisely the point at which you stop reading blogs and speak to a CA.

The reason this matters so much: **turnover is the trigger for a tax audit.** Misunderstand it and you either wrongly believe you need an audit, or — far worse — wrongly believe you do not.

## When a tax audit applies

The broad framework:

- **Turnover up to Rs 2 crore:** generally no audit if you declare profits under the presumptive scheme, or if you declare profit above the presumptive threshold.
- **Digital transactions above 95%:** the audit threshold extends substantially — and F&O, being entirely electronic, generally qualifies.
- **Declaring a loss, or profit below the presumptive rate, while your total income exceeds the basic exemption limit:** **an audit is typically triggered.**

That last case catches an enormous number of loss-making traders who assume that a loss means nothing to report. **A loss is exactly when the compliance burden appears.**

Thresholds and the interaction with the presumptive scheme under Section 44AD move with the Finance Act. Verify the current year with a CA. Do not rely on a figure you read on a forum.

## Expenses you can legitimately deduct

Because F&O is business income, you are taxed on **net profit after expenses**, and the list is broader than most traders use:

- Brokerage, exchange transaction charges, clearing charges
- **STT** (deductible as a business expense, unlike in the capital gains route)
- GST and stamp duty on trades
- Internet and phone bills, apportioned to business use
- Data subscriptions, charting software, research services
- Depreciation on the computer you trade from
- Advisory or professional fees, including your CA
- Interest on money genuinely borrowed for the business

Keep the invoices. An expense you cannot evidence is an expense you do not have.

## Set-off and carry-forward

**Non-speculative F&O loss** can be set off against most other heads of income in the same year — **except salary.** It cannot reduce your salary income.

Whatever remains unabsorbed can be **carried forward for eight assessment years**, to be set off against future business income.

**Speculative loss** (intraday equity) is far more restricted: it can only be set off against speculative gains, and carries forward for four years.

## The rule that costs traders the most money

**You lose the right to carry forward your losses if you do not file your return by the due date.**

Think about what that means for a trader who had a bad year. The loss is real. The shield against eight years of future tax is real. And it evaporates entirely because a form was filed late.

**File on time, especially in a loss year.** It is the single highest-return hour of administrative work available to a trader.

## Which form, and what you will need

**ITR-3** for business income, including F&O.

Gather:

- The **tax P&L statement** from your broker — every major broker generates one, with turnover computed
- Your **contract notes** for the year
- Bank statements for the trading account
- Expense invoices
- **The AIS and Form 26AS** from the income tax portal, reconciled against your broker statement before you file

## The honest advice

F&O taxation is one of the few areas where a Rs 3,000 CA fee routinely saves multiples of itself — in audit exposure, in expenses correctly claimed, and in losses correctly carried forward.

And if the compliance load feels heavy relative to what you actually earned from trading, that is itself information worth sitting with. See [what SEBI''s data says about F&O outcomes](/blog/why-91-percent-fno-traders-lose-money-sebi-data).

**Disclaimer: This article is for general education, reflects the position as understood in July 2026, and is not tax advice. Thresholds, audit rules and the presumptive taxation framework change with each Finance Act. Consult a qualified chartered accountant. Root Nivesh is a SEBI Registered Research Analyst and does not provide tax or accounting advice.**',
 'RootNivesh Research', 10, 'published', '2026-07-05 09:30:00'),

-- 8 — FUNDAMENTALS CLUSTER ----------------------------------------
('pe-ratio-pb-roe-valuation-explained',
 'P/E, P/B and ROE: Reading a Stock''s Valuation Without a Finance Degree',
 'investing',
 'Three ratios do most of the work in a first-pass valuation. What each one actually measures, the traps hidden inside them, and why a low P/E is often a warning rather than a bargain.',
 'https://images.unsplash.com/photo-1543286386-713bdd548da4?w=1200&q=80&auto=format&fit=crop',
 '## Start with the only question that matters

Valuation is not about finding a cheap number. It is about answering one question: **what am I paying, and what am I getting for it?**

Three ratios answer most of that on a first pass. None of them works alone. All of them lie in specific, knowable ways.

## P/E — the price of a rupee of profit

**Price to Earnings = Share Price divided by Earnings Per Share.**

A P/E of 25 means you are paying Rs 25 for every Rs 1 of annual profit the company currently earns. Flip it and you get an earnings yield of 4% — a useful sanity check against what a bond would pay you.

### What P/E is really telling you

**P/E is a measure of expectation, not of value.** A high P/E means the market expects earnings to grow. A low P/E means it does not.

That reframing kills the single most common beginner mistake: **buying a low P/E because it looks cheap.** A stock at 6 times earnings is not on sale. It is being told, by a market of people who have looked at it, that those earnings are about to fall, are of poor quality, or belong to a business in decline.

Sometimes the market is wrong, and that is where value investing lives. But your starting assumption should be that the low number is a verdict, not a discount.

### Where P/E breaks

**Cyclicals invert it.** A commodity producer at the top of its cycle shows record earnings and therefore a *low* P/E — precisely when it is most dangerous. At the bottom of the cycle, earnings collapse and the P/E looks absurdly high — often the best moment to buy. **For cyclicals, a low P/E is a sell signal more often than a buy signal.**

**Loss-making companies have no P/E at all.** The ratio is meaningless with negative earnings.

**One-off items distort it.** An asset sale inflates a year of profit and deflates the P/E, and the business has not changed at all.

**Compare only within an industry.** An IT services P/E and a bank P/E are different currencies. A 30 P/E is expensive for a PSU bank and unremarkable for a consumer brand.

## P/B — the price of a rupee of net assets

**Price to Book = Market Capitalisation divided by Shareholder Equity (net worth).**

A P/B of 1 means you are paying exactly what the accountants say the company''s net assets are worth.

### Where P/B earns its keep

**Banks and financial companies.** For a lender, the balance sheet *is* the business. Book value is a meaningful, comparable number, and P/B is the primary lens.

### Where P/B is nearly useless

**Asset-light businesses.** A software company, a consultancy, a consumer brand — their real assets are code, people and brand, and none of those sit on a balance sheet. A high P/B here tells you almost nothing.

**Old asset bases.** Land bought in 1985 sits at 1985 cost. The book value understates reality, sometimes wildly.

**A P/B below 1 is not automatically a bargain.** It often means the market believes the stated book value is fiction — that the assets are impaired, or the loans will not be repaid.

## ROE — the ratio that separates good businesses from cheap ones

**Return on Equity = Net Profit divided by Shareholder Equity.**

**This is the quality ratio.** It answers: for every rupee shareholders have in this business, how much profit does it generate each year?

An ROE of 20% means the business turns Rs 100 of shareholder capital into Rs 20 of profit annually. That is a genuinely good business. Sustained over a decade, it is a compounding machine.

### The trap inside ROE

**Debt inflates it.** Because equity sits in the denominator, a company can raise its ROE simply by borrowing more and holding less equity. A 25% ROE built on a mountain of debt is not quality — it is leverage wearing quality''s clothes.

**Always read ROE next to the debt-to-equity ratio.** High ROE with low debt is the combination worth hunting. High ROE with high debt is a risk profile, not an achievement.

## Putting the three together

Alone, each ratio misleads. Together, they triangulate:

- **High ROE, low debt, reasonable P/E** — a good business at a fair price. This is the rare, boring, wonderful quadrant.
- **Low P/E, low ROE** — cheap for a reason. The market is usually right.
- **High P/E, high ROE** — a quality business that everyone has already found. The business is fine; the price is the risk.
- **Low P/B, low ROE, in a bank** — often distress rather than opportunity.

This is essentially what the quality and value factors formalise — see [factor investing](/blog/factor-investing-101-value-momentum-and-quality-explained) for how these get turned into systematic strategies.

## What ratios cannot tell you

They are a screen, not a thesis. They will not tell you whether the promoter is honest, whether the moat is eroding, whether the auditor resigned, or whether the growth is real.

For that you have to open the accounts. Start with our guide to [reading a balance sheet](/blog/how-to-read-a-balance-sheet-six-numbers).

**Disclaimer: This article is for education only and is not a recommendation to buy or sell any security. Investments in securities are subject to market risk.**',
 'RootNivesh Research', 9, 'published', '2026-07-04 10:00:00'),

-- 9 ---------------------------------------------------------------
('how-to-read-a-balance-sheet-six-numbers',
 'How to Read a Balance Sheet: Six Numbers That Actually Matter',
 'investing',
 'You do not need to be an accountant to spot a fragile company. Six numbers — debt, cash flow, receivables, inventory, promoter pledge and interest cover — do most of the work.',
 'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?w=1200&q=80&auto=format&fit=crop',
 '## The one identity everything rests on

**Assets = Liabilities + Equity.**

What the company owns equals what it owes plus what belongs to shareholders. Every balance sheet, everywhere, obeys this. It always balances — which is exactly why balancing proves nothing about quality.

The job is not to check the arithmetic. The job is to find the six places where fragility hides.

## 1. Debt — and specifically, debt to equity

**Debt-to-Equity = Total Borrowings divided by Shareholder Equity.**

Below 0.5 is comfortable for most industries. Above 1 demands an explanation. Above 2, outside of financial or heavy infrastructure businesses, is a business whose lenders have more say in its future than its owners do.

**Debt does not kill companies in good times. It kills them in bad ones** — when refinancing gets hard and cash flow dips at the same moment.

Note the exceptions: banks and NBFCs are *supposed* to be leveraged; that is their business model. Utilities and infrastructure carry structurally higher debt against predictable cash flows. **Compare within the industry, never across it.**

## 2. Cash flow from operations — the lie detector

**Profit is an opinion. Cash is a fact.**

Net profit sits in the P&L and is shaped by judgement calls: when to recognise revenue, how fast to depreciate, what to provide for. Cash flow from operations records what actually arrived in the bank.

**The test: does cash flow from operations broadly track net profit over time?**

If a company reports rising profits year after year while operating cash flow stays flat or negative, something is wrong. Either it is selling to customers who do not pay, or it is booking revenue it has not earned. **This single comparison has flagged more accounting disasters than any ratio in finance.**

Look at three to five years, not one. A single bad year can be a genuine working-capital swing.

## 3. Receivables — is anyone actually paying?

**Receivables are sales made but money not yet collected.**

If revenue grows 15% and receivables grow 60%, the company is booking sales to customers who are not paying. That is not growth. That is a warehouse of invoices.

Watch **days sales outstanding** — receivables relative to revenue, expressed in days. A number that keeps climbing means the company is effectively lending to its own customers to keep the growth story alive.

## 4. Inventory — is the product still wanted?

Rising inventory in a business with flat sales means goods are not moving. In fashion, electronics or anything with a shelf life, that inventory is quietly losing value and will eventually be written down — a loss that has already happened but has not been admitted yet.

Inventory growing much faster than revenue, for several periods, is a warning that should send you to the notes.

## 5. Promoter pledging — the one to check first

**Pledged shares are promoter holdings mortgaged to a lender.**

This is, in the Indian market, among the most reliable single red flags available to a retail investor. It means the people running the company needed money and put their own stake up as collateral.

If the stock falls, the lender issues a margin call. If the promoter cannot meet it, the lender sells the pledged shares into the open market — which pushes the price down further, triggering more calls. **This is a self-reinforcing collapse, and it has destroyed shareholders in India repeatedly.**

Pledge data is disclosed quarterly in the shareholding pattern. It takes two minutes to check. A high and, worse, *rising* pledge percentage is reason enough to walk away without further analysis.

## 6. Interest coverage — can it survive a bad year?

**Interest Coverage = EBIT divided by Interest Expense.**

How many times over can the company pay the interest on its debt out of its operating profit?

Below 2 is fragile. Below 1.5, a single weak quarter or a rate rise can push the company into default. Above 5 is comfortable.

This is the ratio that tells you whether the debt on line 1 is a tool or a noose.

## Reading them together

No single number condemns a company. The pattern does.

**Rising debt, flat operating cash flow, ballooning receivables and a growing promoter pledge is not four problems. It is one problem showing up in four places** — a business that is not generating the cash it claims to, and is borrowing to paper over the gap.

Conversely: low debt, operating cash flow that tracks profit, stable receivables and no pledge is a durable business, even if this quarter was dull.

## What to do with this

Pull the last five annual reports — they are free on the company website and the exchange filings. Put these six numbers in a spreadsheet, one column per year.

**You are not looking for a good year. You are looking for a trend.** Five years of a number quietly deteriorating tells you more than any single headline figure, and it is visible to anyone willing to spend an hour.

Then bring in valuation — see [P/E, P/B and ROE explained](/blog/pe-ratio-pb-roe-valuation-explained). A great balance sheet at an insane price is still a bad investment, and a cheap price on a rotting balance sheet is a trap.

**Disclaimer: This article is for education only and is not a recommendation to buy or sell any security. Investments in securities are subject to market risk.**',
 'RootNivesh Research', 10, 'published', '2026-07-03 09:30:00'),

-- 10 --------------------------------------------------------------
('dividend-bonus-split-buyback-difference',
 'Dividend, Bonus, Split and Buyback: What Each One Actually Does to Your Shares',
 'education',
 'A bonus issue does not make you richer. A split does not make the stock cheaper. A buyback might. What corporate actions really do to your holding — and what they signal about management.',
 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&q=80&auto=format&fit=crop',
 '## The idea that clears up all four

Before the definitions, hold on to this: **a corporate action cannot create value out of nothing.**

If a company is worth Rs 1,000 crore this morning, no rearrangement of its shares makes it worth Rs 1,100 crore this afternoon. What changes is how that value is *sliced*, and — sometimes — how much cash leaves the company.

Every confusion about bonuses and splits dissolves once you internalise that. The pizza does not get bigger because you cut it into more pieces.

## Dividend — cash actually leaves the company

**A dividend is a cash payment from the company to shareholders.**

If you hold 100 shares and the company declares Rs 5 per share, you receive Rs 500. Real money, into your bank account.

**But look at what happened to the company:** it just paid out cash it previously held. Its value fell by exactly that amount. On the ex-dividend date, the share price typically opens lower by roughly the dividend.

**You are not richer on the day. You have simply converted a bit of your shareholding into cash** — and, in India, triggered a tax event while doing so, because **dividends are taxed at your slab rate**.

### What a dividend signals

A long, unbroken record of dividends signals a mature business generating genuine cash — and management confident enough to commit to returning it. That confidence is the real information, more than the yield.

But a very high dividend payout can also signal that management **cannot find anything better to do with the money.** For a young company with growth ahead of it, reinvesting beats paying out.

## Bonus issue — the pizza gets more slices

**A bonus issue gives you extra shares free, in proportion to what you hold.**

In a 1:1 bonus, holding 100 shares at Rs 400 becomes **200 shares at Rs 200.**

Your holding was worth Rs 40,000. It is still worth Rs 40,000. **Nothing happened.**

No cash left the company. No value was created. The company capitalised its reserves and issued more shares against them. You own the identical fraction of the identical business.

### Then why do bonuses exist?

**Liquidity and optics.** A lower per-share price makes the stock accessible to more small investors and typically improves trading volume.

**Signalling.** A company issuing a bonus is implicitly saying its reserves are healthy and it expects to sustain earnings across a larger share count. Markets often read that positively — which is why bonus announcements are frequently followed by a price rise.

**That rise is sentiment, not arithmetic.** Do not confuse the two.

## Stock split — the same thing, by a different accounting route

**A split reduces the face value of each share and increases the count proportionally.**

A 1:5 split on a Rs 10 face value share makes it five shares of Rs 2 face value. Hold 100 at Rs 1,000 and you now hold **500 at Rs 200.**

Economically, this is nearly identical to a bonus. **The difference is purely mechanical** — a bonus capitalises reserves and keeps face value intact; a split cuts face value and leaves reserves alone.

**For you as a shareholder, the effect is the same: more shares, proportionally lower price, identical value.**

## Buyback — the only one that can genuinely add per-share value

**In a buyback the company uses its own cash to purchase its own shares from the market, and then extinguishes them.**

This is the one that is different in kind, not just in degree.

The share count falls. **Every remaining share now represents a larger slice of the company.** Earnings per share rises mechanically, even with identical total profits. If you did not tender your shares, your ownership percentage went up without you doing anything.

### But it depends entirely on the price paid

**A buyback at a sensible price creates value for continuing shareholders. A buyback at an inflated price destroys it.**

The company is spending real cash. If it overpays for its own stock, it has burned money exactly as surely as if it had overpaid for an acquisition.

### What a buyback signals

At its best: management believes the stock is undervalued and there is no better use for the cash than buying it. That is a genuinely strong statement, because it puts the company''s money where its mouth is.

At its worst: a way to flatter EPS, or to prop up a sagging share price, or to return cash without committing to a recurring dividend.

**Ask what price they are paying relative to what the business earns.** Our piece on [P/E, P/B and ROE](/blog/pe-ratio-pb-roe-valuation-explained) is the lens for that question.

## The summary table, in words

**Dividend** — cash leaves the company, you get taxed, your total wealth is unchanged on the day, and a steady record signals genuine cash generation.

**Bonus** — nothing leaves, nothing is created, you hold more shares at a lower price, and the signal is management confidence.

**Split** — the same as a bonus by a different mechanism.

**Buyback** — cash leaves, share count shrinks, and continuing shareholders can genuinely gain **if and only if** the price paid was sensible.

## The habit worth building

When a corporate action is announced and the stock jumps, ask the only question that matters:

**Did the business get better, or did the shares just get rearranged?**

Most of the time it is the second one. Knowing the difference is what stops you from paying a premium for a haircut.

**Disclaimer: This article is for education only and is not a recommendation to buy or sell any security. Tax treatment depends on individual circumstances. Investments in securities are subject to market risk.**',
 'RootNivesh Research', 9, 'published', '2026-07-02 10:00:00');
