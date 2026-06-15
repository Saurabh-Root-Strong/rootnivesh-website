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

// Session lifetime guards.
const ADMIN_SESSION_IDLE_MAX     = 2  * 3600;  // 2h since last activity
const ADMIN_SESSION_ABSOLUTE_MAX = 12 * 3600;  // 12h since login, regardless of activity

function admin_is_logged_in() {
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) return false;
    $now = time();
    $loginAt = $_SESSION['admin_login_at']    ?? 0;
    $seenAt  = $_SESSION['admin_last_seen_at'] ?? $loginAt;
    if (($now - $loginAt) > ADMIN_SESSION_ABSOLUTE_MAX || ($now - $seenAt) > ADMIN_SESSION_IDLE_MAX) {
        admin_logout();
        return false;
    }
    $_SESSION['admin_last_seen_at'] = $now;
    return true;
}

function admin_require_login() {
    if (!admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/* ---- CSRF: per-session token, verified on every state-changing POST. ---- */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify() {
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

function admin_login($username, $password) {
    if ($username !== ADMIN_USER) return false;
    if (!password_verify($password, ADMIN_PASS_HASH)) return false;
    session_regenerate_id(true);
    $_SESSION['admin_logged_in']   = true;
    $_SESSION['admin_login_at']    = time();
    $_SESSION['admin_last_seen_at'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32)); // fresh token per login
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
