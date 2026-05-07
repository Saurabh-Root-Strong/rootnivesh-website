<?php
/* admin/hash-password.php — ONE-TIME helper.
   Visit https://rootnivesh.in/admin/hash-password.php?p=YourPassword
   Copy the bcrypt hash it prints, paste into admin/config.php as ADMIN_PASS_HASH.
   THEN DELETE THIS FILE so nobody else can hit it. */

header('Content-Type: text/plain; charset=utf-8');

$p = $_GET['p'] ?? '';
if ($p === '' || strlen($p) < 8) {
    echo "Pass a password as ?p=YourPassword (min 8 chars).\n";
    echo "Example: /admin/hash-password.php?p=MyVeryStrongPass2026\n";
    exit;
}

$hash = password_hash($p, PASSWORD_BCRYPT);
echo "Bcrypt hash for your password:\n\n";
echo $hash . "\n\n";
echo "Now: paste this hash into admin/config.php as the value of ADMIN_PASS_HASH.\n";
echo "Then DELETE this file (admin/hash-password.php) for security.\n";
