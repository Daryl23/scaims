<?php
require_once 'db_connection.php';
require_once dirname(__DIR__, 4) . '/shared/crop_catalog.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $filter = $_POST['filter'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $search = $_POST['search'] ?? '';
    $reporterType = $_POST['reporterType'] ?? '';
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $limit = 10;
    $filterDate = $_POST['filterDate'] ?? '';
    $predefinedCrops = $_POST['predefinedCrops'] ?? scaims_get_active_crop_names($conn);
    if (!is_array($predefinedCrops)) {
        $predefinedCrops = scaims_get_active_crop_names($conn);
    }

    $sql = "SELECT dr.id,
                   COALESCE(CONCAT(rf.FirstName, ' ', rf.Surname), CONCAT(cf.FirstName, ' ', cf.Surname), dr.created_by) AS Name,
                   dr.dmgLocation AS Barangay,
                   dr.dmgCropType AS CropType,
                   dr.latitude AS Latitude,
                   dr.longitude AS Longitude,
                   dr.date_occurrence AS DateOccurrence,
                   dr.reportDate AS DateReported,
                   COALESCE(ca.AdminName, CONCAT(cf.FirstName, ' ', cf.Surname), dr.created_by) AS ReportedBy,
                   CASE WHEN ca.AdminName IS NOT NULL THEN 'Admin' ELSE 'Farmer' END AS ReporterType,
                   CASE
                       WHEN dr.reportDate >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) THEN 'new'
                       ELSE 'previous'
                   END AS report_age
            FROM damage_reports dr
            LEFT JOIN farmer rf ON dr.FarmerID = rf.FarmerID
            LEFT JOIN user cu ON dr.created_by = cu.UName
            LEFT JOIN farmer cf ON cu.UserID = cf.UserID
            LEFT JOIN admin ca ON dr.created_by = ca.AdminName
            WHERE 1=1";

    $params = [];
    $types = '';

    if ($filter === 'Other' && is_array($predefinedCrops) && !empty($predefinedCrops)) {
        $placeholders = implode(',', array_fill(0, count($predefinedCrops), '?'));
        $sql .= " AND LOWER(dr.dmgCropType) NOT IN ($placeholders)";
        foreach ($predefinedCrops as $cropName) {
            $params[] = strtolower($cropName);
            $types .= 's';
        }
    } elseif ($filter && $filter !== 'Other') {
        $sql .= " AND " . scaims_crop_match_condition('dr.dmgCropType');
        $params[] = $filter;
        $params[] = scaims_crop_filter_slug($filter);
        $types .= 'ss';
    }

    if ($barangay) {
        $sql .= " AND LOWER(dr.dmgLocation) = LOWER(?)";
        $params[] = $barangay;
        $types .= 's';
    }

    if ($reporterType === 'admin') {
        $sql .= " AND ca.AdminName IS NOT NULL";
    } elseif ($reporterType === 'farmer') {
        $sql .= " AND ca.AdminName IS NULL";
    }

    if ($search) {
        $searchTerm = '%' . $search . '%';
        $sql .= " AND (
            LOWER(COALESCE(CONCAT(rf.FirstName, ' ', rf.Surname), CONCAT(cf.FirstName, ' ', cf.Surname), '')) LIKE LOWER(?)
            OR LOWER(COALESCE(ca.AdminName, CONCAT(cf.FirstName, ' ', cf.Surname), dr.created_by)) LIKE LOWER(?)
            OR LOWER(dr.dmgLocation) LIKE LOWER(?)
            OR LOWER(dr.dmgCropType) LIKE LOWER(?)
            OR LOWER(dr.dmgCropCommodity) LIKE LOWER(?)
        )";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sssss';
    }

    if ($filterDate) {
        $sql .= " AND DATE_FORMAT(dr.date_occurrence, '%Y-%m') = ?";
        $params[] = $filterDate;
        $types .= 's';
    }

    $sql .= " ORDER BY dr.reportDate DESC, dr.id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare report query: ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $groupedRows = [
        'new' => [],
        'previous' => []
    ];

    while ($row = $result->fetch_assoc()) {
        $groupedRows[$row['report_age']][] = $row;
    }

    $newCount = count($groupedRows['new']);
    $previousCount = count($groupedRows['previous']);
    $totalReports = $newCount + $previousCount;

    $newOutput = '';
    foreach ($groupedRows['new'] as $row) {
        $newOutput .= "<tr data-id='" . (int) $row['id'] . "'>
            <td>" . htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars(ucwords(strtolower($row['Barangay'])), ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars(ucwords(strtolower($row['CropType'])), ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['Latitude'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['Longitude'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['ReportedBy'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['ReporterType'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['DateOccurrence'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['DateReported'], ENT_QUOTES, 'UTF-8') . "</td>
            <td><button class='action-button'>View</button></td>
        </tr>";
    }

    $previousOutput = '';
    if ($previousCount > 0) {
        $start = ($page - 1) * $limit;
        $paginatedPrevious = array_slice($groupedRows['previous'], $start, $limit);

        foreach ($paginatedPrevious as $row) {
            $previousOutput .= "<tr data-id='" . (int) $row['id'] . "'>
                <td>" . htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars(ucwords(strtolower($row['Barangay'])), ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars(ucwords(strtolower($row['CropType'])), ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['Latitude'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['Longitude'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['ReportedBy'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['ReporterType'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['DateOccurrence'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['DateReported'], ENT_QUOTES, 'UTF-8') . "</td>
                <td><button class='action-button'>View</button></td>
            </tr>";
        }
    }

    if ($newOutput === '') {
        $newOutput = "<tr><td colspan='10'>No new reports found</td></tr>";
    }

    if ($previousOutput === '') {
        $previousOutput = "<tr><td colspan='10'>No previous reports found</td></tr>";
    }

    echo json_encode([
        'newOutput' => $newOutput,
        'previousOutput' => $previousOutput,
        'total' => $totalReports,
        'newCount' => $newCount,
        'previousCount' => $previousCount,
        'currentPage' => $page,
        'totalPages' => (int) ceil($previousCount / $limit)
    ]);

    $stmt->close();
} catch (Exception $e) {
    error_log('Error in fetchDamageReports.php: ' . $e->getMessage());
    echo json_encode([
        'error' => $e->getMessage(),
        'newOutput' => "<tr><td colspan='10'>Error loading reports</td></tr>",
        'previousOutput' => "<tr><td colspan='10'>Error loading reports</td></tr>",
        'total' => 0,
        'newCount' => 0,
        'previousCount' => 0
    ]);
}

$conn->close();
?>
