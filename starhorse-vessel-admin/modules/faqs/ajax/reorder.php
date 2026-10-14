<?php
/**
 * ajax/reorder.php — POST { order: [id, id, id, ...], csrf_token } → persist
 * a new FAQ display order from drag-and-drop in the admin table.
 *
 * Sent as its own lightweight JSON request (not a full form submission)
 * every time a row is dropped into a new position, mirroring how
 * delete.php already takes a small JSON body rather than piggybacking on
 * update.php. Only the ordering changes here — question/answer/status are
 * untouched.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/faq_functions.php';

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

// Every entry must be a valid positive integer ID — reject the whole
// request rather than silently dropping bad entries, since a partial
// order would produce a confusing result.
$orderedIds = [];
foreach ($order as $value) {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        json_error('Invalid FAQ order data.', 400);
    }
    $orderedIds[] = $id;
}

try {
    update_faq_order($orderedIds);
    json_response([
        'success' => true,
        'message' => 'FAQ order updated.',
    ]);
} catch (InvalidArgumentException $e) {
    // A FAQ referenced by the dragged order no longer exists (e.g. deleted
    // in another tab moments ago) — tell the admin plainly so the UI can
    // refresh to the real current state instead of pretending it worked.
    json_error($e->getMessage(), 409);
} catch (Throwable $e) {
    error_log('[FAQs reorder] ' . $e->getMessage());
    json_error('Something went wrong while saving the new order. Please try again.', 500);
}
