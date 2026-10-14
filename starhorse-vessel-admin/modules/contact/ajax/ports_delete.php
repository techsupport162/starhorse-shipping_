<?php
/**
 * ajax/ports_delete.php — POST id → delete a port. Mirrors
 * modules/faqs/ajax/delete.php.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/contact_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!csrf_verify($input['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$id = filter_var($input['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid port ID.', 400);
}

try {
    $deleted = delete_port($id);
    if (!$deleted) {
        json_error('Port not found.', 404);
    }
    json_response(['success' => true, 'message' => 'Port deleted.']);
} catch (Throwable $e) {
    error_log('[Contact ports delete] ' . $e->getMessage());
    json_error('Something went wrong while deleting the port. Please try again.', 500);
}
