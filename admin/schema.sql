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
  entry_price     DECIMAL(12,2) NOT NULL,
  target_price    DECIMAL(12,2) DEFAULT NULL,   -- primary target (T1) for R:R math
  targets         VARCHAR(120)  DEFAULT NULL,   -- full list as posted, e.g. "1030, 1045, 1062"
  stop_loss       DECIMAL(12,2) DEFAULT NULL,   -- primary stop (first) for R:R math
  stop_losses     VARCHAR(120)  DEFAULT NULL,   -- full SL list as posted; display-only
  thesis          TEXT,
  status          ENUM('open','target_hit','stop_hit','closed','cancelled') NOT NULL DEFAULT 'open',
  exit_price      DECIMAL(12,2) DEFAULT NULL,
  exit_at         DATETIME DEFAULT NULL,
  pnl_pct         DECIMAL(6,2) DEFAULT NULL,
  is_public       TINYINT(1) NOT NULL DEFAULT 1,
  shared_to_wa_at DATETIME DEFAULT NULL,
  notes           TEXT,
  created_by      VARCHAR(50) DEFAULT NULL,
  INDEX idx_posted_at (posted_at DESC),
  INDEX idx_status (status),
  INDEX idx_call_type (call_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For EXISTING installs, run these once (ignore "duplicate column" if applied):
--   ALTER TABLE calls ADD COLUMN created_by VARCHAR(50) DEFAULT NULL AFTER notes;
--   ALTER TABLE calls ADD COLUMN targets VARCHAR(120) DEFAULT NULL AFTER target_price;
--   ALTER TABLE calls ADD COLUMN stop_losses VARCHAR(120) DEFAULT NULL AFTER stop_loss;

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
