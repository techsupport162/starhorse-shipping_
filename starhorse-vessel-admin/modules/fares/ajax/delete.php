<?php
/**
 * ajax/delete.php — POST id → delete a fare route and remove its
 * passenger/cargo image files from disk.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/fare_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!csrf_verify($input['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$id = filter_var($input['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid fare ID.', 400);
}

try {
    $deleted = delete_fare($id);
    if (!$deleted) {
        json_error('Fare route not found.', 404);
    }
    json_response(['success' => true, 'message' => 'Fare route deleted.']);
} catch (Throwable $e) {
    error_log('[Fares delete] ' . $e->getMessage());
    json_error('Something went wrong while deleting the fare route. Please try again.', 500);
}