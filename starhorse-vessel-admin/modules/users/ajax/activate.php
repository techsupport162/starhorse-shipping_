<?php
/**
 * ajax/activate.php — POST { id, status } → set status to 'active' or
 * 'inactive'. Handles both directions in one endpoint (DRY) rather than
 * separate activate/deactivate scripts, since the logic is identical
 * apart from the target value.
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
$status = ($input['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

if (!$id) {
    json_error('Invalid user ID.', 400);
}

$user = get_user($id);
if (!$user) {
    json_error('User not found.', 404);
}

if ($status === 'inactive') {
    if ((int) ($_SESSION['admin_id'] ?? 0) === $id) {
        json_error('You cannot deactivate your own account while logged in.', 422);
    }
    if ($user['user_access'] === 'administrator' && count_active_administrators($id) === 0) {
        json_error('At least one active administrator account must remain.', 422);
    }
}

try {
    set_user_status($id, $status);
    $message = $status === 'active' ? 'User activated successfully.' : 'User deactivated successfully.';
    json_response(['success' => true, 'message' => $message]);
} catch (Throwable $e) {
    error_log('[Users activate] ' . $e->getMessage());
    json_error('Something went wrong while updating the account status. Please try again.', 500);
}
