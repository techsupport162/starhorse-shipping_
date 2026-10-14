<?php
/**
 * ajax/delete.php — POST id → permanently delete a user account and its
 * profile image file. Administrator-only, with two safety checks: you
 * can't delete your own logged-in account, and you can't delete the
 * last remaining active administrator.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/user_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!csrf_verify($input['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$id = filter_var($input['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid user ID.', 400);
}

$user = get_user($id);
if (!$user) {
    json_error('User not found.', 404);
}

if ((int) ($_SESSION['admin_id'] ?? 0) === $id) {
    json_error('You cannot delete your own account while logged in.', 422);
}

if ($user['user_access'] === 'administrator' && count_active_administrators($id) === 0) {
    json_error('At least one active administrator account must remain.', 422);
}

try {
    $deleted = delete_user($id);
    if (!$deleted) {
        json_error('User not found.', 404);
    }
    if (!empty($user['profile_image'])) {
        delete_uploaded_image_file($user['profile_image'], 'users');
    }
    json_response(['success' => true, 'message' => 'User deleted successfully.']);
} catch (Throwable $e) {
    error_log('[Users delete] ' . $e->getMessage());
    json_error('Something went wrong while deleting the user. Please try again.', 500);
}
