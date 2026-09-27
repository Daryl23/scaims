<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['UName'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in before editing a report.']);
    exit();
}

require_once dirname(__DIR__, 3) . '/conn.php';
require_once dirname(__DIR__, 3) . '/shared/report_image_storage.php';
require_once dirname(__DIR__, 3) . '/shared/gps_photo.php';
require_once dirname(__DIR__, 3) . '/shared/crop_catalog.php';
require_once dirname(__DIR__, 3) . '/shared/farmer_report_defaults.php';

function respond_report_update(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit();
}

function clean_report_value(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function clean_optional_report_value(string $key): ?string
{
    $value = clean_report_value($key);
    return $value === '' ? null : $value;
}

function normalize_report_coordinate(?string $value, float $min, float $max)
{
    if ($value === null) {
        return null;
    }

    if (!is_numeric($value)) {
        return false;
    }

    $number = (float) $value;
    if ($number < $min || $number > $max) {
        return false;
    }

    return number_format($number, 7, '.', '');
}

function require_report_value(string $value, string $label): void
{
    if ($value === '') {
        respond_report_update(false, $label . ' is required.', [], 400);
    }
}

function require_positive_report_number(string $value, string $label): void
{
    if ($value === '' || !is_numeric($value) || (float) $value <= 0) {
        respond_report_update(false, 'Please enter a valid positive number for ' . $label . '.', [], 400);
    }
}

function require_report_date(string $value, string $label): void
{
    require_report_value($value, $label);
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        respond_report_update(false, 'Please enter a valid date for ' . $label . '.', [], 400);
    }
}

function get_current_farmer_id(mysqli $conn): int
{
    $stmt = $conn->prepare("SELECT f.FarmerID FROM farmer f JOIN user u ON f.UserID = u.UserID WHERE u.UName = ? LIMIT 1");
    if (!$stmt) {
        respond_report_update(false, 'Unable to verify your farmer profile.', [], 500);
    }

    $stmt->bind_param('s', $_SESSION['UName']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        respond_report_update(false, 'Unable to find your farmer profile. Please sign in again.', [], 403);
    }

    return (int) $row['FarmerID'];
}

function fetch_updated_farmer_report(mysqli $conn, string $reportType, int $reportId, int $farmerId): array
{
    if ($reportType === 'crop') {
        $sql = "SELECT
                    'crop' AS report_type,
                    id,
                    location,
                    crop_commodity,
                    crop_type,
                    crop_variety,
                    date_planted,
                    harvest_date,
                    NULL AS date_occurrence,
                    estimate_harvest,
                    crop_status,
                    farm_gate_price,
                    number_of_trees,
                    NULL AS damage_cause,
                    NULL AS percent_damage,
                    NULL AS estimated_loss,
                    latitude,
                    longitude,
                    reportDate,
                    created_by,
                    image_path,
                    photo_capture_date, photo_capture_time, photo_location, photo_latitude, photo_longitude, photo_timezone, photo_ocr_version
                FROM crop_reports
                WHERE id = ? AND FarmerID = ?";
    } else {
        $sql = "SELECT
                    'damage' AS report_type,
                    id,
                    dmgLocation AS location,
                    dmgCropCommodity AS crop_commodity,
                    dmgCropType AS crop_type,
                    dmgCropVariety AS crop_variety,
                    NULL AS date_planted,
                    NULL AS harvest_date,
                    date_occurrence,
                    NULL AS estimate_harvest,
                    NULL AS crop_status,
                    dmgFarmGatePrice AS farm_gate_price,
                    damage_number_of_trees AS number_of_trees,
                    damage_cause,
                    percent_damage,
                    estimated_loss,
                    latitude,
                    longitude,
                    reportDate,
                    created_by,
                    image_path,
                    photo_capture_date, photo_capture_time, photo_location, photo_latitude, photo_longitude, photo_timezone, photo_ocr_version
                FROM damage_reports
                WHERE id = ? AND FarmerID = ?";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        respond_report_update(false, 'Unable to reload the updated report.', [], 500);
    }

    $stmt->bind_param('ii', $reportId, $farmerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        respond_report_update(false, 'Report not found.', [], 404);
    }

    $row['gps_photo'] = scaims_photo_from_row($row);
    $row['image_url'] = scaims_report_image_url($row['image_path'] ?? null, '../../../');
    unset($row['image_path']);

    return $row;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_report_update(false, 'Invalid request method.', [], 405);
}

