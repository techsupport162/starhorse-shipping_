<?php
/**
 * includes/sidebar.php
 * -----------------------------------------------------------------------
 * Included by header.php. Uses $activeNav (set by the calling page) to
 * highlight the current section. Only "vessels" is fully wired up today;
 * the rest are placeholder modules ready for future expansion.
 * -----------------------------------------------------------------------
 */
$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2',        'href' => $base . '/dashboard.php',                'soon' => false],
    ['key' => 'vessels',   'label' => 'Vessels',   'icon' => 'bi-water',               'href' => $base . '/modules/vessels/index.php',    'soon' => false],
    ['key' => 'fares',     'label' => 'Fare Management', 'icon' => 'bi-cash-coin',      'href' => $base . '/modules/fares/index.php',      'soon' => false],
    ['key' => 'routes',    'label' => 'Routes Gallery Management', 'icon' => 'bi-images', 'href' => $base . '/modules/routes/index.php',  'soon' => false],
    ['key' => 'faqs',      'label' => 'FAQ Management', 'icon' => 'bi-question-circle', 'href' => $base . '/modules/faqs/index.php',       'soon' => false],
    ['key' => 'contact',   'label' => 'Contact Management', 'icon' => 'bi-geo-alt',      'href' => $base . '/modules/contact/index.php',    'soon' => false],
    ['key' => 'schedules', 'label' => 'Schedules', 'icon' => 'bi-calendar3',           'href' => $base . '/modules/schedules/index.php',  'soon' => true],
    ['key' => 'settings',  'label' => 'Settings',  'icon' => 'bi-gear',                'href' => $base . '/modules/settings/index.php',   'soon' => true],
];

// Career Management is a nav group (own section title) rather than a
// single item, since it has three distinct areas — Job Postings,
// Applicants, and Position Settings. Built out phase by phase:
// Position Settings is live now; Job Postings and Applicants are staged
// with the same "Soon" badge already used elsewhere in this sidebar
// (Schedules/Settings) until their modules land.
$careerNavItems = [
    ['key' => 'career-postings',  'label' => 'Job Postings',       'icon' => 'bi-briefcase',      'href' => $base . '/modules/career/postings/index.php',  'soon' => false],
    ['key' => 'career-applicants', 'label' => 'Applicants',        'icon' => 'bi-people-fill',    'href' => $base . '/modules/career/applicants/index.php', 'soon' => false],
    ['key' => 'career-positions', 'label' => 'Position Settings',  'icon' => 'bi-person-badge',   'href' => $base . '/modules/career/positions/index.php', 'soon' => false],
];
?>
<aside class="sh-sidebar">
    <div class="sh-sidebar__brand">
        <a href="<?= htmlspecialchars($base . '/dashboard.php', ENT_QUOTES, 'UTF-8') ?>" class="sh-logo-link">
            <img src="<?= htmlspecialchars($base . '/assets/images/logo-placeholder.png', ENT_QUOTES, 'UTF-8') ?>"
                alt="Starhorse Shipping Line, Inc." class="sh-logo-full">
            <img src="<?= htmlspecialchars($base . '/assets/images/logo-placeholder.png', ENT_QUOTES, 'UTF-8') ?>"
                alt="Starhorse" class="sh-logo-compact">
        </a>
    </div>

    <nav class="sh-sidebar__nav">
        <div class="sh-nav-section-title">Content</div>
        <a class="sh-nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>"
            href="<?= htmlspecialchars($navItems[0]['href'], ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-speedometer2"></i>
            <span class="sh-nav-label">Dashboard</span>
        </a>
        <a class="sh-nav-item <?= $activeNav === 'vessels' ? 'active' : '' ?>"
            href="<?= htmlspecialchars($navItems[1]['href'], ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-water"></i>
            <span class="sh-nav-label">Vessels</span>
        </a>
        <a class="sh-nav-item <?= $activeNav === 'fares' ? 'active' : '' ?>"
            href="<?= htmlspecialchars($navItems[2]['href'], ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-cash-coin"></i>
            <span class="sh-nav-label">Fare Management</span>
        </a>
        <a class="sh-nav-item <?= $activeNav === 'routes' ? 'active' : '' ?>"
            href="<?= htmlspecialchars($navItems[3]['href'], ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-images"></i>
            <span class="sh-nav-label">Routes Gallery Management</span>
        </a>
        <a class="sh-nav-item <?= $activeNav === 'faqs' ? 'active' : '' ?>"
            href="<?= htmlspecialchars($navItems[4]['href'], ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-question-circle"></i>
            <span class="sh-nav-label">FAQ Management</span>
        </a>
        <a class="sh-nav-item <?= $activeNav === 'contact' ? 'active' : '' ?>"
            href="<?= htmlspecialchars($navItems[5]['href'], ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-geo-alt"></i>
            <span class="sh-nav-label">Contact Management</span>
        </a>

        <div class="sh-nav-section-title">Career Management</div>
        <?php foreach ($careerNavItems as $item): ?>
        <a class="sh-nav-item <?= $activeNav === $item['key'] ? 'active' : '' ?>"
            href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
            <span class="sh-nav-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($item['soon']): ?>
            <span class="sh-badge-soon">Soon</span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>

        <?php /* User Management is only ever rendered for administrators — this
             hides it in the UI, but the real security boundary is
             require_admin() on every page/endpoint inside modules/users/. */ ?>
        <?php if (is_admin()): ?>
        <a class="sh-nav-item <?= $activeNav === 'users' ? 'active' : '' ?>"
            href="<?= htmlspecialchars($base . '/modules/users/index.php', ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-people"></i>
            <span class="sh-nav-label">User Management</span>
        </a>
        <?php endif; ?>

        <?php /* Schedules/Settings are administration modules — same
             admin-only rule as User Management, enforced server-side by
             require_admin() on each page; hidden here as a UX nicety. */ ?>
        <?php if (is_admin()): ?>
        <a class="sh-nav-item <?= $activeNav === 'schedules' ? 'active' : '' ?>"
            href="<?= htmlspecialchars($navItems[6]['href'], ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-calendar3"></i>
            <span class="sh-nav-label">Schedules</span>
            <span class="sh-badge-soon">Soon</span>
        </a>
        <a class="sh-nav-item <?= $activeNav === 'settings' ? 'active' : '' ?>"
            href="<?= htmlspecialchars($navItems[7]['href'], ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-gear"></i>
            <span class="sh-nav-label">Settings</span>
            <span class="sh-badge-soon">Soon</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sh-sidebar__footer">
        &copy; <?= date('Y') ?> Starhorse Shipping Line, Inc.
    </div>
</aside>