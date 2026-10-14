<?php
/**
 * ajax/list.php — returns every FAQ as JSON. Consumed once by DataTables
 * client-side (search/sort/pagination all handled in-browser, which is
 * plenty fast for an FAQ-sized dataset).
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/faq_functions.php';

$result = get_faqs_for_admin('', 100000, 0, 'sort_order', 'ASC');

$rows = array_map(function ($f) {
    return [
        'id'         => (int) $f['id'],
        'question'   => $f['question'],
        'answer'     => $f['answer'],
        'status'     => $f['status'],
        'created_at' => $f['created_at'],
    ];
}, $result['rows']);

json_response(['success' => true, 'data' => $rows]);
