<?php
/**
 * includes/auth.php
 * -----------------------------------------------------------------------
 * Starts the admin session and provides require_login(), which every
 * protected page/AJAX endpoint calls first. Kept separate from
 * functions/helpers.php so it can be included very early, before any
 * output, since it may set the session cookie and send redirects.
 * -----------------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'path'     => '/',
        // Never send the admin session cookie over plain HTTP.
        'secure'   => (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** True if an admin is currently logged in. */
function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/** True if the logged-in account has the 'administrator' access level. */
function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['admin_role'] ?? '') === 'administrator';
}

/**
 * Require the logged-in account to be an Administrator. Call this AFTER
 * require_login() on every User Management page/endpoint. Regular 'user'
 * accounts are blocked here even if they guess the URL directly — this
 * is the server-side check; hiding the nav link is a UX nicety, not the
 * actual security boundary.
 */
function require_admin(): void
{
    require_login();

    if (is_admin()) {
        return;
    }

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
        exit;
    }

    header('Location: ' . admin_base_url() . '/403.php');
    exit;
}

/**
 * Redirect to the login page (or return a 401 JSON error for AJAX calls)
 * if no admin is logged in. Call this at the top of every protected file.
 */
function require_login(): void
{
    if (is_logged_in()) {
        return;
    }

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Your session has expired. Please log in again.']);
        exit;
    }

    header('Location: ' . admin_base_url() . '/login.php');
    exit;
}

/** Base URL of the admin app, relative to the web root, without trailing slash. */
function admin_base_url(): string
{
    // Derived from this file's location so it keeps working regardless of
    // how deep a module/ajax script sits (modules/vessels/ajax/create.php, etc).
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if (preg_match('#(.*/starhorse-vessel-admin)#', $scriptDir, $m)) {
        return $m[1];
    }
    return '/starhorse-webbb/starhorse-vessel-admin';
}