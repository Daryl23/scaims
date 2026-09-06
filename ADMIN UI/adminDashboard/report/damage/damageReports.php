<?php
session_start();
if (!isset($_SESSION['AdminID'])) {
    header('Location: ../../../adminLogin/adminLogin.html');
    exit();
}

$activePage = 'reports';
$assetPath = '../../../assets/';
$loginPath = '../../../adminLogin/adminLogin.html';
$sharedPath = '../../../shared/';
$rootSharedPath = '../../../../shared/';
$damageCssVersion = @filemtime(__DIR__ . '/damages.css') ?: time();
$damageJsVersion = @filemtime(__DIR__ . '/cropDamage.js') ?: time();
$leafletCropIconsVersion = @filemtime(__DIR__ . '/../../../../shared/leaflet-crop-icons.js') ?: time();
$cropCatalogVersion = @filemtime(__DIR__ . '/../../../../shared/crop-catalog.js') ?: time();
$sharedImageCssVersion = @filemtime(__DIR__ . '/../../../../shared/report-image-capture.css') ?: time();
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crop Damage Reports</title>
    <link rel="stylesheet" href="damages.css?v=<?php echo $damageCssVersion; ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($rootSharedPath); ?>report-image-capture.css?v=<?php echo $sharedImageCssVersion; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="<?php echo htmlspecialchars($rootSharedPath); ?>app-path.js"></script>
    <script src="<?php echo htmlspecialchars($rootSharedPath); ?>crop-catalog.js?v=<?php echo $cropCatalogVersion; ?>"></script>
    <script src="<?php echo htmlspecialchars($rootSharedPath); ?>leaflet-crop-icons.js?v=<?php echo $leafletCropIconsVersion; ?>"></script>
    <script src="cropDamage.js?v=<?php echo $damageJsVersion; ?>" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    <style>
        .container {
            display: flex;
            min-height: 100vh;
        }

        .content {
            flex: 1;
            padding: 20px;
            min-height: 100vh;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../../../shared/admin-sidebar.php'; ?>

        <div class="content">
            <div class="header" id="search-container">
                <input type="text" id="search-bar" placeholder="Search farmer, reporter, or crop">
                
                <div class="filter-group">
                    <input type="month" id="date-filter" name="date-filter">
                    <select id="reporter-filter" name="reporter-filter">
                        <option value="">All Reporters</option>
                        <option value="farmer">Farmer Reports</option>
                        <option value="admin">Admin Reports</option>
                    </select>
                    <select id="crop-filter" name="filter">
                        <option value="">All Crops</option>
                        <option value="Other">Other</option>
                        <option value="rice">Rice</option>
                        <option value="corn">Corn</option>
                        <option value="coconut">Coconut</option>
                        <option value="coffee">Coffee</option>
                        <option value="sugarcane">Sugarcane</option>
                        <option value="black-pepper">Black Pepper</option>
                        <option value="cacao">Cacao</option>
                        <option value="sweet-potato">Sweet Potato</option>
                        <option value="cassava">Cassava</option>
                        <option value="ube/yam">Ube/Yam</option>
                        <option value="ginger">Ginger</option>
                        <option value="gabi">Gabi</option>
                        <option value="ampalaya">Ampalaya</option>
                        <option value="bell-pepper">Bell Pepper</option>
                        <option value="bush-sitao">Bush Sitao</option>
                        <option value="chayote">Chayote</option>
                        <option value="cowpea">Cowpea</option>
                        <option value="cucumber">Cucumber</option>
                        <option value="eggplant">Eggplant</option>
                        <option value="hot-pepper">Hot Pepper</option>
                        <option value="lady-finger">Lady Finger (Okra)</option>
                        <option value="lettuce">Lettuce</option>
                        <option value="mung-bean">Mung Bean</option>
                        <option value="mustard">Mustard</option>
                        <option value="patola">Patola</option>
                        <option value="pechay">Pechay</option>
                        <option value="pole-sitao">Pole Sitao</option>
                        <option value="radish">Radish</option>
                        <option value="sigarilyas">Sigarilyas</option>
                        <option value="snap-beans">Snap Beans</option>
                        <option value="soybeans">Soybeans</option>
                        <option value="squash">Squash</option>
                        <option value="tomato">Tomato</option>
                        <option value="upo">Upo</option>
                        <option value="avocado">Avocado</option>
                        <option value="banana">Banana</option>
                        <option value="calamansi">Calamansi</option>
                        <option value="citrus">Citrus</option>
                        <option value="pomelo">Pomelo</option>
                        <option value="papaya">Papaya</option>
                        <option value="dragon-fruit">Dragon Fruit</option>
                        <option value="durian">Durian</option>
                        <option value="guyabano">Guyabano</option>
                        <option value="jackfruit">Jackfruit</option>
                        <option value="lanzones">Lanzones</option>
                        <option value="mango">Mango</option>
                        <option value="mango-pajo">Mango Pajo</option>
                        <option value="mangosteen">Mangosteen</option>
                        <option value="pineapple">Pineapple</option>
                        <option value="rambutan">Rambutan</option>
                        <option value="santol">Santol</option>
                        <option value="tamarind">Tamarind</option>
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
                    <button id="view-graph-button">View Graph</button>
                </div>
            </div>
    
            <div class="cropreport-count" id="damage-report-count">Showing 0 of 0 Total Damage Reports</div>
            
            <!-- New Reports Section -->
            <h3 class="section-title">NEW CROP DAMAGE REPORTS (last 7 days)</h3>
            <div class="table-scroll">
            <table class="damagereport-table">
                <thead>
                    <tr>
                        <th>Farmer</th>
                        <th>Barangay</th>
                        <th>Crop Type</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Reported By</th>
                        <th>Reporter Type</th>
                        <th>Date of Occurrence</th>
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
            <h3 class="section-title">PREVIOUS CROP DAMAGE REPORTS</h3>
            <div class="table-scroll">
            <table class="damagereport-table">
                <thead>
                    <tr>
                        <th>Farmer</th>
                        <th>Barangay</th>
                        <th>Crop Type</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Reported By</th>
                        <th>Reporter Type</th>
                        <th>Date of Occurrence</th>
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
                    <span id="current-page">Page 1</span>
                    <button id="next-page">Next</button>
                </div>
            </div>

            <!-- Damage Report Modal -->
            <div id="damageReportModal" class="modal">
                <div class="modal-content">
                    <span class="close-button">&times;</span>
                    <h2 id="modalTitle">Crop Damage Report Details</h2>
                    <div id="damageReportImagePanel" class="report-image-detail" hidden>
                        <h3>Larawan ng Nasirang Pananim</h3>
                        <img id="damageReportImage" src="" alt="Damaged crop report image" hidden>
                        <p id="damageReportNoImage">Walang larawan.</p>
                    </div>
                    <div id="modal-body">
                        <p><strong>Farmer:</strong> <span id="farmerID"></span></p>
                        <p><strong>Reported By:</strong> <span id="reportedBy"></span></p>
                        <p><strong>Reporter Type:</strong> <span id="reporterType"></span></p>
                        <p><strong>Location:</strong> <span id="dmgLocation"></span></p>
                        <p><strong>Crop Commodity:</strong> <span id="dmgCropCommodity"></span></p>
                        <p><strong>Crop Type:</strong> <span id="dmgCropType"></span></p>
                        <p><strong>Crop Variety:</strong> <span id="dmgCropVariety"></span></p>
                        <p><strong>Date of Occurrence:</strong> <span id="dateOccurrence"></span></p>
                        <p><strong>Damage Cause:</strong> <span id="damageCause"></span></p>
                        <p><strong>Percent Damage:</strong> <span id="percentDamage"></span></p>
                        <p><strong>Number of Trees:</strong> <span id="numberOfTrees"></span></p>
                        <p><strong>Estimated Loss:</strong> <span id="estimatedLoss"></span></p>
                        <p><strong>Farm Gate Price:</strong> <span id="dmgFarmGatePrice"></span></p>
                        <p><strong>Latitude:</strong> <span id="damageLatitude"></span></p>
                        <p><strong>Longitude:</strong> <span id="damageLongitude"></span></p>
                        <p><strong>Date Reported:</strong> <span id="dmgReportDate"></span></p>
                    </div>
                    <div class="report-map-card">
                        <div class="report-map-card-header">
                            <div>
                                <h3>GIS Map Preview</h3>
                                <p>Preview the submitted damage report on the map using its saved coordinates.</p>
                            </div>
                            <div class="report-map-actions">
                                <button type="button" id="damage-show-directions-button" class="map-action-button map-action-button-secondary">
                                    Show Directions
                                </button>
                                <button type="button" id="damage-open-map-button" class="map-action-button">
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
                                    <div class="report-map-value" id="damageMapCardLocation">N/A</div>
                                    <div class="report-map-label">Location</div>
                                </div>
                            </div>
                            <div class="report-map-stat">
                                <div class="report-map-icon harvest-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v8m4-4H8"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 12a8 8 0 11-16 0 8 8 0 0116 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="report-map-value" id="damageMapCardCoordinates">N/A</div>
                                    <div class="report-map-label">Coordinates</div>
                                </div>
                            </div>
                        </div>

                        <div class="report-map-toolbar">
                            <button type="button" id="damage-map-street" class="report-map-layer-btn active">Street</button>
                            <button type="button" id="damage-map-satellite" class="report-map-layer-btn">Satellite</button>
                        </div>

                        <div id="damage-report-map-message" class="report-map-message">
                            No latitude and longitude available for this damage report.
                        </div>
                        <div id="damage-report-map"></div>
                    </div>
                </div>
            </div>

            <!-- Graph Modal -->
            <div id="graphModal" class="modal">
                <div class="modal-content">
                    <span class="close-button">&times;</span>
                    <h2>Crop Damage Distribution</h2>
                    <canvas id="damageChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
