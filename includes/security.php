<?php
/**
 * includes/security.php
 * -----------------------------------------------------------------------
 * Public-site security helpers: HTTPS detection, the hardened session
 * used for the Career form's CSRF token, and the landing page's
 * Content-Security-Policy. Site-wide headers that apply to every file
 * (nosniff, Referrer-Policy, Permissions-Policy, HSTS) live in the root
 * .htaccess instead.
 * -----------------------------------------------------------------------
 */

/** True if the visitor reached us over HTTPS (directly or via a TLS proxy/CDN). */
function sh_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/**
 * Start the session with hardened cookie flags. Guarded so it is safe to
 * call from every entry point (index.php, sections/career.php,
 * ajax/career_apply.php) whether or not a session is already active.
 */
function sh_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'path'     => '/',
        'secure'   => sh_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Send the landing page's Content-Security-Policy. Every allowance below
 * maps to a resource index.php / sections/*.php actually load — update it
 * whenever a third-party resource is added or removed.
 *
 * The one inline <script> in index.php's <head> is allowed by its SHA-256
 * hash; if that script's text changes by even one character, recompute
 * the hash (DevTools prints the expected value in the CSP error).
 */
function sh_send_page_csp(): void
{
    $directives = [
        "default-src 'self'",
        "script-src 'self' https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/ 'sha256-/x7W7R75k8Roq0WaVRQX9blP4OufE5xbAdzklGxsgpw='",
        "style-src 'self' https://fonts.googleapis.com https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/ https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/",
        "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/ https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/",
        "img-src 'self' data: https://res.cloudinary.com",
        "media-src 'self' https://res.cloudinary.com",
        // Google Maps embed in sections/contact.php
        "frame-src https://www.google.com https://maps.google.com",
        // Career form submission (ajax/career_apply.php)
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];
    if (sh_is_https()) {
        $directives[] = 'upgrade-insecure-requests';
    }
    header('Content-Security-Policy: ' . implode('; ', $directives));
}

/**
 * Return $url only if it is an absolute http(s) URL (optionally limited to
 * $allowedHosts), otherwise ''. Used for admin-entered URLs rendered into
 * href/src attributes — htmlspecialchars() alone does not stop a
 * "javascript:" URL from executing.
 */
function sh_safe_url(?string $url, array $allowedHosts = [], bool $httpsOnly = false): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    $parts  = parse_url($url);
    $scheme = strtolower($parts['scheme'] ?? '');
    $host   = strtolower($parts['host'] ?? '');

    $schemeOk = $httpsOnly ? $scheme === 'https' : in_array($scheme, ['http', 'https'], true);
    if (!$schemeOk || $host === '') {
        return '';
    }
    if ($allowedHosts && !in_array($host, $allowedHosts, true)) {
        return '';
    }
    return $url;
}
