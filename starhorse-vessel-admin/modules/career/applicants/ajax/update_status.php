<?php
/**
 * ajax/update_status.php — POST { id, status } → move an applicant
 * through the review workflow (New / Reviewed / Shortlisted / Interview
 * / Accepted / Rejected). Used by both the inline table dropdown and the
 * View modal's status control. Never touches career_postings.status —
 * these two states are intentionally independent (see
 * functions/career_functions.php).
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
    json_error('Invalid applicant ID.', 400);
}

$status = (string) ($input['status'] ?? '');
if (!in_array($status, CAREER_APPLICANT_STATUSES, true)) {
    json_error('Invalid status selected.', 422);
}

if (!get_career_applicant($id)) {
    json_error('Applicant not found.', 404);
}

try {
    set_career_applicant_status($id, $status);
    json_response([
        'success' => true,
        'message' => 'Applicant status updated to "' . ucfirst($status) . '".',
    ]);
} catch (Throwable $e) {
    error_log('[Career Applicants update_status] ' . $e->getMessage());
    json_error('Something went wrong while updating the status. Please try again.', 500);
}