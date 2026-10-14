<?php
/**
 * index.php — admin app entry point. Routes to the dashboard if logged
 * in, otherwise to the login page.
 */
require_once __DIR__ . '/includes/auth.php';

header('Location: ' . admin_base_url() . (is_logged_in() ? '/dashboard.php' : '/login.php'));
exit;