<?php
/* ============================================================
   admin/config.sample.php â€” RENAME TO config.php on Hostinger
   ============================================================
   ONE-TIME SETUP:
   1. Copy this file to /admin/config.php on Hostinger File Manager
   2. Fill in DB_NAME, DB_USER, DB_PASS from cPanel -> MySQL Databases
   3. Generate ADMIN_PASS_HASH offline (never over a URL â€” a password in the
      query string lands in the server access logs in plaintext):
        php -r "echo password_hash('YourStrongPassword', PASSWORD_BCRYPT), PHP_EOL;"
   4. NEVER commit config.php to git (it's in .gitignore)
   ============================================================ */

// MySQL connection (Hostinger usually localhost; check cPanel "MySQL Databases")
define('DB_HOST', 'localhost');
define('DB_NAME', 'CHANGE_ME_database_name');
define('DB_USER', 'CHANGE_ME_db_user');
define('DB_PASS', 'CHANGE_ME_db_password');

// Admin login. Username is fixed to "admin" â€” change here if you want another.
define('ADMIN_USER', 'admin');

// Bcrypt hash of your admin password. Generate offline (never over a URL):
//   php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"
define('ADMIN_PASS_HASH', '$2y$10$REPLACE_WITH_YOUR_BCRYPT_HASH');

// WhatsApp number (international format, no + or spaces).
// Used for the "Share to WhatsApp" deep links.
define('WA_NUMBER', '917467094575');

// SEBI registration number â€” appended to every shared call message.
// Replace placeholder once you have your real INH number.
define('SEBI_REG', 'INH000XXXXX');

