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
  target_price    DECIMAL(12,2) DEFAULT NULL,
  stop_loss       DECIMAL(12,2) DEFAULT NULL,
  thesis          TEXT,
  status          ENUM('open','target_hit','stop_hit','closed','cancelled') NOT NULL DEFAULT 'open',
  exit_price      DECIMAL(12,2) DEFAULT NULL,
  exit_at         DATETIME DEFAULT NULL,
  pnl_pct         DECIMAL(6,2) DEFAULT NULL,
  is_public       TINYINT(1) NOT NULL DEFAULT 1,
  shared_to_wa_at DATETIME DEFAULT NULL,
  notes           TEXT,
  INDEX idx_posted_at (posted_at DESC),
  INDEX idx_status (status),
  INDEX idx_call_type (call_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
