<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Farm Parcel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="fpStyles.css">
</head>
<body>
    <div class="container">
        <?php include '../../shared/admin-sidebar.php'; ?>
        <div class="content">
            <div class="filters">
                <input type="text" id="searchBox" placeholder="Search by farmer name" onkeyup="filterTable()">
                <select id="locationFilter" onchange="filterTable()">
                    <option value="">All Locations</option>
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
                <select id="cropCommodityFilter" onchange="updateCropTypeFilter()">
                    <option value="">All Crop Commodities</option>
                    <option value="grains">Grains</option>
                    <option value="plantation">Plantation Crops</option>
                    <option value="root_crops">Root Crops</option>
                    <option value="vegetables">Vegetables</option>
                    <option value="fruits">Fruits</option>
                </select>
                <select id="cropTypeFilter">
                    <option value="" disabled selected>Crop Type</option>
                </select>
                <button id="exportButton">Export</button>
            </div>

            <div class="parcel-count" id="parcel-count">Showing 0 of 0 total Parcels</div>

            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Farmer Location</th>
                        <th>Number of Parcels</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="farmersTable">
                    <!-- Table content will be populated dynamically -->
                </tbody>
            </table>

            <div class="pagination-container">
                <div class="pagination">
                    <button id="prev-page">Previous</button>
                    <span id="current-page">Page 1</span>
                    <button id="next-page">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle"></h2>
            <br>
            <div id="parcelDetails">
                <div class="farm-info-section">
                    <!-- Farm Parcel Information will be inserted here -->
                </div>
                <div class="parcel-container">
                    <!-- Parcel details and crops will be dynamically added here -->
                </div>
            </div>
            <div id="navigationButtons">
                <button id="prevButton" onclick="navigateParcel(-1)">&lt; Previous</button>
                <button id="nextButton" onclick="navigateParcel(1)">Next &gt;</button>
            </div>
        </div>
    </div>

    <script src="../../../shared/crop-catalog.js"></script>
    <script src="scripts.js"></script>
</body>
</html>
