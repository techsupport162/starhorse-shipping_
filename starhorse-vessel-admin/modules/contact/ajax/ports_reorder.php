<?php
/**
 * ajax/ports_reorder.php — POST { order: [id, id, id, ...], csrf_token }
 * → persist a new port display order from drag-and-drop in the admin
 * table. Mirrors modules/faqs/ajax/reorder.php.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/contact_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!csrf_verify($input['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$order = $input['order'] ?? null;
if (!is_array($order) || empty($order)) {
    json_error('No order was provided.', 400);
}

$orderedIds = [];
foreach ($order as $value) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        json_error('Invalid port order data.', 400);
    }
    $orderedIds[] = $id;
}

try {
    update_port_order($orderedIds);
    json_response([
        'success' => true,
        'message' => 'Port order updated.',
    ]);
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 409);
} catch (Throwable $e) {
    error_log('[Contact ports reorder] ' . $e->getMessage());
    json_error('Something went wrong while saving the new order. Please try again.', 500);
}
