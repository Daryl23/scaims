<?php
session_start();
if (!isset($_SESSION['UName'])) {
    header("Location: ../farmerLogin/farmerlogin.html");
    exit();
}

if (isset($_SESSION['message'])) {
    echo "<div class='alert alert-success'>{$_SESSION['message']}</div>";
    unset($_SESSION['message']);
}

if (!isset($_SESSION['UName'])) {
    error_log('Session UName is not set.');
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Parcel Information</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="farmStyles.css">
    <style>
        .button-container button,
        .button-container button[type="submit"],
        .btn-edit-parcel,
        button[type="button"],
        a[class*="btn"] {
            font-family: 'Poppins', sans-serif !important;
            font-weight: 500;
            letter-spacing: 0.25px;
        }
        
        .crop-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .crop-group {
            flex: 1;
            min-width: 200px;
        }
        
        @media (max-width: 768px) {
            .crop-row {
                flex-direction: column;
            }
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                width: 250px !important;
                z-index: 999;
            }
            
            .menu-toggle {
                display: block;
                z-index: 1000;
            }
            
            .container {
                flex-direction: column;
            }
            
            .main-content {
                width: 100%;
                padding: 15px;
                margin-top: 60px;
            }
        }
        .menu-toggle {
            display: none;
        }
        .sidebar-overlay {
            display: none;
        }
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1000;
                background: #198754;
                border: none;
                padding: 10px;
                border-radius: 5px;
                cursor: pointer;
            }
            .menu-toggle span {
                display: block;
                width: 25px;
                height: 3px;
                background: white;
                margin: 5px 0;
                transition: all 0.3s ease;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 998;
            }
            .sidebar-overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <form action="submitFarmInfo.php" method="POST" enctype="multipart/form-data" id="farmForm">
            <h2>ADD FARM PARCEL</h2>
            <fieldset>
                <hr>
                <h3>Parcel Information</h3>
                <div class="form-row">
                    <!-- Farm Location -->
                    <div class="form-group">
                        <label for="location_1">Parcel Location:</label>
                        <select id="location_1" name="location_1" required>
                        <option value="" disabled selected>Pumili ng Barangay</option>
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
                    </div>

                    <!-- Total Farm Area -->
                    <div class="form-group">
                        <label for="totalArea_1">Parcel Size (ha):</label>
                        <input type="number" id="totalArea_1" name="totalArea_1" min="0" step="0.0001" placeholder="Parcel Area" required>
                    </div>

                    <!-- Ownership Type -->
                    <div class="form-group">
                        <label for="ownership_1">Ownership Type:</label>
                        <select id="ownership_1" name="ownership_1" required onchange="checkCustomOwnership(1)">
                            <option value="" disabled selected></option>
                            <option value="registered_owner">Registered Owner</option>
                            <option value="lessee">Lessee</option>
                            <option value="tenant">Tenant</option>
                            <option value="other">Other</option>
                        </select>
                        <input type="text" id="customOwnership_1" name="customOwnership_1" 
                               placeholder="Specify ownership type" style="display:none;">
                    </div>
                </div>

                <hr>
                <!-- Number of Crops -->
                <div class="form-group">
                    <label for="numCrops_1">Number of Crops:</label>
                    <input type="number" id="numCrops_1" name="numCrops_1" min="1" placeholder="Ilagay ang bilang ng crops" required onchange="generateCropFields(1)">
                </div>

                <!-- Container for crop fields -->
                <div id="cropFields_1"></div>
            </fieldset>

            <hr>

            <div class="form-row">
                <div class="form-group">
                    <label for="farmType">Farm Type:</label>
                    <select id="farmType" name="farmType" required>
                        <option value="" disabled selected>Pumili ng Farm Type</option>
                        <option value="irrigated">Irrigated</option>
                        <option value="rainfed_upland">Rainfed Upland</option>
                        <option value="rainfed_lowland">Rainfed Lowland</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="organic">Organic Practitioner:</label>
                    <select id="organic" name="organic" required>
                        <option value="" disabled selected></option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                </div>
            </div>

            <hr>

            <div class="button-container">
                <button type="submit">Add Parcel</button>
                <button type="button" class="btn btn-secondary" onclick="confirmCancel()">Cancel</button>
            </div>
        </form>
    </div>

    <script src="../../../shared/crop-catalog.js"></script>
    <script src="farmForm.js"></script>
    
    <script>
        function confirmCancel() {
            if (confirm("Are you sure you do not want to proceed?")) {
                window.location.href = "farmersFP.php";
            }
        }
    </script>
    
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');

        menuToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', function() {
            menuToggle.classList.remove('active');
            sidebar.classList.remove('active');
            this.classList.remove('active');
        });
    });
    </script>
</body>
</html>
