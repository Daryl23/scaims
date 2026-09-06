<?php
session_start();
if (!isset($_SESSION['UName'])) {
    header("Location: ../../farmerLogin/farmerlogin.html");
    exit();
}

$statusMessage = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

require_once dirname(__DIR__, 3) . '/conn.php';
require_once dirname(__DIR__, 3) . '/shared/report_image_storage.php';
require_once dirname(__DIR__, 3) . '/shared/crop_catalog.php';
require_once dirname(__DIR__, 3) . '/shared/farmer_report_defaults.php';

$dashboardCurrentPage = 'report';
$reportCssVersion = @filemtime(__DIR__ . '/reps.css') ?: time();
$reportJsVersion = @filemtime(__DIR__ . '/report.js') ?: time();
$sharedImageCssVersion = @filemtime(dirname(__DIR__, 3) . '/shared/report-image-capture.css') ?: time();
$sharedImageJsVersion = @filemtime(dirname(__DIR__, 3) . '/shared/report-image-capture.js') ?: time();
$initialReportType = $_GET['report_type'] ?? '';
if (!in_array($initialReportType, ['crop', 'damage'], true)) {
    $initialReportType = '';
}

$pendingToken = $_GET['image_token'] ?? '';
$pendingImage = $pendingToken !== '' ? scaims_get_pending_report_image($pendingToken) : null;
if ($pendingToken !== '' && $pendingImage === null) {
    $statusMessage = 'The selected image is no longer available. Please choose it again.';
}

$pendingCropToken = ($initialReportType === 'crop' && $pendingImage) ? $pendingImage['token'] : '';
$pendingDamageToken = ($initialReportType === 'damage' && $pendingImage) ? $pendingImage['token'] : '';
$pendingCropImageUrl = ($initialReportType === 'crop' && $pendingImage) ? scaims_report_image_url($pendingImage['path'], '../../../') : '';
$pendingDamageImageUrl = ($initialReportType === 'damage' && $pendingImage) ? scaims_report_image_url($pendingImage['path'], '../../../') : '';

