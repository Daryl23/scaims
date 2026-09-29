<?php
require_once __DIR__ . '/../../../../shared/admin_auth.php';
scaims_require_admin('../../../adminLogin/adminLogin.html');
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
    $page = max(1, isset($_POST['page']) ? (int) $_POST['page'] : 1);
    $limit = 10;
    $filterDate = $_POST['filterDate'] ?? '';
    $predefinedCrops = $_POST['predefinedCrops'] ?? scaims_get_active_crop_names($conn);
    if (!is_array($predefinedCrops)) {
        $predefinedCrops = scaims_get_active_crop_names($conn);
    }

    $select = "SELECT dr.id,
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
            ";
    $fromWhere = "FROM damage_reports dr
            LEFT JOIN farmer rf ON dr.FarmerID = rf.FarmerID
            LEFT JOIN user cu ON dr.created_by = cu.UName
            LEFT JOIN farmer cf ON cu.UserID = cf.UserID
            LEFT JOIN admin ca ON dr.created_by = ca.AdminName
            WHERE 1=1";

    $params = [];
    $types = '';

    if ($filter === 'Other' && is_array($predefinedCrops) && !empty($predefinedCrops)) {
        $placeholders = implode(',', array_fill(0, count($predefinedCrops), '?'));
        $fromWhere .= " AND LOWER(dr.dmgCropType) NOT IN ($placeholders)";
        foreach ($predefinedCrops as $cropName) {
            $params[] = strtolower($cropName);
            $types .= 's';
        }
    } elseif ($filter && $filter !== 'Other') {
        $fromWhere .= " AND " . scaims_crop_match_condition('dr.dmgCropType');
        $params[] = $filter;
        $params[] = scaims_crop_filter_slug($filter);
        $types .= 'ss';
    }

    if ($barangay) {
        $fromWhere .= " AND LOWER(dr.dmgLocation) = LOWER(?)";
        $params[] = $barangay;
        $types .= 's';
    }

    if ($reporterType === 'admin') {
        $fromWhere .= " AND ca.AdminName IS NOT NULL";
    } elseif ($reporterType === 'farmer') {
        $fromWhere .= " AND ca.AdminName IS NULL";
    }

    if ($search) {
        $searchTerm = '%' . $search . '%';
        $fromWhere .= " AND (
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
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $filterDate)) {
            $startDate = $filterDate . '-01';
            $endDate = date('Y-m-d', strtotime($startDate . ' +1 month'));
            $fromWhere .= " AND dr.date_occurrence >= ? AND dr.date_occurrence < ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= 'ss';
        } else {
            $fromWhere .= " AND DATE_FORMAT(dr.date_occurrence, '%Y-%m') = ?";
            $params[] = $filterDate;
            $types .= 's';
        }
    }

    $runQuery = static function (string $sql, string $queryTypes, array $queryParams) use ($conn): mysqli_result {
        $statement = $conn->prepare($sql);
        if (!$statement) {
            throw new Exception('Failed to prepare report query.');
        }
        if ($queryParams) {
            $statement->bind_param($queryTypes, ...$queryParams);
        }
        $statement->execute();
        $queryResult = $statement->get_result();
        $statement->close();
        return $queryResult;
    };

    $ageNew = 'dr.reportDate >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)';
    $agePrevious = '(dr.reportDate < DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) OR dr.reportDate IS NULL)';
    $countSql = "SELECT COUNT(*) AS total,
                        COALESCE(SUM($ageNew), 0) AS new_count,
                        COALESCE(SUM($agePrevious), 0) AS previous_count
                 $fromWhere";
    $countRow = $runQuery($countSql, $types, $params)->fetch_assoc();
    $newCount = (int) ($countRow['new_count'] ?? 0);
    $previousCount = (int) ($countRow['previous_count'] ?? 0);
    $totalReports = $newCount + $previousCount;

    $newRows = $runQuery(
        "$select $fromWhere AND $ageNew ORDER BY dr.reportDate DESC, dr.id DESC",
        $types,
        $params
    )->fetch_all(MYSQLI_ASSOC);

    $offset = ($page - 1) * $limit;
    $previousRows = $runQuery(
        "$select $fromWhere AND $agePrevious ORDER BY dr.reportDate DESC, dr.id DESC LIMIT ? OFFSET ?",
        $types . 'ii',
        array_merge($params, [$limit, $offset])
    )->fetch_all(MYSQLI_ASSOC);

    $newOutput = '';
    foreach ($newRows as $row) {
        $newOutput .= "<tr data-id='" . (int) $row['id'] . "'>
            <td>" . htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars(ucwords(strtolower($row['Barangay'])), ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars(ucwords(strtolower($row['CropType'])), ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['Latitude'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['Longitude'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['ReportedBy'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['ReporterType'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars($row['DateOccurrence'], ENT_QUOTES, 'UTF-8') . "</td>
            <td>" . htmlspecialchars((string) $row['DateReported'], ENT_QUOTES, 'UTF-8') . "</td>
            <td><button class='action-button'>View</button></td>
        </tr>";
    }

    $previousOutput = '';
    if ($previousCount > 0) {
        foreach ($previousRows as $row) {
            $previousOutput .= "<tr data-id='" . (int) $row['id'] . "'>
                <td>" . htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars(ucwords(strtolower($row['Barangay'])), ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars(ucwords(strtolower($row['CropType'])), ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['Latitude'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['Longitude'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['ReportedBy'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['ReporterType'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($row['DateOccurrence'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars((string) $row['DateReported'], ENT_QUOTES, 'UTF-8') . "</td>
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
