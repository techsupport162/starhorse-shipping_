<?php
/**
 * ajax/change_password.php — POST → set a new hashed password for a
 * user. Kept separate from update.php so the Edit modal never touches
 * the password field, per spec.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/user_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid user ID.', 400);
}
if (!get_user($id)) {
    json_error('User not found.', 404);
}

$newPassword = (string) ($_POST['new_password'] ?? '');
$confirm     = (string) ($_POST['confirm_password'] ?? '');

$fieldErrors = [];
if (!is_valid_password($newPassword)) {
    $fieldErrors['new_password'] = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, and a symbol.';
} elseif ($newPassword !== $confirm) {
    $fieldErrors['confirm_password'] = 'Password and confirmation do not match.';
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    change_user_password($id, password_hash($newPassword, PASSWORD_DEFAULT));
    json_response(['success' => true, 'message' => 'Password changed successfully.']);
} catch (Throwable $e) {
    error_log('[Users change_password] ' . $e->getMessage());
    json_error('Something went wrong while changing the password. Please try again.', 500);
}
