<?php
/**
 * ajax/view.php — GET ?id=123 → full vessel record + gallery images.
 * Used to populate both the read-only View modal and to pre-fill the
 * Edit form.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/vessel_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid vessel ID.', 400);
}

$vessel = get_vessel_with_images($id);
if (!$vessel) {
    json_error('Vessel not found.', 404);
}

$base = admin_base_url();
$vessel['images'] = array_map(function ($img) use ($base) {
    return [
        'id'  => (int) $img['id'],
        'url' => image_asset_url($img['image'], $base . '/uploads/vessels/'),
    ];
}, $vessel['images']);

// 'features' (full objects) + 'feature_ids' (just the IDs) are already
// attached by get_vessel_with_images() — features array powers the View
// modal's read-only chip list, feature_ids powers the Edit form's
// checkbox pre-check.

json_response(['success' => true, 'data' => $vessel]);