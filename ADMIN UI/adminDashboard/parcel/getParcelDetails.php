<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the error log file
ini_set('log_e rrors', 1);
ini_set('error_log', '/path/to/your/error.log'); // Change this to your desired log file path

require_once dirname(__DIR__, 3) . '/conn.php';

// Initialize error condition
$errorCondition = false; // Initialize to false
$errorMessage = ''; // Initialize error message
$parcelDetails = []; // Initialize parcel details

// Fetch the farmerID from the request
$farmerID = intval($_GET['farmerID']);
error_log("Fetching details for Farmer ID: " . $farmerID); // Log the farmerID

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    $errorCondition = true; // Set error condition to true
    $errorMessage = $e->getMessage(); // Set error message
}

// Updated query to fetch comprehensive parcel details
$sql = "SELECT 
    f.FarmerID,
    f.FirstName,
    f.Surname,
    CONCAT(f.FirstName, ' ', f.Surname) AS FarmerName,
    u.UName,
    fp.id AS farm_parcel_id,
    fp.farm_location,
    fp.farm_area,
    fp.num_parcels,
    fp.farm_type,
    fp.organic,
    pd.id AS parcel_detail_id,
    pd.location AS parcel_location,
    pd.total_area,
    pd.ownership_type,
    cd.id AS crop_detail_id,
    cd.crop_commodity,
    cd.crop_type,
    cd.crop_variety,
    cd.num_trees,
    cd.tree_status
FROM 
    farmer f
JOIN 
    user u ON f.UserID = u.UserID
LEFT JOIN 
    farm_parcels fp ON fp.created_by = u.UName
LEFT JOIN 
    parcel_details pd ON pd.farm_parcel_id = fp.id
LEFT JOIN
    crop_details cd ON cd.parcel_detail_id = pd.id
WHERE 
    f.FarmerID = ?
ORDER BY 
    fp.id, pd.id, cd.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $farmerID);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    // Group crops by parcel
    $parcelKey = $row['farm_parcel_id'];
    if (!isset($rows[$parcelKey])) {
        $rows[$parcelKey] = [
            'parcel_location' => $row['parcel_location'],
            'farm_area' => $row['farm_area'],
            'ownership_type' => $row['ownership_type'],
            'crops' => [],
            'farm_type' => $row['farm_type'],
            'organic' => $row['organic']
        ];
    }
    
    // Add crop details
    if ($row['crop_detail_id']) {
        $rows[$parcelKey]['crops'][] = [
            'crop_commodity' => $row['crop_commodity'],
            'crop_type' => $row['crop_type'],
            'crop_variety' => $row['crop_variety'],
            'num_trees' => $row['num_trees'],
            'tree_status' => $row['tree_status']
        ];
    }
}

// Log the number of rows fetched
error_log("Number of rows fetched: " . count($rows));

$stmt->close();

// New query to get total farm area and total number of parcels for the user
$totalAreaSql = "SELECT SUM(pd.total_area) AS total_farm_area, COUNT(pd.id) AS total_parcels
                  FROM parcel_details pd
                  JOIN farm_parcels fp ON pd.farm_parcel_id = fp.id
                  WHERE fp.created_by = (SELECT u.UName FROM user u WHERE u.UserID = ?)";
$totalAreaStmt = $conn->prepare($totalAreaSql);
$totalAreaStmt->bind_param("i", $farmerID);
$totalAreaStmt->execute();
$totalAreaResult = $totalAreaStmt->get_result();
$totalAreaData = $totalAreaResult->fetch_assoc();
$totalAreaStmt->close();

$conn->close();

// Debug: Print out detailed information
header('Content-Type: application/json');

// Check for errors before returning JSON
if ($errorCondition) {
    echo json_encode(['error' => $errorMessage]);
    exit; // Ensure no further output is sent
}

if (empty($rows)) {
    // If no rows, check if the farmer exists
    $farmerCheckSql = "SELECT COUNT(*) AS farmer_exists FROM farmer WHERE FarmerID = ?";
    $farmerCheckStmt = $conn->prepare($farmerCheckSql);
    $farmerCheckStmt->bind_param("i", $farmerID);
    $farmerCheckStmt->execute();
    $farmerCheckResult = $farmerCheckStmt->get_result();
    $farmerExists = $farmerCheckResult->fetch_assoc()['farmer_exists'] > 0;
    $farmerCheckStmt->close();

    if (!$farmerExists) {
        echo json_encode(['error' => 'Farmer not found', 'farmerID' => $farmerID]);
    } else {
        echo json_encode(['error' => 'No parcel details found for this farmer', 'farmerID' => $farmerID]);
    }
} else {
    // Prepare final response
    $response = [
        'farmerData' => array_values($rows), // Convert associative array to indexed array
        'totalFarmArea' => $totalAreaData['total_farm_area'] ?? 0,
        'totalParcels' => count($rows) // Count parcels
    ];

    echo json_encode($response);
}
?>
