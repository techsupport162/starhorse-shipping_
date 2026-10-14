<?php
/**
 * ajax/ports_view.php — GET ?id=123 → full port record. Used to pre-fill
 * the Edit form. Mirrors modules/faqs/ajax/view.php.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/contact_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid port ID.', 400);
}

$port = get_port($id);
if (!$port) {
    json_error('Port not found.', 404);
}

json_response(['success' => true, 'data' => $port]);
