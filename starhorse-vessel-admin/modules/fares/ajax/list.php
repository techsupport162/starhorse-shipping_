<?php
/**
 * ajax/list.php — returns every fare route as JSON. Consumed once by
 * DataTables client-side (search/sort/pagination all handled in-browser),
 * same pattern as the Vessels module.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/fare_functions.php';

$result = get_fares_for_admin('', 100000, 0, 'created_at', 'DESC');
$base = admin_base_url();

$rows = array_map(function ($f) use ($base) {
    return [
        'id'                     => (int) $f['id'],
        'origin'                 => $f['origin'],
        'destination'            => $f['destination'],
        'status'                 => $f['status'],
        'is_vice_versa'          => (bool) $f['is_vice_versa'],
        'passenger_fare_image'   => $f['passenger_fare_image'],
        'cargo_fare_image'       => $f['cargo_fare_image'],
        'passenger_fare_url'     => image_asset_url($f['passenger_fare_image'], $base . '/uploads/fares/'),
        'cargo_fare_url'         => image_asset_url($f['cargo_fare_image'], $base . '/uploads/fares/'),
        'created_at'             => $f['created_at'],
        'updated_at'             => $f['updated_at'],
    ];
}, $result['rows']);

json_response(['success' => true, 'data' => $rows]);