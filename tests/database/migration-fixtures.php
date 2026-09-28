<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__, 2) . '/conn.php';

$action = $argv[1] ?? '';
$database = $argv[2] ?? '';
if (!preg_match('/^scaims_phase2_test_[0-9]+_[0-9]+$/', $database)) {
    throw new RuntimeException('Invalid Phase 2 test database name.');
}

$conn = scaims_db_mysqli();

if ($action === 'create-empty') {
    $conn->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo 'ready';
    exit;
}

if ($action === 'setup') {
    $conn->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $conn->select_db($database);
    $conn->query("CREATE TABLE admin (
        AdminID VARCHAR(64) NOT NULL PRIMARY KEY,
        AdminName VARCHAR(120) NOT NULL,
        AdminPass VARCHAR(255) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach (['crop_reports', 'damage_reports'] as $table) {
        $conn->query("CREATE TABLE `$table` (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    echo 'ready';
    exit;
}

if ($action === 'baseline-state') {
    $conn->select_db($database);
    $tables = [];
    $counts = [];
    $result = $conn->query('SHOW TABLES');
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
        $counts[$row[0]] = (int) $conn->query("SELECT COUNT(*) AS total FROM `{$row[0]}`")->fetch_assoc()['total'];
    }
    echo json_encode(['tables' => $tables, 'counts' => $counts]);
    exit;
}

if ($action === 'state') {
    $conn->select_db($database);
    $tables = [];
    $result = $conn->query('SHOW TABLES');
    while ($row = $result->fetch_row()) $tables[] = $row[0];

    $columns = [];
    foreach (['admin', 'crop_reports', 'damage_reports', 'farmer_report_defaults', 'crop_catalog', 'schema_migrations'] as $table) {
        $columns[$table] = [];
        $result = $conn->query("SHOW COLUMNS FROM `$table`");
        if ($result) while ($row = $result->fetch_assoc()) $columns[$table][] = $row['Field'];
    }

    $migrations = $conn->query('SELECT migration_id FROM schema_migrations ORDER BY migration_id')->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['tables' => $tables, 'columns' => $columns, 'migrations' => $migrations]);
    exit;
}

if ($action === 'cleanup') {
    $conn->select_db(SCAIMS_DB_NAME);
    $conn->query("DROP DATABASE `$database`");
    echo 'cleaned';
    exit;
}

throw new RuntimeException('Unknown fixture action.');
