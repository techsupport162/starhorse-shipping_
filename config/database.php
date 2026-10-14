<?php
/**
 * config/database.php
 * -----------------------------------------------------------------------
 * Single shared PDO connection (PostgreSQL) used by BOTH:
 *   - the public website (sections/vessels.php, etc.)
 *   - the admin CMS (starhorse-vessel-admin/)
 *
 * Credential resolution order (first match wins):
 *   1. config/database.local.php  — local dev overrides (git-ignored)
 *   2. .env file in the project root — loaded automatically if present
 *   3. Process environment variables — set in Render / any host dashboard
 *
 * Environment variable names follow the PostgreSQL standard (PG*):
 *   PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD, PGSSLMODE, PGOPTIONS
 * Or a standard connection string:
 *   DATABASE_URL
 * -----------------------------------------------------------------------
 */

// 1. Local-dev credentials file (git-ignored; beats everything else)
if (is_file(__DIR__ . '/database.local.php')) {
    require __DIR__ . '/database.local.php';
}

// 2. .env file loader — reads KEY='value' lines, skips comments/blanks.
//    Only needed locally; on Render the host injects env vars directly.
if (!defined('DB_HOST')) {
    $envFile = __DIR__ . '/../.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t'\"");
            // Only set if not already in the process environment
            if (getenv($key) === false) {
                putenv("$key=$val");
            }
        }
    }
}

// If DATABASE_URL is provided and individual PG* vars are not set, parse it
$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl && filter_var($databaseUrl, FILTER_VALIDATE_URL)) {
    $parsed = parse_url($databaseUrl);
    if (!empty($parsed['host']) && getenv('PGHOST') === false) {
        putenv('PGHOST=' . $parsed['host']);
    }
    if (!empty($parsed['port']) && getenv('PGPORT') === false) {
        putenv('PGPORT=' . $parsed['port']);
    }
    if (!empty($parsed['path']) && getenv('PGDATABASE') === false) {
        putenv('PGDATABASE=' . ltrim($parsed['path'], '/'));
    }
    if (!empty($parsed['user']) && getenv('PGUSER') === false) {
        putenv('PGUSER=' . $parsed['user']);
    }
    if (!empty($parsed['pass']) && getenv('PGPASSWORD') === false) {
        putenv('PGPASSWORD=' . $parsed['pass']);
    }
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $queryParams);
        if (!empty($queryParams['sslmode']) && getenv('PGSSLMODE') === false) {
            putenv('PGSSLMODE=' . $queryParams['sslmode']);
        }
        if (!empty($queryParams['options']) && getenv('PGOPTIONS') === false) {
            putenv('PGOPTIONS=' . $queryParams['options']);
        }
    }
}

// 3. Resolve from environment (set by Render dashboard, or by .env above)
defined('DB_HOST')    || define('DB_HOST',    getenv('PGHOST')     ?: '127.0.0.1');
defined('DB_PORT')    || define('DB_PORT',    getenv('PGPORT')     ?: '5432');
defined('DB_NAME')    || define('DB_NAME',    getenv('PGDATABASE') ?: 'starhorse_cms');
defined('DB_USER')    || define('DB_USER',    getenv('PGUSER')     ?: 'postgres');
defined('DB_PASS')    || define('DB_PASS',    getenv('PGPASSWORD') ?: '');
defined('DB_SSLMODE') || define('DB_SSLMODE', getenv('PGSSLMODE')  ?: 'prefer');
defined('DB_OPTIONS') || define('DB_OPTIONS', getenv('PGOPTIONS')  ?: '');

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

    $dsn = 'pgsql:host=' . DB_HOST
         . ';port='    . DB_PORT
         . ';dbname='  . DB_NAME
         . ';sslmode=' . DB_SSLMODE;

    // Handle Neon SNI / endpoint routing:
    // If PGOPTIONS is provided, or if connecting to a Neon host without options,
    // attach the endpoint ID so older/Windows libpq clients connect without SNI errors.
    $optionsStr = DB_OPTIONS;
    if (empty($optionsStr) && strpos(DB_HOST, '.neon.tech') !== false) {
        $endpoint = preg_replace('/-pooler$/', '', explode('.', DB_HOST)[0]);
        $optionsStr = "endpoint=$endpoint";
    }
    if (!empty($optionsStr)) {
        $trimmed = trim($optionsStr, "'\"");
        $dsn .= ";options='$trimmed'";
    }

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
       // TEMPORARY DEBUG: Print the raw PDO exception directly to screen
        die('Database Connection Error: ' . $e->getMessage() . ' | DSN: ' . $dsn);
    }

    return $pdo;
}
