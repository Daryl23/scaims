<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
// Completely suppress all output and errors
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once dirname(__DIR__, 3) . '/conn.php';

// Capture any potential errors
$errorMessage = '';

try {
    $conn = scaims_db_mysqli();

    // Set connection charset and collation
    $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

    // Diagnostic query to check table collations
    $collationQuery = "
        SELECT 
            TABLE_SCHEMA, 
            TABLE_NAME, 
            COLUMN_NAME, 
            COLLATION_NAME
        FROM 
            INFORMATION_SCHEMA.COLUMNS
        WHERE 
            TABLE_SCHEMA = '" . $conn->real_escape_string(SCAIMS_DB_NAME) . "' AND 
            TABLE_NAME IN ('farmer', 'user', 'farm_parcels', 'parcel_details')
    ";
    $collationResult = $conn->query($collationQuery);
    $collations = [];
    while ($row = $collationResult->fetch_assoc()) {
        $collations[] = $row;
    }

    $sql = "SELECT 
        f.FarmerID, 
        CONCAT(f.FirstName, ' ', f.Surname) AS FarmerName,
        COALESCE(MAX(f.Barangay), 'Unknown') AS FarmLocation,
        COALESCE(COUNT(DISTINCT pd.id), 0) AS NumberOfParcels,
        GROUP_CONCAT(DISTINCT cd.crop_commodity) as Commodities,
        GROUP_CONCAT(DISTINCT cd.crop_type) as CropTypes
    FROM 
        farmer f
    LEFT JOIN 
        user u ON f.UserID = u.UserID
    LEFT JOIN 
        farm_parcels fp ON fp.created_by = u.UName
    LEFT JOIN 
        parcel_details pd ON pd.farm_parcel_id = fp.id
    LEFT JOIN 
        crop_details cd ON cd.parcel_detail_id = pd.id
    GROUP BY 
        f.FarmerID, f.FirstName, f.Surname
    ORDER BY 
        f.FarmerID
    ";

    $result = $conn->query($sql);

    if ($result === false) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $rows = [];
    while($row = $result->fetch_assoc()) {
        $rows[] = [
            'FarmerID' => intval($row['FarmerID']),
            'FarmerName' => htmlspecialchars($row['FarmerName']),
            'FarmLocation' => htmlspecialchars($row['FarmLocation']),
            'NumberOfParcels' => intval($row['NumberOfParcels']),
            'Commodities' => $row['Commodities'] ? htmlspecialchars($row['Commodities']) : '',
            'CropTypes' => $row['CropTypes'] ? htmlspecialchars($row['CropTypes']) : ''
        ];
    }

    $conn->close();

    // Clear any accidental output
    ob_end_clean();

    // Send the response
    header('Content-Type: application/json');
    echo json_encode([
        'farmers' => $rows,
        'error' => null
    ]);
    exit;

} catch (Exception $e) {
    // Clear any output
    ob_end_clean();

    // Send error as JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>
