<?php
require_once 'db_connection.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$id = $_POST['id'] ?? '';

if (is_numeric($id)) {
    $sql = "SELECT cr.id, 
                   COALESCE(CONCAT(rf.FirstName, ' ', rf.Surname), CONCAT(cf.FirstName, ' ', cf.Surname), cr.created_by) AS Name,
                   cr.location AS Barangay,
                   cr.crop_commodity AS CropCommodity,
                   cr.crop_type AS Crop, 
                   cr.crop_variety AS CropVariety, 
                   cr.date_planted AS DatePlanted, 
                   cr.harvest_date AS HarvestDate, 
                   cr.estimate_harvest AS EstimatedHarvest,
                   cr.crop_status AS CropStatus,
                   cr.farm_gate_price AS FarmGatePrice,
                   cr.number_of_trees AS NumberOfTrees,
                   cr.latitude AS Latitude,
                   cr.longitude AS Longitude,
                   cr.reportDate AS DateReported,
                   COALESCE(ca.AdminName, CONCAT(cf.FirstName, ' ', cf.Surname), cr.created_by) AS ReportedBy,
                   CASE WHEN ca.AdminName IS NOT NULL THEN 'Admin' ELSE 'Farmer' END AS ReporterType
            FROM crop_reports cr
            LEFT JOIN farmer rf ON cr.FarmerID = rf.FarmerID
            LEFT JOIN user cu ON cr.created_by = cu.UName
            LEFT JOIN farmer cf ON cu.UserID = cf.UserID
            LEFT JOIN admin ca ON cr.created_by = ca.AdminName
            WHERE cr.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $cropReportDetails = $result->fetch_assoc();
        
        // Apply ucwords to relevant fields
        $cropReportDetails['Name'] = htmlspecialchars(ucwords($cropReportDetails['Name']), ENT_QUOTES, 'UTF-8');
        $cropReportDetails['Barangay'] = htmlspecialchars(ucwords($cropReportDetails['Barangay']), ENT_QUOTES, 'UTF-8');
        $cropReportDetails['CropCommodity'] = htmlspecialchars(ucwords($cropReportDetails['CropCommodity']), ENT_QUOTES, 'UTF-8');
        $cropReportDetails['Crop'] = htmlspecialchars(ucwords($cropReportDetails['Crop']), ENT_QUOTES, 'UTF-8');
        $cropReportDetails['CropVariety'] = htmlspecialchars(ucwords($cropReportDetails['CropVariety']), ENT_QUOTES, 'UTF-8');
        $cropReportDetails['CropStatus'] = htmlspecialchars(ucwords($cropReportDetails['CropStatus']), ENT_QUOTES, 'UTF-8');
        $cropReportDetails['EstimatedHarvest'] = htmlspecialchars($cropReportDetails['EstimatedHarvest'], ENT_QUOTES, 'UTF-8');
        $cropReportDetails['FarmGatePrice'] = htmlspecialchars($cropReportDetails['FarmGatePrice'], ENT_QUOTES, 'UTF-8');
        $cropReportDetails['NumberOfTrees'] = htmlspecialchars($cropReportDetails['NumberOfTrees'], ENT_QUOTES, 'UTF-8');
        $cropReportDetails['Latitude'] = htmlspecialchars($cropReportDetails['Latitude'] ?? '', ENT_QUOTES, 'UTF-8');
        $cropReportDetails['Longitude'] = htmlspecialchars($cropReportDetails['Longitude'] ?? '', ENT_QUOTES, 'UTF-8');
        $cropReportDetails['DateReported'] = htmlspecialchars($cropReportDetails['DateReported'], ENT_QUOTES, 'UTF-8');
        $cropReportDetails['ReportedBy'] = htmlspecialchars(ucwords($cropReportDetails['ReportedBy']), ENT_QUOTES, 'UTF-8');
        $cropReportDetails['ReporterType'] = htmlspecialchars($cropReportDetails['ReporterType'], ENT_QUOTES, 'UTF-8');

        error_log("Crop Harvest Report Details: " . json_encode($cropReportDetails));
        echo json_encode($cropReportDetails);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Crop Harvest Report not found"]);
    }

    if ($stmt->error) {
        error_log("SQL Error: " . $stmt->error);
    }

    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing crop harvest report ID"]);
}

$conn->close();
?>
