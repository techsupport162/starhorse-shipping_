<?php
/**
 * modules/schedules/index.php — placeholder module. Administrator-only (an 'administration module' per the User Management spec), enforced server-side via require_admin().
 * Scaffolded for future expansion; not yet functional.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../functions/helpers.php';

$pageTitle = 'Schedules';
$activeNav = 'schedules';

require __DIR__ . '/../../includes/header.php';
?>

<div class="sh-card">
    <div class="sh-coming-soon">
        <i class="bi bi-calendar3"></i>
        <h2 class="h5">Schedules module coming soon</h2>
        <p class="mb-0">This section is scaffolded and ready for development. It will follow the same pattern as the
            Vessels module: a database table, CRUD functions, and an AJAX-driven admin screen.</p>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>