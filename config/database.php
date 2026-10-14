<?php
/**
 * config/database.php
 * -----------------------------------------------------------------------
 * Single shared PDO connection used by BOTH:
 *   - the public website (sections/vessels.php, etc.)
 *   - the admin CMS (starhorse-vessel-admin/)
 *
 * Credential resolution order (first one that sets a value wins):
 *   1. database.local.php  — local XAMPP overrides (git-ignored)
 *   2. Environment variables (DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT)
 *      set in the Render dashboard (or any host's env config)
 *   3. Hard-coded XAMPP defaults below
 * -----------------------------------------------------------------------
 */

if (is_file(__DIR__ . '/database.local.php')) {
    require __DIR__ . '/database.local.php';
}

// Read from environment variables if not already set by database.local.php.
// On Render, set DB_HOST / DB_NAME / DB_USER / DB_PASS / DB_PORT
// in Dashboard → Environment → Environment Variables.
defined('DB_HOST')    || define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');
defined('DB_PORT')    || define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
defined('DB_NAME')    || define('DB_NAME',    getenv('DB_NAME')    ?: 'starhorse_cms');
defined('DB_USER')    || define('DB_USER',    getenv('DB_USER')    ?: 'root');
defined('DB_PASS')    || define('DB_PASS',    getenv('DB_PASS')    ?: '');
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

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

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