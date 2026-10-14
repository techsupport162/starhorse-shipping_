<?php
/**
 * ajax/view.php — GET ?id=123 → full destination record with a resolved
 * image URL. Used to populate both the read-only View modal and to
 * pre-fill the Edit form.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/routes_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid destination ID.', 400);
}

$destination = get_destination($id);
if (!$destination) {
    json_error('Destination not found.', 404);
}

$base = admin_base_url();
$destination['image_url'] = $destination['destination_image']
    ? $base . '/uploads/routes/' . rawurlencode($destination['destination_image'])
    : null;

json_response(['success' => true, 'data' => $destination]);
