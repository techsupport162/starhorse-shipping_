<?php
/**
 * ajax/list.php — returns every destination as JSON. Consumed once by
 * DataTables client-side (search/sort/pagination all handled in-browser),
 * same pattern as the Vessels and Fares modules.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/routes_functions.php';

$result = get_destinations_for_admin('', 100000, 0, 'sort_order', 'ASC');
$base = admin_base_url();

$rows = array_map(function ($d) use ($base) {
    return [
        'id'                => (int) $d['id'],
        'location_name'     => $d['location_name'],
        'status'            => $d['status'],
        'sort_order'        => (int) $d['sort_order'],
        'destination_image' => $d['destination_image'],
        'image_url'         => $d['destination_image'] ? $base . '/uploads/routes/' . rawurlencode($d['destination_image']) : null,
        'created_at'        => $d['created_at'],
        'updated_at'        => $d['updated_at'],
    ];
}, $result['rows']);

json_response(['success' => true, 'data' => $rows]);
