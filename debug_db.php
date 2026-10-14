<?php
require_once __DIR__ . '/config/database.php';

// Build DSN manually for debugging
$dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';sslmode=' . DB_SSLMODE;
if (!empty(DB_OPTIONS)) {
    $trimmed = trim(DB_OPTIONS, "'\"");
    $dsn .= ";options='" . $trimmed . "'";
}
error_log('[Debug DB] DSN: ' . $dsn);

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    echo "Connection successful!\n";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    error_log('[Debug DB] Connection failed: ' . $e->getMessage());
}
?>

