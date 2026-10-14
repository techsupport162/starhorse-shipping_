<?php
/**
 * ajax/create.php — POST multipart/form-data → insert a new vessel and
 * any attached images. Called from the Add Vessel modal.
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

$name = clean_str($_POST['name'] ?? '');
$tagline = clean_str($_POST['tagline'] ?? '');
$status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

// ---- Field-level validation ------------------------------------------
// Collected into one object and returned together via json_field_errors()
// so the modal can show every invalid field inline at once, instead of
// stopping at the first problem.
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
    $vesselId = create_vessel($data);

    // Assign features (checkboxes in the Add form) — same helper used by
    // update.php, safe to call with an empty/missing list (no features).
    $featureIds = isset($_POST['feature_ids']) && is_array($_POST['feature_ids']) ? $_POST['feature_ids'] : [];
    set_vessel_features($vesselId, $featureIds);

    $uploadErrors = [];
    if (!empty($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
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
            add_vessel_image($vesselId, $filename, $i);
        }
    }

    json_response([
        'success' => true,
        'message' => 'Vessel added successfully.' . ($uploadErrors ? ' (' . count($uploadErrors) . ' image(s) skipped)' : ''),
        'id' => $vesselId,
        'upload_errors' => $uploadErrors,
    ]);
} catch (Throwable $e) {
    error_log('[Vessels create] ' . $e->getMessage());
    json_error('Something went wrong while saving the vessel. Please try again.', 500);
}