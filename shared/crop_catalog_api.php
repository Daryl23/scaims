<?php

header('Content-Type: application/json');

require_once __DIR__ . '/crop_catalog.php';

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_crop_catalog_table($conn);

    $groups = scaims_get_crop_catalog_groups($conn, false);
    $rows = scaims_get_crop_catalog_rows($conn, false);

    echo json_encode([
        'success' => true,
        'commodities' => scaims_crop_commodity_labels(),
        'groups' => $groups,
        'rows' => $rows,
        'allCrops' => array_values(array_map(static function ($row) {
            return $row['crop_name'];
        }, $rows)),
    ]);

    $conn->close();
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $error->getMessage(),
    ]);
}