$farmerReports = [];
$newReportPrefill = scaims_empty_farmer_report_defaults();
$newReportPrefillByType = [
    'crop' => scaims_empty_farmer_report_defaults(),
    'damage' => scaims_empty_farmer_report_defaults(),
];
$farmerReportSort = ($_GET['sort'] ?? 'newest') === 'oldest' ? 'oldest' : 'newest';
$farmerReportSortDirection = $farmerReportSort === 'oldest' ? 'ASC' : 'DESC';

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_report_image_columns($conn);
    scaims_ensure_farmer_report_defaults_table($conn);

    $farmerLookup = $conn->prepare("SELECT f.FarmerID, f.Barangay FROM farmer f JOIN user u ON f.UserID = u.UserID WHERE u.UName = ? LIMIT 1");
    $farmerId = null;
    if ($farmerLookup) {
        $farmerLookup->bind_param("s", $_SESSION['UName']);
        $farmerLookup->execute();
        $farmerLookupResult = $farmerLookup->get_result();
        if ($farmerLookupResult && $farmerLookupResult->num_rows > 0) {
            $farmerRow = $farmerLookupResult->fetch_assoc();
            $farmerId = (int) $farmerRow['FarmerID'];
            $newReportPrefill['location'] = trim((string) ($farmerRow['Barangay'] ?? ''));
        }
        $farmerLookup->close();
    }

    if ($farmerId !== null) {
        $cropPrefillSql = "SELECT
                cd.crop_commodity,
                cd.crop_type,
                cd.crop_variety,
                cd.num_trees,
                cd.tree_status,
                pd.location AS parcel_location
            FROM crop_details cd
            LEFT JOIN parcel_details pd ON cd.parcel_detail_id = pd.id
            WHERE cd.created_by = ?";
        $cropPrefillStmt = $conn->prepare($cropPrefillSql);
        if ($cropPrefillStmt) {
            $cropPrefillStmt->bind_param("s", $_SESSION['UName']);
            $cropPrefillStmt->execute();
            $cropPrefillResult = $cropPrefillStmt->get_result();
            $distinctCropRows = [];
            $distinctLocations = [];

            while ($cropRow = $cropPrefillResult->fetch_assoc()) {
                $normalizedCommodity = scaims_normalize_crop_commodity($cropRow['crop_commodity'] ?? '');
                $cropSignature = implode('|', [
                    $normalizedCommodity,
                    trim((string) ($cropRow['crop_type'] ?? '')),
                    trim((string) ($cropRow['crop_variety'] ?? '')),
                    trim((string) ($cropRow['num_trees'] ?? '')),
                    trim((string) ($cropRow['tree_status'] ?? '')),
                ]);
                $distinctCropRows[$cropSignature] = [
                    'crop_commodity' => $normalizedCommodity,
                    'crop_type' => trim((string) ($cropRow['crop_type'] ?? '')),
                    'crop_variety' => trim((string) ($cropRow['crop_variety'] ?? '')),
                    'number_of_trees' => trim((string) ($cropRow['num_trees'] ?? '')),
                    'crop_status' => trim((string) ($cropRow['tree_status'] ?? '')),
                ];

                $parcelLocation = trim((string) ($cropRow['parcel_location'] ?? ''));
                if ($parcelLocation !== '') {
                    $distinctLocations[strtolower($parcelLocation)] = $parcelLocation;
                }
            }

            if ($newReportPrefill['location'] === '' && count($distinctLocations) === 1) {
                $newReportPrefill['location'] = array_values($distinctLocations)[0];
            }

            if (count($distinctCropRows) === 1) {
                $newReportPrefill = array_merge($newReportPrefill, array_values($distinctCropRows)[0]);
            }

            $cropPrefillStmt->close();
        }

        $newReportPrefillByType = [
            'crop' => $newReportPrefill,
            'damage' => $newReportPrefill,
        ];
        $savedReportDefaults = scaims_get_farmer_report_defaults($conn, $farmerId);
        $newReportPrefillByType['crop'] = scaims_merge_farmer_report_defaults($newReportPrefillByType['crop'], $savedReportDefaults['crop']);
        $newReportPrefillByType['damage'] = scaims_merge_farmer_report_defaults($newReportPrefillByType['damage'], $savedReportDefaults['damage']);
        $newReportPrefill = $newReportPrefillByType[$initialReportType ?: 'crop'];

        $reportsSql = "
            SELECT *
            FROM (
                SELECT
                    'crop' AS report_type,
                    id,
                    location,
                    crop_commodity,
                    crop_type,
                    crop_variety,
                    date_planted,
                    harvest_date,
                    NULL AS date_occurrence,
                    estimate_harvest,
                    crop_status,
                    farm_gate_price,
                    number_of_trees,
                    NULL AS damage_cause,
                    NULL AS percent_damage,
                    NULL AS estimated_loss,
                    latitude,
                    longitude,
                    reportDate,
                    created_by,
                    image_path
                FROM crop_reports
                WHERE FarmerID = ?
                UNION ALL
                SELECT
                    'damage' AS report_type,
                    id,
                    dmgLocation AS location,
                    dmgCropCommodity AS crop_commodity,
                    dmgCropType AS crop_type,
                    dmgCropVariety AS crop_variety,
                    NULL AS date_planted,
                    NULL AS harvest_date,
                    date_occurrence,
                    NULL AS estimate_harvest,
                    NULL AS crop_status,
                    dmgFarmGatePrice AS farm_gate_price,
                    damage_number_of_trees AS number_of_trees,
                    damage_cause,
                    percent_damage,
                    estimated_loss,
                    latitude,
                    longitude,
                    reportDate,
                    created_by,
                    image_path
                FROM damage_reports
                WHERE FarmerID = ?
            ) reports
            ORDER BY reportDate $farmerReportSortDirection, id $farmerReportSortDirection";
        $reportsStmt = $conn->prepare($reportsSql);
        if ($reportsStmt) {
            $reportsStmt->bind_param("ii", $farmerId, $farmerId);
            $reportsStmt->execute();
            $reportsResult = $reportsStmt->get_result();
            while ($row = $reportsResult->fetch_assoc()) {
                $row['image_url'] = scaims_report_image_url($row['image_path'] ?? null, '../../../');
                unset($row['image_path']);
                $farmerReports[] = $row;
            }
            $reportsStmt->close();
        }
    }

    $conn->close();
} catch (RuntimeException $e) {
    $statusMessage = $statusMessage ?: 'Unable to load your submitted reports right now.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmers Reports</title>
    <link rel="stylesheet" href="../shared/dashboard-shell.css">
    <link rel="stylesheet" href="reps.css?v=<?php echo $reportCssVersion; ?>">
    <link rel="stylesheet" href="../../../shared/report-image-capture.css?v=<?php echo $sharedImageCssVersion; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../shared/farmer-mobile.css?v=<?php echo @filemtime(__DIR__ . '/../shared/farmer-mobile.css') ?: time(); ?>">
    <link rel="stylesheet" href="../../../shared/choice-cards.css?v=20260908">
    <script src="../../../shared/choice-cards.js?v=20260908" defer></script>
</head>
<body>
    <div class="container">
        <?php include '../shared/sidebar.php'; ?>

        <div class="main-content">
            <header class="header"> 
                <h1 data-i18n="report_title">REPORT FORM</h1>
            </header>
            <div class="dashboard-help-note">
                <p data-i18n="report_help">Pick the report type first, then fill in one section at a time.</p>
            </div>
            <?php if ($statusMessage): ?>
                <div class="report-status-message">
                    <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <div class="report-form">
                <form action="report_submit.php" method="POST" enctype="multipart/form-data" id="farmer-report-form" data-initial-report-type="<?php echo htmlspecialchars($initialReportType, ENT_QUOTES, 'UTF-8'); ?>">
                <label for="report-type"><h2><b data-i18n="report_type_label">Type of Report</b></h2></label>
                    <select id="report-type" name="report-type" data-choice-cards onchange="toggleCropReport()">
                        <option value="" disabled selected data-i18n="report_type_placeholder">Select a type of report</option>
                        <option value="crop" data-i18n="report_type_crop">Crop Harvest Report</option>
                        <option value="damage" data-i18n="report_type_damage">Crop Damage Report</option>
                    </select>
                    
                    <div id="crop-report-fields" style="display: none;">
                    <hr><br>
                        <h3>Crop Harvest Report</h3>
                        <div
                            data-report-image-capture
                            data-input-name="crop-report-image"
                            data-pending-input-name="crop-pending-report-image-token"
                            data-pending-image-token="<?php echo htmlspecialchars($pendingCropToken, ENT_QUOTES, 'UTF-8'); ?>"
                            data-pending-image-url="<?php echo htmlspecialchars($pendingCropImageUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-locked="true"
                            data-label="Larawan ng Inaning Pananim"
                            data-capture-label="Kumuha ng Larawan"
                            data-upload-label="Pumili mula sa Gallery"
                            data-replace-label="Palitan ang Larawan"
                            data-remove-label="Alisin ang Larawan"
                        ></div>
                        <label for="crop-location">Location:</label>
                        <select id="crop-location" name="crop-location" required>
                            <option value="" disabled selected>Pumili ng Lokasyon</option>
                            <option value="adya">Adya</option>
                            <option value="anilao">Anilao</option>
                            <option value="anilao-labac">Anilao-Labac</option>
                            <option value="antipolodelnorte">Antipolo del Norte</option>
                            <option value="antipolodelsur">Antipolo del Sur</option>
                            <option value="bagongpook">Bagong Pook</option>
                            <option value="balintawak">Balintawak</option>
                            <option value="banaybanay">Banaybanay</option>
                            <option value="bolbok">Bolbok</option>
                            <option value="bugtongnapulo">Bugtong na Pulo</option>
                            <option value="bulacnin">Bulacnin</option>
                            <option value="bulaklakan">Bulaklakan</option>
                            <option value="calamias">Calamias</option>
                            <option value="cumba">Cumba</option>
                            <option value="dagatan">Dagatan</option>
                            <option value="duhatan">Duhatan</option>
                            <option value="halang">Halang</option>
                            <option value="inosluban">Inosluban</option>
                            <option value="kayumanggi">Kayumanggi</option>
                            <option value="latag">Latag</option>
                            <option value="lodlod">Lodlod</option>
                            <option value="lumbang">Lumbang</option>
                            <option value="mabini">Mabini</option>
                            <option value="malagonlong">Malagonlong</option>
                            <option value="malitlit">Malitlit</option>
                            <option value="marawoy">Marawoy</option>
                            <option value="mataasnalupa">Mataas na Lupa</option>
                            <option value="muntingpulo">Munting Pulo</option>
                            <option value="pagolinginbata">Pagolingin Bata</option>
                            <option value="pagolingineast">Pagolingin East</option>
                            <option value="pagolinginwest">Pagolingin West</option>
                            <option value="pangao">Pangao</option>
                            <option value="pinagkawitan">Pinagkawitan</option>
                            <option value="pinagtongulan">Pinagtongulan</option>
                            <option value="plaridel">Plaridel</option>
                            <option value="poblacionbarangay1">Poblacion Barangay 1</option>
                            <option value="poblacionbarangay2">Poblacion Barangay 2</option>
                            <option value="poblacionbarangay3">Poblacion Barangay 3</option>
                            <option value="poblacionbarangay4">Poblacion Barangay 4</option>
                            <option value="poblacionbarangay5">Poblacion Barangay 5</option>
                            <option value="poblacionbarangay6">Poblacion Barangay 6</option>
                            <option value="poblacionbarangay7">Poblacion Barangay 7</option>
                            <option value="poblacionbarangay8">Poblacion Barangay 8</option>
                            <option value="poblacionbarangay9">Poblacion Barangay 9</option>
                            <option value="poblacionbarangay9a">Poblacion Barangay 9-A</option>
                            <option value="poblacionbarangay10">Poblacion Barangay 10</option>
                            <option value="poblacionbarangay11">Poblacion Barangay 11</option>
                            <option value="poblacionbarangay12">Poblacion Barangay 12</option>
                            <option value="pusil">Pusil</option>
                            <option value="quezon">Quezon</option>
                            <option value="rizal">Rizal</option>
                            <option value="sabang">Sabang</option>
                            <option value="sampaguita">Sampaguita</option>
                            <option value="sanbenito">San Benito</option>
                            <option value="sancarlos">San Carlos</option>
                            <option value="sancelestino">San Celestino</option>
                            <option value="sanfrancisco">San Francisco</option>
                            <option value="sanguillermo">San Guillermo</option>
                            <option value="sanjose">San Jose</option>
                            <option value="sanlucas">San Lucas</option>
                            <option value="sansalvador">San Salvador</option>
                            <option value="sansebastian">San Sebastian</option>
                            <option value="santonino">Santo Niño</option>
                            <option value="santoribio">Santo Toribio</option>
                            <option value="sapac">Sapac</option>
                            <option value="sico">Sico</option>
                            <option value="talisay">Talisay</option>
                            <option value="tambo">Tambo</option>
                            <option value="tangob">Tangob</option>
                            <option value="tanguay">Tanguay</option>
                            <option value="tibig">Tibig</option>
                            <option value="tipacan">Tipacan</option>
                        </select>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="crop-commodity">Crop Commodity:</label>
                                <select id="crop-commodity" name="crop-commodity" required onchange="updateCropTypes()">
                                    <option value="" disabled selected>Pumili ng Crop Commodity</option>
                                    <option value="grains">Grains</option>
                                    <option value="plantation">Plantation Crops</option>
                                    <option value="root">Root Crops</option>
                                    <option value="vegetables">Vegetables</option>
                                    <option value="fruits">Fruits</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="crop-type">Crop Type:</label>
                                <select id="crop-type" name="crop-type" required>
                                    <option value="" disabled selected>Pumili ng Crop Type</option>
                                </select>
                                <input type="text" id="other-crop-type" name="other-crop-type" style="display: none;" placeholder="Please specify" />
                            </div>
                            
                            <div class="form-group">
                                <label for="crop-variety">Crop Variety:</label>
                                <input type="text" id="crop-variety" name="crop-variety" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date-planted">Date Planted:</label>
                                <input type="date" id="date-planted" name="date-planted" required>
                            </div>

                            <div class="form-group">
                                <label for="harvest-date">Harvest Date:</label>
                                <input type="date" id="harvest-date" name="harvest-date" required>
                            </div>

                            <div class="form-group">
                                <label for="estimate-harvest">Estimated Harvest (kg):</label>
                                <input type="number" id="estimate-harvest" name="estimate-harvest" placeholder="Tinatayang Dami ng Ani" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="crop-status">Crop Status:</label>
                                <select id="crop-status" name="crop-status" data-choice-cards required>
                                    <option value="" disabled selected>Pumili ng Status ng Pananim</option>
                                    <option value="Seedling/Vegetative">Seedling/Vegetative</option>
                                    <option value="Bearing">Bearing</option>
                                    <option value="Non-Bearing">Non-Bearing</option>
                                </select>
                            </div>

                            <div class = "form-group">
                            <label for="number-of-trees">Number of Trees:</label>
                            <input type="text" id="number-of-trees" name="number-of-trees" placeholder="Kung wala, ilagay ang 'N/A'">
                            </div>

                            <div class="form-group">
                                <label for="farm-gate-price">Farm Gate Price (₱/kg):</label>
                                <input type="number" id="farm-gate-price" name="farm-gate-price" placeholder="Presyo sa Farm Gate" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="crop-coordinate-mode">Coordinates:</label>
                                <select id="crop-coordinate-mode" name="crop-coordinate-mode" data-choice-cards>
                                    <option value="" selected>Do not include coordinates</option>
                                    <option value="auto">Auto via GPS</option>
                                    <option value="manual">Manual input</option>
                                </select>
                            </div>
                            <div class="form-group" id="crop-coordinate-action" style="display: none;">
                                <label>GPS Access:</label>
                                <button type="button" class="submit-btn gps-button" id="crop-use-gps"><i class="bi bi-geo-alt"></i><span>Use Current Location</span></button>
                                <small id="crop-coordinate-status">Allow location access to fill latitude and longitude automatically.</small>
                            </div>
                            <div class="form-group coordinate-field-group coordinate-field-group-wide">
                                <label for="crop-coordinate-input">Coordinates:</label>
                                <input type="text" id="crop-coordinate-input" placeholder="Paste from Google Maps or Waze: 13°50'11.2&quot;N 121°16'50.5&quot;E">
                                <small>Use the Google Maps or Waze format: 13°50'11.2&quot;N 121°16'50.5&quot;E.</small>
                                <input type="hidden" id="crop-latitude" name="crop-latitude">
                                <input type="hidden" id="crop-longitude" name="crop-longitude">
                            </div>
                        </div>
                        <hr>
                        <div class="report-edit-actions" data-new-report-actions="crop">
                            <button type="button" class="report-edit-button" data-new-report-edit="crop">Edit / I-edit</button>
                            <button type="button" class="report-save-button" data-new-report-save="crop" hidden><i class="bi bi-save"></i><span>Save</span></button>
                            <button type="button" class="report-cancel-edit-button" data-new-report-cancel="crop" hidden>Cancel Edit</button>
                        </div>
                        <div class="report-edit-message" data-new-report-message="crop" role="alert" aria-live="polite"></div>
                        <button type="button" onclick="confirmSubmission()" class="submit-btn report-submit-action"><i class="bi bi-send-check"></i><span>Submit Report</span></button>
                    </div>
                    
                    <div id="damage-report-fields" style="display: none;">
                    <hr><br>
                        <h3>Crop Damage Report</h3>
                        <div
                            data-report-image-capture
                            data-input-name="damage-report-image"
                            data-pending-input-name="damage-pending-report-image-token"
                            data-pending-image-token="<?php echo htmlspecialchars($pendingDamageToken, ENT_QUOTES, 'UTF-8'); ?>"
                            data-pending-image-url="<?php echo htmlspecialchars($pendingDamageImageUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-locked="true"
                            data-label="Larawan ng Nasirang Pananim"
                            data-capture-label="Kumuha ng Larawan"
                            data-upload-label="Pumili mula sa Gallery"
                            data-replace-label="Palitan ang Larawan"
                            data-remove-label="Alisin ang Larawan"
                        ></div>
                        <label for="damage-location">Location:</label>
                        <select id="damage-location" name="damage-location" required>
                            <option value="" disabled selected>Pumili ng Lokasyon</option>
                            <option value="adya">Adya</option>
                            <option value="anilao">Anilao</option>
                            <option value="anilao-labac">Anilao-Labac</option>
                            <option value="antipolodelnorte">Antipolo del Norte</option>
                            <option value="antipolodelsur">Antipolo del Sur</option>
                            <option value="bagongpook">Bagong Pook</option>
                            <option value="balintawak">Balintawak</option>
                            <option value="banaybanay">Banaybanay</option>
                            <option value="bolbok">Bolbok</option>
                            <option value="bugtongnapulo">Bugtong na Pulo</option>
                            <option value="bulacnin">Bulacnin</option>
                            <option value="bulaklakan">Bulaklakan</option>
                            <option value="calamias">Calamias</option>
                            <option value="cumba">Cumba</option>
                            <option value="dagatan">Dagatan</option>
                            <option value="duhatan">Duhatan</option>
                            <option value="halang">Halang</option>
                            <option value="inosluban">Inosluban</option>
                            <option value="kayumanggi">Kayumanggi</option>
                            <option value="latag">Latag</option>
                            <option value="lodlod">Lodlod</option>
                            <option value="lumbang">Lumbang</option>
                            <option value="mabini">Mabini</option>
                            <option value="malagonlong">Malagonlong</option>
                            <option value="malitlit">Malitlit</option>
                            <option value="marawoy">Marawoy</option>
                            <option value="mataasnalupa">Mataas na Lupa</option>
                            <option value="muntingpulo">Munting Pulo</option>
                            <option value="pagolinginbata">Pagolingin Bata</option>
                            <option value="pagolingineast">Pagolingin East</option>
                            <option value="pagolinginwest">Pagolingin West</option>
                            <option value="pangao">Pangao</option>
                            <option value="pinagkawitan">Pinagkawitan</option>
                            <option value="pinagtongulan">Pinagtongulan</option>
                            <option value="plaridel">Plaridel</option>
                            <option value="poblacionbarangay1">Poblacion Barangay 1</option>
                            <option value="poblacionbarangay2">Poblacion Barangay 2</option>
                            <option value="poblacionbarangay3">Poblacion Barangay 3</option>
                            <option value="poblacionbarangay4">Poblacion Barangay 4</option>
                            <option value="poblacionbarangay5">Poblacion Barangay 5</option>
                            <option value="poblacionbarangay6">Poblacion Barangay 6</option>
                            <option value="poblacionbarangay7">Poblacion Barangay 7</option>
                            <option value="poblacionbarangay8">Poblacion Barangay 8</option>
                            <option value="poblacionbarangay9">Poblacion Barangay 9</option>
                            <option value="poblacionbarangay9a">Poblacion Barangay 9-A</option>
                            <option value="poblacionbarangay10">Poblacion Barangay 10</option>
                            <option value="poblacionbarangay11">Poblacion Barangay 11</option>
                            <option value="poblacionbarangay12">Poblacion Barangay 12</option>
                            <option value="pusil">Pusil</option>
                            <option value="quezon">Quezon</option>
                            <option value="rizal">Rizal</option>
                            <option value="sabang">Sabang</option>
                            <option value="sampaguita">Sampaguita</option>
                            <option value="sanbenito">San Benito</option>
                            <option value="sancarlos">San Carlos</option>
                            <option value="sancelestino">San Celestino</option>
                            <option value="sanfrancisco">San Francisco</option>
                            <option value="sanguillermo">San Guillermo</option>
                            <option value="sanjose">San Jose</option>
                            <option value="sanlucas">San Lucas</option>
                            <option value="sansalvador">San Salvador</option>
                            <option value="sansebastian">San Sebastian</option>
                            <option value="santonino">Santo Niño</option>
                            <option value="santoribio">Santo Toribio</option>
                            <option value="sapac">Sapac</option>
                            <option value="sico">Sico</option>
                            <option value="talisay">Talisay</option>
                            <option value="tambo">Tambo</option>
                            <option value="tangob">Tangob</option>
                            <option value="tanguay">Tanguay</option>
                            <option value="tibig">Tibig</option>
                            <option value="tipacan">Tipacan</option>
                        </select>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="damage-crop-commodity">Crop Commodity:</label>
                                <select id="damage-crop-commodity" name="damage-crop-commodity" required onchange="updateCropDamageTypes()">
                                    <option value="" disabled selected>Pumili ng Crop Commodity</option>
                                    <option value="grains">Grains</option>
                                    <option value="plantation">Plantation Crops</option>
                                    <option value="root">Root Crops</option>
                                    <option value="vegetables">Vegetables</option>
                                    <option value="fruits">Fruits</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="damage-crop-type">Crop Type:</label>
                                <select id="damage-crop-type" name="damage-crop-type" required>
                                    <option value="" disabled selected>Pumili ng Crop Type</option>
                                </select>
                                <input type="text" id="other-damage-crop-type" name="other-damage-crop-type" style="display: none;" placeholder="Please specify" />
                            </div>
                            
                            <div class="form-group">
                                <label for="damage-crop-variety">Crop Variety:</label>
                                <input type="text" id="damage-crop-variety" name="damage-crop-variety" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="date-occurrence">Date of Occurrence:</label>
                                <input type="date" id="date-occurrence" name="date-occurrence" required>
                            </div>

                            <div class="form-group">
                                <label for="damage-cause">Cause of Damage:</label>
                                <input type="text" id="damage-cause" name="damage-cause" min="0" max="100" placeholder="Dahilan ng Pinsala">
                            </div>

                            <div class="form-group">
                                <label for="damage-percentage">Percentage of Damage (if any):</label>
                                <input type="number" id="damage-percentage" name="damage-percentage" min="0" max="100" placeholder="Pursyento ng naging Pinasala">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="damage-number-of-trees">Number of Trees:</label>
                                <input type="text" id="damage-number-of-trees" name="damage-number-of-trees" placeholder="Kung wala, ilagay ang 'N/A'">
                            </div>
                            <div class="form-group">
                                <label for="estimated-loss">Estimated Loss (kg):</label>
                                <input type="number" id="estimated-loss" name="estimated-loss" min="0" max="100" placeholder="Tinatayang Dami ng Nawala/Nasira">
                            </div>

                            <div class="form-group">
                                <label for="damage-farm-gate-price">Farm Gate Price (₱/kg):</label>
                                <input type="number" id="damage-farm-gate-price" name="damage-farm-gate-price" placeholder="Presyo sa Farm Gate" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="damage-coordinate-mode">Coordinates:</label>
                                <select id="damage-coordinate-mode" name="damage-coordinate-mode" data-choice-cards>
                                    <option value="" selected>Do not include coordinates</option>
                                    <option value="auto">Auto via GPS</option>
                                    <option value="manual">Manual input</option>
                                </select>
                            </div>
                            <div class="form-group" id="damage-coordinate-action" style="display: none;">
                                <label>GPS Access:</label>
                                <button type="button" class="submit-btn gps-button" id="damage-use-gps"><i class="bi bi-geo-alt"></i><span>Use Current Location</span></button>
                                <small id="damage-coordinate-status">Allow location access to fill latitude and longitude automatically.</small>
                            </div>
                            <div class="form-group coordinate-field-group coordinate-field-group-wide">
                                <label for="damage-coordinate-input">Coordinates:</label>
                                <input type="text" id="damage-coordinate-input" placeholder="Paste from Google Maps or Waze: 13°50'11.2&quot;N 121°16'50.5&quot;E">
                                <small>Use the Google Maps or Waze format: 13°50'11.2&quot;N 121°16'50.5&quot;E.</small>
                                <input type="hidden" id="damage-latitude" name="damage-latitude">
                                <input type="hidden" id="damage-longitude" name="damage-longitude">
                            </div>
                        </div>
                        <hr>
                        <div class="report-edit-actions" data-new-report-actions="damage">
                            <button type="button" class="report-edit-button" data-new-report-edit="damage">Edit / I-edit</button>
                            <button type="button" class="report-save-button" data-new-report-save="damage" hidden><i class="bi bi-save"></i><span>Save</span></button>
                            <button type="button" class="report-cancel-edit-button" data-new-report-cancel="damage" hidden>Cancel Edit</button>
                        </div>
                        <div class="report-edit-message" data-new-report-message="damage" role="alert" aria-live="polite"></div>
                        <button type="button" onclick="confirmSubmission()" class="submit-btn report-submit-action"><i class="bi bi-send-check"></i><span>Submit Report</span></button>
                    </div>
                </form>
            </div>

            <section class="farmer-report-list" aria-labelledby="farmer-report-list-title">
                <div class="farmer-report-list-header">
                    <div>
                        <h2 id="farmer-report-list-title">Submitted Reports</h2>
                        <p>Mga ulat na naipasa sa agriculture office.</p>
                    </div>
                    <form method="GET" class="report-sort-form">
                        <?php if ($initialReportType !== ''): ?>
                            <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($initialReportType, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endif; ?>
                        <?php if ($pendingImage !== null): ?>
                            <input type="hidden" name="image_token" value="<?php echo htmlspecialchars($pendingImage['token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endif; ?>
                        <label for="report-sort">Sort by date</label>
                        <select id="report-sort" name="sort" onchange="this.form.submit()">
                            <option value="newest" <?php echo $farmerReportSort === 'newest' ? 'selected' : ''; ?>>Newest to Oldest</option>
                            <option value="oldest" <?php echo $farmerReportSort === 'oldest' ? 'selected' : ''; ?>>Oldest to Newest</option>
                        </select>
                    </form>
                </div>

                <div class="farmer-report-table-wrap">
                    <table class="farmer-report-table">
                        <thead>
                            <tr>
                                <th>Larawan</th>
                                <th>Uri ng Ulat</th>
                                <th>Pananim</th>
                                <th>Lokasyon</th>
                                <th>Petsa</th>
                                <th>Naipasa</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($farmerReports)): ?>
                                <tr>
                                    <td colspan="7" class="farmer-report-empty">Wala pang naipapasang ulat.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($farmerReports as $index => $report): ?>
                                    <?php
                                        $isCropReport = $report['report_type'] === 'crop';
                                        $reportTypeLabel = $isCropReport ? 'Harvest Report' : 'Damaged Crop Report';
                                        $eventDate = $isCropReport ? $report['harvest_date'] : $report['date_occurrence'];
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($report['image_url'])): ?>
                                                <span class="report-thumbnail">
                                                    <img src="<?php echo htmlspecialchars($report['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($reportTypeLabel, ENT_QUOTES, 'UTF-8'); ?> image">
                                                </span>
                                            <?php else: ?>
                                                <span class="report-thumbnail-empty">Walang larawan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($reportTypeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($report['crop_type'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($report['location'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($eventDate ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($report['reportDate'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <button type="button" class="farmer-report-view-button" data-report-index="<?php echo (int) $index; ?>">View</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <div id="farmerReportModal" class="farmer-report-modal" aria-hidden="true">
                <div class="farmer-report-modal-content">
                    <button type="button" class="farmer-report-modal-close" aria-label="Close">&times;</button>
                    <h2 id="farmerReportModalTitle">Report Details</h2>
                    <div id="farmerReportImageDetail" class="report-image-detail">
                        <h3>Larawan ng Ulat</h3>
                        <img id="farmerReportDetailImage" alt="Report image" hidden>
                        <p id="farmerReportNoImage">Walang larawan.</p>
                    </div>
                    <div id="farmerReportDetails" class="farmer-report-detail-grid"></div>
                    <form id="farmerReportEditForm" class="farmer-report-edit-form" enctype="multipart/form-data" hidden>
                        <input type="hidden" name="report-id" id="edit-report-id">
                        <input type="hidden" name="report-type" id="edit-report-type">
                        <input type="hidden" name="remove-report-image" id="edit-remove-report-image" value="0">
                        <div class="farmer-report-edit-image-actions" id="farmerReportEditImageActions" hidden>
                            <input type="file" name="report-image" id="edit-report-image" accept="image/jpeg,image/png,image/webp" hidden>
                            <button type="button" class="report-edit-button" id="edit-replace-report-image">Palitan ang Larawan</button>
                            <button type="button" class="report-cancel-edit-button" id="edit-remove-image-button">Alisin ang Larawan</button>
                        </div>
                        <div id="farmerReportEditFields" class="farmer-report-detail-grid farmer-report-edit-grid"></div>
                        <div class="report-edit-actions">
                            <button type="submit" class="report-save-button"><i class="bi bi-save"></i><span>Save Changes</span></button>
                            <button type="button" class="report-cancel-edit-button" id="farmerReportCancelEdit">Cancel</button>
                        </div>
                        <div id="farmerReportEditMessage" class="report-edit-message" role="alert" aria-live="polite"></div>
                    </form>
                    <div class="report-edit-actions" id="farmerReportViewActions">
                        <button type="button" class="report-edit-button" id="farmerReportEditButton">Edit / I-edit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../shared/dashboard-language.js"></script>
    <script src="../shared/dashboard-shell.js"></script>
    <script src="../../../shared/crop-catalog.js"></script>
    <script>
        window.farmerReportPrefillByType = <?php echo json_encode($newReportPrefillByType, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.farmerReportPrefill = <?php echo json_encode($newReportPrefill, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.farmerSubmittedReports = <?php echo json_encode($farmerReports, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    </script>
    <script src="../../../shared/report-image-capture.js?v=<?php echo $sharedImageJsVersion; ?>"></script>
    <script src="report.js?v=<?php echo $reportJsVersion; ?>"></script>
</body>
</html>
