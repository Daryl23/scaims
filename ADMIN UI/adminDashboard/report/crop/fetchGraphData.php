<?php
require_once 'db_connection.php';
require_once dirname(__DIR__, 4) . '/shared/crop_catalog.php';

$cropType = $_POST['cropType'] ?? '';
$year = $_POST['year'] ?? date('Y');

if ($cropType) {
    // SQL query based on whether "Other" is selected or not
    if ($cropType === 'Other') {
        $predefinedCrops = scaims_get_active_crop_names($conn);
        
        $placeholders = str_repeat('?,', count($predefinedCrops) - 1) . '?';
        $sql = "SELECT 
                MONTH(cr.harvest_date) AS month,
                cr.crop_type,
                COUNT(*) AS count,
                SUM(cr.estimate_harvest) as total_harvest
            FROM crop_reports cr
            WHERE LOWER(cr.crop_type) NOT IN ($placeholders)
            AND YEAR(cr.harvest_date) = ?
            GROUP BY MONTH(cr.harvest_date), cr.crop_type
            ORDER BY month ASC, cr.crop_type ASC";
        
        $stmt = $conn->prepare($sql);
        $params = array_merge(array_map('strtolower', $predefinedCrops), [$year]);
        $types = str_repeat('s', count($predefinedCrops)) . 'i';
        $stmt->bind_param($types, ...$params);
    } else {
        // Original query for specific crop type
        $sql = "SELECT 
                MONTH(cr.harvest_date) AS month, 
                COUNT(*) AS count,
                SUM(cr.estimate_harvest) as total_harvest
            FROM crop_reports cr
            WHERE cr.crop_type = ?
            AND YEAR(cr.harvest_date) = ?
            GROUP BY MONTH(cr.harvest_date)
            ORDER BY month ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $cropType, $year);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $yearlyData = [];
    
    // Initialize the selected year with all months set to 0
    $yearlyData[$year] = [
        'months' => range(1, 12),
        'harvestCounts' => array_fill(0, 12, 0),
        'totalHarvest' => array_fill(0, 12, 0),
        'cropTypes' => array_fill(0, 12, []) // Initialize cropTypes array for each month
    ];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $month = $row['month'];
            $monthIndex = $month - 1;

            if ($cropType === 'Other') {
                // Add individual crop type data
                $yearlyData[$year]['cropTypes'][$monthIndex][] = [
                    'type' => $row['crop_type'],
                    'count' => (int)$row['count'],
                    'harvest' => (float)$row['total_harvest']
                ];
                
                // Update total counts for the month
                $yearlyData[$year]['harvestCounts'][$monthIndex] += (int)$row['count'];
                $yearlyData[$year]['totalHarvest'][$monthIndex] += (float)$row['total_harvest'];
            } else {
                // Original behavior for specific crop type
                $yearlyData[$year]['harvestCounts'][$monthIndex] = (int)$row['count'];
                $yearlyData[$year]['totalHarvest'][$monthIndex] = (float)$row['total_harvest'];
            }
        }

        echo json_encode([
            'success' => true,
            'yearlyData' => $yearlyData
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'yearlyData' => $yearlyData  // Return initialized data with zeros
        ]);
    }

    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing crop type"]);
}

$conn->close();
?> 
