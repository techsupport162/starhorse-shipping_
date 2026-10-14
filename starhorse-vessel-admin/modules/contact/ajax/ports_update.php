<?php
/**
 * ajax/ports_update.php — POST → update an existing port's fields.
 * Mirrors modules/faqs/ajax/update.php.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/contact_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid port ID.', 400);
}

$existing = get_port($id);
if (!$existing) {
    json_error('Port not found.', 404);
}

$portName = clean_str($_POST['port_name'] ?? '');
$phoneNumber = clean_str($_POST['phone_number'] ?? '');
$status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

// ---- Field-level validation (see ports_create.php for the same pattern) ----
$fieldErrors = [];

if ($portName === '') {
    $fieldErrors['port_name'] = 'Port name is required.';
} elseif (mb_strlen($portName) > 150) {
    $fieldErrors['port_name'] = 'Port name is too long (150 characters max).';
}

if ($phoneNumber === '') {
    $fieldErrors['phone_number'] = 'Contact number is required.';
} elseif (mb_strlen($phoneNumber) > 30) {
    $fieldErrors['phone_number'] = 'Contact number is too long (30 characters max).';
} elseif (!preg_match('/^[0-9+()\-.\s]{5,30}$/', $phoneNumber)) {
    $fieldErrors['phone_number'] = 'Enter a valid phone number (digits, spaces, +, -, ( ) only).';
}

if (!in_array($_POST['status'] ?? '', ['published', 'draft'], true)) {
    $fieldErrors['status'] = 'Invalid status selected.';
}

if (empty($fieldErrors['port_name']) && port_name_exists($portName, $id)) {
    $fieldErrors['port_name'] = 'Another port already uses this name.';
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    update_port($id, [
        'port_name'    => $portName,
        'phone_number' => $phoneNumber,
        'status'       => $status,
    ]);

    json_response([
        'success' => true,
        'message' => 'Port updated successfully.',
    ]);
} catch (Throwable $e) {
    error_log('[Contact ports update] ' . $e->getMessage());
    json_error('Something went wrong while updating the port. Please try again.', 500);
}
