<?php
/**
 * dashboard.php — CMS landing page. Not vessel-specific: shows overall
 * site stats and quick links so it reads as a real CMS home, not a
 * single-module tool.
 */
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/vessel_functions.php';
require_once __DIR__ . '/functions/user_functions.php';
require_once __DIR__ . '/functions/fare_functions.php';
require_once __DIR__ . '/functions/faq_functions.php';
require_once __DIR__ . '/functions/routes_functions.php';

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$vesselCount = count_all_vessels();
$userCount = count_all_users();
$fareCount = count_all_fares();
$faqCount = count_all_faqs();
$destinationCount = count_all_destinations();

require __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="sh-card sh-stat-card">
            <span class="sh-stat-icon" style="background: var(--sh-primary);"><i class="bi bi-water"></i></span>
            <div>
                <div class="sh-stat-value"><?= (int) $vesselCount ?></div>
                <div class="sh-stat-label">Vessels in Fleet</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="sh-card sh-stat-card">
            <span class="sh-stat-icon" style="background: var(--sh-navy);"><i class="bi bi-cash-coin"></i></span>
            <div>
                <div class="sh-stat-value"><?= (int) $fareCount ?></div>
                <div class="sh-stat-label">Fare Routes</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="sh-card sh-stat-card">
            <span class="sh-stat-icon" style="background: var(--sh-teal);"><i class="bi bi-question-circle"></i></span>
            <div>
                <div class="sh-stat-value"><?= (int) $faqCount ?></div>
                <div class="sh-stat-label">FAQs</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="sh-card sh-stat-card">
            <span class="sh-stat-icon" style="background: var(--sh-gold);"><i class="bi bi-people"></i></span>
            <div>
                <div class="sh-stat-value"><?= (int) $userCount ?></div>
                <div class="sh-stat-label">System Users</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="sh-card sh-stat-card">
            <span class="sh-stat-icon" style="background: var(--bs-primary);"><i class="bi bi-signpost-2"></i></span>
            <div>
                <div class="sh-stat-value"><?= (int) $destinationCount ?></div>
                <div class="sh-stat-label">Ports Served</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="sh-card sh-stat-card">
            <span class="sh-stat-icon" style="background: var(--sh-navy-deep);"><i class="bi bi-calendar3"></i></span>
            <div>
                <div class="sh-stat-value">0</div>
                <div class="sh-stat-label">Schedules</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="sh-card h-100">
            <div class="sh-card__header">
                <h2 class="sh-card__title">Welcome back,
                    <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></h2>
            </div>
            <div class="sh-card__body">
                <p class="text-muted mb-3">This is your CMS home base. Vessels, Fare Management, FAQ Management,
                    Routes Gallery Management, and User Management are fully functional today — manage your fleet,
                    fares, FAQs, port logos, and staff accounts right from here. Other sections below are
                    staged for future rollout.</p>
                <a href="modules/vessels/index.php" class="btn btn-sh-primary">
                    <i class="bi bi-water me-1"></i> Manage Vessels
                </a>
                <a href="modules/fares/index.php" class="btn btn-sh-primary ms-2">
                    <i class="bi bi-cash-coin me-1"></i> Manage Fares
                </a>
                <a href="modules/faqs/index.php" class="btn btn-sh-primary ms-2">
                    <i class="bi bi-question-circle me-1"></i> Manage FAQs
                </a>
                <a href="modules/routes/index.php" class="btn btn-sh-primary ms-2">
                    <i class="bi bi-signpost-2 me-1"></i> Manage Routes
                </a>
                <?php if (is_admin()): ?>
                <a href="modules/users/index.php" class="btn btn-sh-outline ms-2">
                    <i class="bi bi-people me-1"></i> Manage Users
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="sh-card h-100">
            <div class="sh-card__header">
                <h2 class="sh-card__title">Coming Up</h2>
            </div>
            <div class="sh-card__body">
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2"><i class="bi bi-calendar3 me-2 text-muted"></i>Route Schedules module</li>
                    <li class="mb-0"><i class="bi bi-gear me-2 text-muted"></i>Site Settings</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>