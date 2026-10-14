<?php
/**
 * ajax/toggle_status.php — POST { id, status } → quick Publish / Draft
 * (hide) toggle from the table's status action, without opening the full
 * Edit form. Handles both directions in one endpoint (mirrors
 * modules/users/ajax/activate.php). This is entirely separate from an
 * applicant's own status workflow — see functions/career_functions.php.
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
    json_error('Invalid job posting ID.', 400);
}

$posting = get_career_posting($id);
if (!$posting) {
    json_error('Job posting not found.', 404);
}

$status = ($input['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

try {
    set_career_posting_status($id, $status);
    $message = $status === 'published'
        ? 'Job posting published — now visible on the public Career section.'
        : 'Job posting moved to Draft — hidden from the public site.';
    json_response(['success' => true, 'message' => $message]);
} catch (Throwable $e) {
    error_log('[Career Postings toggle_status] ' . $e->getMessage());
    json_error('Something went wrong while updating the status. Please try again.', 500);
}