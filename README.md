# RootNivesh Research — Site Repository

SEBI Registered Research Analyst website live at [rootnivesh.in](https://rootnivesh.in).
Hosted on Hostinger; auto-deploys on push to `main`.

## Stack at a glance

- **Frontend**: Single-page app — vanilla HTML / CSS / JS, no build step
- **Backend**: PHP endpoints (Hostinger LiteSpeed + PHP), MySQL for the admin/calls panel
- **Live data**: NSE / BSE / Yahoo Finance / Groww via server-side proxy with cookie handshake
- **Mobile app**: Capacitor scaffold ready in `/apk` for Play Store

## Folder layout

```
/                                    project root (everything ships to Hostinger)
│
├── index.html                       single-page app — every "page" is a #page-* div inside this file
├── .htaccess                        Apache/LiteSpeed: HTTPS, security headers, SPA routing
├── sitemap.xml                      8 URLs with images
├── robots.txt                       blocks /admin, /apk, all *.php API endpoints
├── google5959...html                Google Search Console verification
├── README.md                        this file
│
├── css/
│   └── style.css                    all styles, ~80 KB
│
├── js/
│   ├── script.js                    all behaviour: live ticker, indices, FII/DII, IPO, plans, etc.
│   └── data.js                      static config: PLANS, IPO_ENDPOINTS, TICKER_SYMBOLS, GST_RATE
│
├── images/                          logos + hero assets
├── videos/                          embedded video assets (Stock-Market-Discussion.mp4)
├── docs/                            (reserved for future research PDFs)
│
├── data/                            *gitignored* — PHP cache files written at runtime
│
├── admin/                           admin panel (call broadcast workflow)
│   ├── login.php · index.php · logout.php · auth.php · db.php
│   ├── schema.sql                   one-time DB schema
│   ├── config.sample.php            template — rename to config.php on Hostinger (gitignored)
│   ├── hash-password.php            one-time bcrypt helper, delete after use
│   ├── admin.css                    admin panel styles
│   └── SETUP.md                     one-time deployment checklist
│
├── apk/                             Android APK build scaffold (Capacitor 6)
│   ├── capacitor.config.json · package.json
│   ├── www/index.html               offline fallback
│   ├── icons/                       icon prep folder + recipe
│   ├── README.md                    laptop setup walkthrough
│   └── playstore-listing.md         Play Console copy-paste templates
│
└── (PHP endpoints — all at root, all called from JS)
    ├── proxy.php                    generic NSE / Yahoo / Chittorgarh proxy (whitelisted hosts)
    ├── indices.php                  Nifty 50 + BSE Sensex (NSE/BSE direct, cookie handshake)
    ├── gainers-losers.php           Nifty 50 top movers (NSE)
    ├── fii-dii.php                  FII/DII fallback (NSE)
    ├── fii-dii-history.php          FII/DII primary (Groww 30-day series)
    ├── ipo.php                      IPO tabs Open / Upcoming / Closed (NSE, cookie handshake)
    ├── calls.php                    public read API for the admin/calls DB
    └── contact.php                  contact-form mailer
```

## Live data feeds — what calls what

| Widget on the site | Calls (frontend) | Calls (server upstream) | Refresh |
|---|---|---|---|
| Top live ticker bar | `/proxy.php?url=...` | Yahoo Finance | 60s |
| NIFTY 50 / BSE SENSEX tiles | `/indices.php` | NSE direct → BSE direct → Yahoo | 60s |
| Top Gainers / Losers | `/gainers-losers.php` | NSE | 60s |
| FII / DII tiles + 30-day table | `/fii-dii-history.php` (primary), `/fii-dii.php` (fallback) | Groww, NSE | 60s |
| IPO Open / Upcoming / Closed | `/ipo.php?tab=...` | NSE (cookie handshake) | 60s on active tab |
| IPO "About Company" cell | `/proxy.php?url=...` | NSE quote-equity → Chittorgarh | one-time, 24h localStorage |
| Allotment Status tab | (no fetch — direct outbound links) | BSE / NSE official portals | n/a |
| (Future) Latest Calls feed | `/calls.php` | MySQL `calls` table | TBD |

All polling is gated to **Mon–Fri 09:00–15:45 IST** (the extra 15 min past close is to capture the post-auction official close).

## Admin / WhatsApp workflow

After one-time setup (see [admin/SETUP.md](admin/SETUP.md)):

1. Log in at `/admin/login.php`
2. Post a new call from the form — saved to MySQL
3. Click **📤 Share to WhatsApp** on the new call's row → WhatsApp opens with auto-formatted message including SEBI Reg + standard disclaimer
4. Pick your group → send

Status updates (target hit / stop hit / closed) tracked per call. PnL% computed automatically from entry vs exit.

## Mobile app

See [apk/README.md](apk/README.md) — Capacitor wraps the live site, so every push to `main` auto-flows to the app without rebuilding the APK. Rebuild only needed when you change icon, splash, version, or add a native plugin.

## SEBI compliance

Every page footer + every shared call carries:
- SEBI Reg. No.: **INH000XXXXX** — replace with real INH number once allotted
- Standard "Investments are subject to market risk" disclaimer
- Conflict-of-interest disclosure path (per SEBI RA Regulations, 2014)

## Local dev

There is no build step. To preview locally:

```bash
# Any local HTTP server. PHP needs a real host to test (or `php -S localhost:8080`).
php -S localhost:8080
# Open http://localhost:8080
```

Note: live data feeds (`indices.php`, `ipo.php`, etc.) need network access to reach NSE/BSE — they'll fall back to cached / stale responses if those upstreams are unreachable.

## Deployment

- **Pushing to `main`** triggers Hostinger to pull the repo
- **CSS / JS changes** require a cache-buster bump in `index.html` (the `?v=` query string on `style.css` and `script.js`)
- **PHP changes** are picked up immediately (no build, no cache-buster needed)
- **Server-side cache files** (`data/*.json`) hold per-endpoint TTLs from 60s (live market) to 1 hour (off-hours)

## Memory file (Claude Code)

`MEMORY.md` and the `memory/` folder are local-only context for working with Claude Code on this repo. They do not get deployed (gitignored via `.claude/`).
