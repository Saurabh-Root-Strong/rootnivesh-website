<?php
/* admin/db.php — single PDO instance shared across admin/calls.php endpoints */

if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(500);
    echo 'Configuration missing. Copy admin/config.sample.php to admin/config.php and fill in your DB credentials.';
    exit;
}
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            // Don't leak DB credentials in the error message.
            echo 'Database connection failed. Check admin/config.php.';
            exit;
        }
    }
    return $pdo;
}
