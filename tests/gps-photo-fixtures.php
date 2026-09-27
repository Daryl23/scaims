<?php
// CLI-only fixture manager. Test records never enter the application database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/conn.php';
require dirname(__DIR__) . '/shared/report_image_storage.php';
$database = $argv[2] ?? '';
if (!preg_match('/^scaims_gps_test_[0-9]+$/', $database)) throw new RuntimeException('Invalid test database');
$c = scaims_db_mysqli();
if ($argv[1] === 'setup') {
    $c->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $tables = $c->query('SHOW TABLES');
    while ($row = $tables->fetch_row()) {
        $table = $row[0];
        $c->query("CREATE TABLE `$database`.`$table` LIKE `" . SCAIMS_DB_NAME . "`.`$table`");
    }
    $c->select_db($database);
    $c->query("INSERT INTO user (UserID,UName,UPass,UserRole) VALUES (1,'gps-test-farmer','unused','farmer'),(2,'gps-test-other','unused','farmer')");
    $c->query("INSERT INTO farmer (FarmerID,FirstName,Surname,Sex,BirthDate,ContactNo,Address,Barangay,UserID,is_active) VALUES (1,'GPS','Fixture','Male','1980-01-01','09000000000','Test only','adya',1,1),(2,'Other','Fixture','Female','1980-01-01','09000000001','Test only','adya',2,1)");
    $c->query("INSERT INTO admin (AdminID,AdminName,AdminPass) VALUES ('gps-test-admin','GPS Test Personnel','unused')");
    $c->query("INSERT INTO farm_parcels (id,farm_location,farm_area,num_parcels,farm_type,organic,created_by) VALUES (1,'adya','1',1,'Owned','No','gps-test-farmer')");
    $c->query("INSERT INTO parcel_details (id,farm_parcel_id,location,total_area,ownership_type,created_by,parcel_number) VALUES (1,1,'adya','1','Owned','gps-test-farmer',1)");
    $c->query("INSERT INTO crop_details (parcel_detail_id,crop_commodity,crop_type,crop_variety,num_trees,tree_status,created_by) VALUES (1,'fruits','Mango','Carabao',10,'Bearing','gps-test-farmer')");
    echo 'ready';
} elseif ($argv[1] === 'rows') {
    $c->select_db($database);
    $out = [];
    foreach (['crop_reports','damage_reports'] as $table) $out[$table] = $c->query("SELECT * FROM `$table`")->fetch_all(MYSQLI_ASSOC);
    echo json_encode($out);
} elseif ($argv[1] === 'legacy') {
    $c->select_db($database);
    $c->query("INSERT INTO crop_reports (location,crop_commodity,crop_type,crop_variety,date_planted,harvest_date,estimate_harvest,FarmerID,reportDate,created_by,farm_gate_price,crop_status,number_of_trees) VALUES ('adya','fruits','Mango','Carabao','2026-01-01','2026-09-01',50,1,'2026-09-01','gps-test-farmer',50,'Bearing','10')");
    echo $c->insert_id;
} elseif ($argv[1] === 'cleanup') {
    $c->select_db($database);
    foreach (['crop_reports','damage_reports'] as $table) {
        $rows = $c->query("SELECT image_path FROM `$table` WHERE image_path IS NOT NULL");
        while ($row = $rows->fetch_assoc()) scaims_delete_report_image($row['image_path']);
    }
    $sessionDir = __DIR__ . '/gps-photo-results/' . $database . '-sessions';
    foreach (glob($sessionDir . '/sess_*') ?: [] as $file) {
        $session = @unserialize(file_get_contents($file), ['allowed_classes' => false]);
        foreach (($session['pending_report_images'] ?? []) as $pending) scaims_delete_report_image($pending['path'] ?? null);
        unlink($file);
    }
    if (is_dir($sessionDir)) rmdir($sessionDir);
    $c->query("DROP DATABASE `$database`");
    echo 'cleaned';
}
