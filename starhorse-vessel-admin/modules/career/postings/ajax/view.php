<?php
/**
 * ajax/view.php — GET ?id=123 → full career posting record (any status
 * — this is the admin-facing view, not the public one). Used to populate
 * both the read-only View modal and to pre-fill the Edit form.
 */
require_once __DIR__ . '/../../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../../functions/helpers.php';
require_once __DIR__ . '/../../../../functions/career_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid job posting ID.', 400);
}

$posting = get_career_posting($id);
if (!$posting) {
    json_error('Job posting not found.', 404);
}

$base = admin_base_url();

json_response([
    'success' => true,
    'data' => [
        'id'                        => (int) $posting['id'],
        'position_id'               => (int) $posting['position_id'],
        'position_name'             => $posting['position_name'],
        'position_is_active'        => (bool) $posting['position_is_active'],
        'title'                     => $posting['title'],
        'short_description'         => $posting['short_description'],
        'job_description'           => $posting['job_description'],
        'qualifications'            => $posting['qualifications'],
        'responsibilities'          => $posting['responsibilities'],
        'application_instructions' => $posting['application_instructions'],
        'contact_email'             => $posting['contact_email'],
        'recruitment_image'         => $posting['recruitment_image'],
        'recruitment_image_url'     => $posting['recruitment_image']
            ? $base . '/uploads/career/' . rawurlencode($posting['recruitment_image'])
            : null,
        'status'                    => $posting['status'],
        'created_at'                => $posting['created_at'],
        'updated_at'                => $posting['updated_at'],
    ],
]);