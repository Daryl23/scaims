<?php
require_once __DIR__ . '/../../../../shared/admin_auth.php';
scaims_require_admin('../../../adminLogin/adminLogin.html');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['AdminID'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in as an admin to view report details.']);
    exit;
}

require_once 'db_connection.php';
require_once dirname(__DIR__, 4) . '/shared/report_image_storage.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    scaims_ensure_report_image_columns($conn);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Report image information is unavailable right now. Please try again later.']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['error' => 'Report ID is required']);
    exit;
}

$reportId = (int) $_POST['id'];

try {
    $sql = "SELECT dr.id,
                   COALESCE(CONCAT(rf.FirstName, ' ', rf.Surname), CONCAT(cf.FirstName, ' ', cf.Surname), dr.created_by) AS Name,
                   dr.dmgLocation AS Location,
                   dr.dmgCropCommodity AS CropCommodity,
                   dr.dmgCropType AS CropType,
                   dr.dmgCropVariety AS CropVariety,
                   dr.date_occurrence AS DateOccurrence,
                   dr.damage_cause AS DamageCause,
                   dr.percent_damage AS PercentDamage,
                   dr.estimated_loss AS EstimatedLoss,
                   dr.dmgFarmGatePrice AS FarmGatePrice,
                   dr.damage_number_of_trees AS NumberOfTrees,
                   dr.latitude AS Latitude,
                   dr.longitude AS Longitude,
                   dr.image_path AS ImagePath,
                   dr.reportDate AS ReportDate,
                   COALESCE(ca.AdminName, CONCAT(cf.FirstName, ' ', cf.Surname), dr.created_by) AS ReportedBy,
                   CASE WHEN ca.AdminName IS NOT NULL THEN 'Admin' ELSE 'Farmer' END AS ReporterType
            FROM damage_reports dr
            LEFT JOIN farmer rf ON dr.FarmerID = rf.FarmerID
            LEFT JOIN user cu ON dr.created_by = cu.UName
            LEFT JOIN farmer cf ON cu.UserID = cf.UserID
            LEFT JOIN admin ca ON dr.created_by = ca.AdminName
            WHERE dr.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'No report found with the provided ID']);
        exit;
    }

    $report = $result->fetch_assoc();
    $imageUrl = scaims_report_image_url($report['ImagePath'] ?? null, '../../../../');

    echo json_encode([
        'id' => $report['id'],
        'Name' => htmlspecialchars(ucwords($report['Name']), ENT_QUOTES, 'UTF-8'),
        'ReportedBy' => htmlspecialchars($report['ReportedBy'], ENT_QUOTES, 'UTF-8'),
        'ReporterType' => htmlspecialchars($report['ReporterType'], ENT_QUOTES, 'UTF-8'),
        'Location' => htmlspecialchars(ucwords($report['Location']), ENT_QUOTES, 'UTF-8'),
        'CropCommodity' => htmlspecialchars(ucwords($report['CropCommodity']), ENT_QUOTES, 'UTF-8'),
        'CropType' => htmlspecialchars(ucwords($report['CropType']), ENT_QUOTES, 'UTF-8'),
        'CropVariety' => htmlspecialchars(ucwords($report['CropVariety']), ENT_QUOTES, 'UTF-8'),
        'DateOccurrence' => htmlspecialchars($report['DateOccurrence'], ENT_QUOTES, 'UTF-8'),
        'DamageCause' => htmlspecialchars(ucwords($report['DamageCause']), ENT_QUOTES, 'UTF-8'),
        'PercentDamage' => htmlspecialchars($report['PercentDamage'], ENT_QUOTES, 'UTF-8'),
        'EstimatedLoss' => htmlspecialchars($report['EstimatedLoss'], ENT_QUOTES, 'UTF-8'),
        'FarmGatePrice' => htmlspecialchars($report['FarmGatePrice'], ENT_QUOTES, 'UTF-8'),
        'NumberOfTrees' => htmlspecialchars($report['NumberOfTrees'], ENT_QUOTES, 'UTF-8'),
        'Latitude' => htmlspecialchars($report['Latitude'] ?? '', ENT_QUOTES, 'UTF-8'),
        'Longitude' => htmlspecialchars($report['Longitude'] ?? '', ENT_QUOTES, 'UTF-8'),
        'ReportDate' => htmlspecialchars($report['ReportDate'], ENT_QUOTES, 'UTF-8'),
        'ImageUrl' => $imageUrl ? htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') : ''
    ]);

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['error' => 'An error occurred while fetching the report details: ' . $e->getMessage()]);
}

$conn->close();
?>
