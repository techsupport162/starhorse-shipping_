<?php
/**
 * 403.php — shown when require_admin() blocks a non-administrator from
 * a page-level request (AJAX requests get a JSON 403 instead, handled
 * directly in includes/auth.php).
 */

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/functions/helpers.php';

$pageTitle = 'Access Denied';
$activeNav = '';
http_response_code(403);

require __DIR__ . '/includes/header.php';
?>

<div class="sh-card">
    <div class="sh-coming-soon">
        <i class="bi bi-shield-lock text-danger"></i>
        <h2 class="h5">403 — Access Denied</h2>
        <p class="mb-3">Your account does not have permission to view this page. If you believe this is a mistake,
            contact an administrator.</p>
        <a href="<?= admin_base_url() ?>/dashboard.php" class="btn btn-sh-primary btn-sm">
            <i class="bi bi-speedometer2 me-1"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>