-- =============================================================
-- RootNivesh — Calls table schema
-- =============================================================
-- One-time setup. Run this once via Hostinger cPanel -> phpMyAdmin
-- on your site's MySQL database. Steps:
--   1. cPanel -> MySQL Databases -> create a database (e.g. rootnivesh_calls)
--   2. Note the DB name, username, and password
--   3. cPanel -> phpMyAdmin -> open your database -> SQL tab
--   4. Paste this whole file -> Go
--   5. Edit /admin/config.php with the credentials from step 2
--
-- The `calls` table holds every trading call you broadcast: the
-- segment (intraday / swing / longterm / fno), entry/target/stop,
-- thesis text, and a status field so you can mark calls hit / closed.
-- =============================================================

CREATE TABLE IF NOT EXISTS calls (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  posted_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  call_type       ENUM('intraday','swing','longterm','fno','positional') NOT NULL DEFAULT 'intraday',
  action          ENUM('BUY','SELL') NOT NULL DEFAULT 'BUY',
  symbol          VARCHAR(20) NOT NULL,
  company_name    VARCHAR(200) DEFAULT NULL,
  yahoo_symbol    VARCHAR(40)  DEFAULT NULL,    -- confirmed Yahoo ticker (e.g. JUBLINGREA.NS); blank = auto-derive from symbol
  entry_price     DECIMAL(12,2) NOT NULL,
  target_price    DECIMAL(12,2) DEFAULT NULL,   -- primary target (T1) for R:R math
  targets         VARCHAR(120)  DEFAULT NULL,   -- full list as posted, e.g. "1030, 1045, 1062"
  targets_hit     INT NOT NULL  DEFAULT 0,       -- how many targets achieved so far (partial progress)
  stop_loss       DECIMAL(12,2) DEFAULT NULL,   -- primary stop (first) for R:R math
  stop_losses     VARCHAR(120)  DEFAULT NULL,   -- full SL list as posted; display-only
  thesis          TEXT,
  status          ENUM('open','target_hit','stop_hit','closed','cancelled') NOT NULL DEFAULT 'open',
  exit_price      DECIMAL(12,2) DEFAULT NULL,
  exit_at         DATETIME DEFAULT NULL,
  pnl_pct         DECIMAL(6,2) DEFAULT NULL,
  last_price      DECIMAL(12,2) DEFAULT NULL,   -- last live price the monitor cron saw
  last_checked_at DATETIME DEFAULT NULL,         -- when the monitor last polled this call
  is_public       TINYINT(1) NOT NULL DEFAULT 1,
  shared_to_wa_at DATETIME DEFAULT NULL,
  notes           TEXT,
  created_by      VARCHAR(50) DEFAULT NULL,
  INDEX idx_posted_at (posted_at DESC),
  INDEX idx_status (status),
  INDEX idx_call_type (call_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For EXISTING installs, run these once (ignore "duplicate column" if applied):
--   ALTER TABLE calls ADD COLUMN yahoo_symbol VARCHAR(40) DEFAULT NULL AFTER company_name;
--   ALTER TABLE calls ADD COLUMN created_by VARCHAR(50) DEFAULT NULL AFTER notes;
--   ALTER TABLE calls ADD COLUMN targets VARCHAR(120) DEFAULT NULL AFTER target_price;
--   ALTER TABLE calls ADD COLUMN stop_losses VARCHAR(120) DEFAULT NULL AFTER stop_loss;
--   ALTER TABLE calls ADD COLUMN targets_hit INT NOT NULL DEFAULT 0 AFTER targets;

-- =============================================================
-- Team accounts. Each analyst gets their own login so the
-- Performance tab / calls record who posted what. The `owner`
-- role can create and deactivate other users via /admin/users.php.
-- The bootstrap owner in admin/config.php (ADMIN_USER) always
-- works even before this table has any rows.
-- =============================================================
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  pass_hash     VARCHAR(255) NOT NULL,
  display_name  VARCHAR(100) DEFAULT NULL,
  role          ENUM('owner','analyst') NOT NULL DEFAULT 'analyst',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME     DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Blog posts. Each article the team writes from /admin/blog.php.
-- `body` is lite-markdown plain text (## Heading, blank-line paragraphs,
-- **bold**) — the public site parses it into HTML + a TOC. No HTML
-- knowledge needed by the team.
-- =============================================================
-- =============================================================
-- Price-watch ALERTS. The monitor cron (admin/monitor.php) polls
-- live prices for every OPEN call and, when a target or the stop is
-- breached, drops ONE row here. The team sees it in the admin
-- "Alerts" tab, taps "Send to WhatsApp" (1 tap), then confirms the
-- call's progress. The engine never edits the call itself — humans
-- confirm, so a bad tick can't auto-close a position.
--
-- Two snapshot columns on `calls` let the Alerts/Calls views show the
-- last seen price without re-fetching.
-- =============================================================
CREATE TABLE IF NOT EXISTS call_alerts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  call_id       INT NOT NULL,
  symbol        VARCHAR(40) NOT NULL,
  action        ENUM('BUY','SELL') NOT NULL DEFAULT 'BUY',
  kind          ENUM('target_hit','stop_hit','no_price') NOT NULL,
  level_index   INT NOT NULL DEFAULT 0,        -- 1=T1, 2=T2, ...; 0 for the stop / no_price
  level_price   DECIMAL(12,2) DEFAULT NULL,    -- the target/stop value that was crossed
  trigger_price DECIMAL(12,2) DEFAULT NULL,    -- the live price that crossed it
  entry_price   DECIMAL(12,2) DEFAULT NULL,    -- snapshot of the call's entry
  pnl_pct       DECIMAL(6,2)  DEFAULT NULL,    -- % from entry at the trigger price
  status        ENUM('new','sent','dismissed') NOT NULL DEFAULT 'new',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at       DATETIME DEFAULT NULL,
  handled_by    VARCHAR(50) DEFAULT NULL,
  -- Price fingerprint of the call (symbol+action+entry+targets+stops). Dedup
  -- is per SETUP, not per row: an identical re-post never re-alerts, but if
  -- any level changes the fingerprint changes and alerting resumes.
  setup_key     CHAR(32) DEFAULT NULL,
  -- One alert per price-setup + kind + level. Re-polls that see the same breach
  -- are ignored by the engine (INSERT IGNORE on this unique key).
  UNIQUE KEY uniq_setup_kind_level (setup_key, kind, level_index),
  INDEX idx_status (status),
  INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If you created call_alerts BEFORE the 'no_price' kind existed, widen it once:
--   ALTER TABLE call_alerts MODIFY kind ENUM('target_hit','stop_hit','no_price') NOT NULL;
-- If you created call_alerts with the OLD per-row unique key, migrate to the
-- per-setup key once (safe to run; existing test rows keep a NULL setup_key):
--   ALTER TABLE call_alerts ADD COLUMN setup_key CHAR(32) DEFAULT NULL AFTER handled_by;
--   ALTER TABLE call_alerts DROP INDEX uniq_call_kind_level;
--   ALTER TABLE call_alerts ADD UNIQUE KEY uniq_setup_kind_level (setup_key, kind, level_index);

-- Snapshot of the last price the monitor saw, per call (display only).
-- For EXISTING installs run these two once (ignore "duplicate column"):
--   ALTER TABLE calls ADD COLUMN last_price DECIMAL(12,2) DEFAULT NULL AFTER pnl_pct;
--   ALTER TABLE calls ADD COLUMN last_checked_at DATETIME DEFAULT NULL AFTER last_price;

CREATE TABLE IF NOT EXISTS posts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(200) NOT NULL UNIQUE,
  title         VARCHAR(255) NOT NULL,
  category      VARCHAR(50)  NOT NULL DEFAULT 'markets',
  excerpt       VARCHAR(500) DEFAULT NULL,
  cover_image   VARCHAR(500) DEFAULT NULL,   -- image URL
  body          MEDIUMTEXT,
  author        VARCHAR(100) DEFAULT NULL,
  read_minutes  INT          DEFAULT NULL,
  status        ENUM('draft','published') NOT NULL DEFAULT 'published',
  published_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_published (published_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
