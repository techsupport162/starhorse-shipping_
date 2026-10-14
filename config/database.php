<?php
/**
 * config/database.php
 * -----------------------------------------------------------------------
 * Single shared PDO connection used by BOTH:
 *   - the public website (sections/vessels.php, etc.)
 *   - the admin CMS (starhorse-vessel-admin/)
 *
 * The defaults below are for local XAMPP development. On the live
 * server, copy database.local.example.php to database.local.php (same
 * folder) and put the real credentials there — that file, when present,
 * is loaded first and its values win. Keeps production passwords out of
 * this file, so it can be copied between environments unchanged.
 * -----------------------------------------------------------------------
 */

if (is_file(__DIR__ . '/database.local.php')) {
    require __DIR__ . '/database.local.php';
}

defined('DB_HOST')    || define('DB_HOST', '127.0.0.1');
defined('DB_NAME')    || define('DB_NAME', 'starhorse_cms');
defined('DB_USER')    || define('DB_USER', 'root');
defined('DB_PASS')    || define('DB_PASS', '');       // XAMPP default MySQL root password is empty
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO instance (created once per request).
 *
 * @return PDO
 */
function get_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Never leak credentials or raw exception details to the browser.
        error_log('[Starhorse DB] Connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('A database connection error occurred. Please try again later.');
    }

    return $pdo;
}