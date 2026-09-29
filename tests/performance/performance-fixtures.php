<?php

// CLI-only data generator for Phase 10. It can select only a disposable
// Phase 1 database and never creates, truncates, or drops databases itself.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__, 2) . '/conn.php';

$action = $argv[1] ?? '';
$database = $argv[2] ?? '';
$reportCount = max(100, min(5000, (int) ($argv[3] ?? 1200)));

if (!preg_match('/^scaims_phase1_test_[0-9]+_[0-9]+$/', $database)) {
    throw new RuntimeException('Performance fixtures require a disposable Phase 1 database.');
}

function performance_query(mysqli $conn, string $sql): void
{
    if (!$conn->query($sql)) {
        throw new RuntimeException('Performance fixture query failed: ' . $conn->error);
    }
}

function performance_insert_chunks(mysqli $conn, string $prefix, array $rows, int $chunkSize = 200): void
{
    foreach (array_chunk($rows, $chunkSize) as $chunk) {
        performance_query($conn, $prefix . implode(",\n", $chunk));
    }
}

$conn = scaims_db_mysqli();
$conn->select_db($database);

if ($action === 'seed') {
    $users = [];
    $farmers = [];
    $farms = [];
    $parcels = [];
    $crops = [];

    for ($index = 1; $index <= 40; $index++) {
        $userId = 10000 + $index;
        $farmerId = 11000 + $index;
        $farmId = 12000 + $index;
        $parcelId = 13000 + $index;
        $cropId = 14000 + $index;
        $username = sprintf('PERF-FARMER-%03d', $index);
        $location = $index % 2 === 0 ? 'adya' : 'balintawak';
        $area = number_format(1 + ($index / 10), 4, '.', '');

        $users[] = "($userId,'$username','unused-performance-fixture','farmer',NOW(),NULL,NULL)";
        $farmers[] = "($farmerId,'Performance','Farmer $index','Fixture',NULL,'Male','1990-01-01','09990000000','Performance fixture','$location',CURDATE(),$userId,1,'PERF-$index')";
        $farms[] = "($farmId,'$location',$area,1,'Owned','No','$username')";
        $parcels[] = "($parcelId,$farmId,'$location','$area','Registered Owner','$username',1)";
        $crops[] = "($cropId,$parcelId,'fruits','Mango','Performance Variety $index',10,'Bearing','$username')";
    }

    performance_insert_chunks($conn, 'INSERT INTO user (UserID,UName,UPass,UserRole,creationDate,ProfileImage,plain_password) VALUES ', $users);
    performance_insert_chunks($conn, 'INSERT INTO farmer (FarmerID,FirstName,Surname,MiddleName,Suffix,Sex,BirthDate,ContactNo,Address,Barangay,registration_date,UserID,is_active,RSBSA_Number) VALUES ', $farmers);
    performance_insert_chunks($conn, 'INSERT INTO farm_parcels (id,farm_location,farm_area,num_parcels,farm_type,organic,created_by) VALUES ', $farms);
    performance_insert_chunks($conn, 'INSERT INTO parcel_details (id,farm_parcel_id,location,total_area,ownership_type,created_by,parcel_number) VALUES ', $parcels);
    performance_insert_chunks($conn, 'INSERT INTO crop_details (id,parcel_detail_id,crop_commodity,crop_type,crop_variety,num_trees,tree_status,created_by) VALUES ', $crops);

    $cropReports = [];
    $damageReports = [];
    for ($index = 1; $index <= $reportCount; $index++) {
        $farmerOffset = (($index - 1) % 40) + 1;
        $farmerId = 11000 + $farmerOffset;
        $username = sprintf('PERF-FARMER-%03d', $farmerOffset);
        $createdBy = $index % 7 === 0 ? 'TEST-ADMIN-001' : $username;
        $location = $farmerOffset % 2 === 0 ? 'adya' : 'balintawak';
        $cropType = $index % 2 === 0 ? 'Mango' : 'Tomato';
        $commodity = $cropType === 'Mango' ? 'fruits' : 'vegetables';
        $ageDays = $index <= 8 ? $index - 1 : 30 + ($index % 540);
        $reportDate = date('Y-m-d', strtotime("-$ageDays days"));
        $occurrenceDate = date('Y-m-d', strtotime('-' . ($ageDays + 2) . ' days'));
        $cropId = 20000 + $index;
        $damageId = 30000 + $index;
        $latitude = $location === 'adya' ? '13.9180000' : '13.9290000';
        $longitude = $location === 'adya' ? '121.1450000' : '121.1520000';

        $cropReports[] = "($cropId,'$location','$commodity','$cropType','Performance Variety','2025-01-01','2025-09-01',50,$farmerId,'$reportDate','$createdBy',60,'Bearing','10',$latitude,$longitude,NULL)";
        $damageReports[] = "($damageId,'$location','$commodity','$cropType','Performance Variety','$occurrenceDate','Typhoon',25,100,$farmerId,'$reportDate','$createdBy',60,'2',$latitude,$longitude,NULL)";
    }

    performance_insert_chunks($conn, 'INSERT INTO crop_reports (id,location,crop_commodity,crop_type,crop_variety,date_planted,harvest_date,estimate_harvest,FarmerID,reportDate,created_by,farm_gate_price,crop_status,number_of_trees,latitude,longitude,image_path) VALUES ', $cropReports);
    performance_insert_chunks($conn, 'INSERT INTO damage_reports (id,dmgLocation,dmgCropCommodity,dmgCropType,dmgCropVariety,date_occurrence,damage_cause,percent_damage,estimated_loss,FarmerID,reportDate,created_by,dmgFarmGatePrice,damage_number_of_trees,latitude,longitude,image_path) VALUES ', $damageReports);
    performance_query($conn, "INSERT INTO crop_reports (id,location,crop_commodity,crop_type,crop_variety,date_planted,harvest_date,estimate_harvest,FarmerID,reportDate,created_by,farm_gate_price,crop_status,number_of_trees,latitude,longitude,image_path) VALUES (19999,'adya','fruits','Mango','Null Date Fixture','2025-01-01','2025-09-01',1,NULL,NULL,'NULL-DATE-FIXTURE',1,'Bearing','1',13.9180000,121.1450000,NULL)");
    performance_query($conn, "INSERT INTO damage_reports (id,dmgLocation,dmgCropCommodity,dmgCropType,dmgCropVariety,date_occurrence,damage_cause,percent_damage,estimated_loss,FarmerID,reportDate,created_by,dmgFarmGatePrice,damage_number_of_trees,latitude,longitude,image_path) VALUES (29999,'adya','fruits','Mango','Null Date Fixture','2025-01-01','Other',1,1,NULL,NULL,'NULL-DATE-FIXTURE',1,'1',13.9180000,121.1450000,NULL)");

    echo json_encode(['farmers' => 40, 'crop_reports' => $reportCount, 'damage_reports' => $reportCount]);
    exit;
}

