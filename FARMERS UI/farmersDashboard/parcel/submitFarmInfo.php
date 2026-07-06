<?php
session_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $pdo = scaims_db_pdo();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => "Connection failed: " . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Log the incoming POST data
    error_log(print_r($_POST, true));

    // Validate required fields
    if (empty($_POST['farmType'])) {
        echo json_encode(['success' => false, 'message' => "Farm Type is required."]);
        exit;
    }
    if (empty($_POST['numCrops_1'])) {
        echo json_encode(['success' => false, 'message' => "Number of Crops is required."]);
        exit;
    }
    if (!isset($_SESSION['UName'])) {
        echo json_encode(['success' => false, 'message' => "User not logged in."]);
        exit;
    }

    $farmLocation = $_POST['location_1'];
    $farmArea = $_POST['totalArea_1'];
    $numParcels = 1;
    $farmType = $_POST['farmType'];
    $organic = $_POST['organic'];
    $created_by = $_SESSION['UName'];

    try {
        $stmt = $pdo->prepare("INSERT INTO farm_parcels (farm_location, farm_area, num_parcels, farm_type, organic, created_by) 
                               VALUES (:farm_location, :farm_area, :num_parcels, :farm_type, :organic, :created_by)");

        $stmt->execute([
            ':farm_location' => $farmLocation,
            ':farm_area' => $farmArea,
            ':num_parcels' => $numParcels,
            ':farm_type' => $farmType,
            ':organic' => $organic,
            ':created_by' => $created_by
        ]);

        $farmParcelId = $pdo->lastInsertId();

        // Fetch the last parcel number for the user
        $stmt = $pdo->prepare("SELECT MAX(parcel_number) AS last_parcel_number FROM parcel_details WHERE created_by = :created_by");
        $stmt->execute([':created_by' => $created_by]);
        $lastParcelNumber = $stmt->fetchColumn();
        $nextParcelNumber = $lastParcelNumber ? $lastParcelNumber + 1 : 1; // Start from 1 if no parcels exist

        $totalFarmArea = 0; // Initialize total farm area

        for ($i = 1; $i <= $numParcels; $i++) {
            $location = $_POST["location_$i"];
            $totalArea = $_POST["totalArea_$i"];
            $ownershipType = $_POST["ownership_$i"];
            if ($ownershipType === 'other') {
                $ownershipType = $_POST["customOwnership_$i"];
            }
            $numCrops = $_POST["numCrops_$i"];

            // Insert parcel details with the correct parcel number
            $parcelStmt = $pdo->prepare("INSERT INTO parcel_details 
                (farm_parcel_id, location, total_area, ownership_type, parcel_number, created_by)
                VALUES (:farm_parcel_id, :location, :total_area, :ownership_type, :parcel_number, :created_by)");

            $parcelStmt->execute([
                ':farm_parcel_id' => $farmParcelId,
                ':location' => $location,
                ':total_area' => $totalArea,
                ':ownership_type' => $ownershipType,
                ':parcel_number' => $nextParcelNumber++,
                ':created_by' => $created_by
            ]);

            $parcelDetailId = $pdo->lastInsertId();

            // Insert crop details for each crop
            for ($j = 1; $j <= $numCrops; $j++) {
                $cropCommodity = $_POST["crop_{$i}_{$j}"];
                $cropType = $_POST["cropType_{$i}_{$j}"];
                $cropVariety = $_POST["cropVariety_{$i}_{$j}"];
                $numTrees = !empty($_POST["numTrees_{$i}_{$j}"]) ? $_POST["numTrees_{$i}_{$j}"] : null;
                $treeStatus = !empty($_POST["treeStatus_{$i}_{$j}"]) ? $_POST["treeStatus_{$i}_{$j}"] : null;

                $cropStmt = $pdo->prepare("INSERT INTO crop_details 
                    (parcel_detail_id, crop_commodity, crop_type, crop_variety, num_trees, tree_status, created_by)
                    VALUES (:parcel_detail_id, :crop_commodity, :crop_type, :crop_variety, :num_trees, :tree_status, :created_by)");

                $cropStmt->execute([
                    ':parcel_detail_id' => $parcelDetailId,
                    ':crop_commodity' => $cropCommodity,
                    ':crop_type' => $cropType,
                    ':crop_variety' => $cropVariety,
                    ':num_trees' => $numTrees,
                    ':tree_status' => $treeStatus,
                    ':created_by' => $created_by
                ]);
            }

            $totalFarmArea += $totalArea; // Add to total farm area
        }

        // After inserting all parcels, update the total area in farm_parcels
        $updateStmt = $pdo->prepare("UPDATE farm_parcels SET farm_area = :total_farm_area WHERE id = :farm_parcel_id");
        $updateStmt->execute([
            ':total_farm_area' => $totalFarmArea,
            ':farm_parcel_id' => $farmParcelId
        ]);

        echo json_encode(['success' => true, 'message' => "Farm parcel and details added successfully!"]);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => "Error: " . htmlspecialchars($e->getMessage())]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => "Invalid request method."]);
    exit;
}
?>
