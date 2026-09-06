<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
require 'db_connection.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set headers for Excel file
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="Farm_Parcels_Report.xls"');

// Helper functions
function formatText($text) {
    if (!$text) return 'N/A';
    return ucwords(str_replace('_', ' ', $text));
}

function cleanData($str) {
    if (!$str) return 'N/A';
    return htmlspecialchars(trim($str));
}

// Add the capitalizeFirstLetter function
function capitalizeFirstLetter($string) {
    if (!$string) return 'N/A';
    return ucfirst(strtolower($string));
}

// Get filter parameters
$barangay = isset($_POST['barangay']) ? $conn->real_escape_string($_POST['barangay']) : '';
$search = isset($_POST['search']) ? $conn->real_escape_string($_POST['search']) : '';
$commodity = isset($_POST['commodity']) ? $conn->real_escape_string($_POST['commodity']) : '';
$cropType = isset($_POST['cropType']) ? $conn->real_escape_string($_POST['cropType']) : '';

// Updated query to include all necessary fields and proper summations
$query = "SELECT DISTINCT
    f.FarmerID,
    CONCAT(f.FirstName, ' ', f.Surname) AS FarmerName,
    f.Barangay AS FarmLocation,
    fp.farm_type AS FarmType,
    fp.organic AS OrganicPractitioner,
    pd.id AS ParcelID,
    pd.parcel_number AS ParcelNumber,
    pd.location AS ParcelLocation,
    pd.total_area AS ParcelArea,
    pd.ownership_type AS OwnershipType,
    cd.crop_commodity AS CropCommodity,
    cd.crop_type AS CropType,
    cd.crop_variety AS CropVariety,
    cd.num_trees AS NumberOfTrees,
    cd.tree_status AS TreeStatus
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
WHERE 1=1";

if (!empty($search)) {
    $query .= " AND CONCAT(f.FirstName, ' ', f.Surname) LIKE '%$search%'";
}
if (!empty($barangay)) {
    $query .= " AND f.Barangay LIKE '%$barangay%'";
}
if (!empty($commodity)) {
    $query .= " AND cd.crop_commodity LIKE '%$commodity%'";
}
if (!empty($cropType)) {
    $query .= " AND cd.crop_type LIKE '%$cropType%'";
}

$query .= " ORDER BY f.FarmerID";

$result = $conn->query($query);

