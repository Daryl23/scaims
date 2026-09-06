<?php
require_once __DIR__ . '/../../../../shared/admin_auth.php';
scaims_require_admin('../../../adminLogin/adminLogin.html');
require_once 'db_connection.php';
require_once dirname(__DIR__, 4) . '/shared/crop_catalog.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$filter = $_POST['filter'] ?? '';
$barangay = $_POST['barangay'] ?? '';
$search = $_POST['search'] ?? '';
$reporterType = $_POST['reporterType'] ?? '';
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
$offset = ($page - 1) * $limit;
$month = $_POST['month'] ?? '';
$year = $_POST['year'] ?? '';
$predefinedCrops = $_POST['predefinedCrops'] ?? scaims_get_active_crop_names($conn);
if (!is_array($predefinedCrops)) {
    $predefinedCrops = scaims_get_active_crop_names($conn);
}

// Main data query without LIMIT
$sql = "SELECT cr.id, 
               COALESCE(CONCAT(rf.FirstName, ' ', rf.Surname), CONCAT(cf.FirstName, ' ', cf.Surname), cr.created_by) AS Name,
               cr.location AS Barangay, 
               cr.crop_type AS Crops,
               cr.crop_status AS Status, 
               cr.latitude AS Latitude,
               cr.longitude AS Longitude,
               cr.harvest_date AS HarvestDate,
               cr.reportDate AS DateReported,
               cr.number_of_trees,
               COALESCE(ca.AdminName, CONCAT(cf.FirstName, ' ', cf.Surname), cr.created_by) AS ReportedBy,
               CASE WHEN ca.AdminName IS NOT NULL THEN 'Admin' ELSE 'Farmer' END AS ReporterType,
               CASE 
                   WHEN cr.reportDate >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) THEN 'new'
                   ELSE 'previous'
               END as report_age
        FROM crop_reports cr
        LEFT JOIN farmer rf ON cr.FarmerID = rf.FarmerID
        LEFT JOIN user cu ON cr.created_by = cu.UName
        LEFT JOIN farmer cf ON cu.UserID = cf.UserID
        LEFT JOIN admin ca ON cr.created_by = ca.AdminName
        WHERE 1=1";

$params = [];
$types = "";

// Add filters
if ($filter === 'Other' && $predefinedCrops) {
    $placeholders = implode(',', array_fill(0, count($predefinedCrops), '?'));
    $sql .= " AND LOWER(cr.crop_type) NOT IN ($placeholders)";
    foreach ($predefinedCrops as $cropName) {
        $params[] = strtolower($cropName);
        $types .= "s";
    }
} elseif ($filter && $filter !== 'Other') {
    // Normal filter for specific crop
    $sql .= " AND " . scaims_crop_match_condition('cr.crop_type');
    $params[] = $filter;
    $params[] = scaims_crop_filter_slug($filter);
    $types .= "ss";
}

if ($barangay) {
    $sql .= " AND cr.location = ?";
    $params[] = $barangay;
    $types .= "s";
}

if ($reporterType === 'admin') {
    $sql .= " AND ca.AdminName IS NOT NULL";
} elseif ($reporterType === 'farmer') {
    $sql .= " AND ca.AdminName IS NULL";
}

if ($search) {
    $searchParam = "%$search%";
    $sql .= " AND (
        COALESCE(CONCAT(rf.FirstName, ' ', rf.Surname), CONCAT(cf.FirstName, ' ', cf.Surname), '') LIKE ?
        OR COALESCE(ca.AdminName, CONCAT(cf.FirstName, ' ', cf.Surname), cr.created_by) LIKE ?
        OR cr.location LIKE ?
        OR cr.crop_type LIKE ?
    )";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ssss";
}

if ($month && $year) {
    $startDate = date('Y-m-01', strtotime("$year-$month-01"));
    $endDate = date('Y-m-t', strtotime("$year-$month-01"));
    $sql .= " AND (cr.reportDate BETWEEN ? AND ?)";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
}

$sql .= " ORDER BY cr.reportDate DESC, cr.id DESC";

// Execute query without pagination first to get all results
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$allRows = [];
$newCount = 0;
$previousCount = 0;

