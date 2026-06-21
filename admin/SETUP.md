# RootNivesh Admin Panel — One-Time Setup

This panel lets you post daily trading calls and broadcast each one to your WhatsApp group with a single tap. The calls are also stored in MySQL (audit trail) and exposed via `/calls.php` for the public website.

## Step 1 — Create the MySQL database

1. Hostinger cPanel → **MySQL Databases**
2. Create a new database (suggest: `rootnivesh_calls`)
3. Create a new database user, give it **all privileges** on that database
4. Note: **DB name**, **username**, **password**

## Step 2 — Run the schema

1. Hostinger cPanel → **phpMyAdmin**
2. Open your `rootnivesh_calls` database (left sidebar)
3. Click the **SQL** tab
4. Open `admin/schema.sql` (in this repo), copy the whole file, paste, click **Go**
5. You should see a `calls` table appear in the sidebar

## Step 3 — Create config.php

1. In Hostinger File Manager, go to `public_html/admin/`
2. Find `config.sample.php` — make a copy named `config.php`
3. Edit `config.php` and replace these four values:
   - `DB_NAME` — your DB name
   - `DB_USER` — your DB username
   - `DB_PASS` — your DB password
   - `ADMIN_PASS_HASH` — see Step 4

## Step 4 — Generate your admin password hash

Generate the hash **on a machine, never over a URL** — passing the password in a
URL (`?p=...`) writes it in plaintext to the server access logs.

- **Hostinger has SSH / a Terminal?** Run:
  ```
  php -r "echo password_hash('YourStrongPassword', PASSWORD_BCRYPT), PHP_EOL;"
  ```
- **No shell?** Run the same one-liner on any local PHP install, or use a trusted
  offline bcrypt generator, then:

1. Copy the bcrypt hash (starts with `$2y$`)
2. Paste it into `config.php` as the value of `ADMIN_PASS_HASH`

## Step 5 — Test the admin panel

1. Visit `https://rootnivesh.in/admin/login.php`
2. Username: `admin` (unless you changed `ADMIN_USER` in config)
3. Password: whatever you used in step 4
4. You should land on the dashboard. Try posting a test call.

## Step 6 — Test the share-to-WhatsApp flow

1. After posting a test call, click **📤 Share to WhatsApp**
2. WhatsApp should open with a fully formatted message including:
   - 🟢/🔴 emoji
   - Call type, action, symbol
   - Entry/Target/Stop-Loss
   - Your thesis
   - SEBI registration number + standard disclaimer
3. Pick your daily-calls group, hit send. Done.

## Daily workflow (after setup)

1. Login at `/admin/login.php`
2. Fill in the **Post a new call** form
3. Click **Post call** — saved to DB
4. Click **📤 Share to WhatsApp** on the new call card
5. Pick your group → send

When the call hits target or stops out, click **Update status** on that call's card to mark it.

## Price-watch alerts (auto target / stop-loss notifications)

The panel can watch live prices for every **open** call and raise an alert the
moment a target or the stop-loss is breached. The alert shows in the **🔔 Alerts**
tab with a one-tap **Send to WhatsApp** button. The engine never edits a call on
its own — your team confirms each one (so a bad tick can't wrongly close a trade).

Data comes from Yahoo Finance (NSE `.NS` tickers); no API key needed.

### One-time setup

1. **Database** — open phpMyAdmin → your DB → **SQL** tab and run the new block
   from `admin/schema.sql` (the `call_alerts` table). On an **existing** install
   also run the `ALTER TABLE calls ...` lines noted in that file to add
   `last_price`, `last_checked_at`, and `yahoo_symbol`. Fresh installs that paste
   the whole `schema.sql` get everything automatically.

2. **config.php** — add these three lines (copy from `config.sample.php`):
   - `MONITOR_SECRET` — a long random string (the cron URL's password). Generate:
     `php -r "echo bin2hex(random_bytes(16));"`
   - `MONITOR_ENABLED` — `true`
   - `$GLOBALS['YAHOO_SYMBOL_MAP']` — index/ticker overrides (defaults provided)

3. **Cron job** — Hostinger cPanel → **Cron Jobs**. Add one that runs every
   5 minutes (`*/5 * * * *`). Either form works:
   - **URL pinger / wget:**
     ```
     wget -q -O /dev/null "https://rootnivesh.in/admin/monitor.php?key=YOUR_MONITOR_SECRET"
     ```
   - **PHP CLI:**
     ```
     php /home/USERNAME/public_html/admin/monitor.php key=YOUR_MONITOR_SECRET
     ```
   The script self-guards to Mon–Fri 9:15–15:35 IST, so an always-on every-5-min
   cron is fine — it just no-ops outside market hours.

### Getting the right ticker (no config edits needed)

Yahoo prices a call by its NSE ticker. Most symbols auto-resolve (`CONCOR` →
`CONCOR.NS`), but a few names differ from their ticker (`JUBILANT INGREVIA` →
`JUBLINGREA`). Two safety nets handle this — the team never touches config.php:

- **At post time:** in the Post-a-call form, type the symbol and tap
  **✓ Check live price**. It shows e.g. *"✓ AEROFLEX.NS — ₹506"* and pins that
  ticker to the call. If it can't find one, it asks for the exact NSE symbol.
- **If skipped and the engine can't price it:** a yellow **"⚠️ Can't price X"**
  card appears in the Alerts tab with a box to type the NSE symbol. Fix it once
  there and the engine tracks it from the next run.

### Daily flow with alerts

1. Post your calls as usual.
2. When price hits a target or the stop, an alert appears in **🔔 Alerts** (the
   nav badge shows the count; it refreshes every ~45s without reloading).
3. Tap **Send to WhatsApp**, pick your group, send.
4. Go to **Calls** and set that call's progress (1st Target / Stop hit / etc).

### Test it now

Visit (logged in or via the secret) — `force=1` ignores market hours:
`https://rootnivesh.in/admin/monitor.php?key=YOUR_MONITOR_SECRET&force=1`
It returns JSON like `{"ok":true,"open_calls":3,"priced":3,"new_alerts":1,...}`.

## Public API

Anyone can fetch the latest public calls at:
`https://rootnivesh.in/calls.php?limit=10`

Frontend integration to display these on the public Calls page is a separate step (we can do that next).

## Security notes

- `admin/config.php` is gitignored — never committed
- `admin/` folder uses session-based auth on every page
- Password is stored as bcrypt hash, not plaintext
- All form inputs use prepared statements (no SQL injection)
- Cookies are HTTP-only and secure (over HTTPS)

## Troubleshooting

- **"Configuration missing"** error → you didn't create `admin/config.php` (Step 3)
- **"Database connection failed"** → wrong DB credentials in `config.php`
- **Login fails with correct password** → re-check the bcrypt hash you copied (the whole `$2y$10$...` string, no extra spaces)
- **Forgot your admin password** → re-generate a hash with the `php -r` one-liner in Step 4 and replace `ADMIN_PASS_HASH` in `config.php`

## SEBI compliance reminder

Every call shared via the WhatsApp button automatically includes:
- Your SEBI Reg. No. (from `SEBI_REG` constant in config)
- "Investments are subject to market risk" disclaimer

Replace the placeholder `INH000XXXXX` with your real registration number in `config.php` once allotted.
