<?php
/**
 * includes/header.php
 * -----------------------------------------------------------------------
 * Shared admin layout head + shell opening markup. The including page
 * must set $pageTitle before requiring this file, and should require
 * includes/auth.php + call require_login() even earlier (before any
 * output) if the page is protected.
 * -----------------------------------------------------------------------
 */

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}
$activeNav = $activeNav ?? '';
$base = admin_base_url();
require_once __DIR__ . '/../functions/helpers.php';
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'administrator';
$adminAvatar = $_SESSION['admin_avatar'] ?? null;
$adminAvatarUrl = image_asset_url($adminAvatar, $base . '/uploads/users/');
$flash = get_flash();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> · Starhorse Admin</title>
    <link rel="icon" type="image/x-icon" href="<?= $base ?>/../assets/img/starhorse-logo-wname.svg">

    <!-- Bootstrap 5 + Icons (matches public site version) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- DataTables (+ Responsive extension, so responsive:true actually collapses columns on narrow screens) -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

    <!-- Admin theme -->
    <link href="<?= $base ?>/assets/css/admin.css" rel="stylesheet">
</head>

<body class="sh-admin-body">
    <div class="sh-shell">

        <?php require __DIR__ . '/sidebar.php'; ?>

        <div class="sh-main">
            <header class="sh-topbar">
                <div class="sh-topbar__left">
                    <button class="sh-sidebar-toggle d-none d-lg-flex" id="shSidebarToggle" type="button"
                        aria-label="Toggle sidebar">
                        <i class="bi bi-layout-sidebar-inset"></i>
                    </button>
                    <button class="sh-sidebar-toggle d-lg-none" id="shMobileToggle" type="button"
                        aria-label="Open menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="sh-page-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
                <div class="sh-topbar__right">
                    <a href="<?= $base ?>/../index.php" target="_blank" class="btn-sh-outline btn btn-sm"
                        title="View public site">
                        <i class="bi bi-box-arrow-up-right me-1"></i> View Site
                    </a>
                    <div class="dropdown">
                        <button class="sh-user-chip border-0" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <?php if ($adminAvatarUrl): ?>
                            <img src="<?= htmlspecialchars($adminAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" class="sh-avatar"
                                style="object-fit:cover;" alt="">
                            <?php else: ?>
                            <span
                                class="sh-avatar"><?= htmlspecialchars(strtoupper(substr($adminName, 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <span class="text-start">
                                <span
                                    class="sh-user-name d-block"><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></span>
                                <span
                                    class="sh-user-role"><?= htmlspecialchars(ucfirst($adminRole), ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= $base ?>/logout.php"><i
                                        class="bi bi-box-arrow-right me-2"></i>Log out</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="sh-content">
                <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> sh-flash-alert"
                    role="alert">
                    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>