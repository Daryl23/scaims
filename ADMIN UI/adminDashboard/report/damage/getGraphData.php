<?php
require_once __DIR__ . '/../../../../shared/admin_auth.php';
scaims_require_admin('../../../adminLogin/adminLogin.html');
require_once 'db_connection.php';
require_once dirname(__DIR__, 4) . '/shared/crop_catalog.php';

try {
    $barangay = $_POST['barangay'] ?? '';
    $filterDate = $_POST['filterDate'] ?? '';
    $cropFilter = $_POST['filter'] ?? '';

    // Base query structure depends on whether a crop filter is applied
    if ($cropFilter && $cropFilter !== '' && $cropFilter !== 'Other') {
        // When crop is filtered, show damage for that specific crop type
        $sql = "SELECT 
                    dmgCropType as cropType,
                    AVG(percent_damage) as averageDamage,
                    COUNT(*) as reportCount
                FROM damage_reports 
                WHERE percent_damage > 0
                AND " . scaims_crop_match_condition('dmgCropType');
        
        $params = [$cropFilter, scaims_crop_filter_slug($cropFilter)];
        $types = "ss";

        // Add barangay filter if specified
        if ($barangay) {
            $sql .= " AND dmgLocation = ?";
            $params[] = $barangay;
            $types .= "s";
        }

        // Add date filter if specified
        if ($filterDate) {
            $sql .= " AND DATE_FORMAT(reportDate, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')";
            $params[] = $filterDate . "-01";
            $types .= "s";
        }

        $sql .= " GROUP BY dmgCropType
                  HAVING reportCount > 0
                  ORDER BY averageDamage DESC";
    } else {
        // Original query for all crops
        $sql = "SELECT 
                    dmgCropType as cropType,
                    AVG(percent_damage) as averageDamage,
                    COUNT(*) as reportCount
                FROM damage_reports 
                WHERE percent_damage > 0";

        $params = [];
        $types = "";

        if ($cropFilter === 'Other') {
            $predefinedCrops = scaims_get_active_crop_names($conn);
            if (!empty($predefinedCrops)) {
                $placeholders = implode(',', array_fill(0, count($predefinedCrops), '?'));
                $sql .= " AND LOWER(dmgCropType) NOT IN ($placeholders)";
                foreach ($predefinedCrops as $cropName) {
                    $params[] = strtolower($cropName);
                    $types .= "s";
                }
            }
        }

        // Add barangay filter
        if ($barangay) {
            $sql .= " AND dmgLocation = ?";
            $params[] = $barangay;
            $types .= "s";
        }

        // Add date filter
        if ($filterDate) {
            $sql .= " AND DATE_FORMAT(reportDate, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')";
            $params[] = $filterDate . "-01";
            $types .= "s";
        }

        $sql .= " GROUP BY dmgCropType
                  HAVING reportCount > 0
                  ORDER BY averageDamage DESC";
    }

    $stmt = $conn->prepare($sql);
    
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $data = [];

    while ($row = $result->fetch_assoc()) {
        // Format for crop-based results (now used for both filtered and unfiltered)
        $data[] = [
            'cropType' => ucwords(str_replace('-', ' ', $row['cropType'])),
            'percentage' => round($row['averageDamage'], 1),
            'count' => $row['reportCount']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'barangay' => $barangay ? ucwords(str_replace('-', ' ', $barangay)) : 'All Barangays',
        'cropName' => $cropFilter ? ucwords(str_replace('-', ' ', $cropFilter)) : 'All Crops'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?> 
