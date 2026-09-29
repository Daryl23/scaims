<?php
require_once dirname(__DIR__, 4) . '/shared/admin_auth.php';
scaims_require_admin('../../../adminLogin/adminLogin.html');

$activePage = 'reports';
$assetPath = '../../../assets/';
$loginPath = '../../../adminLogin/adminLogin.html';
$sharedPath = '../../../shared/';
$rootSharedPath = '../../../../shared/';
$cropCssVersion = @filemtime(__DIR__ . '/crop.css') ?: time();
$cropJsVersion = @filemtime(__DIR__ . '/cropReports.js') ?: time();
$leafletCropIconsVersion = @filemtime(__DIR__ . '/../../../../shared/leaflet-crop-icons.js') ?: time();
$cropCatalogVersion = @filemtime(__DIR__ . '/../../../../shared/crop-catalog.js') ?: time();
$sharedImageCssVersion = @filemtime(__DIR__ . '/../../../../shared/report-image-capture.css') ?: time();
$sharedReportViewCssVersion = @filemtime(__DIR__ . '/../../../../shared/admin-report-view/report-view.css') ?: time();
$sharedReportViewCoreVersion = @filemtime(__DIR__ . '/../../../../shared/admin-report-view/report-view-core.js') ?: time();
$sharedReportMapVersion = @filemtime(__DIR__ . '/../../../../shared/admin-report-view/report-map.js') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crop Harvest Reports</title>
    <link rel="stylesheet" href="crop.css?v=<?php echo $cropCssVersion; ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($rootSharedPath); ?>report-image-capture.css?v=<?php echo $sharedImageCssVersion; ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($rootSharedPath); ?>admin-report-view/report-view.css?v=<?php echo $sharedReportViewCssVersion; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="<?php echo htmlspecialchars($rootSharedPath); ?>app-path.js"></script>
    <script src="<?php echo htmlspecialchars($rootSharedPath); ?>crop-catalog.js?v=<?php echo $cropCatalogVersion; ?>"></script>
    <script src="<?php echo htmlspecialchars($rootSharedPath); ?>leaflet-crop-icons.js?v=<?php echo $leafletCropIconsVersion; ?>"></script>
    <script src="<?php echo htmlspecialchars($rootSharedPath); ?>admin-report-view/report-view-core.js?v=<?php echo $sharedReportViewCoreVersion; ?>"></script>
    <script src="<?php echo htmlspecialchars($rootSharedPath); ?>admin-report-view/report-map.js?v=<?php echo $sharedReportMapVersion; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="cropReports.js?v=<?php echo $cropJsVersion; ?>" defer></script>
    