// Start HTML output
echo '
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style>
    .title { font-size: 16pt; font-weight: bold; text-align: center; }
    .header { background-color: #198754; color: white; }
    td { padding: 5px; text-align: center; border: 1px solid black; }
    th { padding: 5px; text-align: center; background-color: #198754; color: white; border: 1px solid black; }
    .farmer-name { font-size: 14pt; font-weight: bold; text-align: left; padding: 10px 0; }
    .parcel-info { border: 2px solid black; margin-bottom: 10px; padding: 10px; }
    .parcel-details { border: 2px solid black; margin-bottom: 10px; padding: 10px; }
    .crop-details { border: 2px solid black; margin-bottom: 10px; padding: 10px; }
</style>
</head>
<body>
    <div class="title">LIPA CITY AGRICULTURE OFFICE</div>
    <div class="title">Farm Parcels Report</div>
    <div style="text-align: center;">Generated on: ' . date('F d, Y') . '</div>
    <br>';

// Modified data processing section
$farmData = [];
while ($row = $result->fetch_assoc()) {
    $farmerId = $row['FarmerID'];
    if (!isset($farmData[$farmerId])) {
        // Get total farm area and number of parcels for this farmer
        $totalAreaQuery = "SELECT 
            SUM(pd.total_area) as total_area,
            COUNT(DISTINCT pd.id) as total_parcels
        FROM farmer f
        JOIN user u ON f.UserID = u.UserID
        JOIN farm_parcels fp ON fp.created_by = u.UName
        JOIN parcel_details pd ON pd.farm_parcel_id = fp.id
        WHERE f.FarmerID = '$farmerId'";
        
        $totalsResult = $conn->query($totalAreaQuery);
        $totals = $totalsResult->fetch_assoc();
        
        $farmData[$farmerId] = [
            'farmerInfo' => [
                'name' => $row['FarmerName'],
                'farmLocation' => $row['FarmLocation'],
                'farmArea' => $totals['total_area'],
                'numberOfParcels' => $totals['total_parcels'],
                'farmType' => $row['FarmType'],
                'organic' => $row['OrganicPractitioner']
            ],
            'parcels' => []
        ];
    }
    
    // Organize parcels by parcel number
    if ($row['ParcelID']) {
        $parcelId = $row['ParcelID'];
        if (!isset($farmData[$farmerId]['parcels'][$parcelId])) {
            $farmData[$farmerId]['parcels'][$parcelId] = [
                'parcelNumber' => $row['ParcelNumber'],
                'location' => $row['ParcelLocation'],
                'area' => $row['ParcelArea'],
                'ownershipType' => $row['OwnershipType'],
                'crops' => []
            ];
        }
        if ($row['CropCommodity']) {
            $farmData[$farmerId]['parcels'][$parcelId]['crops'][] = [
                'commodity' => $row['CropCommodity'],
                'type' => $row['CropType'],
                'variety' => $row['CropVariety'],
                'numTrees' => $row['NumberOfTrees'],
                'treeStatus' => $row['TreeStatus']
            ];
        }
    }
}

// Modified output section
foreach ($farmData as $farmerData) {
    echo '<div class="farmer-name">Parcel Details of ' . cleanData($farmerData['farmerInfo']['name']) . '</div>';
    
    // Farm Information Section
    echo '<div class="parcel-info">';
    echo '<table width="100%" cellpadding="3" cellspacing="0">';
    echo '<tr><th colspan="11">FARM PARCEL INFORMATION</th></tr>';
    echo '<tr>
        <td colspan="2"><strong>Farm Location:</strong> ' . cleanData(formatText($farmerData['farmerInfo']['farmLocation'])) . '</td>
        <td colspan="3"><strong>Total Farm Area:</strong> ' . number_format($farmerData['farmerInfo']['farmArea'], 4) . ' hectares</td>
        <td colspan="2"><strong>Number of Parcels:</strong> ' . $farmerData['farmerInfo']['numberOfParcels'] . '</td>
        <td colspan="2"><strong>Farm Type:</strong> ' . cleanData(formatText($farmerData['farmerInfo']['farmType'])) . '</td>
        <td colspan="2"><strong>Organic Practitioner:</strong> ' . cleanData(capitalizeFirstLetter($farmerData['farmerInfo']['organic'])) . '</td>
    </tr></table></div><br>';

    // Individual Parcel Sections
    foreach ($farmerData['parcels'] as $parcel) {
        echo '<div class="parcel-details">';
        echo '<table width="100%" cellpadding="3" cellspacing="0">';
        
        // Parcel Header
        echo '<tr><th colspan="11">PARCEL ' . $parcel['parcelNumber'] . ' DETAILS</th></tr>';
        echo '<tr>
            <td colspan="4"><strong>Parcel Location:</strong> ' . cleanData(formatText($parcel['location'])) . '</td>
            <td colspan="4"><strong>Parcel Area:</strong> ' . number_format($parcel['area'], 4) . ' hectares</td>
            <td colspan="3"><strong>Ownership Type:</strong> ' . cleanData(formatText($parcel['ownershipType'])) . '</td>
        </tr>';

        // Crops Header
        echo '<tr><th colspan="11">CROPS UNDER PARCEL ' . $parcel['parcelNumber'] . '</th></tr>';
        echo '<tr>
            <th>Commodity</th>
            <th>Crop Type</th>
            <th>Crop Variety</th>
            <th>Number of Trees</th>
            <th>Tree Status</th>
        </tr>';

        // Crops Data
        foreach ($parcel['crops'] as $index => $crop) {
            echo '<tr>
                <td>' . cleanData(formatText($crop['commodity'])) . '</td>
                <td>' . cleanData(formatText($crop['type'])) . '</td>
                <td>' . cleanData(formatText($crop['variety'])) . '</td>
                <td>' . cleanData($crop['numTrees']) . '</td>
                <td>' . cleanData(formatText($crop['treeStatus'])) . '</td>
            </tr>';
        }
        
        echo '</table></div><br>';
    }
    
    echo '<br><br>';
}

echo '</body></html>';

$conn->close();

exit;
?>