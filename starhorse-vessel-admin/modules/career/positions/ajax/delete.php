<?php
/**
 * ajax/delete.php — POST id → delete a career position.
 *
 * Refuses the delete (with a clear, actionable message) if any career
 * posting still references this position — existing postings must never
 * be left pointing at a missing position. The admin should disable the
 * position instead (ajax/toggle.php) if it's still in use but no longer
 * needed for new postings, or reassign/delete those postings first.
 * This mirrors the DB's own ON DELETE RESTRICT foreign key as a
 * friendlier, pre-emptive check rather than surfacing a raw SQL error.
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

if (!get_position($id)) {
    json_error('Position not found.', 404);
}

try {
    $result = delete_career_position($id);

    if (!$result['deleted']) {
        $count = $result['in_use'];
        $noun = $count === 1 ? 'career posting' : 'career postings';
        json_error(
            "This position can't be deleted because it is used by {$count} {$noun}. " .
            'Disable it instead to hide it from new postings, or reassign/delete those postings first.',
            409
        );
    }

    json_response(['success' => true, 'message' => 'Position deleted.']);
} catch (Throwable $e) {
    error_log('[Career Positions delete] ' . $e->getMessage());
    json_error('Something went wrong while deleting the position. Please try again.', 500);
}