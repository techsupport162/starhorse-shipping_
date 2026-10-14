<?php
/**
 * ajax/list.php — returns every career position as JSON, including how
 * many career postings currently use each one. Consumed once by
 * DataTables client-side (search/sort/pagination all handled in-browser
 * — position lists are small).
 */
require_once __DIR__ . '/../../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../../functions/helpers.php';
require_once __DIR__ . '/../../../../functions/career_functions.php';

$positions = get_positions_for_admin();

$rows = array_map(function ($p) {
    return [
        'id'            => (int) $p['id'],
        'name'          => $p['name'],
        'is_active'     => (bool) $p['is_active'],
        'posting_count' => (int) $p['posting_count'],
        'created_at'    => $p['created_at'],
    ];
}, $positions);

json_response(['success' => true, 'data' => $rows]);