<?php
/**
 * ajax/update.php — POST multipart/form-data → update a vessel's fields,
 * optionally append new images and remove selected existing ones.
 * (Sent as POST rather than PUT because multipart file uploads via PUT
 * aren't reliably supported by PHP's request parsing.)
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/vessel_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid vessel ID.', 400);
}

$existing = get_vessel_with_images($id);
if (!$existing) {
    json_error('Vessel not found.', 404);
}

$name = clean_str($_POST['name'] ?? '');
$tagline = clean_str($_POST['tagline'] ?? '');
$status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

// ---- Field-level validation (see create.php for the same pattern) ----
$fieldErrors = [];

if ($name === '') {
    $fieldErrors['name'] = 'Vessel name is required.';
} elseif (mb_strlen($name) > 150) {
    $fieldErrors['name'] = 'Vessel name is too long (150 characters max).';
}

if (mb_strlen($tagline) > 100) {
    $fieldErrors['tagline'] = 'Carousel label is too long (100 characters max).';
}

$specLabels = [
    'length_m'      => 'Length',
    'breadth_m'     => 'Breadth',
    'depth_m'       => 'Depth',
    'gross_tonnage' => 'Gross Tonnage',
];
foreach ($specLabels as $field => $label) {
    $error = validate_decimal_field($_POST[$field] ?? null, $label);
    if ($error) {
        $fieldErrors[$field] = $error;
    }
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

$data = [
    'name'          => $name,
    'tagline'       => $tagline ?: null,
    'length_m'      => clean_decimal($_POST['length_m'] ?? null),
    'breadth_m'     => clean_decimal($_POST['breadth_m'] ?? null),
    'depth_m'       => clean_decimal($_POST['depth_m'] ?? null),
    'gross_tonnage' => clean_decimal($_POST['gross_tonnage'] ?? null),
    'description'   => clean_str($_POST['description'] ?? '') ?: null,
    'status'        => $status,
];

try {
    update_vessel($id, $data);

    // Assign features (checkboxes in the Edit form) — replaces the full
    // set each save, same as a normal "these are the checked boxes now".
    $featureIds = isset($_POST['feature_ids']) && is_array($_POST['feature_ids']) ? $_POST['feature_ids'] : [];
    set_vessel_features($id, $featureIds);

    // Remove any existing images the user deleted in the edit modal.
    if (!empty($_POST['remove_image_ids'])) {
        $removeIds = json_decode($_POST['remove_image_ids'], true);
        if (is_array($removeIds)) {
            foreach ($removeIds as $imageId) {
                $imageId = filter_var($imageId, FILTER_VALIDATE_INT);
                if ($imageId) {
                    delete_vessel_image($imageId);
                }
            }
        }
    }

    // Apply the admin's drag-reordered / "set as primary" order for the
    // images that remain (after the removals above). Only IDs that still
    // belong to this vessel are honored — see reorder_vessel_images().
    $imageCount = count(get_vessel_image_rows($id));
    if (!empty($_POST['image_order'])) {
        $order = json_decode($_POST['image_order'], true);
        if (is_array($order)) {
            $imageCount = reorder_vessel_images($id, $order);
        }
    }

    // Append any newly uploaded images.
    $uploadErrors = [];
    if (!empty($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
        $startOrder = $imageCount;
        $count = count($_FILES['images']['tmp_name']);
        for ($i = 0; $i < $count; $i++) {
            if (($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = [
                'name'     => $_FILES['images']['name'][$i],
                'type'     => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error'    => $_FILES['images']['error'][$i],
                'size'     => $_FILES['images']['size'][$i],
            ];
            $check = validate_image_upload($file);
            if (!$check['ok']) {
                $uploadErrors[] = $file['name'] . ': ' . $check['error'];
                continue;
            }
            $filename = store_vessel_image($file, $check['ext']);
            add_vessel_image($id, $filename, $startOrder + $i);
        }
    }

    json_response([
        'success' => true,
        'message' => 'Vessel updated successfully.' . ($uploadErrors ? ' (' . count($uploadErrors) . ' image(s) skipped)' : ''),
        'upload_errors' => $uploadErrors,
    ]);
} catch (Throwable $e) {
    error_log('[Vessels update] ' . $e->getMessage());
    json_error('Something went wrong while updating the vessel. Please try again.', 500);
}