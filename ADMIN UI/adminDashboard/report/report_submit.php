<?php
require_once dirname(__DIR__, 3) . '/shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
require_once dirname(__DIR__, 3) . '/conn.php';
require_once dirname(__DIR__, 3) . '/shared/report_image_storage.php';
require_once dirname(__DIR__, 3) . '/shared/gps_photo.php';

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_report_image_columns($conn);
    scaims_ensure_gps_photo_columns($conn);
} catch (RuntimeException $e) {
    $_SESSION['message'] = 'The report database is unavailable right now. Please try again later.';
    header("Location: reportAnalytics.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $conn->close();
    header("Location: reportAnalytics.php");
    exit();
}

$reportType = $_POST['report-type'] ?? '';
$farmerId = isset($_POST['reporting-for']) ? (int) $_POST['reporting-for'] : 0;
$createdBy = $_SESSION['AdminName'] ?? $_SESSION['AdminID'];

function parseCoordinateValue($value) {
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function normalizeCoordinateValue($value, $min, $max) {
    if ($value === null) {
        return null;
    }

    if (!is_numeric($value)) {
        return false;
    }

    $numericValue = (float) $value;
    if ($numericValue < $min || $numericValue > $max) {
        return false;
    }

    return number_format($numericValue, 7, '.', '');
}

function cleanAdminReportValue(string $key): string {
    return trim((string) ($_POST[$key] ?? ''));
}

function requireAdminReportValue(string $value, string $label): void {
    if ($value === '') {
        $_SESSION['message'] = $label . ' is required.';
        header("Location: reportAnalytics.php");
        exit();
    }
}

function requireAdminReportDate(string $value, string $label): void {
    requireAdminReportValue($value, $label);
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        $_SESSION['message'] = 'Please enter a valid date for ' . $label . '.';
        header("Location: reportAnalytics.php");
        exit();
    }
}

function requirePositiveAdminReportNumber(string $value, string $label): void {
    if ($value === '' || !is_numeric($value) || (float) $value <= 0) {
        $_SESSION['message'] = 'Please enter a valid positive number for ' . $label . '.';
        header("Location: reportAnalytics.php");
        exit();
    }
}

function requireAdminReportPercentage(string $value): void {
    if ($value === '' || !is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
        $_SESSION['message'] = 'Please enter a valid damage percentage between 0 and 100.';
        header("Location: reportAnalytics.php");
        exit();
    }
}

function adminReportImageField(string $legacyField): string {
    $adminField = 'admin-report-image';
    if (isset($_FILES[$adminField]) && ($_FILES[$adminField]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        return $adminField;
    }

    return $legacyField;
}

if ($farmerId <= 0) {
    $_SESSION['message'] = 'Please select the farmer you are reporting for.';
    $conn->close();
    header("Location: reportAnalytics.php");
    exit();
}

$farmerCheck = $conn->prepare("SELECT FarmerID FROM farmer WHERE FarmerID = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
$farmerCheck->bind_param("i", $farmerId);
$farmerCheck->execute();
$farmerResult = $farmerCheck->get_result();
if ($farmerResult->num_rows === 0) {
    $_SESSION['message'] = 'Selected farmer was not found.';
    $farmerCheck->close();
    $conn->close();
    header("Location: reportAnalytics.php");
    exit();
}
$farmerCheck->close();

$gpsPending = scaims_pending_gps_photo($_POST['pending-report-image-token'] ?? null);
if (!$gpsPending || !empty($_FILES['report-image']['tmp_name']) || !empty($_FILES['admin-report-image']['tmp_name']) || !empty($_FILES['crop-report-image']['tmp_name']) || !empty($_FILES['damage-report-image']['tmp_name'])) {
    $_SESSION['message'] = 'Please upload a GPS Camera photo and wait for GPS information to be detected.';
    header('Location: reportAnalytics.php'); exit();
}
$photo = $gpsPending['photo'];

if ($reportType === 'crop') {
    $location = mb_substr($photo['location'], 0, 255);
    $cropCommodity = cleanAdminReportValue("crop-commodity");
    $cropType = cleanAdminReportValue("crop-type");
    if ($cropType === "Other") {
        $cropType = cleanAdminReportValue("other-crop-type");
    }
    $cropVariety = cleanAdminReportValue("crop-variety");
    $datePlanted = cleanAdminReportValue("date-planted");
    $harvestDate = cleanAdminReportValue("harvest-date");
    $estimateHarvest = cleanAdminReportValue("estimate-harvest");
    $cropStatus = cleanAdminReportValue("crop-status");
    $farmGatePrice = cleanAdminReportValue("farm-gate-price");
    $numberOfTrees = cleanAdminReportValue("number-of-trees");
    $latitude = $photo['latitude'];
    $longitude = $photo['longitude'];

    requireAdminReportValue($location, 'Location');
    requireAdminReportValue($cropCommodity, 'Crop commodity');
    requireAdminReportValue($cropType, 'Crop type');
    requireAdminReportValue($cropVariety, 'Crop variety');
    requireAdminReportDate($datePlanted, 'date planted');
    requireAdminReportDate($harvestDate, 'harvest date');
    requirePositiveAdminReportNumber($estimateHarvest, 'estimated harvest');
    requireAdminReportValue($cropStatus, 'Crop status');
    requirePositiveAdminReportNumber($farmGatePrice, 'farm gate price');

    if (strtotime($datePlanted) > strtotime(date('Y-m-d'))) {
        $_SESSION['message'] = 'Date planted cannot be in the future.';
        $conn->close();
        header("Location: reportAnalytics.php");
        exit();
    }

    if (strtotime($harvestDate) <= strtotime($datePlanted)) {
        $_SESSION['message'] = 'Harvest date must be after the date planted.';
        $conn->close();
        header("Location: reportAnalytics.php");
        exit();
    }

    if (($latitude === null) xor ($longitude === null)) {
        $_SESSION['message'] = 'Please provide both latitude and longitude, or leave both empty.';
        $conn->close();
        header("Location: reportAnalytics.php");
        exit();
    }

    if ($latitude === false || $longitude === false) {
        $_SESSION['message'] = 'Coordinates are invalid. Latitude must be between -90 and 90, and longitude must be between -180 and 180.';
        $conn->close();
        header("Location: reportAnalytics.php");
        exit();
    }

    $imageResult = scaims_finalize_pending_report_image($gpsPending['token'], $reportType);
    if (!$imageResult['ok'] || empty($imageResult['path'])) {
        $_SESSION['message'] = $imageResult['message'] ?? 'Please upload a GPS Camera photo.';
        $conn->close();
        header("Location: reportAnalytics.php");
        exit();
    }
    $imagePath = $imageResult['path'] ?? null;

    $stmt = $conn->prepare("INSERT INTO crop_reports (
        location,
        crop_commodity,
        crop_type,
        crop_variety,
        date_planted,
        harvest_date,
        estimate_harvest,
        FarmerID,
        reportDate,
        created_by,
        farm_gate_price,
        crop_status,
        number_of_trees,
        latitude,
        longitude,
        image_path
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)");

    if (!$stmt) {
        scaims_delete_report_image($imagePath);
        $_SESSION['message'] = 'Error: ' . $conn->error;
        $conn->close();
        header("Location: reportAnalytics.php");
        exit();
    }

    $stmt->bind_param(
        "sssssssisssssss",
        $location,
        $cropCommodity,
        $cropType,
        $cropVariety,
        $datePlanted,
        $harvestDate,
        $estimateHarvest,
        $farmerId,
        $createdBy,
        $farmGatePrice,
        $cropStatus,
        $numberOfTrees,
        $latitude,
        $longitude,
        $imagePath
    );

    if (scaims_execute_photo_report($conn, $stmt, $reportType, $photo)) {
        $_SESSION['message'] = 'Crop report submitted successfully.';
    } else {
        scaims_delete_report_image($imagePath);
        $_SESSION['message'] = 'Error: ' . $stmt->error;
    }
    $stmt->close();
} elseif ($reportType === 'damage') {
    $location = mb_substr($photo['location'], 0, 255);
    $cropCommodity = cleanAdminReportValue("damage-crop-commodity");
    $cropType = cleanAdminReportValue("damage-crop-type");
    if ($cropType === "Other") {
        $cropType = cleanAdminReportValue("other-damage-crop-type");
    }
    $cropVariety = cleanAdminReportValue("damage-crop-variety");
    $dateOccurrence = $photo['date'];
    $damageCause = cleanAdminReportValue("damage-cause");
    $percentDamage = cleanAdminReportValue("damage-percentage");
    $estimatedLoss = cleanAdminReportValue("estimated-loss");
    $farmGatePrice = cleanAdminReportValue("damage-farm-gate-price");
    $numberOfTrees = cleanAdminReportValue("damage-number-of-trees");
    $latitude = $photo['latitude'];
    $longitude = $photo['longitude'];

    requireAdminReportValue($location, 'Location');
    requireAdminReportValue($cropCommodity, 'Crop commodity');
    requireAdminReportValue($cropType, 'Crop type');
    requireAdminReportValue($cropVariety, 'Crop variety');
    requireAdminReportDate($dateOccurrence, 'date of occurrence');
    requireAdminReportValue($damageCause, 'Damage cause');
    requireAdminReportPercentage($percentDamage);
    requirePositiveAdminReportNumber($estimatedLoss, 'estimated loss');
    requirePositiveAdminReportNumber($farmGatePrice, 'farm gate price');

    if (strtotime($dateOccurrence) > strtotime(date('Y-m-d'))) {
        $_SESSION['message'] = 'Date of occurrence cannot be in the future.';
        $conn->close();
        header("Location: reportAnalytics.php");
        exit();
    }

    if (($latitude === null) xor ($longitude === null)) {
        $_SESSION['message'] = 'Please provide both latitude and longitude, or leave both empty.';
        $conn->close();
        header("Location: reportAnalytics.php");
        exit();
    }

    if ($latitude === false || $longitude === false) {
        $_SESSION['message'] = 'Coordinates are invalid. Latitude must be between -90 and 90, and longitude must be between -180 and 180.';
        $conn->close();
        header("Location: reportAnalytics.php");
        exit();
    }

    $imageResult = scaims_finalize_pending_report_image($gpsPending['token'], $reportType);
    if (!$imageResult['ok'] || empty($imageResult['path'])) {
        $_SESSION['message'] = $imageResult['message'] ?? 'Please upload a GPS Camera photo.';
        $conn->close();
        header("Location: reportAnalytics.php");
        exit();
    }
    $imagePath = $imageResult['path'] ?? null;

    $stmt = $conn->prepare("INSERT INTO damage_reports (
        dmgLocation,
        dmgCropCommodity,
        dmgCropType,
        dmgCropVariety,
        date_occurrence,
        damage_cause,
        percent_damage,
        estimated_loss,
        FarmerID,
        reportDate,
        created_by,
        dmgFarmGatePrice,
        damage_number_of_trees,
        latitude,
        longitude,
        image_path
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)");

    if (!$stmt) {
        scaims_delete_report_image($imagePath);
        $_SESSION['message'] = 'Error: ' . $conn->error;
        $conn->close();
        header("Location: reportAnalytics.php");
        exit();
    }

    $stmt->bind_param(
        "ssssssssissssss",
        $location,
        $cropCommodity,
        $cropType,
        $cropVariety,
        $dateOccurrence,
        $damageCause,
        $percentDamage,
        $estimatedLoss,
        $farmerId,
        $createdBy,
        $farmGatePrice,
        $numberOfTrees,
        $latitude,
        $longitude,
        $imagePath
    );

    if (scaims_execute_photo_report($conn, $stmt, $reportType, $photo)) {
        $_SESSION['message'] = 'Damage report submitted successfully.';
    } else {
        scaims_delete_report_image($imagePath);
        $_SESSION['message'] = 'Error: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $_SESSION['message'] = 'Please choose a report type.';
}

$conn->close();
header("Location: reportAnalytics.php");
exit();
