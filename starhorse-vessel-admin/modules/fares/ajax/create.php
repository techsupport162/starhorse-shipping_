<?php
/**
 * ajax/create.php — POST multipart/form-data → insert a new fare route,
 * plus its optional passenger/cargo fare images. Called from the Add
 * Fare modal.
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

$origin = clean_str($_POST['origin'] ?? '');
$destination = clean_str($_POST['destination'] ?? '');
$status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
$isViceVersa = !empty($_POST['is_vice_versa']) && $_POST['is_vice_versa'] === '1';

// ---- Field-level validation ------------------------------------------
// Collected into one object and returned together via json_field_errors()
// so the modal can show every invalid field inline at once, instead of
// stopping at the first problem.
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

if (empty($fieldErrors['origin']) && empty($fieldErrors['destination']) && fare_route_exists($origin, $destination)) {
    $fieldErrors['destination'] = 'This route already exists (in this direction, or as the reverse of an '
        . 'existing two-way route). Please edit the existing fare instead.';
}

// ---- Image validation (both optional on create — a route can be added
// first and have its fare images uploaded/replaced later) -------------
$imageFields = [
    'passenger_image' => 'Passenger Fare Image',
    'cargo_image'      => 'Cargo / Vehicle Fare Image',
];
$imageChecks = [];
foreach ($imageFields as $field => $label) {
    if (!empty($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $check = validate_image_upload($_FILES[$field]);
        if (!$check['ok']) {
            $fieldErrors[$field] = $label . ': ' . $check['error'];
        } else {
            $imageChecks[$field] = $check;
        }
    }
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    $fareId = create_fare([
        'origin'         => $origin,
        'destination'    => $destination,
        'status'         => $status,
        'is_vice_versa'  => $isViceVersa,
    ]);

    if (isset($imageChecks['passenger_image'])) {
        $filename = store_uploaded_image($_FILES['passenger_image'], $imageChecks['passenger_image']['ext'], 'fares');
        update_fare_image($fareId, 'passenger_fare_image', $filename);
    }
    if (isset($imageChecks['cargo_image'])) {
        $filename = store_uploaded_image($_FILES['cargo_image'], $imageChecks['cargo_image']['ext'], 'fares');
        update_fare_image($fareId, 'cargo_fare_image', $filename);
    }

    json_response([
        'success' => true,
        'message' => 'Fare route added successfully.',
        'id' => $fareId,
    ]);
} catch (Throwable $e) {
    error_log('[Fares create] ' . $e->getMessage());
    json_error('Something went wrong while saving the fare route. Please try again.', 500);
}