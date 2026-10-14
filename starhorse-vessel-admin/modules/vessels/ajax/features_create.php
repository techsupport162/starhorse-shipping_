<?php
/**
 * ajax/features_create.php — POST → add a new reusable vessel feature
 * (name + Bootstrap Icons class). Called from the Feature Manager modal.
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
$icon = clean_str($_POST['icon'] ?? '');

$fieldErrors = [];

if ($name === '') {
    $fieldErrors['name'] = 'Feature name is required.';
} elseif (mb_strlen($name) > 100) {
    $fieldErrors['name'] = 'Feature name is too long (100 characters max).';
} elseif (feature_name_exists($name)) {
    $fieldErrors['name'] = 'A feature with this name already exists.';
}

// Bootstrap Icons classes are always "bi-" followed by lowercase letters,
// digits, and hyphens — reject anything else rather than trusting free
// text into a class attribute.
if ($icon === '') {
    $fieldErrors['icon'] = 'Please choose an icon.';
} elseif (!preg_match('/^bi-[a-z0-9-]+$/', $icon)) {
    $fieldErrors['icon'] = 'Invalid icon selected.';
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    $id = create_feature($name, $icon);
    json_response([
        'success' => true,
        'message' => 'Feature added successfully.',
        'data'    => ['id' => $id, 'name' => $name, 'icon' => $icon],
    ]);
} catch (Throwable $e) {
    error_log('[Vessel features create] ' . $e->getMessage());
    json_error('Something went wrong while saving the feature. Please try again.', 500);
}