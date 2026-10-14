<?php
/**
 * ajax/list.php — returns every applicant as JSON, left-joined with
 * their posting's current title/status. Consumed once by DataTables
 * client-side; the status filter dropdown also runs entirely
 * client-side against this same payload (see applicants.js).
 */
require_once __DIR__ . '/../../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../../functions/helpers.php';
require_once __DIR__ . '/../../../../functions/career_functions.php';

$result = get_career_applicants_for_admin('', 100000, 0, 'applied_at', 'DESC');
$base = admin_base_url();

$rows = array_map(function ($a) use ($base) {
    return [
        'id'                     => (int) $a['id'],
        'posting_id'             => $a['posting_id'] !== null ? (int) $a['posting_id'] : null,
        'posting_title'          => $a['posting_title'],
        'posting_status'         => $a['posting_status'],
        'applied_position_title' => $a['applied_position_title'],
        'full_name'              => $a['full_name'],
        'email'                  => $a['email'],
        'contact_number'         => $a['contact_number'],
        'status'                 => $a['status'],
        'resume_file'            => $a['resume_file'],
        'resume_url'             => $a['resume_file']
            ? $base . '/modules/career/applicants/ajax/download_resume.php?id=' . (int) $a['id']
            : null,
        'applied_at'             => $a['applied_at'],
    ];
}, $result['rows']);

json_response(['success' => true, 'data' => $rows]);