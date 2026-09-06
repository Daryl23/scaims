<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User Management</title>
    <link rel="stylesheet" href="user.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="userManagement.js" defer></script>
</head>
<body>
<div class="container">
<?php include '../../shared/admin-sidebar.php'; ?>

    
<div class="content">
    <div class="header" id="search-container">
        <input type="text" id="search-bar" placeholder="Search farmer name">
        <div class="filter-group">
            <select id="user-filter" name="filter">
                <option value="Farmer">Farmers</option>
                <option value="Admin">Admins</option>
            </select>
            <select id="rsbsa-filter" name="rsbsa-filter" class="filter-select">
                <option value="">RSBSA</option>
                <option value="has">With RSBSA Number</option>
                <option value="no">Without RSBSA Number</option>
            </select>
            <select id="barangay-filter" name="barangay-filter" class="filter-select">
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
                <option value="san benito">San Benito</option>
                <option value="sancarlos">San Carlos</option>
                <option value="sancelestino">San Celestino</option>
                <option value="sanfrancisco">San Francisco</option>
                <option value="sanguillermo">San Guillermo</option>
                <option value="sanjose">San Jose</option>
                <option value="sanlucas">San Lucas</option>
                <option value="sansalvador">San Salvador</option>
                <option value="sansebastian">San Sebastian</option>
                <option value="santonino">Santo Niño</option>
                <option value="santo toribio">Santo Toribio</option>
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
        </div>
    </div>

    <div id="user-count-container">
        <span id="user-count"></span>
    </div>
    <div id="user-table-body">
        <!-- Dynamic content will be inserted here -->
    </div>
    
</div>
</div>
</body>
</html>