$reportType = clean_report_value('report-type');
$reportId = isset($_POST['report-id']) ? (int) $_POST['report-id'] : 0;

if (!in_array($reportType, ['crop', 'damage'], true) || $reportId <= 0) {
    respond_report_update(false, 'Invalid report selection.', [], 400);
}

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_report_image_columns($conn);
    scaims_ensure_gps_photo_columns($conn);
    scaims_ensure_farmer_report_defaults_table($conn);
} catch (RuntimeException $e) {
    respond_report_update(false, 'The report database is unavailable right now. Please try again later.', [], 500);
}

$farmerId = get_current_farmer_id($conn);

$oldImagePath = null;
if ($reportType === 'crop') {
    $existingStmt = $conn->prepare('SELECT * FROM crop_reports WHERE id = ? AND FarmerID = ? LIMIT 1');
} else {
    $existingStmt = $conn->prepare('SELECT * FROM damage_reports WHERE id = ? AND FarmerID = ? LIMIT 1');
}

if (!$existingStmt) {
    $conn->close();
    respond_report_update(false, 'Unable to verify the selected report.', [], 500);
}

$existingStmt->bind_param('ii', $reportId, $farmerId);
$existingStmt->execute();
$existingRow = $existingStmt->get_result()->fetch_assoc();
$existingStmt->close();

if (!$existingRow) {
    $conn->close();
    respond_report_update(false, 'Report not found or you do not have permission to edit it.', [], 404);
}

$oldImagePath = $existingRow['image_path'] ?? null;
$uploadedImagePath = null;
$newImagePath = $oldImagePath;
$removeImage = clean_report_value('remove-report-image') === '1';
$photo = scaims_photo_from_row($existingRow);
$replacement = null;
if ($photo !== null) {
    if ($removeImage || !empty($_FILES['report-image']['tmp_name'])) {
        respond_report_update(false, 'Replace this GPS photo with another successfully read GPS Camera photo.', [], 400);
    }
    $replacementToken = clean_report_value('edit-pending-report-image-token');
    if ($replacementToken !== '') {
        $replacement = scaims_pending_gps_photo($replacementToken);
        if (!$replacement) respond_report_update(false, 'GPS extraction is missing or expired. Choose the photo again.', [], 400);
        $photo = $replacement['photo'];
    }
}


$latitude = $photo ? $photo['latitude'] : normalize_report_coordinate(clean_optional_report_value('latitude'), -90, 90);
$longitude = $photo ? $photo['longitude'] : normalize_report_coordinate(clean_optional_report_value('longitude'), -180, 180);
if (($latitude === null) xor ($longitude === null)) {
    scaims_delete_report_image($uploadedImagePath);
    $conn->close();
    respond_report_update(false, 'Please provide both latitude and longitude, or leave both empty.', [], 400);
}

if ($latitude === false || $longitude === false) {
    scaims_delete_report_image($uploadedImagePath);
    $conn->close();
    respond_report_update(false, 'Coordinates are invalid. Latitude must be between -90 and 90, and longitude must be between -180 and 180.', [], 400);
}

