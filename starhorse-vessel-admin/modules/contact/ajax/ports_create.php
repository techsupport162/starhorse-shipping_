<?php
/**
 * ajax/ports_create.php — POST → insert a new port. Called from the Add
 * Port modal. Mirrors modules/faqs/ajax/create.php.
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

$portName = clean_str($_POST['port_name'] ?? '');
$phoneNumber = clean_str($_POST['phone_number'] ?? '');
$status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

// ---- Field-level validation -------------------------------------------
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

if (empty($fieldErrors['port_name']) && port_name_exists($portName)) {
    $fieldErrors['port_name'] = 'This port already exists. Please edit the existing entry instead.';
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    $portId = create_port([
        'port_name'    => $portName,
        'phone_number' => $phoneNumber,
        'status'       => $status,
    ]);

    json_response([
        'success' => true,
        'message' => 'Port added successfully.',
        'id' => $portId,
    ]);
} catch (Throwable $e) {
    error_log('[Contact ports create] ' . $e->getMessage());
    json_error('Something went wrong while saving the port. Please try again.', 500);
}