// Separate new and previous reports
while ($row = $result->fetch_assoc()) {
    if ($row['report_age'] === 'new') {
        $newCount++;
        $allRows['new'][] = $row;
    } else {
        $previousCount++;
        $allRows['previous'][] = $row;
    }
}

// Generate output for new reports (show all)
$newOutput = '';
if (!empty($allRows['new'])) {
    foreach ($allRows['new'] as $row) {
        $name = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
        $barangay = htmlspecialchars(ucfirst(strtolower($row['Barangay'])), ENT_QUOTES, 'UTF-8');
        $crops = htmlspecialchars(ucfirst(strtolower($row['Crops'])), ENT_QUOTES, 'UTF-8');
        $harvestDate = htmlspecialchars($row['HarvestDate'], ENT_QUOTES, 'UTF-8');
        $dateReported = htmlspecialchars($row['DateReported'], ENT_QUOTES, 'UTF-8');

        $latitude = $row['Latitude'] !== null ? htmlspecialchars($row['Latitude'], ENT_QUOTES, 'UTF-8') : 'N/A';
        $longitude = $row['Longitude'] !== null ? htmlspecialchars($row['Longitude'], ENT_QUOTES, 'UTF-8') : 'N/A';

        $newOutput .= "<tr data-id='{$row['id']}'>
            <td>{$name}</td>
            <td>{$barangay}</td>
            <td>{$crops}</td>
            <td>{$row['Status']}</td>
            <td>{$latitude}</td>
            <td>{$longitude}</td>
            <td>" . htmlspecialchars($row['ReportedBy'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['ReporterType'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>{$harvestDate}</td>
            <td>{$dateReported}</td>
            <td><button class='action-button' onclick='showCropReportDetails({$row['id']})'>View</button></td>
        </tr>";
    }
}

// Generate output for previous reports (paginated)
$previousOutput = '';
if (!empty($allRows['previous'])) {
    // Calculate pagination for previous reports
    $totalPreviousPages = ceil(count($allRows['previous']) / $limit);
    $start = ($page - 1) * $limit;
    $paginatedPrevious = array_slice($allRows['previous'], $start, $limit);

    foreach ($paginatedPrevious as $row) {
        $name = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
        $barangay = htmlspecialchars(ucfirst(strtolower($row['Barangay'])), ENT_QUOTES, 'UTF-8');
        $crops = htmlspecialchars(ucfirst(strtolower($row['Crops'])), ENT_QUOTES, 'UTF-8');
        $harvestDate = htmlspecialchars($row['HarvestDate'], ENT_QUOTES, 'UTF-8');
        $dateReported = htmlspecialchars($row['DateReported'], ENT_QUOTES, 'UTF-8');

        $latitude = $row['Latitude'] !== null ? htmlspecialchars($row['Latitude'], ENT_QUOTES, 'UTF-8') : 'N/A';
        $longitude = $row['Longitude'] !== null ? htmlspecialchars($row['Longitude'], ENT_QUOTES, 'UTF-8') : 'N/A';

        $previousOutput .= "<tr data-id='{$row['id']}'>
            <td>{$name}</td>
            <td>{$barangay}</td>
            <td>{$crops}</td>
            <td>{$row['Status']}</td>
            <td>{$latitude}</td>
            <td>{$longitude}</td>
            <td>" . htmlspecialchars($row['ReportedBy'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['ReporterType'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>{$harvestDate}</td>
            <td>{$dateReported}</td>
            <td><button class='action-button' onclick='showCropReportDetails({$row['id']})'>View</button></td>
        </tr>";
    }
} else {
    $previousOutput = "<tr><td colspan='11'>No previous reports found</td></tr>";
}

// Return JSON response
echo json_encode([
    'newOutput' => $newOutput,
    'previousOutput' => $previousOutput,
    'total' => $newCount + $previousCount,
    'newCount' => $newCount,
    'previousCount' => $previousCount,
    'currentPage' => $page,
    'totalPages' => ceil($previousCount / $limit),
    'debug' => [
        'page' => $page,
        'limit' => $limit,
        'totalPrevious' => $previousCount,
        'paginatedCount' => count($paginatedPrevious ?? [])
    ]
]);

$stmt->close();
$conn->close();
?>
