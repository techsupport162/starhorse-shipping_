<?php
/**
 * ajax/view.php — GET ?id=123 → single user record (no password), used
 * to pre-fill the Edit User modal.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/user_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid user ID.', 400);
}

$user = get_user($id);
if (!$user) {
    json_error('User not found.', 404);
}

$base = admin_base_url();
$user['profile_image_url'] = $user['profile_image'] ? $base . '/uploads/users/' . rawurlencode($user['profile_image']) : null;

json_response(['success' => true, 'data' => $user]);
