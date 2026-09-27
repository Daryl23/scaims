<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This seed helper is CLI-only.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/conn.php';

$password = getenv('SCAIMS_LOADTEST_PASSWORD') ?: 'ScaimsLoadTest#2026';
$farmerUsername = getenv('SCAIMS_LOADTEST_FARMER_USERNAME') ?: 'loadtest.farmer';
$adminUsername = getenv('SCAIMS_LOADTEST_ADMIN_USERNAME') ?: 'loadtest.admin';
$adminId = getenv('SCAIMS_LOADTEST_ADMIN_ID') ?: 'LOADTEST-ADMIN-001';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

function fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function exec_stmt(mysqli $conn, string $sql, string $types = '', array $params = []): void
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }

    $stmt->close();
}

try {
    $conn = scaims_db_mysqli();
    $conn->begin_transaction();

    $user = fetch_one($conn, 'SELECT UserID FROM user WHERE UName = ? LIMIT 1', 's', [$farmerUsername]);
    if ($user) {
        $userId = (int) $user['UserID'];
        exec_stmt($conn, 'UPDATE user SET UPass = ?, UserRole = ? WHERE UserID = ?', 'ssi', [$passwordHash, 'farmer', $userId]);
    } else {
        exec_stmt(
            $conn,
            'INSERT INTO user (UName, UPass, UserRole, creationDate, ProfileImage, plain_password) VALUES (?, ?, ?, NOW(), NULL, NULL)',
            'sss',
            [$farmerUsername, $passwordHash, 'farmer']
        );
        $userId = (int) $conn->insert_id;
    }

    $farmer = fetch_one($conn, 'SELECT FarmerID FROM farmer WHERE UserID = ? LIMIT 1', 'i', [$userId]);
    if ($farmer) {
        $farmerId = (int) $farmer['FarmerID'];
        exec_stmt(
            $conn,
            'UPDATE farmer SET FirstName = ?, Surname = ?, MiddleName = ?, Suffix = NULL, Sex = ?, BirthDate = ?, ContactNo = ?, Address = ?, Barangay = ?, is_active = 1, RSBSA_Number = ? WHERE FarmerID = ?',
            'sssssssssi',
            ['Loadtest', 'Farmer', 'SCAIMS', 'Male', '1990-01-01', '09170000000', 'Loadtest address', 'adya', '00-00-00-000-000000', $farmerId]
        );
    } else {
        exec_stmt(
            $conn,
            'INSERT INTO farmer (FirstName, Surname, MiddleName, Suffix, Sex, BirthDate, ContactNo, Address, Barangay, UserID, is_active, RSBSA_Number) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, 1, ?)',
            'ssssssssis',
            ['Loadtest', 'Farmer', 'SCAIMS', 'Male', '1990-01-01', '09170000000', 'Loadtest address', 'adya', $userId, '00-00-00-000-000000']
        );
        $farmerId = (int) $conn->insert_id;
    }

    exec_stmt(
        $conn,
        'INSERT INTO admin (AdminID, AdminName, AdminPass, AdminRole, is_active) VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE AdminName = VALUES(AdminName), AdminPass = VALUES(AdminPass), AdminRole = VALUES(AdminRole), is_active = 1',
        'ssss',
        [$adminId, $adminUsername, $passwordHash, 'admin']
    );

    $parcel = fetch_one($conn, 'SELECT id FROM farm_parcels WHERE created_by = ? AND farm_location = ? LIMIT 1', 'ss', [$farmerUsername, 'adya']);
    if ($parcel) {
        $farmParcelId = (int) $parcel['id'];
    } else {
        exec_stmt(
            $conn,
            'INSERT INTO farm_parcels (farm_location, farm_area, num_parcels, farm_type, organic, created_by) VALUES (?, ?, 1, ?, ?, ?)',
            'sdsss',
            ['adya', 1.2500, 'irrigated', 'yes', $farmerUsername]
        );
        $farmParcelId = (int) $conn->insert_id;
    }

    $parcelDetail = fetch_one($conn, 'SELECT id FROM parcel_details WHERE farm_parcel_id = ? AND created_by = ? LIMIT 1', 'is', [$farmParcelId, $farmerUsername]);
    if ($parcelDetail) {
        $parcelDetailId = (int) $parcelDetail['id'];
    } else {
        exec_stmt(
            $conn,
            'INSERT INTO parcel_details (farm_parcel_id, location, total_area, ownership_type, created_by, parcel_number) VALUES (?, ?, ?, ?, ?, 1)',
            'issss',
            [$farmParcelId, 'adya', '1.2500', 'registered_owner', $farmerUsername]
        );
        $parcelDetailId = (int) $conn->insert_id;
    }

    $cropDetail = fetch_one($conn, 'SELECT id FROM crop_details WHERE parcel_detail_id = ? AND created_by = ? AND crop_type = ? LIMIT 1', 'iss', [$parcelDetailId, $farmerUsername, 'Rice']);
    if (!$cropDetail) {
        exec_stmt(
            $conn,
            'INSERT INTO crop_details (parcel_detail_id, crop_commodity, crop_type, crop_variety, num_trees, tree_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'isssiss',
            [$parcelDetailId, 'grains', 'Rice', 'Loadtest Variety', 0, 'Bearing', $farmerUsername]
        );
    }

    $cropReport = fetch_one($conn, 'SELECT id FROM crop_reports WHERE FarmerID = ? AND created_by = ? AND crop_variety = ? LIMIT 1', 'iss', [$farmerId, $farmerUsername, 'Loadtest Readonly Crop']);
    if (!$cropReport) {
        exec_stmt(
            $conn,
            'INSERT INTO crop_reports (location, crop_commodity, crop_type, crop_variety, date_planted, harvest_date, estimate_harvest, FarmerID, reportDate, created_by, farm_gate_price, crop_status, number_of_trees, latitude, longitude, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, NULL)',
            'ssssssdisdssss',
            ['adya', 'grains', 'Rice', 'Loadtest Readonly Crop', '2026-08-01', '2026-09-01', 12.5, $farmerId, $farmerUsername, 30.25, 'Bearing', 'N/A', '13.9180000', '121.1450000']
        );
    }

    $damageReport = fetch_one($conn, 'SELECT id FROM damage_reports WHERE FarmerID = ? AND created_by = ? AND dmgCropVariety = ? LIMIT 1', 'iss', [$farmerId, $farmerUsername, 'Loadtest Readonly Damage']);
    if (!$damageReport) {
        exec_stmt(
            $conn,
            'INSERT INTO damage_reports (dmgLocation, dmgCropCommodity, dmgCropType, dmgCropVariety, date_occurrence, damage_cause, percent_damage, estimated_loss, FarmerID, reportDate, created_by, dmgFarmGatePrice, damage_number_of_trees, latitude, longitude, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, NULL)',
            'ssssssddisdsss',
            ['adya', 'grains', 'Rice', 'Loadtest Readonly Damage', '2026-09-01', 'Loadtest isolated damage cause', 5.0, 1.5, $farmerId, $farmerUsername, 30.25, 'N/A', '13.9180000', '121.1450000']
        );
    }

    $conn->commit();

    echo "Seeded isolated SCAIMS load-test data.\n";
    echo "Farmer username: {$farmerUsername}\n";
    echo "Admin username: {$adminUsername}\n";
    echo "Password: {$password}\n";
    echo "FarmerID: {$farmerId}\n";
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }

    fwrite(STDERR, "Seed failed: {$error->getMessage()}\n");
    exit(1);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