</head>
<body>
    <div class="container">
        <?php include '../../../shared/admin-sidebar.php'; ?>

        <div class="content">
            <div class="header" id="search-container">
                <input type="text" id="search-bar" placeholder="Search farmer, reporter, or crop">
                
                <div class="filter-group">
                    <input type="month" id="month-filter" name="month-filter">
                    <select id="reporter-filter" name="reporter-filter">
                        <option value="">All Reporters</option>
                        <option value="farmer">Farmer Reports</option>
                        <option value="admin">Admin Reports</option>
                    </select>
                    <select id="crop-filter" name="filter">
                        <option value="">All Crops</option>
                        <option value="Other">Other</option>
                        <option value="Rice">Rice</option>
                        <option value="Corn">Corn</option>
                        <option value="Coconut">Coconut</option>
                        <option value="Coffee">Coffee</option>
                        <option value="Sugarcane">Sugarcane</option>
                        <option value="Black Pepper">Black Pepper</option>
                        <option value="Cacao">Cacao</option>
                        <option value="Sweet Potato">Sweet Potato</option>
                        <option value="Cassava">Cassava</option>
                        <option value="Ube/Yam">Ube/Yam</option>
                        <option value="Ginger">Ginger</option>
                        <option value="Gabi">Gabi</option>
                        <option value="Ampalaya">Ampalaya</option>
                        <option value="Bell Pepper">Bell Pepper</option>
                        <option value="Bush Sitao">Bush Sitao</option>
                        <option value="Chayote">Chayote</option>
                        <option value="Cowpea">Cowpea</option>
                        <option value="Cucumber">Cucumber</option>
                        <option value="Eggplant">Eggplant</option>
                        <option value="Hot Pepper">Hot Pepper</option>
                        <option value="Lady Finger">Lady Finger (Okra)</option>
                        <option value="Lettuce">Lettuce</option>
                        <option value="Mung Bean">Mung Bean</option>
                        <option value="Mustard">Mustard</option>
                        <option value="Patola">Patola</option>
                        <option value="Pechay">Pechay</option>
                        <option value="Pole Sitao">Pole Sitao</option>
                        <option value="Radish">Radish</option>
                        <option value="Sigarilyas">Sigarilyas</option>
                        <option value="Snap Beans">Snap Beans</option>
                        <option value="Soybeans">Soybeans</option>
                        <option value="Squash">Squash</option>
                        <option value="Tomato">Tomato</option>
                        <option value="Upo">Upo</option>
                        <option value="Avocado">Avocado</option>
                        <option value="Banana">Banana</option>
                        <option value="Calamansi">Calamansi</option>
                        <option value="Citrus">Citrus</option>
                        <option value="Pomelo">Pomelo</option>
                        <option value="Papaya">Papaya</option>
                        <option value="Dragon Fruit">Dragon Fruit</option>
                        <option value="Durian">Durian</option>
                        <option value="Guyabano">Guyabano</option>
                        <option value="Jackfruit">Jackfruit</option>
                        <option value="Lanzones">Lanzones</option>
                        <option value="Mango">Mango</option>
                        <option value="Mango Pajo">Mango Pajo</option>
                        <option value="Mangosteen">Mangosteen</option>
                        <option value="Pineapple">Pineapple</option>
                        <option value="Rambutan">Rambutan</option>
                        <option value="Santol">Santol</option>
                        <option value="Tamarind">Tamarind</option>
                        <option value="Pili">Pili</option>
                    </select>
                    
                    <select id="barangay-filter" name="barangay-filter">
                        <option value="">All Barangays</option>
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

                    <button id="export-button">Export</button>
                    <button id="view-graph-button" class="action-button">View Graph</button>
                </div>
            </div>
    
            <div class="cropreport-count" id="cropreport-count">Showing 0 of 0 Total Crop Harvest Reports</div>

            <!-- New Reports Section -->
            <h3 class="section-title">NEW CROP HARVEST REPORTS (last 7 days)</h3>
            <div class="table-scroll">
            <table class="cropreport-table">
                <thead>
                    <tr>
                        <th>Farmer</th>
                        <th>Barangay</th>
                        <th>Crop Type</th>
                        <th>Status</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Reported By</th>
                        <th>Reporter Type</th>
                        <th>Harvest Date</th>
                        <th>Date Reported</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="new-reports-body">
                    <!-- New reports will be inserted here -->
                </tbody>
            </table>
            </div>

            <!-- Previous Reports Section -->
            <h3 id="previous-reports-section" class="section-title">PREVIOUS CROP HARVEST REPORTS</h3>
            <div class="table-scroll">
            <table class="cropreport-table">
                <thead>
                    <tr>
                        <th>Farmer</th>
                        <th>Barangay</th>
                        <th>Crop Type</th>
                        <th>Status</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Reported By</th>
                        <th>Reporter Type</th>
                        <th>Harvest Date</th>
                        <th>Date Reported</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="previous-reports-body">
                    <!-- Previous reports will be inserted here -->
                </tbody>
            </table>
            </div>

            <div class="pagination-container">
                <div class="pagination">
                    <button id="prev-page">Previous</button>
                    <span id="current-page">Page 1 of 1</span>
                    <button id="next-page">Next</button>
                </div>
            </div>

            <!-- Crop Harvest Report Modal -->
            <div id="cropReportModal" class="modal">
                <div class="modal-content">
                    <span class="close-button">&times;</span>
                    <h2 id="modalTitle">Crop Harvest Report Details</h2>
                    <div id="cropReportImagePanel" class="report-image-detail" hidden>
                        <h3 data-i18n="gps_photo_title">GPS Camera Photo</h3>
                        <img id="cropReportImage" alt="Crop harvest report image" hidden>
                        <p id="cropReportNoImage" data-i18n="gps_photo_empty">No image available</p>
                    </div>
                    <div id="modal-body">
                        <p><strong>Farmer:</strong> <span id="farmerID"></span></p>
                        <p><strong>Reported By:</strong> <span id="reportedBy"></span></p>
                        <p><strong>Reporter Type:</strong> <span id="reporterType"></span></p>
                        <p><strong>Location:</strong> <span id="location"></span></p>
                        <p><strong>Crop Commodity:</strong> <span id="cropCommodity"></span></p>
                        <p><strong>Crop Type:</strong> <span id="cropType"></span></p>
                        <p><strong>Crop Variety:</strong> <span id="cropVariety"></span></p>
                        <p><strong>Date Planted:</strong> <span id="datePlanted"></span></p>
                        <p><strong>Harvest Date:</strong> <span id="harvestDate"></span></p>
                        <p><strong>Estimated Harvest:</strong> <span id="estimateHarvest"></span></p>
                        <p><strong>Number of Trees:</strong> <span id="numberOfTrees"></span></p>
                        <p><strong>Crop Status:</strong> <span id="cropStatus"></span></p>
                        <p><strong>Farm Gate Price:</strong> <span id="farmGatePrice"></span></p>
                        <p><strong>Latitude:</strong> <span id="cropLatitude"></span></p>
                        <p><strong>Longitude:</strong> <span id="cropLongitude"></span></p>
                        <p><strong>Date Reported:</strong> <span id="dateReported"></span></p>
                    </div>
                    <div class="report-map-card">
                        <div class="report-map-card-header">
                            <div>
                                <h3>GIS Map Preview</h3>
                                <p>Preview the submitted crop report on the map using its saved coordinates.</p>
                            </div>
                            <div class="report-map-actions">
                                <button type="button" id="show-directions-button" class="map-action-button map-action-button-secondary">
                                    Show Directions
                                </button>
                                <button type="button" id="open-report-map-button" class="map-action-button">
                                    Open GIS Map
                                </button>
                            </div>
                        </div>

                        <div class="report-map-stats">
                            <div class="report-map-stat">
                                <div class="report-map-icon olive-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="report-map-value" id="mapCardLocation">N/A</div>
                                    <div class="report-map-label">Location</div>
                                </div>
                            </div>
                            <div class="report-map-stat">
                                <div class="report-map-icon harvest-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="report-map-value" id="mapCardCoordinates">N/A</div>
                                    <div class="report-map-label">Coordinates</div>
                                </div>
                            </div>
                        </div>

                        <div class="report-map-toolbar">
                            <button type="button" id="crop-map-street" class="report-map-layer-btn active">Street</button>
                            <button type="button" id="crop-map-satellite" class="report-map-layer-btn">Satellite</button>
                        </div>

                        <div id="crop-report-map-message" class="report-map-message">
                            No latitude and longitude available for this crop report.
                        </div>
                            <div id="crop-report-map" class="admin-report-map"></div>
                    </div>
                </div>
            </div>

            <!-- Graph Modal -->
            <div id="graphModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="graphModalTitle"></h2>
                        <select id="graph-year-filter">
                            <!-- Will be populated dynamically -->
                        </select>
                        <button class="close-button">&times;</button>
                    </div>
                    <div class="modal-body">
                        <canvas id="harvestGraph"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script src="../../../../shared/report-image-capture.js?v=<?php echo filemtime(__DIR__ . "/../../../../shared/report-image-capture.js"); ?>"></script>
</body>
</html>
