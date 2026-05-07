<?php
/* admin/auth.php — session-based admin auth helper.
   Include this at the top of every admin page that requires login.
   It auto-redirects unauthenticated users to login.php. */

require_once __DIR__ . '/db.php';

// Harden session cookie — HTTPS only, JS can't read, no cross-site leak.
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS'])) ini_set('session.cookie_secure', '1');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function admin_is_logged_in() {
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function admin_require_login() {
    if (!admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function admin_login($username, $password) {
    if ($username !== ADMIN_USER) return false;
    if (!password_verify($password, ADMIN_PASS_HASH)) return false;
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_login_at']  = time();
    return true;
}

function admin_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
