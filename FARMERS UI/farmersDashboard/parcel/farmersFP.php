<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', 'error.log');
error_log("Script started");

session_start();
error_log("Session started");
error_log('Session UserID: ' . ($_SESSION['UserID'] ?? 'Not set'));

    if (!isset($_SESSION['UName'])) {
        header("Location: ../../farmerLogin/farmerlogin.html");
        exit();
    }

    include 'db_connection.php'; 
    $user_name = $_SESSION['UName']; 

    $farmTypeMapping = [
        'rainfed_lowland' => 'Rainfed Lowland',
        'rainfed_upland' => 'Rainfed Upland',
        'irrigated' => 'Irrigated',
    ];

    $cropCommodityMapping = [
        'grains' => 'Grains',
        'plantation' => 'Plantation Crops',
        'root_crops' => 'Root Crops',
        'vegetables' => 'Vegetables',
        'fruits' => 'Fruits'
    ];

    $treeStatusMapping = [
        'seedling' => 'Seedling',
        'bearing' => 'Bearing',
        'non-bearing' => 'Non-Bearing',
    ];

    $ownershipTypeMapping = [
        'registered_owner' => 'Registered Owner',
        'lessee' => 'Lessee',
        'tenant' => 'Tenant',
    ];

    $farmLocationMapping = [
        'adya' => 'Adya',
        'anilao' => 'Anilao',
        'anilao-labac' => 'Anilao-Labac',
        'antipolodelnorte' => 'Antipolo del Norte',
        'antipolodelsur' => 'Antipolo del Sur',
        'bagongpook' => 'Bagong Pook',
        'balintawak' => 'Balintawak',
        'banaybanay' => 'Banaybanay',
        'bolbok' => 'Bolbok',
        'bugtongnapulo' => 'Bugtong na Pulo',
        'bulacnin' => 'Bulacnin',
        'bulaklakan' => 'Bulaklakan',
        'calamias' => 'Calamias',
        'cumba' => 'Cumba',
        'dagatan' => 'Dagatan',
        'duhatan' => 'Duhatan',
        'halang' => 'Halang',
        'inosluban' => 'Inosluban',
        'kayumanggi' => 'Kayumanggi',
        'latag' => 'Latag',
        'lodlod' => 'Lodlod',
        'lumbang' => 'Lumbang',
        'mabini' => 'Mabini',
        'malagonlong' => 'Malagonlong',
        'malitlit' => 'Malitlit',
        'marawoy' => 'Marawoy',
        'mataasnalupa' => 'Mataas na Lupa',
        'muntingpulo' => 'Munting Pulo',
        'pagolinginbata' => 'Pagolingin Bata',
        'pagolingineast' => 'Pagolingin East',
        'pagolinginwest' => 'Pagolingin West',
        'pangao' => 'Pangao',
        'pinagkawitan' => 'Pinagkawitan',
        'pinagtongulan' => 'Pinagtongulan',
        'plaridel' => 'Plaridel',
        'poblacionbarangay1' => 'Poblacion Barangay 1',
        'poblacionbarangay2' => 'Poblacion Barangay 2',
        'poblacionbarangay3' => 'Poblacion Barangay 3',
        'poblacionbarangay4' => 'Poblacion Barangay 4',
        'poblacionbarangay5' => 'Poblacion Barangay 5',
        'poblacionbarangay6' => 'Poblacion Barangay 6',
        'poblacionbarangay7' => 'Poblacion Barangay 7',
        'poblacionbarangay8' => 'Poblacion Barangay 8',
        'poblacionbarangay9' => 'Poblacion Barangay 9',
        'poblacionbarangay9a' => 'Poblacion Barangay 9-A',
        'poblacionbarangay10' => 'Poblacion Barangay 10',
        'poblacionbarangay11' => 'Poblacion Barangay 11',
        'poblacionbarangay12' => 'Poblacion Barangay 12',
        'pusil' => 'Pusil',
        'quezon' => 'Quezon',
        'rizal' => 'Rizal',
        'sabang' => 'Sabang',
        'sampaguita' => 'Sampaguita',
        'sanbenito' => 'San Benito',
        'sancarlos' => 'San Carlos',
        'sancelestino' => 'San Celestino',
        'sanfrancisco' => 'San Francisco',
        'sanguillermo' => 'San Guillermo',
        'sanjose' => 'San Jose',
        'sanlucas' => 'San Lucas',
        'sansalvador' => 'San Salvador',
        'sansebastian' => 'San Sebastian',
        'santonino' => 'Santo Niño',
        'santoribio' => 'Santo Toribio',
        'sapac' => 'Sapac',
        'sico' => 'Sico',
        'talisay' => 'Talisay',
        'tambo' => 'Tambo',
        'tangob' => 'Tangob',
        'tanguay' => 'Tanguay',
        'tibig' => 'Tibig',
        'tipacan' => 'Tipacan',
    ];

    $parcels_query = "SELECT 
        fp.id as farm_parcel_id,
        fp.farm_location,
        fp.farm_area,
        fp.num_parcels,
        fp.farm_type,
        fp.organic,
        pd.id as parcel_detail_id,
        pd.location,
        pd.total_area,
        pd.ownership_type,
        f.Barangay,
        f.UserID,
        GROUP_CONCAT(
            JSON_OBJECT(
                'crop_commodity', cd.crop_commodity,
                'crop_type', cd.crop_type,
                'crop_variety', cd.crop_variety,
                'num_trees', cd.num_trees,
                'tree_status', cd.tree_status
            )
        ) as crops,
        (SELECT SUM(total_area) FROM parcel_details WHERE created_by = ?) as total_farm_area
    FROM farm_parcels fp
    LEFT JOIN parcel_details pd ON fp.id = pd.farm_parcel_id
    LEFT JOIN crop_details cd ON pd.id = cd.parcel_detail_id
    LEFT JOIN farmer f ON fp.created_by = f.UserID
    WHERE fp.created_by = ?
    GROUP BY fp.id, pd.id
    ORDER BY fp.id, pd.id";

    $stmt = $conn->prepare($parcels_query);
    $stmt->bind_param('ss', $user_name, $user_name);
    $stmt->execute();
    $result = $stmt->get_result();

    $parcels = array();
    $current_parcel = null;

    while ($row = $result->fetch_assoc()) {
        // Debug: print out raw row data
        error_log('Raw Row Data: ' . print_r($row, true));
        
        // Check if Barangay is set
        if (!isset($row['Barangay'])) {
            error_log('Barangay is not set for UserID: ' . $row['UserID']);
        } else {
            error_log('Barangay value: ' . $row['Barangay']);
        }

        // If this is a new parcel or the first row
        if ($current_parcel === null || $current_parcel['parcel_detail_id'] !== $row['parcel_detail_id']) {
            // If we have a previous parcel, add it to the parcels array
            if ($current_parcel !== null) {
                $parcels[] = $current_parcel;
            }
            
            // Start a new parcel
            $current_parcel = array(
                'farm_parcel_id' => $row['farm_parcel_id'],
                'farm_location' => $row['farm_location'],
                'farm_area' => $row['farm_area'],
                'num_parcels' => $row['num_parcels'],
                'farm_type' => $row['farm_type'],
                'organic' => $row['organic'],
                'parcel_detail_id' => $row['parcel_detail_id'],
                'location' => $row['location'],
                'total_area' => $row['total_area'],
                'ownership_type' => $row['ownership_type'],
                'crops' => array(),
                'farmer' => [
                    'UserID' => $row['UserID'] ?? null,
                    'Barangay' => $row['Barangay'] ?? 'N/A'
                ],
                'total_farm_area' => $row['total_farm_area'] ?? 0
            );
        }
        
        // Add crops if they exist
        if ($row['crops'] !== null) {
            $crops = json_decode('[' . $row['crops'] . ']', true);
            error_log('Decoded Crops: ' . print_r($crops, true));
            if (is_array($crops)) {
                $current_parcel['crops'] = array_merge($current_parcel['crops'], $crops);
            }
        }
    }

    // Add the last parcel to the array
    if ($current_parcel !== null) {
        $parcels[] = $current_parcel;
    }

    // Debug: print out final parcels array
    error_log('Final Parcels: ' . print_r($parcels, true));

    // Get selected parcel if any
    $selected_parcel = isset($_POST['parcel_id']) ? $_POST['parcel_id'] : (isset($_GET['parcel']) ? $_GET['parcel'] : (isset($parcels[0]) ? $parcels[0]['parcel_detail_id'] : null));
    $display_parcel = null;

    if ($selected_parcel !== null) {
        foreach ($parcels as $parcel) {
            if ($parcel['parcel_detail_id'] == $selected_parcel) {
                $display_parcel = $parcel;
                break;
            }
        }
    }

    // If no parcel is selected, use the first parcel
    if ($display_parcel === null && !empty($parcels)) {
        $display_parcel = $parcels[0];
    }

    $parcel_count = count($parcels);

    // After the parcel is added, fetch the Barangay value
    if (isset($_SESSION['UserID'])) {
        $user_id = $_SESSION['UserID'];
        $barangay_query = "SELECT Barangay FROM farmer WHERE UserID = ?";
        $stmt_barangay = $conn->prepare($barangay_query);
        $stmt_barangay->bind_param('s', $user_id);
        $stmt_barangay->execute();
        $result_barangay = $stmt_barangay->get_result();

        if ($result_barangay->num_rows > 0) {
            $row_barangay = $result_barangay->fetch_assoc();
            $barangay_value = ucfirst(htmlspecialchars($row_barangay['Barangay']));
        } else {
            $barangay_value = 'N/A'; // Default value if no barangay is found
        }
    } else {
        $barangay_value = 'N/A'; // Default value if UserID is not set
        error_log('UserID is not set in session.');
    }

    $dashboardCurrentPage = 'parcel';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Farmers Parcel</title>
    <link rel="stylesheet" href="../../../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../shared/dashboard-shell.css">
    <link rel="stylesheet" href="farmerParcel.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        .farm-parcels-container {
            background-color: #fefffb;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .parcels-section {
            margin-bottom: 30px;
        }

        .parcels-section select {
            width: 100%;
            padding: 10px;
            border: 1px solid #674636;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            margin-top: 10px;
        }

        .parcel-info-section {
            background-color: #f5f5f3;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-row p {
            margin: 5px 0;
            color: #674636;
        }

        .crops-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .crop-card {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .crop-card h4 {
            color: #576f1b;
            margin: 0 0 10px 0;
            font-size: 1.1em;
        }

        .crop-card p {
            margin: 5px 0;
            color: #674636;
            font-size: 0.9em;
        }

        .no-parcel {
            text-align: center;
            color: #674636;
            padding: 40px;
            background-color: #f5f5f3;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .parcel-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .add-parcel-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .add-parcel-btn:hover {
            background-color: #218838;
        }

        .crops-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            padding: 10px;
        }

        @media (max-width: 768px) {
            .crops-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Add this rule for the parcel control buttons */
        .parcel-controls .btn-add-parcel,
        .parcel-controls .btn-edit-parcel {
            font-family: 'Poppins', sans-serif !important;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
    </style>
    <link rel="stylesheet" href="../shared/farmer-mobile.css?v=<?php echo @filemtime(__DIR__ . '/../shared/farmer-mobile.css') ?: time(); ?>">
</head>
<body>
    <div class="container">
        <?php include '../shared/sidebar.php'; ?>

        <div class="main-content">
            <header class="header">
                <h1 data-i18n="parcel_title">FARM PARCEL</h1>
                <div class="parcel-controls">
                    <select class="parcel-dropdown" id="parcelSelect">
                        <?php foreach ($parcels as $index => $parcel): ?>
                            <option value="<?php echo $parcel['parcel_detail_id']; ?>" <?php echo $index === 0 ? 'selected' : ''; ?>>
                                Parcel <?php echo $index + 1; ?> (<?php echo ucwords(htmlspecialchars($parcel['location'] ?? 'Unnamed Parcel')); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="parcel-buttons">
                        <button onclick="addNewParcel()" class="btn btn-add-parcel" data-i18n-aria-label="parcel_add_button">
                            <i class="bi bi-plus-lg"></i> <span data-i18n="parcel_add_button">Add Parcel</span>
                        </button>
                        <button onclick="editCurrentParcel()" class="btn btn-edit-parcel" data-i18n-aria-label="parcel_edit_button">
                            <i class="bi bi-pencil-square"></i> <span data-i18n="parcel_edit_button">Edit Parcel</span>
                        </button>
                    </div>
                </div>
            </header>
            <div class="dashboard-help-note">
                <p data-i18n="parcel_help">Choose a parcel to view its farm details and crop information.</p>
            </div>

            <?php foreach ($parcels as $index => $parcel): ?>   
            <div class="parcel-card" id="parcel-<?php echo $parcel['parcel_detail_id']; ?>" style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon">
                            <img src="assets/nav-icons/location.png" alt="Farm Location">
                        </div>
                        <div class="info-text">
                            <h3 data-i18n="parcel_farmer_location">Farmer Location</h3>
                            <p><?php echo $barangay_value; ?></p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">
                            <img src="assets/nav-icons/area.png" alt="Total Farm Area">
                        </div>
                        <div class="info-text">
                            <h3 data-i18n="parcel_total_farm_area">Total Farm Area</h3>
                            <p><?php echo htmlspecialchars($parcel['total_farm_area']); ?> hectares</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">
                            <img src="assets/nav-icons/farm.png" alt="Farm Type">
                        </div>
                        <div class="info-text">
                            <h3 data-i18n="parcel_farm_type">Farm Type</h3>
                            <p><?php echo htmlspecialchars($farmTypeMapping[$parcel['farm_type']]); ?></p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">
                            <img src="assets/nav-icons/organic.png" alt="Organic">
                        </div>
                        <div class="info-text">
                            <h3 data-i18n="parcel_organic_practitioner">Organic Practitioner</h3>
                            <p><?php echo $parcel['organic'] === 'yes' ? 'Yes' : 'No'; ?></p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">
                            <img src="assets/nav-icons/parcel_area.png" alt="Parcel Area">
                        </div>
                        <div class="info-text">
                            <h3 data-i18n="parcel_area_label">Parcel Area</h3>
                            <p><?php echo htmlspecialchars($parcel['total_area']); ?> hectares</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">
                            <img src="assets/nav-icons/ownership.png" alt="Ownership Type">
                        </div>
                        <div class="info-text">
                            <h3 data-i18n="parcel_ownership_type">Ownership Type</h3>
                            <?php if (isset($parcel['ownership_type'])) {
                                $ownershipTypeDisplay = $ownershipTypeMapping[$parcel['ownership_type']] ?? ucwords($parcel['ownership_type']);
                            } else {
                                $ownershipTypeDisplay = 'Unknown';
                            }
                            ?>
                            <p><?php echo htmlspecialchars($ownershipTypeDisplay); ?></p>
                        </div>
                    </div>
                </div>
                <style>
                    /* Base styles for the info grid */
                    .info-grid {
                        display: grid; /* Use CSS Grid */
                        grid-template-columns: repeat(auto-fill, minmax(1fr)); /* Responsive columns */
                        gap: 20px; /* Space between grid items */
                    }

                    /* Styles for each info item */
                    .info-item {
                        background-color: white; /* Background color for items */
                        border: 1px solid #e0e0e0;
                        border-radius: 8px; /* Rounded corners */
                    }

                    /* Responsive styles for mobile devices */
                    @media only screen and (max-width: 768px) {
                        .info-grid {
                            grid-template-columns: 1fr; /* Stack items in a single column */
                        }
                    }
                </style>
                <div class="crops-information">
                    <h2 data-i18n="parcel_crops_information">Crops Information</h2>
                    <?php 
                    $parcelCrops = isset($parcel['crops']) && is_array($parcel['crops']) ? $parcel['crops'] : [];
                    if (!empty($parcelCrops)) { ?>
                        <?php foreach ($parcelCrops as $cropIndex => $crop) { ?>
                        <div class="crop-details" style="display: <?php echo $cropIndex === 0 ? 'block' : 'none'; ?>">
                            <div class="crops-grid" id="cropContent">
                                <div class="crop-item">
                                    <h3 data-i18n="parcel_crop_commodity">Crop Commodity</h3>
                                    <p><?php 
                                        $commodity = $crop['crop_commodity'] ?? 'N/A';
                                        echo isset($cropCommodityMapping[$commodity]) ? $cropCommodityMapping[$commodity] : $commodity; 
                                    ?></p>
                                </div>
                                <div class="crop-item">
                                    <h3 data-i18n="parcel_crop_type">Crop Type</h3>
                                    <p><?php echo htmlspecialchars($crop['crop_type'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="crop-item">
                                    <h3 data-i18n="parcel_crop_variety">Crop Variety</h3>
                                    <p><?php echo htmlspecialchars($crop['crop_variety'] ?? 'N/A'); ?></p>
                                </div>
                                <?php if (!empty($crop['num_trees'])): ?>
                                <div class="crop-item">
                                    <h3 data-i18n="parcel_number_of_trees">Number of Trees</h3>
                                    <p><?php echo htmlspecialchars($crop['num_trees']); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($crop['tree_status'])): ?>
                                <div class="crop-item">
                                    <h3 data-i18n="parcel_tree_status">Tree Status</h3>
                                    <p><?php 
                                        $treeStatus = $crop['tree_status'] ?? 'N/A';
                                        echo isset($treeStatusMapping[$treeStatus]) ? $treeStatusMapping[$treeStatus] : $treeStatus; 
                                    ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php } ?>
                        
                        <div class="crop-navigation">
                            <?php if (count($parcelCrops) > 1) { ?>
                                <button class="prev-btn" onclick="navigateCrop(-1)" data-i18n="parcel_previous">Previous</button>
                                <span class="crop-count" id="cropCount" data-total-crops="<?php echo count($parcelCrops); ?>">1 / <?php echo count($parcelCrops); ?></span>
                                <button class="next-btn" onclick="navigateCrop(1)" data-i18n="parcel_next">Next</button>
                            <?php } else { ?>
                                <span class="crop-count">1 / 1</span>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="crops-grid">
                            <p data-i18n="parcel_no_crops">No crops found for this parcel.</p>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
    function addNewParcel() {
        // Redirect to the farm parcel registration page with correct path
        window.location.href = 'farmParcel.php';
    }

    function openNewParcelForm() {
        // Existing function, kept for compatibility
        window.location.href = 'farmParcel.php';
    }

    const parcelSelect = document.getElementById('parcelSelect');
    parcelSelect.addEventListener('change', function() {
        const selectedParcel = this.value;
        document.querySelectorAll('.parcel-card').forEach(card => {
            card.style.display = 'none';
        });
        document.getElementById(`parcel-${selectedParcel}`).style.display = 'block';
    });
    </script>
    <script>
    function navigateCrop(direction) {
        // Find the currently visible parcel card
        const visibleParcelCard = document.querySelector('.parcel-card[style*="display: block"]');
        if (!visibleParcelCard) return;

        // Find crop details within this parcel card
        const cropContainers = visibleParcelCard.querySelectorAll('.crop-details');
        const cropCount = visibleParcelCard.querySelector('.crop-count');
        const totalCrops = cropContainers.length;

        if (totalCrops <= 1) return;

        let currentIndex = parseInt(cropCount.dataset.currentIndex || 0);

        // Hide all crop details
        cropContainers.forEach(container => {
            container.style.display = 'none';
        });

        // Calculate new index
        currentIndex += direction;

        // Ensure index is within bounds
        currentIndex = Math.max(0, Math.min(currentIndex, totalCrops - 1));

        // Show current crop details
        if (cropContainers[currentIndex]) {
            cropContainers[currentIndex].style.display = 'block';
        }

        // Update crop count display
        cropCount.textContent = `${currentIndex + 1} / ${totalCrops}`;
        cropCount.dataset.currentIndex = currentIndex;

        // Disable/enable navigation buttons
        const prevBtn = visibleParcelCard.querySelector('.prev-btn');
        const nextBtn = visibleParcelCard.querySelector('.next-btn');
        
        prevBtn.disabled = (currentIndex <= 0);
        nextBtn.disabled = (currentIndex >= totalCrops - 1);
    }

    // Parcel selection functionality
    document.addEventListener('DOMContentLoaded', function() {
        const parcelSelect = document.getElementById('parcelSelect');
        const parcelCards = document.querySelectorAll('.parcel-card');

        parcelSelect.addEventListener('change', function() {
            const selectedParcelId = this.value;

            // Hide all parcel cards
            parcelCards.forEach(card => {
                card.style.display = 'none';
            });

            // Show selected parcel card
            const selectedParcelCard = document.getElementById(`parcel-${selectedParcelId}`);
            if (selectedParcelCard) {
                selectedParcelCard.style.display = 'block';
            }

            // Reset crop navigation for the selected parcel
            const cropContainers = selectedParcelCard.querySelectorAll('.crop-details');
            const cropCount = selectedParcelCard.querySelector('.crop-count');
            const totalCrops = cropContainers.length;

            // Initially hide all but the first crop details
            cropContainers.forEach((container, index) => {
                container.style.display = index === 0 ? 'block' : 'none';
            });

            // Update crop count
            if (cropCount) {
                cropCount.textContent = `1 / ${totalCrops}`;
                cropCount.dataset.currentIndex = 0;

                // Disable/enable navigation buttons
                const prevBtn = selectedParcelCard.querySelector('.prev-btn');
                const nextBtn = selectedParcelCard.querySelector('.next-btn');
                
                if (prevBtn && nextBtn) {
                    prevBtn.disabled = (totalCrops <= 1);
                    nextBtn.disabled = (totalCrops <= 1);
                }
            }
        });
    });
    </script>
    <script>
    function editCurrentParcel() {
        // Get the currently selected parcel ID
        const parcelSelect = document.getElementById('parcelSelect');
        const selectedParcelId = parcelSelect.value;
        
        // Redirect to the farm parcel edit page with the selected parcel ID
        window.location.href = `editParcel.php?parcel_id=${selectedParcelId}`;
    }
    </script>
    <script src="../shared/dashboard-language.js"></script>
    <script src="../shared/dashboard-shell.js"></script>
</body>
</html>
