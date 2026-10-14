<?php
/**
 * ajax/features_update.php — POST → rename/change the icon of an existing
 * feature. Assignments to vessels are untouched (they reference the
 * feature by ID), so editing a feature updates it everywhere it's used.
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
    json_error('Invalid feature ID.', 400);
}

$name = clean_str($_POST['name'] ?? '');
$icon = clean_str($_POST['icon'] ?? '');

$fieldErrors = [];

if ($name === '') {
    $fieldErrors['name'] = 'Feature name is required.';
} elseif (mb_strlen($name) > 100) {
    $fieldErrors['name'] = 'Feature name is too long (100 characters max).';
} elseif (feature_name_exists($name, $id)) {
    $fieldErrors['name'] = 'A feature with this name already exists.';
}

if ($icon === '') {
    $fieldErrors['icon'] = 'Please choose an icon.';
} elseif (!preg_match('/^bi-[a-z0-9-]+$/', $icon)) {
    $fieldErrors['icon'] = 'Invalid icon selected.';
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    update_feature($id, $name, $icon);
    json_response(['success' => true, 'message' => 'Feature updated successfully.']);
} catch (Throwable $e) {
    error_log('[Vessel features update] ' . $e->getMessage());
    json_error('Something went wrong while updating the feature. Please try again.', 500);
}