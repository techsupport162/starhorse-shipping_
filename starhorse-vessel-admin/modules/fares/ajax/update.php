<?php
/**
 * ajax/update.php — POST multipart/form-data → update a fare route's
 * fields, and independently keep / replace / remove each of its two
 * images. Sent as POST rather than PUT for the same reason as Vessels:
 * multipart file uploads via PUT aren't reliably supported by PHP.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/fare_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid fare ID.', 400);
}

$existing = get_fare($id);
if (!$existing) {
    json_error('Fare route not found.', 404);
}

$origin = clean_str($_POST['origin'] ?? '');
$destination = clean_str($_POST['destination'] ?? '');
$status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
$isViceVersa = !empty($_POST['is_vice_versa']) && $_POST['is_vice_versa'] === '1';

// ---- Field-level validation (see create.php for the same pattern) ----
$fieldErrors = [];

if ($origin === '') {
    $fieldErrors['origin'] = 'Origin (From) is required.';
} elseif (mb_strlen($origin) > 100) {
    $fieldErrors['origin'] = 'Origin is too long (100 characters max).';
}

if ($destination === '') {
    $fieldErrors['destination'] = 'Destination (To) is required.';
} elseif (mb_strlen($destination) > 100) {
    $fieldErrors['destination'] = 'Destination is too long (100 characters max).';
}

if ($origin !== '' && $destination !== '' && mb_strtolower($origin) === mb_strtolower($destination)) {
    $fieldErrors['destination'] = 'Origin and destination cannot be the same.';
}

if (empty($fieldErrors['origin']) && empty($fieldErrors['destination']) && fare_route_exists($origin, $destination, $id)) {
    $fieldErrors['destination'] = 'Another fare route already uses this origin and destination (in this '
        . 'direction, or as the reverse of an existing two-way route).';
}

// ---- Image validation for any newly-uploaded replacement files -------
$imageFields = [
    'passenger_image' => ['label' => 'Passenger Fare Image', 'column' => 'passenger_fare_image'],
    'cargo_image'      => ['label' => 'Cargo / Vehicle Fare Image', 'column' => 'cargo_fare_image'],
];
$imageChecks = [];
foreach ($imageFields as $field => $meta) {
    if (!empty($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $check = validate_image_upload($_FILES[$field]);
        if (!$check['ok']) {
            $fieldErrors[$field] = $meta['label'] . ': ' . $check['error'];
        } else {
            $imageChecks[$field] = $check;
        }
    }
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    update_fare($id, [
        'origin'         => $origin,
        'destination'    => $destination,
        'status'         => $status,
        'is_vice_versa'  => $isViceVersa,
    ]);

    // Each image is independently: replaced (new file uploaded), removed
    // (explicit "remove" flag sent, no new file), or left untouched.
    foreach ($imageFields as $field => $meta) {
        $column = $meta['column'];
        $removeFlag = !empty($_POST['remove_' . $field]) && $_POST['remove_' . $field] === '1';

        if (isset($imageChecks[$field])) {
            // Replace: store the new file, delete the old one, update the column.
            $newFilename = store_uploaded_image($_FILES[$field], $imageChecks[$field]['ext'], 'fares');
            if (!empty($existing[$column])) {
                delete_uploaded_image_file($existing[$column], 'fares');
            }
            update_fare_image($id, $column, $newFilename);
        } elseif ($removeFlag) {
            // Remove: delete the old file (if any) and clear the column.
            if (!empty($existing[$column])) {
                delete_uploaded_image_file($existing[$column], 'fares');
            }
            update_fare_image($id, $column, null);
        }
        // else: no change requested for this image.
    }

    json_response([
        'success' => true,
        'message' => 'Fare route updated successfully.',
    ]);
} catch (Throwable $e) {
    error_log('[Fares update] ' . $e->getMessage());
    json_error('Something went wrong while updating the fare route. Please try again.', 500);
}