if ($reportType === 'crop') {
    $location = $photo ? mb_substr($photo['location'], 0, 255) : clean_report_value('location');
    $commodity = clean_report_value('crop-commodity');
    $cropType = clean_report_value('crop-type');
    $cropVariety = clean_report_value('crop-variety');
    $datePlanted = clean_report_value('date-planted');
    $harvestDate = clean_report_value('harvest-date');
    $estimateHarvest = clean_report_value('estimate-harvest');
    $cropStatus = clean_report_value('crop-status');
    $farmGatePrice = clean_report_value('farm-gate-price');
    $numberOfTrees = clean_optional_report_value('number-of-trees');

    require_report_value($location, 'Location');
    require_report_value($commodity, 'Crop commodity');
    require_report_value($cropType, 'Crop type');
    require_report_value($cropVariety, 'Crop variety');
    require_report_date($datePlanted, 'date planted');
    require_report_date($harvestDate, 'harvest date');
    require_positive_report_number($estimateHarvest, 'estimated harvest');
    require_report_value($cropStatus, 'Crop status');
    require_positive_report_number($farmGatePrice, 'farm gate price');

    if (strtotime($datePlanted) > strtotime(date('Y-m-d'))) {
        scaims_delete_report_image($uploadedImagePath);
        $conn->close();
        respond_report_update(false, 'Date planted cannot be in the future.', [], 400);
    }

    if (strtotime($harvestDate) <= strtotime($datePlanted)) {
        scaims_delete_report_image($uploadedImagePath);
        $conn->close();
        respond_report_update(false, 'Harvest date must be after the date planted.', [], 400);
    }

    $defaultsForUpdate = [
        'location' => $location,
        'crop_commodity' => $commodity,
        'crop_type' => $cropType,
        'crop_variety' => $cropVariety,
        'date_planted' => $datePlanted,
        'harvest_date' => $harvestDate,
        'estimate_harvest' => $estimateHarvest,
        'crop_status' => $cropStatus,
        'number_of_trees' => $numberOfTrees,
        'farm_gate_price' => $farmGatePrice,
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];

    $imageResult = $photo ? ($replacement ? scaims_finalize_pending_report_image($replacement['token'], $reportType) : ['ok' => true, 'path' => null]) : scaims_collect_report_image('report-image', $reportType);
    if (!$imageResult['ok']) {
        $conn->close();
        respond_report_update(false, $imageResult['message'] ?? 'The report image could not be uploaded. Please try again.', [], 400);
    }

    $uploadedImagePath = $imageResult['path'] ?? null;
    if ($uploadedImagePath !== null) {
        $newImagePath = $uploadedImagePath;
    } elseif ($removeImage) {
        $newImagePath = null;
    }

    $stmt = $conn->prepare("UPDATE crop_reports
        SET location = ?,
            crop_commodity = ?,
            crop_type = ?,
            crop_variety = ?,
            date_planted = ?,
            harvest_date = ?,
            estimate_harvest = ?,
            crop_status = ?,
            farm_gate_price = ?,
            number_of_trees = ?,
            latitude = ?,
            longitude = ?,
            image_path = ?
        WHERE id = ? AND FarmerID = ?");

    if (!$stmt) {
        scaims_delete_report_image($uploadedImagePath);
        $conn->close();
        respond_report_update(false, 'Unable to save report changes.', [], 500);
    }

    $stmt->bind_param(
        'sssssssssssssii',
        $location,
        $commodity,
        $cropType,
        $cropVariety,
        $datePlanted,
        $harvestDate,
        $estimateHarvest,
        $cropStatus,
        $farmGatePrice,
        $numberOfTrees,
        $latitude,
        $longitude,
        $newImagePath,
        $reportId,
        $farmerId
    );
} else {
    $location = $photo ? mb_substr($photo['location'], 0, 255) : clean_report_value('location');
    $commodity = clean_report_value('crop-commodity');
    $cropType = clean_report_value('crop-type');
    $cropVariety = clean_report_value('crop-variety');
    $dateOccurrence = $photo ? $photo['date'] : clean_report_value('date-occurrence');
    $damageCause = clean_report_value('damage-cause');
    $percentDamage = clean_report_value('damage-percentage');
    $estimatedLoss = clean_report_value('estimated-loss');
    $farmGatePrice = clean_report_value('farm-gate-price');
    $numberOfTrees = clean_optional_report_value('number-of-trees');

    require_report_value($location, 'Location');
    require_report_value($commodity, 'Crop commodity');
    require_report_value($cropType, 'Crop type');
    require_report_value($cropVariety, 'Crop variety');
    require_report_date($dateOccurrence, 'date of occurrence');
    require_report_value($damageCause, 'Damage cause');

    if ($percentDamage === '' || !is_numeric($percentDamage) || (float) $percentDamage < 0 || (float) $percentDamage > 100) {
        scaims_delete_report_image($uploadedImagePath);
        $conn->close();
        respond_report_update(false, 'Please enter a valid damage percentage between 0 and 100.', [], 400);
    }

    require_positive_report_number($estimatedLoss, 'estimated loss');
    require_positive_report_number($farmGatePrice, 'farm gate price');

    if (strtotime($dateOccurrence) > strtotime(date('Y-m-d'))) {
        scaims_delete_report_image($uploadedImagePath);
        $conn->close();
        respond_report_update(false, 'Date of occurrence cannot be in the future.', [], 400);
    }

    $defaultsForUpdate = [
        'location' => $location,
        'crop_commodity' => $commodity,
        'crop_type' => $cropType,
        'crop_variety' => $cropVariety,
        'date_occurrence' => $dateOccurrence,
        'damage_cause' => $damageCause,
        'percent_damage' => $percentDamage,
        'estimated_loss' => $estimatedLoss,
        'farm_gate_price' => $farmGatePrice,
        'number_of_trees' => $numberOfTrees,
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];

    $imageResult = $photo ? ($replacement ? scaims_finalize_pending_report_image($replacement['token'], $reportType) : ['ok' => true, 'path' => null]) : scaims_collect_report_image('report-image', $reportType);
    if (!$imageResult['ok']) {
        $conn->close();
        respond_report_update(false, $imageResult['message'] ?? 'The report image could not be uploaded. Please try again.', [], 400);
    }

    $uploadedImagePath = $imageResult['path'] ?? null;
    if ($uploadedImagePath !== null) {
        $newImagePath = $uploadedImagePath;
    } elseif ($removeImage) {
        $newImagePath = null;
    }

    $stmt = $conn->prepare("UPDATE damage_reports
        SET dmgLocation = ?,
            dmgCropCommodity = ?,
            dmgCropType = ?,
            dmgCropVariety = ?,
            date_occurrence = ?,
            damage_cause = ?,
            percent_damage = ?,
            estimated_loss = ?,
            dmgFarmGatePrice = ?,
            damage_number_of_trees = ?,
            latitude = ?,
            longitude = ?,
            image_path = ?
        WHERE id = ? AND FarmerID = ?");

    if (!$stmt) {
        scaims_delete_report_image($uploadedImagePath);
        $conn->close();
        respond_report_update(false, 'Unable to save report changes.', [], 500);
    }

    $stmt->bind_param(
        'sssssssssssssii',
        $location,
        $commodity,
        $cropType,
        $cropVariety,
        $dateOccurrence,
        $damageCause,
        $percentDamage,
        $estimatedLoss,
        $farmGatePrice,
        $numberOfTrees,
        $latitude,
        $longitude,
        $newImagePath,
        $reportId,
        $farmerId
    );
}

if (!scaims_execute_photo_report($conn, $stmt, $reportType, $photo, $reportId)) {
    $error = $stmt->error;
    $stmt->close();
    scaims_delete_report_image($uploadedImagePath);
    $conn->close();
    respond_report_update(false, 'Unable to save report changes: ' . $error, [], 500);
}

$stmt->close();

if ($uploadedImagePath !== null && $oldImagePath !== null && $oldImagePath !== $uploadedImagePath) {
    scaims_delete_report_image($oldImagePath);
} elseif ($removeImage && $oldImagePath !== null) {
    scaims_delete_report_image($oldImagePath);
}

$updatedReport = fetch_updated_farmer_report($conn, $reportType, $reportId, $farmerId);
if ($photo) { foreach (['location', 'latitude', 'longitude', 'date_occurrence'] as $key) unset($defaultsForUpdate[$key]); }
if (!scaims_save_farmer_report_defaults($conn, $farmerId, $reportType, $defaultsForUpdate ?? [])) {
    $conn->close();
    respond_report_update(true, 'Report changes saved, but reusable report data could not be saved.', ['report' => $updatedReport]);
}
$conn->close();

respond_report_update(true, 'Report changes saved.', ['report' => $updatedReport]);
