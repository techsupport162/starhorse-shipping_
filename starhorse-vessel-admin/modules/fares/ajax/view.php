<?php
/**
 * ajax/view.php — GET ?id=123 → full fare record with resolved image
 * URLs. Used to populate both the read-only View modal and to pre-fill
 * the Edit form.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/fare_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid fare ID.', 400);
}

$fare = get_fare($id);
if (!$fare) {
    json_error('Fare route not found.', 404);
}

$fare['is_vice_versa'] = (bool) $fare['is_vice_versa'];

$base = admin_base_url();

$fare['passenger_fare_url'] = image_asset_url($fare['passenger_fare_image'], $base . '/uploads/fares/');
$fare['cargo_fare_url'] = image_asset_url($fare['cargo_fare_image'], $base . '/uploads/fares/');

json_response(['success' => true, 'data' => $fare]);