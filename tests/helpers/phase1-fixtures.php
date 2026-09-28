<?php

// CLI-only fixture manager for Phase 1 characterization tests.
// It clones table structure from the configured development database but writes
// only to a uniquely named disposable database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__, 2) . '/conn.php';
require dirname(__DIR__, 2) . '/shared/report_image_storage.php';

$action = $argv[1] ?? '';
$database = $argv[2] ?? '';
if (!preg_match('/^scaims_phase1_test_[0-9]+_[0-9]+$/', $database)) {
    throw new RuntimeException('Invalid Phase 1 test database name.');
}

function phase1_query(mysqli $conn, string $sql): void
{
    if (!$conn->query($sql)) {
        throw new RuntimeException($conn->error . ' SQL: ' . $sql);
    }
}

function phase1_rows(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException($conn->error . ' SQL: ' . $sql);
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

function phase1_cleanup_sessions(string $database): void
{
    $sessionDir = dirname(__DIR__) . '/phase1-results/' . $database . '-sessions';
    foreach (glob($sessionDir . '/sess_*') ?: [] as $file) {
        $session = @unserialize((string) file_get_contents($file), ['allowed_classes' => false]);
        if (is_array($session)) {
            foreach (($session['pending_report_images'] ?? []) as $pending) {
                scaims_delete_report_image($pending['path'] ?? null);
            }
            foreach (($session['phase1_created_files'] ?? []) as $path) {
                scaims_delete_report_image($path);
            }
        }
        @unlink($file);
    }
    if (is_dir($sessionDir)) {
        @rmdir($sessionDir);
    }
}

$conn = scaims_db_mysqli();

if ($action === 'setup') {
    phase1_query($conn, "CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $tables = $conn->query('SHOW TABLES');
    while ($row = $tables->fetch_row()) {
        $table = $row[0];
        phase1_query($conn, "CREATE TABLE `$database`.`$table` LIKE `" . SCAIMS_DB_NAME . "`.`$table`");
    }

    $conn->select_db($database);
    $password = password_hash('SCAIMS-Test-Only#2026', PASSWORD_DEFAULT);
    $passwordSql = $conn->real_escape_string($password);

    phase1_query($conn, "INSERT INTO user (UserID,UName,UPass,UserRole,creationDate,ProfileImage,plain_password) VALUES
        (1001,'TEST-FARMER-001','$passwordSql','farmer',NOW(),NULL,NULL),
        (1002,'TEST-FARMER-002','$passwordSql','farmer',NOW(),NULL,NULL),
        (1003,'TEST-FARMER-INACTIVE','$passwordSql','farmer',NOW(),NULL,NULL)");

    phase1_query($conn, "INSERT INTO farmer
        (FarmerID,FirstName,Surname,MiddleName,Suffix,Sex,BirthDate,ContactNo,Address,Barangay,registration_date,UserID,is_active,RSBSA_Number) VALUES
        (2001,'Test','Farmer One','Phase',NULL,'Male','1990-01-01','09170000001','Phase 1 address','adya',CURDATE(),1001,1,'10-10-10-001-000001'),
        (2002,'Test','Farmer Two','Phase',NULL,'Female','1991-02-02','09170000002','Other owner address','mataas na kahoy',CURDATE(),1002,1,'10-10-10-002-000002'),
        (2003,'Test','Inactive Farmer','Phase',NULL,'Male','1992-03-03','09170000003','Inactive address','adya',CURDATE(),1003,0,'10-10-10-003-000003')");

    phase1_query($conn, "INSERT INTO admin (AdminID,AdminName,AdminPass,AdminRole,is_active) VALUES
        ('TEST-ADMIN-001','TEST-ADMIN-001','$passwordSql','admin',1),
        ('TEST-SUPERADMIN-001','TEST-SUPERADMIN-001','$passwordSql','super_admin',1),
        ('TEST-ADMIN-INACTIVE','TEST-ADMIN-INACTIVE','$passwordSql','admin',0)");

    phase1_query($conn, "INSERT INTO farm_parcels
        (id,farm_location,farm_area,num_parcels,farm_type,organic,created_by) VALUES
        (3001,'adya',1.2500,1,'Owned','No','TEST-FARMER-001'),
        (3002,'mataas na kahoy',2.5000,1,'Leased','Yes','TEST-FARMER-002')");
    phase1_query($conn, "INSERT INTO parcel_details
        (id,farm_parcel_id,location,total_area,ownership_type,created_by,parcel_number) VALUES
        (4001,3001,'adya','1.2500','Registered Owner','TEST-FARMER-001',1),
        (4002,3002,'mataas na kahoy','2.5000','Tenant','TEST-FARMER-002',1)");
    phase1_query($conn, "INSERT INTO crop_details
        (id,parcel_detail_id,crop_commodity,crop_type,crop_variety,num_trees,tree_status,created_by) VALUES
        (5001,4001,'fruits','Mango','Phase One Own Variety',10,'Bearing','TEST-FARMER-001'),
        (5002,4002,'fruits','Mango','Other Secret Variety',20,'Bearing','TEST-FARMER-002')");

    phase1_query($conn, "INSERT INTO crop_reports
        (id,location,crop_commodity,crop_type,crop_variety,date_planted,harvest_date,estimate_harvest,FarmerID,reportDate,created_by,farm_gate_price,crop_status,number_of_trees,latitude,longitude,image_path) VALUES
        (6001,'adya','fruits','Mango','Phase One Own Harvest','2026-01-01','2026-09-01',50,2001,'2026-09-01','TEST-FARMER-001',60,'Bearing','10',13.9180000,121.1450000,NULL),
        (6002,'mataas na kahoy','fruits','Mango','Phase One Other Harvest','2026-01-02','2026-09-02',75,2002,'2026-09-02','TEST-FARMER-002',70,'Bearing','20',13.9600000,121.1100000,NULL),
        (6003,'Manila','fruits','Mango','Unsupported Location','2026-01-03','2026-09-03',30,2003,'2026-09-03','TEST-FARMER-INACTIVE',80,'Bearing','5',14.5995000,120.9842000,NULL),
        (6004,'balintawak','vegetables','Tomato','Fallback Coordinate Crop','2026-01-04','2026-09-04',25,2001,'2026-09-04','TEST-FARMER-001',40,'Bearing','15',NULL,NULL,NULL)");

    phase1_query($conn, "INSERT INTO damage_reports
        (id,dmgLocation,dmgCropCommodity,dmgCropType,dmgCropVariety,date_occurrence,damage_cause,percent_damage,estimated_loss,FarmerID,reportDate,created_by,dmgFarmGatePrice,damage_number_of_trees,latitude,longitude,image_path) VALUES
        (7001,'adya','fruits','Mango','Phase One Own Damage','2026-09-01','Typhoon',25,100,2001,'2026-09-01','TEST-FARMER-001',60,'2',13.9180000,121.1450000,NULL),
        (7002,'mataas na kahoy','fruits','Mango','Phase One Other Damage','2026-09-02','Flood',40,200,2002,'2026-09-02','TEST-FARMER-002',70,'5',13.9600000,121.1100000,NULL)");

    phase1_query($conn, "INSERT INTO crop_catalog (id,commodity,crop_name,is_active,sort_order) VALUES
        (8001,'fruits','Mango',1,10),(8002,'vegetables','Tomato',1,10)");

    echo json_encode(['ready' => true, 'password' => 'SCAIMS-Test-Only#2026']);
    exit;
}

$conn->select_db($database);

if ($action === 'state') {
    echo json_encode([
        'users' => phase1_rows($conn, 'SELECT UserID,UName,UserRole,LENGTH(ProfileImage) AS profile_bytes FROM user ORDER BY UserID'),
        'farmers' => phase1_rows($conn, 'SELECT * FROM farmer ORDER BY FarmerID'),
        'admins' => phase1_rows($conn, 'SELECT AdminID,AdminName,AdminRole,is_active FROM admin ORDER BY AdminID'),
        'farm_parcels' => phase1_rows($conn, 'SELECT * FROM farm_parcels ORDER BY id'),
        'parcel_details' => phase1_rows($conn, 'SELECT * FROM parcel_details ORDER BY id'),
        'crop_details' => phase1_rows($conn, 'SELECT * FROM crop_details ORDER BY id'),
        'crop_reports' => phase1_rows($conn, 'SELECT * FROM crop_reports ORDER BY id'),
        'damage_reports' => phase1_rows($conn, 'SELECT * FROM damage_reports ORDER BY id'),
        'posts' => phase1_rows($conn, 'SELECT * FROM posts ORDER BY id'),
        'suggestions' => phase1_rows($conn, 'SELECT * FROM suggestions ORDER BY id'),
        'crop_catalog' => phase1_rows($conn, 'SELECT id,commodity,crop_name,is_active,sort_order FROM crop_catalog ORDER BY id'),
    ]);
    exit;
}

if ($action === 'legacy-image') {
    $reportType = $argv[3] ?? 'damage';
    $reportId = (int) ($argv[4] ?? 0);
    $source = $argv[5] ?? '';
    if (!in_array($reportType, ['crop', 'damage'], true) || $reportId <= 0 || !is_file($source)) {
        throw new RuntimeException('Invalid legacy-image arguments.');
    }
    $folder = $reportType === 'damage' ? 'damage' : 'harvest';
    $targetDir = dirname(__DIR__, 2) . '/uploads/reports/' . $folder;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $filename = 'phase1-' . $database . '-legacy-' . $reportId . '.png';
    $target = $targetDir . '/' . $filename;
    copy($source, $target);
    $relative = 'uploads/reports/' . $folder . '/' . $filename;
    $table = $reportType === 'damage' ? 'damage_reports' : 'crop_reports';
    $stmt = $conn->prepare("UPDATE `$table` SET image_path = ? WHERE id = ?");
    $stmt->bind_param('si', $relative, $reportId);
    $stmt->execute();
    echo $relative;
    exit;
}

if ($action === 'cleanup') {
    foreach (['crop_reports', 'damage_reports'] as $table) {
        foreach (phase1_rows($conn, "SELECT image_path FROM `$table` WHERE image_path IS NOT NULL") as $row) {
            scaims_delete_report_image($row['image_path'] ?? null);
        }
    }
    foreach (phase1_rows($conn, 'SELECT image_paths FROM posts WHERE image_paths IS NOT NULL') as $row) {
        $paths = json_decode($row['image_paths'], true);
        foreach (is_array($paths) ? $paths : [] as $path) {
            $basename = basename((string) $path);
            if (strpos($basename, 'phase1-' . $database) !== false) {
                @unlink(dirname(__DIR__, 2) . '/uploads/posts/' . $basename);
            }
        }
    }
    foreach (glob(dirname(__DIR__, 2) . '/uploads/posts/*phase1-' . $database . '*') ?: [] as $file) {
        @unlink($file);
    }
    foreach (glob(dirname(__DIR__, 2) . '/uploads/reports/pending/*phase1-' . $database . '*') ?: [] as $file) {
        @unlink($file);
    }
    phase1_cleanup_sessions($database);
    $conn->select_db(SCAIMS_DB_NAME);
    phase1_query($conn, "DROP DATABASE `$database`");
    echo 'cleaned';
    exit;
}

throw new RuntimeException('Unknown Phase 1 fixture action.');

