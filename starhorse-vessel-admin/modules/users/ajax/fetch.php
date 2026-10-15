<?php
/**
 * ajax/fetch.php — returns every user as JSON for the User Management
 * DataTable. Administrator-only (require_admin()), same enforcement
 * pattern as every other endpoint in this module.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/user_functions.php';

$base = admin_base_url();

$rows = array_map(function ($u) use ($base) {
    return [
        'id'                => (int) $u['id'],
        'profile_image_url' => image_asset_url($u['profile_image'], $base . '/uploads/users/'),
        'username'          => $u['username'],
        'full_name'         => $u['full_name'],
        'email'             => $u['email'],
        'contact_number'    => $u['contact_number'],
        'user_access'       => $u['user_access'],
        'status'            => $u['status'],
    ];
}, get_all_users());

json_response(['success' => true, 'data' => $rows]);
