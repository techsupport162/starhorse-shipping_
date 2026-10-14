<?php
/**
 * ajax/features_list.php — GET → every feature in the master list.
 * Used to populate both the Feature Manager modal's table and the
 * Add/Edit Vessel form's feature checklist.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/vessel_functions.php';

$features = array_map(function ($f) {
    return [
        'id'   => (int) $f['id'],
        'name' => $f['name'],
        'icon' => $f['icon'],
    ];
}, get_all_features());

json_response(['success' => true, 'data' => $features]);