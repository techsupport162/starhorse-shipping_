<?php
/**
 * ajax/list.php — returns every career posting as JSON, joined with its
 * position name and applicant count. Consumed once by DataTables
 * client-side (search/sort/pagination handled in-browser).
 */
require_once __DIR__ . '/../../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../../functions/helpers.php';
require_once __DIR__ . '/../../../../functions/career_functions.php';

$result = get_career_postings_for_admin('', 100000, 0, 'created_at', 'DESC');
$base = admin_base_url();

$rows = array_map(function ($p) use ($base) {
    return [
        'id'                     => (int) $p['id'],
        'title'                  => $p['title'],
        'short_description'      => $p['short_description'],
        'status'                 => $p['status'],
        'position_id'            => (int) $p['position_id'],
        'position_name'          => $p['position_name'],
        'position_is_active'     => (bool) $p['position_is_active'],
        'applicant_count'        => (int) $p['applicant_count'],
        'recruitment_image'      => $p['recruitment_image'],
        'recruitment_image_url'  => $p['recruitment_image']
            ? $base . '/uploads/career/' . rawurlencode($p['recruitment_image'])
            : null,
        'created_at'             => $p['created_at'],
        'updated_at'             => $p['updated_at'],
    ];
}, $result['rows']);

json_response(['success' => true, 'data' => $rows]);