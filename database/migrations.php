<?php

// Ordered migration ledger. `automatic = false` means the migration requires
// an explicit data-retention decision and is never run by database/migrate.php.
return [
    [
        'id' => '2026_09_04_add_report_image_paths',
        'file' => '2026_09_04_add_report_image_paths.sql',
        'automatic' => true,
        'idempotent' => true,
    ],
    [
        'id' => '2026_09_05_add_farmer_report_defaults',
        'file' => '2026_09_05_add_farmer_report_defaults.sql',
        'automatic' => true,
        'idempotent' => true,
    ],
    [
        'id' => '2026_09_06_drop_plain_password',
        'file' => '2026_09_06_drop_plain_password.sql',
        'automatic' => false,
        'idempotent' => false,
        'reason' => 'Destructive data-erasure migration; apply manually only after confirming no legacy dependency.',
    ],
    [
        'id' => '2026_09_10_add_gps_photo_information',
        'file' => '2026_09_10_add_gps_photo_information.sql',
        'automatic' => true,
        'idempotent' => true,
    ],
    [
        'id' => '2026_09_11_add_admin_role',
        'file' => '2026_09_11_add_admin_role.sql',
        'automatic' => true,
        'idempotent' => true,
    ],
    [
        'id' => '2026_09_12_create_crop_catalog',
        'file' => '2026_09_12_create_crop_catalog.sql',
        'automatic' => true,
        'idempotent' => true,
    ],
];
