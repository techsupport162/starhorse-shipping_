<?php
/**
 * ajax/list.php — returns every vessel as JSON. Consumed once by
 * DataTables client-side (search/sort/pagination all handled in-browser,
 * which is plenty fast for a fleet-sized dataset).
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/vessel_functions.php';

$result = get_vessels_for_admin('', 100000, 0, 'created_at', 'DESC');
$base = admin_base_url();

$rows = array_map(function ($v) use ($base) {
    return [
        'id'            => (int) $v['id'],
        'name'          => $v['name'],
        'length_m'      => $v['length_m'],
        'breadth_m'     => $v['breadth_m'],
        'depth_m'       => $v['depth_m'],
        'gross_tonnage' => $v['gross_tonnage'],
        'status'        => $v['status'],
        'thumbnail_url' => $v['thumbnail'] ? $base . '/uploads/vessels/' . rawurlencode($v['thumbnail']) : null,
    ];
}, $result['rows']);

json_response(['success' => true, 'data' => $rows]);
