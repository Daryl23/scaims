<?php
session_start();
if (!isset($_SESSION['UName'])) {
    header("Location: ../../farmerLogin/farmerlogin.html");
    exit();
}

if (isset($_SESSION['message'])) {
    echo "<script>alert('" . $_SESSION['message'] . "');</script>";
    unset($_SESSION['message']); 
}

$dashboardCurrentPage = 'report';
$reportCssVersion = @filemtime(__DIR__ . '/reps.css') ?: time();
$reportJsVersion = @filemtime(__DIR__ . '/report.js') ?: time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmers Reports</title>
    <link rel="stylesheet" href="../shared/dashboard-shell.css">
    <link rel="stylesheet" href="reps.css?v=<?php echo $reportCssVersion; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
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
            <div class="report-form">
                <form action="report_submit.php" method="POST" enctype="multipart/form-data">
                <label for="report-type"><h2><b data-i18n="report_type_label">Type of Report</b></h2></label>
                    <select id="report-type" name="report-type" onchange="toggleCropReport()">
                        <option value="" disabled selected data-i18n="report_type_placeholder">Select a type of report</option>
                        <option value="crop" data-i18n="report_type_crop">Crop Harvest Report</option>
                        <option value="damage" data-i18n="report_type_damage">Crop Damage Report</option>
                    </select>
                    
                    <div id="crop-report-fields" style="display: none;">
                    <hr><br>
                        <h3>Crop Harvest Report</h3>
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
                                <select id="crop-status" name="crop-status" required>
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
                                <select id="crop-coordinate-mode" name="crop-coordinate-mode">
                                    <option value="" selected>Do not include coordinates</option>
                                    <option value="auto">Auto via GPS</option>
                                    <option value="manual">Manual input</option>
                                </select>
                            </div>
                            <div class="form-group" id="crop-coordinate-action" style="display: none;">
                                <label>GPS Access:</label>
                                <button type="button" class="submit-btn gps-button" id="crop-use-gps">Use Current Location</button>
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
                        <button type="button" onclick="confirmSubmission()" class="submit-btn">Submit Report</button>
                    </div>
                    
                    <div id="damage-report-fields" style="display: none;">
                    <hr><br>
                        <h3>Crop Damage Report</h3>
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
                                <select id="damage-coordinate-mode" name="damage-coordinate-mode">
                                    <option value="" selected>Do not include coordinates</option>
                                    <option value="auto">Auto via GPS</option>
                                    <option value="manual">Manual input</option>
                                </select>
                            </div>
                            <div class="form-group" id="damage-coordinate-action" style="display: none;">
                                <label>GPS Access:</label>
                                <button type="button" class="submit-btn gps-button" id="damage-use-gps">Use Current Location</button>
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
                        <button type="button" onclick="confirmSubmission()" class="submit-btn">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../shared/dashboard-language.js"></script>
    <script src="../shared/dashboard-shell.js"></script>
    <script src="../../../shared/crop-catalog.js"></script>
    <script src="report.js?v=<?php echo $reportJsVersion; ?>"></script>
</body>
</html>
