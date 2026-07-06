<?php
session_start(); 

require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $report_type = $_POST["report-type"];
    $farmerId = null;

    function parseCoordinateValue($value) {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    function normalizeCoordinateValue($value, $min, $max) {
        if ($value === null) {
            return null;
        }

        if (!is_numeric($value)) {
            return false;
        }

        $numericValue = (float) $value;
        if ($numericValue < $min || $numericValue > $max) {
            return false;
        }

        return number_format($numericValue, 7, '.', '');
    }

    if (isset($_SESSION['UName'])) {
        $farmerLookup = $conn->prepare("SELECT f.FarmerID FROM farmer f JOIN user u ON f.UserID = u.UserID WHERE u.UName = ? LIMIT 1");
        if ($farmerLookup) {
            $farmerLookup->bind_param("s", $_SESSION['UName']);
            $farmerLookup->execute();
            $farmerLookupResult = $farmerLookup->get_result();
            if ($farmerLookupResult && $farmerLookupResult->num_rows > 0) {
                $farmerRow = $farmerLookupResult->fetch_assoc();
                $farmerId = (int) $farmerRow['FarmerID'];
            }
            $farmerLookup->close();
        }
    }

    if ($report_type === "crop") {
        $location = $_POST["crop-location"];
        $crop_commodity = $_POST["crop-commodity"];
        $crop_type = $_POST["crop-type"];
        if ($crop_type === "Other") {
            $crop_type = $_POST["other-crop-type"];
        }
        $crop_variety = $_POST["crop-variety"];
        $date_planted = $_POST["date-planted"];
        $harvest_date = $_POST["harvest-date"] ?? null;
        $estimate_harvest = $_POST["estimate-harvest"] ?? null;
        $crop_status = $_POST["crop-status"];
        $farm_gate_price = $_POST["farm-gate-price"];
        $number_of_trees = $_POST["number-of-trees"] ?? null;
        $latitude = normalizeCoordinateValue(parseCoordinateValue($_POST["crop-latitude"] ?? ''), -90, 90);
        $longitude = normalizeCoordinateValue(parseCoordinateValue($_POST["crop-longitude"] ?? ''), -180, 180);
        $created_by = $_SESSION['UName'];

        if (($latitude === null) xor ($longitude === null)) {
            $_SESSION['message'] = 'Please provide both latitude and longitude, or leave both empty.';
            header("Location: report.php");
            exit();
        }

        if ($latitude === false || $longitude === false) {
            $_SESSION['message'] = 'Coordinates are invalid. Latitude must be between -90 and 90, and longitude must be between -180 and 180.';
            header("Location: report.php");
            exit();
        }

        // Check for null values before executing the SQL statement
        if ($harvest_date === null || $estimate_harvest === null) {
            $_SESSION['message'] = 'Harvest date and estimated harvest cannot be null.';
            header("Location: report.php");
            exit();
        }

        // Updated SQL with crop_status and number_of_trees
        $stmt = $conn->prepare("INSERT INTO crop_reports (
            location, 
            crop_commodity, 
            crop_type, 
            crop_variety, 
            date_planted, 
            harvest_date, 
            estimate_harvest, 
            FarmerID,
            crop_status,
            farm_gate_price, 
            number_of_trees,
            latitude,
            longitude,
            reportDate, 
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NOW(), ?)");
        
        $stmt->bind_param("sssssssissssss", 
            $location, 
            $crop_commodity, 
            $crop_type, 
            $crop_variety, 
            $date_planted, 
            $harvest_date, 
            $estimate_harvest, 
            $farmerId,
            $crop_status,
            $farm_gate_price,
            $number_of_trees,
            $latitude,
            $longitude,
            $created_by
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'New crop report submitted successfully✅'; 
        } else {
            $_SESSION['message'] = 'Error: ' . $stmt->error; 
        }

        $stmt->close();
    
    } elseif ($report_type === "damage") {
        try {
            // Get form data
            $location = $_POST["damage-location"];
            $crop_commodity = $_POST["damage-crop-commodity"];
            $crop_type = $_POST["damage-crop-type"];
            if ($crop_type === "Other") {
                $crop_type = $_POST["other-damage-crop-type"];
            }
            $crop_variety = $_POST["damage-crop-variety"];
            $date_occurrence = $_POST["date-occurrence"];
            $damage_cause = $_POST["damage-cause"];
            $percent_damage = $_POST["damage-percentage"];
            $estimated_loss = $_POST["estimated-loss"];
            $farm_gate_price = $_POST["damage-farm-gate-price"];
            $damage_number_of_trees = $_POST["damage-number-of-trees"] ?? null;
            $latitude = normalizeCoordinateValue(parseCoordinateValue($_POST["damage-latitude"] ?? ''), -90, 90);
            $longitude = normalizeCoordinateValue(parseCoordinateValue($_POST["damage-longitude"] ?? ''), -180, 180);
            $created_by = $_SESSION['UName'];

            if (($latitude === null) xor ($longitude === null)) {
                $_SESSION['message'] = 'Please provide both latitude and longitude, or leave both empty.';
                header("Location: report.php");
                exit();
            }

            if ($latitude === false || $longitude === false) {
                $_SESSION['message'] = 'Coordinates are invalid. Latitude must be between -90 and 90, and longitude must be between -180 and 180.';
                header("Location: report.php");
                exit();
            }
            
            // Updated SQL with damage_number_of_trees
            $sql = "INSERT INTO damage_reports (
                dmgLocation, 
                dmgCropCommodity, 
                dmgCropType, 
                dmgCropVariety, 
                date_occurrence, 
                damage_cause, 
                percent_damage, 
                estimated_loss,
                FarmerID,
                dmgFarmGatePrice, 
                damage_number_of_trees,
                latitude,
                longitude,
                reportDate,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NOW(), ?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("ssssssssisssss",
                $location,
                $crop_commodity,
                $crop_type,
                $crop_variety,
                $date_occurrence,
                $damage_cause,
                $percent_damage,
                $estimated_loss,
                $farmerId,
                $farm_gate_price,
                $damage_number_of_trees,
                $latitude,
                $longitude,
                $created_by
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = "Damage report submitted successfully✅";
            } else {
                throw new Exception("No rows were inserted.");
            }

            $stmt->close();

        } catch (Exception $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
            error_log("Error in damage report submission: " . $e->getMessage());
        }
    }

    $conn->close();
    header("Location: report.php");
    exit();
}
?>
