<?php
/**
 * ajax/ports_list.php — returns every port as JSON. Consumed once by
 * DataTables client-side (mirrors modules/faqs/ajax/list.php).
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/contact_functions.php';

$rows = array_map(function ($p) {
    return [
        'id'           => (int) $p['id'],
        'port_name'    => $p['port_name'],
        'phone_number' => $p['phone_number'],
        'status'       => $p['status'],
        'created_at'   => $p['created_at'],
    ];
}, get_ports_for_admin());

json_response(['success' => true, 'data' => $rows]);