if ($action === 'indexes') {
    $output = [];
    foreach (['admin', 'user', 'farm_parcels', 'crop_reports', 'damage_reports'] as $table) {
        $result = $conn->query("SHOW INDEX FROM `$table`");
        $output[$table] = [];
        while ($row = $result->fetch_assoc()) {
            $output[$table][$row['Key_name']][] = $row['Column_name'];
        }
    }
    echo json_encode($output);
    exit;
}

if ($action === 'explain') {
    $queries = [
        'harvest_page' => "SELECT id FROM crop_reports WHERE reportDate < DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) ORDER BY reportDate DESC, id DESC LIMIT 10",
        'damage_page' => "SELECT id FROM damage_reports WHERE reportDate < DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) ORDER BY reportDate DESC, id DESC LIMIT 10",
        'damage_month' => "SELECT id FROM damage_reports WHERE date_occurrence >= '2025-01-01' AND date_occurrence < '2025-02-01' ORDER BY date_occurrence, id",
        'damage_map_summary' => "SELECT FarmerID, dmgCropType, MAX(percent_damage) FROM damage_reports GROUP BY FarmerID, dmgCropType",
        'parcel_owner_join' => "SELECT fp.id FROM `user` u JOIN farm_parcels fp ON fp.created_by = u.UName WHERE u.UName = 'PERF-FARMER-001'",
    ];
    $output = [];
    foreach ($queries as $name => $sql) {
        $result = $conn->query('EXPLAIN ' . $sql);
        $output[$name] = [];
        while ($row = $result->fetch_assoc()) {
            $output[$name][] = [
                'table' => $row['table'],
                'type' => $row['type'],
                'key' => $row['key'],
                'rows' => (int) $row['rows'],
                'Extra' => $row['Extra'],
            ];
        }
    }
    echo json_encode($output);
    exit;
}

throw new RuntimeException('Unknown performance fixture action.');
