<?php
require_once __DIR__ . '/../../../../shared/admin_auth.php';
scaims_require_admin('../../../adminLogin/adminLogin.html');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connection.php';
require_once dirname(__DIR__, 4) . '/shared/crop_catalog.php';

$barangay = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
$crop = isset($_GET['crop']) ? trim($_GET['crop']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$month = isset($_GET['month']) ? trim($_GET['month']) : '';
$reporterType = isset($_GET['reporterType']) ? trim($_GET['reporterType']) : '';

$predefinedCrops = scaims_get_active_crop_names($conn);

try {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename=CropReports.xls');

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Sheet 1</x:Name><x:WorksheetOptions><x:Print><x:ValidPrinterInfo/></x:Print></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '</head>';
    echo '<body>';

    echo '<table border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse; font-family: Arial; font-size: 12px;">';
    echo '<tr><td colspan="16" align="center" style="font-weight: bold; font-size: 14px;">APGORRS</td></tr>';
    echo '<tr><td colspan="16" align="center" style="font-weight: bold; font-size: 13px;">CROP HARVEST REPORTS</td></tr>';
    echo '<tr><td colspan="16"></td></tr>';

    if ($search) {
        echo '<tr><td colspan="16" style="font-weight: bold;">Search Term: ' . htmlspecialchars($search) . '</td></tr>';
    }
    if ($crop) {
        $cropLabel = $crop === 'Other' ? 'Other (Non-predefined crops)' : ucwords(str_replace('-', ' ', $crop));
        echo '<tr><td colspan="16" style="font-weight: bold;">Crop Type: ' . htmlspecialchars($cropLabel) . '</td></tr>';
    }
    if ($barangay) {
        echo '<tr><td colspan="16" style="font-weight: bold;">Barangay: ' . htmlspecialchars(ucfirst($barangay)) . '</td></tr>';
    }
    if ($reporterType) {
        echo '<tr><td colspan="16" style="font-weight: bold;">Reporter Type: ' . htmlspecialchars(ucfirst($reporterType)) . '</td></tr>';
    }
    if ($month) {
        echo '<tr><td colspan="16" style="font-weight: bold;">Month: ' . htmlspecialchars(date('F Y', strtotime($month . '-01'))) . '</td></tr>';
    }
    echo '<tr><td colspan="16" style="font-weight: bold;">Date Generated: ' . date('F d, Y') . '</td></tr>';
    echo '<tr><td colspan="16"></td></tr>';

    echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
    echo '<td>Farmer</td><td>Barangay</td><td>Crop Commodity</td><td>Crop Type</td>';
    echo '<td>Crop Variety</td><td>Date Planted</td><td>Harvest Date</td>';
    echo '<td>Estimated Harvest (kg)</td><td>Crop Status</td><td>Reported By</td>';
    echo '<td>Reporter Type</td><td>Farm Gate Price (PHP/kg)</td><td>Number of Trees</td><td>Latitude</td><td>Longitude</td><td>Date Reported</td>';
    echo '</tr>';

    $sql = "SELECT
                COALESCE(CONCAT(rf.FirstName, ' ', rf.Surname), CONCAT(cf.FirstName, ' ', cf.Surname), cr.created_by) AS Name,
                cr.location AS Barangay,
                cr.crop_commodity AS CropCommodity,
                cr.crop_type AS CropType,
                cr.crop_variety AS CropVariety,
                DATE_FORMAT(cr.date_planted, '%m/%d/%Y') AS DatePlanted,
                DATE_FORMAT(cr.harvest_date, '%m/%d/%Y') AS HarvestDate,
                cr.estimate_harvest AS EstimatedHarvest,
                cr.crop_status AS CropStatus,
                COALESCE(ca.AdminName, CONCAT(cf.FirstName, ' ', cf.Surname), cr.created_by) AS ReportedBy,
                CASE WHEN ca.AdminName IS NOT NULL THEN 'Admin' ELSE 'Farmer' END AS ReporterType,
                cr.farm_gate_price AS FarmGatePrice,
                cr.number_of_trees AS NumberOfTrees,
                cr.latitude AS Latitude,
                cr.longitude AS Longitude,
                DATE_FORMAT(cr.reportDate, '%m/%d/%Y') AS DateReported
            FROM crop_reports cr
            LEFT JOIN farmer rf ON cr.FarmerID = rf.FarmerID
            LEFT JOIN user cu ON cr.created_by = cu.UName
            LEFT JOIN farmer cf ON cu.UserID = cf.UserID
            LEFT JOIN admin ca ON cr.created_by = ca.AdminName
            WHERE 1=1";

    $params = [];
    $types = '';

    if ($crop === 'Other') {
        if (!empty($predefinedCrops)) {
            $placeholders = implode(',', array_fill(0, count($predefinedCrops), '?'));
            $sql .= " AND LOWER(cr.crop_type) NOT IN ($placeholders)";
            foreach ($predefinedCrops as $item) {
                $params[] = strtolower($item);
                $types .= 's';
            }
        }
    } elseif ($crop) {
        $sql .= " AND " . scaims_crop_match_condition('cr.crop_type');
        $params[] = $crop;
        $params[] = scaims_crop_filter_slug($crop);
        $types .= 'ss';
    }
    if ($barangay) {
        $sql .= " AND cr.location = ?";
        $params[] = $barangay;
        $types .= 's';
    }
    if ($reporterType === 'admin') {
        $sql .= " AND ca.AdminName IS NOT NULL";
    } elseif ($reporterType === 'farmer') {
        $sql .= " AND ca.AdminName IS NULL";
    }
    if ($search) {
        $searchParam = '%' . $search . '%';
        $sql .= " AND (
            COALESCE(CONCAT(rf.FirstName, ' ', rf.Surname), CONCAT(cf.FirstName, ' ', cf.Surname), '') LIKE ?
            OR COALESCE(ca.AdminName, CONCAT(cf.FirstName, ' ', cf.Surname), cr.created_by) LIKE ?
            OR cr.location LIKE ?
            OR cr.crop_type LIKE ?
            OR cr.crop_commodity LIKE ?
        )";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        $types .= 'sssss';
    }
    if ($month) {
        $sql .= " AND DATE_FORMAT(cr.reportDate, '%Y-%m') = ?";
        $params[] = $month;
        $types .= 's';
    }

    $sql .= " ORDER BY cr.reportDate DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    if (!empty($params) && !$stmt->bind_param($types, ...$params)) {
        throw new Exception('Failed to bind parameters: ' . $stmt->error);
    }
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $rowCount = 0;
    while ($row = $result->fetch_assoc()) {
        $bgColor = $rowCount % 2 === 0 ? '#ffffff' : '#f9f9f9';
        echo '<tr style="background-color: ' . $bgColor . ';">';
        foreach ($row as $value) {
            echo '<td>' . htmlspecialchars((string) $value) . '</td>';
        }
        echo '</tr>';
        $rowCount++;
    }

    echo '</table>';
    echo '</body>';
    echo '</html>';

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Export error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Export failed: ' . $e->getMessage();
}
?>
