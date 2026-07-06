<?php
session_start();

if (!isset($_SESSION['AdminID'])) {
    header('Location: ../../adminLogin/adminLogin.html');
    exit();
}

require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
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

if ($farmerId <= 0) {
    $_SESSION['message'] = 'Please select the farmer you are reporting for.';
    $conn->close();
    header("Location: reportAnalytics.php");
    exit();
}

$farmerCheck = $conn->prepare("SELECT FarmerID FROM farmer WHERE FarmerID = ? LIMIT 1");
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

if ($reportType === 'crop') {
    $location = $_POST["crop-location"] ?? '';
    $cropCommodity = $_POST["crop-commodity"] ?? '';
    $cropType = $_POST["crop-type"] ?? '';
    if ($cropType === "Other") {
        $cropType = $_POST["other-crop-type"] ?? '';
    }
    $cropVariety = $_POST["crop-variety"] ?? '';
    $datePlanted = $_POST["date-planted"] ?? '';
    $harvestDate = $_POST["harvest-date"] ?? '';
    $estimateHarvest = $_POST["estimate-harvest"] ?? '';
    $cropStatus = $_POST["crop-status"] ?? '';
    $farmGatePrice = $_POST["farm-gate-price"] ?? '';
    $numberOfTrees = $_POST["number-of-trees"] ?? null;
    $latitude = normalizeCoordinateValue(parseCoordinateValue($_POST["crop-latitude"] ?? ''), -90, 90);
    $longitude = normalizeCoordinateValue(parseCoordinateValue($_POST["crop-longitude"] ?? ''), -180, 180);

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
        longitude
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))");

    $stmt->bind_param(
        "sssssssissssss",
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
        $longitude
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = 'Crop report submitted successfully.';
    } else {
        $_SESSION['message'] = 'Error: ' . $stmt->error;
    }
    $stmt->close();
} elseif ($reportType === 'damage') {
    $location = $_POST["damage-location"] ?? '';
    $cropCommodity = $_POST["damage-crop-commodity"] ?? '';
    $cropType = $_POST["damage-crop-type"] ?? '';
    if ($cropType === "Other") {
        $cropType = $_POST["other-damage-crop-type"] ?? '';
    }
    $cropVariety = $_POST["damage-crop-variety"] ?? '';
    $dateOccurrence = $_POST["date-occurrence"] ?? '';
    $damageCause = $_POST["damage-cause"] ?? '';
    $percentDamage = $_POST["damage-percentage"] ?? '';
    $estimatedLoss = $_POST["estimated-loss"] ?? '';
    $farmGatePrice = $_POST["damage-farm-gate-price"] ?? '';
    $numberOfTrees = $_POST["damage-number-of-trees"] ?? null;
    $latitude = normalizeCoordinateValue(parseCoordinateValue($_POST["damage-latitude"] ?? ''), -90, 90);
    $longitude = normalizeCoordinateValue(parseCoordinateValue($_POST["damage-longitude"] ?? ''), -180, 180);

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
        longitude
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))");

    $stmt->bind_param(
        "ssssssssisssss",
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
        $longitude
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = 'Damage report submitted successfully.';
    } else {
        $_SESSION['message'] = 'Error: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $_SESSION['message'] = 'Please choose a report type.';
}

$conn->close();
header("Location: reportAnalytics.php");
exit();
