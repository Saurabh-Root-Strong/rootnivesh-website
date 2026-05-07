<?php
/* ============================================================
   admin/config.sample.php — RENAME TO config.php on Hostinger
   ============================================================
   ONE-TIME SETUP:
   1. Copy this file to /admin/config.php on Hostinger File Manager
   2. Fill in DB_NAME, DB_USER, DB_PASS from cPanel -> MySQL Databases
   3. Generate ADMIN_PASS_HASH:
      - Easiest: visit /admin/hash-password.php?p=YourStrongPassword
        (hash-password.php is included; delete it after use)
      - Or PHP CLI: php -r "echo password_hash('YourStrongPassword', PASSWORD_BCRYPT);"
   4. NEVER commit config.php to git (it's in .gitignore)
   ============================================================ */

// MySQL connection (Hostinger usually localhost; check cPanel "MySQL Databases")
define('DB_HOST', 'localhost');
define('DB_NAME', 'CHANGE_ME_database_name');
define('DB_USER', 'CHANGE_ME_db_user');
define('DB_PASS', 'CHANGE_ME_db_password');

// Admin login. Username is fixed to "admin" — change here if you want another.
define('ADMIN_USER', 'admin');

// Bcrypt hash of your admin password. Generate via:
//   php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"
// Or use the included /admin/hash-password.php helper, then delete it.
define('ADMIN_PASS_HASH', '$2y$10$REPLACE_WITH_YOUR_BCRYPT_HASH');

// WhatsApp number (international format, no + or spaces).
// Used for the "Share to WhatsApp" deep links.
define('WA_NUMBER', '917467094575');

// SEBI registration number — appended to every shared call message.
// Replace placeholder once you have your real INH number.
define('SEBI_REG', 'INH000XXXXX');
