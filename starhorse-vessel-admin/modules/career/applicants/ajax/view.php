<?php
/**
 * ajax/view.php — GET ?id=123 → full applicant record, including their
 * submitted message and a resume download link (if a resume was
 * uploaded). Used to populate the read-only View modal.
 */
require_once __DIR__ . '/../../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../../functions/helpers.php';
require_once __DIR__ . '/../../../../functions/career_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid applicant ID.', 400);
}

$applicant = get_career_applicant($id);
if (!$applicant) {
    json_error('Applicant not found.', 404);
}

$base = admin_base_url();

json_response([
    'success' => true,
    'data' => [
        'id'                     => (int) $applicant['id'],
        'posting_id'             => $applicant['posting_id'] !== null ? (int) $applicant['posting_id'] : null,
        'posting_title'          => $applicant['posting_title'],
        'posting_status'         => $applicant['posting_status'],
        'applied_position_title' => $applicant['applied_position_title'],
        'full_name'              => $applicant['full_name'],
        'email'                  => $applicant['email'],
        'contact_number'         => $applicant['contact_number'],
        'address'                => $applicant['address'],
        'message'                => $applicant['message'],
        'status'                 => $applicant['status'],
        'resume_file'            => $applicant['resume_file'],
        'resume_url'             => $applicant['resume_file']
            ? $base . '/modules/career/applicants/ajax/download_resume.php?id=' . (int) $applicant['id']
            : null,
        'applied_at'             => $applicant['applied_at'],
        'updated_at'             => $applicant['updated_at'],
    ],
]);