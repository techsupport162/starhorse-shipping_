<?php
/**
 * ajax/toggle.php — POST { id, is_active } → quick Enable/Disable a
 * career position without opening the full Edit form. Handles both
 * directions in one endpoint (mirrors modules/users/ajax/activate.php).
 * Disabling never touches career_postings — it only removes the position
 * from the dropdown offered for NEW postings (see get_active_positions()).
 */
require_once __DIR__ . '/../../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../../functions/helpers.php';
require_once __DIR__ . '/../../../../functions/career_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!csrf_verify($input['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$id = filter_var($input['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid position ID.', 400);
}

$position = get_position($id);
if (!$position) {
    json_error('Position not found.', 404);
}

$isActive = ($input['is_active'] ?? '1') !== '0';

try {
    set_career_position_status($id, $isActive);
    $message = $isActive
        ? 'Position enabled — it is now available when creating a posting.'
        : 'Position disabled — it is now hidden from new postings.';
    json_response(['success' => true, 'message' => $message]);
} catch (Throwable $e) {
    error_log('[Career Positions toggle] ' . $e->getMessage());
    json_error('Something went wrong while updating the position status. Please try again.', 500);
}