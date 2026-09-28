<?php
require_once dirname(__DIR__) . '/shared/farmer-auth.php';
scaims_require_farmer_page('../../farmerLogin/farmerlogin.html');

require_once dirname(__DIR__, 3) . '/conn.php';
require_once dirname(__DIR__, 3) . '/shared/report_image_storage.php';
require_once dirname(__DIR__, 3) . '/shared/gps_photo.php';
require_once dirname(__DIR__, 3) . '/shared/farmer_report_defaults.php';

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_report_image_columns($conn);
    scaims_ensure_gps_photo_columns($conn);
    scaims_ensure_farmer_report_defaults_table($conn);
} catch (RuntimeException $e) {
    $_SESSION['message'] = 'The report database is unavailable right now. Please try again later.';
    header("Location: report.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $report_type = $_POST["report-type"] ?? '';
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

    if ($farmerId === null) {
        $_SESSION['message'] = 'Unable to find your farmer profile. Please sign in again.';
        header("Location: report.php");
        exit();
    }

$gpsPending = scaims_pending_gps_photo($_POST['pending-report-image-token'] ?? null);
if (!$gpsPending || !empty($_FILES['report-image']['tmp_name']) || !empty($_FILES['admin-report-image']['tmp_name']) || !empty($_FILES['crop-report-image']['tmp_name']) || !empty($_FILES['damage-report-image']['tmp_name'])) {
    $_SESSION['message'] = 'Please upload a GPS Camera photo and wait for GPS information to be detected.';
    header('Location: report.php'); exit();
}
$photo = $gpsPending['photo'];

    if ($report_type === "crop") {
        $location = mb_substr($photo['location'], 0, 255);
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
        $latitude = $photo['latitude'];
        $longitude = $photo['longitude'];
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

        $imageResult = scaims_finalize_pending_report_image($gpsPending['token'], $report_type);
        if (!$imageResult['ok'] || empty($imageResult['path'])) {
            $_SESSION['message'] = $imageResult['message'] ?? 'Please upload a GPS Camera photo.';
            header("Location: report.php");
            exit();
        }
        $imagePath = $imageResult['path'] ?? null;

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
            image_path,
            reportDate, 
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, NOW(), ?)");

        if (!$stmt) {
            scaims_delete_report_image($imagePath);
            $_SESSION['message'] = 'Error: ' . $conn->error;
            header("Location: report.php");
            exit();
        }
        
        $stmt->bind_param("sssssssisssssss",
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
            $imagePath,
            $created_by
        );
        
        if (scaims_execute_photo_report($conn, $stmt, $report_type, $photo)) {
            $defaultsSaved = scaims_save_farmer_report_defaults($conn, $farmerId, 'crop', [
                'crop_commodity' => $crop_commodity,
                'crop_type' => $crop_type,
                'crop_variety' => $crop_variety,
                'date_planted' => $date_planted,
                'harvest_date' => $harvest_date,
                'estimate_harvest' => $estimate_harvest,
                'crop_status' => $crop_status,
                'number_of_trees' => $number_of_trees,
                'farm_gate_price' => $farm_gate_price,
            ]);
            $_SESSION['message'] = $defaultsSaved
                ? 'New crop report submitted successfully✅'
                : 'New crop report submitted successfully, but reusable report data could not be saved.';
        } else {
            scaims_delete_report_image($imagePath);
            $_SESSION['message'] = 'Error: ' . $stmt->error; 
        }

        $stmt->close();
    
    } elseif ($report_type === "damage") {
        try {
            // Get form data
            $location = mb_substr($photo['location'], 0, 255);
            $crop_commodity = $_POST["damage-crop-commodity"];
            $crop_type = $_POST["damage-crop-type"];
            if ($crop_type === "Other") {
                $crop_type = $_POST["other-damage-crop-type"];
            }
            $crop_variety = $_POST["damage-crop-variety"];
            $date_occurrence = $photo['date'];
            $damage_cause = $_POST["damage-cause"];
            $percent_damage = $_POST["damage-percentage"];
            $estimated_loss = $_POST["estimated-loss"];
            $farm_gate_price = $_POST["damage-farm-gate-price"];
            $damage_number_of_trees = $_POST["damage-number-of-trees"] ?? null;
            $latitude = $photo['latitude'];
            $longitude = $photo['longitude'];
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

            $imageResult = scaims_finalize_pending_report_image($gpsPending['token'], $report_type);
            if (!$imageResult['ok'] || empty($imageResult['path'])) {
                $_SESSION['message'] = $imageResult['message'] ?? 'Please upload a GPS Camera photo.';
                header("Location: report.php");
                exit();
            }
            $imagePath = $imageResult['path'] ?? null;
            
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
                image_path,
                reportDate,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, NOW(), ?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("ssssssssissssss",
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
                $imagePath,
                $created_by
            );

            if (!scaims_execute_photo_report($conn, $stmt, $report_type, $photo)) {
                scaims_delete_report_image($imagePath);
                throw new Exception("Execute failed: " . $stmt->error);
            }

            if ($stmt->affected_rows > 0) {
                $defaultsSaved = scaims_save_farmer_report_defaults($conn, $farmerId, 'damage', [
                    'crop_commodity' => $crop_commodity,
                    'crop_type' => $crop_type,
                    'crop_variety' => $crop_variety,
                    'damage_cause' => $damage_cause,
                    'percent_damage' => $percent_damage,
                    'estimated_loss' => $estimated_loss,
                    'farm_gate_price' => $farm_gate_price,
                    'number_of_trees' => $damage_number_of_trees,
                ]);
                $_SESSION['message'] = $defaultsSaved
                    ? "Damage report submitted successfully✅"
                    : 'Damage report submitted successfully, but reusable report data could not be saved.';
            } else {
                scaims_delete_report_image($imagePath);
                throw new Exception("No rows were inserted.");
            }

            $stmt->close();

        } catch (Exception $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
            error_log("Error in damage report submission: " . $e->getMessage());
        }
    } else {
        $_SESSION['message'] = 'Please choose a report type.';
    }

    $conn->close();
    header("Location: report.php");
    exit();
}
?>
