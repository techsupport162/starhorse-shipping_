<?php
/**
 * ajax/delete.php — POST id → delete an applicant record (and their
 * uploaded resume file from disk, if any).
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

try {
    $deleted = delete_career_applicant($id);
    if (!$deleted) {
        json_error('Applicant not found.', 404);
    }
    json_response(['success' => true, 'message' => 'Applicant record deleted.']);
} catch (Throwable $e) {
    error_log('[Career Applicants delete] ' . $e->getMessage());
    json_error('Something went wrong while deleting the applicant. Please try again.', 500);
}