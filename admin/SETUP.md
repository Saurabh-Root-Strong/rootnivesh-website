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
