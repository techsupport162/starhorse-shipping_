<?php
/**
 * ajax/delete.php — POST id → delete a career posting (and its
 * recruitment image file). Applicants who applied to this posting are
 * kept on record — see delete_career_posting() in career_functions.php.
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

try {
    $deleted = delete_career_posting($id);
    if (!$deleted) {
        json_error('Job posting not found.', 404);
    }
    json_response(['success' => true, 'message' => 'Job posting deleted.']);
} catch (Throwable $e) {
    error_log('[Career Postings delete] ' . $e->getMessage());
    json_error('Something went wrong while deleting the job posting. Please try again.', 500);
}