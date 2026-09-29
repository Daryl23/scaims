<?php
require_once __DIR__ . '/report-types.php';

function scaims_reporting_find_farmer_by_username(mysqli $conn, string $username): ?array
{
    $stmt = $conn->prepare(
        "SELECT f.FarmerID, f.Barangay,
                CONCAT_WS(' ', f.FirstName, f.MiddleName, f.Surname, f.Suffix) AS FarmerName,
                u.UName
         FROM farmer f
         JOIN user u ON f.UserID = u.UserID
         WHERE u.UName = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function scaims_reporting_find_active_farmer(mysqli $conn, int $farmerId): ?array
{
    $stmt = $conn->prepare(
        "SELECT f.FarmerID, f.Barangay,
                CONCAT_WS(' ', f.FirstName, f.MiddleName, f.Surname, f.Suffix) AS FarmerName,
                u.UName
         FROM farmer f
         LEFT JOIN user u ON f.UserID = u.UserID
         WHERE f.FarmerID = ?
           AND (f.is_active = 1 OR f.is_active IS NULL)
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $farmerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function scaims_reporting_fetch_owned(mysqli $conn, string $reportType, int $reportId, int $farmerId): ?array
{
    $config = scaims_reporting_type_config($reportType);
    if (!$config) {
        return null;
    }

    $table = $config['table'];
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ? AND FarmerID = ? LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException('Unable to verify the selected report.');
    }

    $stmt->bind_param('ii', $reportId, $farmerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function scaims_reporting_fetch_presented(mysqli $conn, string $reportType, int $reportId, int $farmerId, string $imageUrlPrefix): ?array
{
    if ($reportType === 'crop') {
        $sql = "SELECT
                    'crop' AS report_type, id, location, crop_commodity, crop_type, crop_variety,
                    date_planted, harvest_date, NULL AS date_occurrence, estimate_harvest,
                    crop_status, farm_gate_price, number_of_trees, NULL AS damage_cause,
                    NULL AS percent_damage, NULL AS estimated_loss, latitude, longitude,
                    reportDate, created_by, image_path,
                    photo_capture_date, photo_capture_time, photo_location, photo_latitude,
                    photo_longitude, photo_timezone, photo_ocr_version
                FROM crop_reports WHERE id = ? AND FarmerID = ?";
    } elseif ($reportType === 'damage') {
        $sql = "SELECT
                    'damage' AS report_type, id, dmgLocation AS location,
                    dmgCropCommodity AS crop_commodity, dmgCropType AS crop_type,
                    dmgCropVariety AS crop_variety, NULL AS date_planted, NULL AS harvest_date,
                    date_occurrence, NULL AS estimate_harvest, NULL AS crop_status,
                    dmgFarmGatePrice AS farm_gate_price, damage_number_of_trees AS number_of_trees,
                    damage_cause, percent_damage, estimated_loss, latitude, longitude,
                    reportDate, created_by, image_path,
                    photo_capture_date, photo_capture_time, photo_location, photo_latitude,
                    photo_longitude, photo_timezone, photo_ocr_version
                FROM damage_reports WHERE id = ? AND FarmerID = ?";
    } else {
        return null;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to reload the updated report.');
    }
    $stmt->bind_param('ii', $reportId, $farmerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $row['gps_photo'] = scaims_photo_from_row($row);
    $row['image_url'] = scaims_report_image_url($row['image_path'] ?? null, $imageUrlPrefix);
    unset($row['image_path']);

    return $row;
}
