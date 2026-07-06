<?php
session_start();
if (!isset($_SESSION['UName'])) {
    header("Location: ../farmerLogin/farmerlogin.html");
    exit();
}

// Include database connection
require_once 'db_connection.php';

// Get the parcel ID to edit
$parcelDetailId = isset($_GET['parcel_id']) ? intval($_GET['parcel_id']) : null;

// Fetch parcel details
$parcelDetails = null;
$cropDetails = [];

if ($parcelDetailId) {
    // Fetch parcel details
    $parcelQuery = "SELECT pd.*, fp.farm_location AS farm_location, fp.farm_area, fp.farm_type, fp.organic 
                    FROM parcel_details pd
                    JOIN farm_parcels fp ON pd.farm_parcel_id = fp.id
                    WHERE pd.id = ? AND pd.created_by = ?";
    $stmt = $conn->prepare($parcelQuery);
    $stmt->bind_param("is", $parcelDetailId, $_SESSION['UName']);
    $stmt->execute();
    $result = $stmt->get_result();
    $parcelDetails = $result->fetch_assoc();

    // Fetch crop details for this parcel
    $cropQuery = "SELECT * FROM crop_details WHERE parcel_detail_id = ?";
    $cropStmt = $conn->prepare($cropQuery);
    $cropStmt->bind_param("i", $parcelDetailId);
    $cropStmt->execute();
    $cropResult = $cropStmt->get_result();
    
    while ($cropRow = $cropResult->fetch_assoc()) {
        $cropDetails[] = $cropRow;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and process parcel edit
    $location = $_POST['location'] ?? $parcelDetails['location'];
    $farmLocation = $_POST['farm_location'] ?? $parcelDetails['farm_location'];
    $totalArea = $_POST['totalArea'] ?? $parcelDetails['total_area'];
    $farmArea = $_POST['farm_area'] ?? $parcelDetails['farm_area'];
    $ownershipType = $_POST['ownership'] ?? $parcelDetails['ownership_type'];

    // Check if ownershipType is empty
    if (empty($ownershipType)) {
        $errorMessage = "Ownership type is required.";
        // Handle error accordingly
    }

    // Start a transaction to ensure both updates succeed
    $conn->begin_transaction();

    try {
        // Update parcel details
        $updateParcelQuery = "UPDATE parcel_details 
                    SET location = ?, total_area = ?, ownership_type = ?
                    WHERE id = ? AND created_by = ?";
        $updateParcelStmt = $conn->prepare($updateParcelQuery);
        $updateParcelStmt->bind_param("sdsss", $location, $totalArea, $ownershipType, $parcelDetailId, $_SESSION['UName']);
        
        // Update farm parcels
        $updateFarmParcelQuery = "UPDATE farm_parcels fp
                    JOIN parcel_details pd ON fp.id = pd.farm_parcel_id
                    SET fp.farm_location = ?, fp.farm_area = ?
                    WHERE pd.id = ? AND pd.created_by = ?";
        $updateFarmParcelStmt = $conn->prepare($updateFarmParcelQuery);
        $updateFarmParcelStmt->bind_param("sdss", $farmLocation, $farmArea, $parcelDetailId, $_SESSION['UName']);

        // Execute both updates
        if ($updateParcelStmt->execute() && $updateFarmParcelStmt->execute()) {
            // Process crop details
            // First, delete existing crops for this parcel
            $deleteCropsQuery = "DELETE FROM crop_details WHERE parcel_detail_id = ?";
            $deleteStmt = $conn->prepare($deleteCropsQuery);
            $deleteStmt->bind_param("i", $parcelDetailId);
            $deleteStmt->execute();

            // Insert new crop details
            $cropInsertQuery = "INSERT INTO crop_details 
                                (parcel_detail_id, crop_commodity, crop_type, crop_variety, num_trees, tree_status, created_by) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
            $cropStmt = $conn->prepare($cropInsertQuery);

            // Process crops
            $numCrops = $_POST['numCrops'] ?? 0;
            for ($i = 1; $i <= $numCrops; $i++) {
                $cropCommodity = $_POST["crop_$i"] ?? '';
                $cropType = $_POST["cropType_$i"] ?? '';
                $cropVariety = $_POST["cropVariety_$i"] ?? '';
                $numTrees = $_POST["numTrees_$i"] ?? null;
                $treeStatus = $_POST["treeStatus_$i"] ?? null;

                if (!empty($cropCommodity) && !empty($cropType)) {
                    $cropStmt->bind_param(
                        "issssss", 
                        $parcelDetailId, 
                        $cropCommodity, 
                        $cropType, 
                        $cropVariety, 
                        $numTrees, 
                        $treeStatus,
                        $_SESSION['UName']
                    );
                    $cropStmt->execute();
                }
            }

            // Commit the transaction
            $conn->commit();

            // Instead of setting session message and redirecting immediately,
            // return a JSON response
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                // AJAX request
                echo json_encode(['success' => true, 'message' => 'Parcel updated successfully!']);
                exit;
            } else {
                // Regular form submission
                $_SESSION['message'] = "Parcel updated successfully!";
                header("Location: farmersFP.php");
                exit();
            }
        } else {
            // Rollback the transaction if any update fails
            $conn->rollback();
            $errorMessage = "Failed to update parcel. Please try again.";
        }
    } catch (Exception $e) {
        // Rollback the transaction in case of any error
        $conn->rollback();
        $errorMessage = "An error occurred: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Farm Parcel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="farmStyles.css">
    <style>
        /* Add only crop-specific styles that don't exist in farmStyles.css */
        .crop-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .crop-group {
            flex: 1;
            min-width: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <form action="editParcel.php?parcel_id=<?php echo $parcelDetailId; ?>" method="POST" enctype="multipart/form-data" id="editParcelForm">
            <h2>EDIT FARM PARCEL</h2>
            <hr>
            <h3>Farm Parcel Information</h3>

            <div class="form-row">
                <!-- Farm Location -->
                <div class="form-group">
                    <label for="location">Farm Location:</label>
                    <select id="location" name="location" required>
                        <?php 
                        $barangayOptions = [
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
                        foreach ($barangayOptions as $value => $label): 
                            // Use the parcel's location instead of farm_location
                            $selected = ($parcelDetails['location'] === $value) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                        <label for="ownership">Ownership Type:</label>
                        <select id="ownership" name="ownership" required>
                            <option value="" disabled selected></option>
                            <option value="registered_owner" <?php echo ($parcelDetails['ownership_type'] === 'registered_owner') ? 'selected' : ''; ?>>Registered Owner</option>
                            <option value="lessee" <?php echo ($parcelDetails['ownership_type'] === 'lessee') ? 'selected' : ''; ?>>Lessee</option>
                            <option value="tenant" <?php echo ($parcelDetails['ownership_type'] === 'tenant') ? 'selected' : ''; ?>>Tenant</option>
                        </select>
                    </div>

            
            </div>

            <hr>

            <div class="form-group">
                <label for="numCrops">Number of Crops:</label>
                <input type="number" id="numCrops" name="numCrops" min="1" 
                       value="<?php echo count($cropDetails); ?>" 
                       placeholder="Number of Crops" required onchange="generateCropFields()">
            </div>

            <!-- Container for crop forms -->
            <div id="cropFields">
                <?php 
                if (!empty($cropDetails)) {
                    foreach ($cropDetails as $index => $crop) {
                        $cropIndex = $index + 1;
                ?>
                    <fieldset id="cropFieldset_<?php echo $cropIndex; ?>">
                        <h4>
                            Crop <?php echo $cropIndex; ?>
                            <button type="button" class="btn btn-danger btn-sm ml-2" onclick="removeCrop(<?php echo $cropIndex; ?>)">Remove Crop</button>
                        </h4>
                        <div class="crop-row">
                            <div class="crop-group">
                                <label for="crop_<?php echo $cropIndex; ?>">Crop Commodity:</label>
                                <select id="crop_<?php echo $cropIndex; ?>" name="crop_<?php echo $cropIndex; ?>" 
                                        required onchange="showCropType(<?php echo $cropIndex; ?>)">
                                    <option value="" disabled selected>Pumili ng Crop Commodity</option>
                                    <option value="grains" <?php echo ($crop['crop_commodity'] === 'grains') ? 'selected' : ''; ?>>Grains</option>
                                    <option value="plantation" <?php echo ($crop['crop_commodity'] === 'plantation') ? 'selected' : ''; ?>>Plantation Crops</option>
                                    <option value="root_crops" <?php echo ($crop['crop_commodity'] === 'root_crops') ? 'selected' : ''; ?>>Root Crops</option>
                                    <option value="vegetables" <?php echo ($crop['crop_commodity'] === 'vegetables') ? 'selected' : ''; ?>>Vegetables</option>
                                    <option value="fruits" <?php echo ($crop['crop_commodity'] === 'fruits') ? 'selected' : ''; ?>>Fruits</option>
                                </select>
                            </div>

                            <div class="crop-group" id="cropTypeContainer_<?php echo $cropIndex; ?>">
                                <label for="cropType_<?php echo $cropIndex; ?>">Crop Type:</label>
                                <select id="cropType_<?php echo $cropIndex; ?>" name="cropType_<?php echo $cropIndex; ?>" required>
                                    <option value="<?php echo htmlspecialchars($crop['crop_type']); ?>" selected>
                                        <?php echo htmlspecialchars($crop['crop_type']); ?>
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="crop-row">
                            <div class="crop-group">
                                <label for="cropVariety_<?php echo $cropIndex; ?>">Crop Variety:</label>
                                <input type="text" id="cropVariety_<?php echo $cropIndex; ?>" 
                                       name="cropVariety_<?php echo $cropIndex; ?>" 
                                       value="<?php echo htmlspecialchars($crop['crop_variety'] ?? ''); ?>" 
                                       required>
                            </div>

                            <?php if (in_array($crop['crop_commodity'], ['fruits', 'plantation'])): ?>
                            <div id="treeFieldsContainer_<?php echo $cropIndex; ?>" class="tree-fields-container">
                                <div class="crop-group">
                                    <label for="numTrees_<?php echo $cropIndex; ?>">Number of Trees:</label>
                                    <input type="number" id="numTrees_<?php echo $cropIndex; ?>" 
                                           name="numTrees_<?php echo $cropIndex; ?>" 
                                           value="<?php echo htmlspecialchars($crop['num_trees'] ?? ''); ?>" 
                                           min="0"
                                           placeholder="Bilang ng Puno"
                                           required>
                                </div>
                                <div class="crop-group">
                                    <label for="treeStatus_<?php echo $cropIndex; ?>">Tree Status:</label>
                                    <select id="treeStatus_<?php echo $cropIndex; ?>" 
                                            name="treeStatus_<?php echo $cropIndex; ?>"
                                            required>
                                        <option value="" disabled selected>Pumili ng Tree Status</option>
                                        <option value="seedling" <?php echo ($crop['tree_status'] === 'seedling') ? 'selected' : ''; ?>>Seedling/Vegetative</option>
                                        <option value="bearing" <?php echo ($crop['tree_status'] === 'bearing') ? 'selected' : ''; ?>>Bearing</option>
                                        <option value="non-bearing" <?php echo ($crop['tree_status'] === 'non-bearing') ? 'selected' : ''; ?>>Non-Bearing</option>
                                    </select>
                                </div>
                            </div>
                            <?php else: ?>
                            <div id="treeFieldsContainer_<?php echo $cropIndex; ?>" class="tree-fields-container">
                                <div class="crop-group">
                                    <label for="numTrees_<?php echo $cropIndex; ?>">Number of Trees:</label>
                                    <input type="number" id="numTrees_<?php echo $cropIndex; ?>" 
                                           name="numTrees_<?php echo $cropIndex; ?>" 
                                           min="0"
                                           placeholder="Bilang ng Puno">
                                </div>
                                <div class="crop-group">
                                    <label for="treeStatus_<?php echo $cropIndex; ?>">Tree Status:</label>
                                    <select id="treeStatus_<?php echo $cropIndex; ?>" 
                                            name="treeStatus_<?php echo $cropIndex; ?>">
                                        <option value="" disabled selected>Pumili ng Tree Status</option>
                                        <option value="seedling">Seedling/Vegetative</option>
                                        <option value="bearing">Bearing</option>
                                        <option value="non-bearing">Non-Bearing</option>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </fieldset>
                <?php 
                    }
                } 
                ?>
            </div>
            <hr>

            <div class="button-container">
                <button type="submit">Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="confirmCancel()">Cancel</button>
            </div>
        </form>
    </div>

    <script src="../../../shared/crop-catalog.js"></script>
    <script>
    // Crop type mapping (from farmForm.js)
    const cropTypes = {
        grains: ['Rice', 'Corn'],
        root_crops: ['Sweet Potato', 'Cassava', 'Ube/Yam', 'Ginger', 'Gabi'],
        vegetables: ['Ampalaya', 'Bell Pepper', 'Bush Sitao','Chayote', 'Cowpea', 'Cucumber', 'Eggplant', 'Hot Pepper', 'Lady Finger (okra)',
            'Lettuce', 'Mung Bean', 'Mustard', 'Pechay', 'Pole Sitao', 'Radish', 'Snap Beans', 'Soybeans', 'Squash', 'Tomato', 'Upo', 'Patola', 'Sigarilyas'],
        fruits: ['Avocado', 'Banana', 'Calamansi', 'Citrus', 'Pomelo', 'Papaya', 'Dragon Fruit', 'Durian', 'Guyabano', 'Jackfruit', 
            'Lanzones', 'Mango', 'Mango Pajo', 'Mangosteen', 'Pineapple', 'Rambutan', 'Santol', 'Tamarind', 'Pili'],
        plantation: ['Coconut', 'Coffee', 'Sugarcane', 'Black Pepper', 'Cacao', 'Other']
    };

    function getEditableParcelCropTypes(commodity) {
        if (window.SCAIMSCropCatalog && typeof window.SCAIMSCropCatalog.getTypes === 'function') {
            return window.SCAIMSCropCatalog.getTypes(commodity, { includeOther: true });
        }

        return cropTypes[commodity] || [];
    }

    function toggleTreeFields(cropIndex) {
        const cropCommodity = document.getElementById(`crop_${cropIndex}`).value;
        const cropType = document.getElementById(`cropType_${cropIndex}`).value;
        const treeFieldsContainer = document.getElementById(`treeFieldsContainer_${cropIndex}`);
        const numTreesInput = document.getElementById(`numTrees_${cropIndex}`);
        const treeStatusSelect = document.getElementById(`treeStatus_${cropIndex}`);

        if (!treeFieldsContainer || !numTreesInput || !treeStatusSelect) {
            console.error(`Missing elements for crop ${cropIndex}`);
            return;
        }

        // Check if the selected crop commodity requires tree-specific fields
        const isTreeCrop = ['fruits', 'plantation'].includes(cropCommodity) && 
                           (cropType !== 'Other' || cropCommodity === 'fruits');

        // Show/hide tree-specific fields
        if (isTreeCrop) {
            treeFieldsContainer.style.display = 'flex';
            numTreesInput.required = true;
            treeStatusSelect.required = true;
        } else {
            treeFieldsContainer.style.display = 'none';
            numTreesInput.required = false;
            treeStatusSelect.required = false;
            
            // Reset tree-specific inputs
            numTreesInput.value = '';
            treeStatusSelect.selectedIndex = 0;
        }
    }

    function showCropType(cropIndex) {
        const cropCommodity = document.getElementById(`crop_${cropIndex}`).value;
        const cropTypeContainer = document.getElementById(`cropTypeContainer_${cropIndex}`);

        // Clear existing options
        cropTypeContainer.innerHTML = '<label for="cropType_' + cropIndex + '">Crop Type:</label>';

        const cropTypeSelect = document.createElement('select');
        cropTypeSelect.id = `cropType_${cropIndex}`;
        cropTypeSelect.name = `cropType_${cropIndex}`;
        cropTypeSelect.required = true;

        // Add default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.text = 'Pumili ng Crop Type';
        defaultOption.disabled = true;
        defaultOption.selected = true;
        cropTypeSelect.appendChild(defaultOption);

        // Populate crop types based on selected commodity
        const availableCropTypes = getEditableParcelCropTypes(cropCommodity);
        if (availableCropTypes.length) {
            availableCropTypes.forEach(type => {
                const option = document.createElement('option');
                option.value = type;
                option.text = type;
                cropTypeSelect.appendChild(option);
            });
        }

        // Add event listener to handle 'Other' option
        cropTypeSelect.addEventListener('change', function() {
            // Remove any existing custom crop type input
            const existingCustomInput = document.getElementById(`customCropType_${cropIndex}`);
            if (existingCustomInput) {
                existingCustomInput.remove();
            }

            // If 'Other' is selected, add a text input
            if (this.value === 'Other') {
                const customCropTypeInput = document.createElement('input');
                customCropTypeInput.type = 'text';
                customCropTypeInput.id = `customCropType_${cropIndex}`;
                customCropTypeInput.name = `customCropType_${cropIndex}`;
                customCropTypeInput.placeholder = 'Enter specific crop type';
                customCropTypeInput.required = true;

                // Insert the custom input after the select
                this.parentNode.insertBefore(customCropTypeInput, this.nextSibling);
            }
        });

        // Append the select to the container
        cropTypeContainer.appendChild(cropTypeSelect);

        // Toggle tree fields
        toggleTreeFields(cropIndex);
    }

    // Modify existing onchange event for crop commodity
    document.addEventListener('DOMContentLoaded', function() {
        const existingCrops = document.getElementById('cropFields').children;
        for (let i = 0; i < existingCrops.length; i++) {
            const cropIndex = i + 1;
            const cropCommoditySelect = document.getElementById(`crop_${cropIndex}`);
            
            // Add event listener to existing crop commodity selects
            if (cropCommoditySelect) {
                cropCommoditySelect.addEventListener('change', function() {
                    toggleTreeFields(cropIndex);
                });

                // Initial toggle based on current value
                toggleTreeFields(cropIndex);
            }
        }
    });

    function generateCropFields() {
        const numCrops = document.getElementById('numCrops').value;
        const cropFieldsContainer = document.getElementById('cropFields');
        const existingCrops = cropFieldsContainer.children.length;

        // If reducing number of crops, confirm deletion
        if (numCrops < existingCrops) {
            if (!confirm(`Are you sure you want to remove ${existingCrops - numCrops} crop(s)? This cannot be undone.`)) {
                document.getElementById('numCrops').value = existingCrops;
                return;
            }
        }

        // Add new crop fields if increasing number of crops
        if (numCrops > existingCrops) {
            for (let i = existingCrops + 1; i <= numCrops; i++) {
                const fieldset = document.createElement('fieldset');
                fieldset.id = `cropFieldset_${i}`;
                fieldset.innerHTML = `
                    <h4>
                        Crop ${i}
                        <button type="button" class="btn btn-danger btn-sm ml-2" onclick="removeCrop(${i})">Remove Crop</button>
                    </h4>
                    <div class="crop-row">
                        <div class="crop-group">
                            <label for="crop_${i}">Crop Commodity:</label>
                            <select id="crop_${i}" name="crop_${i}" required onchange="showCropType(${i})">
                                <option value="" disabled selected>Pumili ng Crop Commodity</option>
                                <option value="grains">Grains</option>
                                <option value="plantation">Plantation Crops</option>
                                <option value="root_crops">Root Crops</option>
                                <option value="vegetables">Vegetables</option>
                                <option value="fruits">Fruits</option>
                            </select>
                        </div>

                        <div class="crop-group" id="cropTypeContainer_${i}">
                            <label for="cropType_${i}">Crop Type:</label>
                            <select id="cropType_${i}" name="cropType_${i}" required disabled>
                                <option value="">Pumili ng Crop Type</option>
                            </select>
                        </div>
                    </div>

                    <div class="crop-row">
                        <div class="crop-group">
                            <label for="cropVariety_${i}">Crop Variety:</label>
                            <input type="text" id="cropVariety_${i}" 
                                   name="cropVariety_${i}" 
                                   required>
                        </div>

                        <div id="treeFieldsContainer_${i}" class="tree-fields-container">
                            <div class="crop-group">
                                <label for="numTrees_${i}">Number of Trees:</label>
                                <input type="number" id="numTrees_${i}" 
                                       name="numTrees_${i}" 
                                       min="0"
                                       placeholder="Bilang ng Puno"
                                       required>
                            </div>
                            <div class="crop-group">
                                <label for="treeStatus_${i}">Tree Status:</label>
                                <select id="treeStatus_${i}" 
                                        name="treeStatus_${i}"
                                        required>
                                    <option value="" disabled selected>Pumili ng Tree Status</option>
                                    <option value="seedling">Seedling/Vegetative</option>
                                    <option value="bearing">Bearing</option>
                                    <option value="non-bearing">Non-Bearing</option>
                                </select>
                            </div>
                        </div>
                    </div>
                `;

                cropFieldsContainer.appendChild(fieldset);
            }
        }
    }

    function removeCrop(cropIndex) {
        const cropFieldset = document.getElementById(`cropFieldset_${cropIndex}`);
        if (cropFieldset) {
            // Remove the fieldset
            cropFieldset.remove();

            // Adjust the number of crops
            const numCropsInput = document.getElementById('numCrops');
            numCropsInput.value = parseInt(numCropsInput.value) - 1;

            // Reindex remaining crops
            const cropFields = document.getElementById('cropFields');
            const remainingCrops = cropFields.children;
            for (let i = 0; i < remainingCrops.length; i++) {
                const crop = remainingCrops[i];
                const oldIndex = crop.querySelector('h4 button').getAttribute('onclick').match(/\d+/)[0];
                const newIndex = i + 1;

                // Update header
                crop.querySelector('h4').innerHTML = `
                    Crop ${newIndex}
                    <button type="button" class="btn btn-danger btn-sm ml-2" onclick="removeCrop(${newIndex})">Remove Crop</button>
                `;

                // Update input field names and IDs
                const inputs = crop.querySelectorAll('[id], [name]');
                inputs.forEach(input => {
                    if (input.id) input.id = input.id.replace(`_${oldIndex}`, `_${newIndex}`);
                    if (input.name) input.name = input.name.replace(`_${oldIndex}`, `_${newIndex}`);
                });
            }
        }
    }

    function confirmCancel() {
        if (confirm("Are you sure you do not want to proceed?")) {
            window.location.href = "farmersFP.php";
        }
    }

    // Ensure tree fields are handled on page load and crop commodity change
    document.addEventListener('DOMContentLoaded', function() {
        const existingCrops = document.getElementById('cropFields').children;
        
        for (let i = 0; i < existingCrops.length; i++) {
            const cropIndex = i + 1;
            const cropCommoditySelect = document.getElementById(`crop_${cropIndex}`);
            
            if (cropCommoditySelect) {
                // Initial toggle
                toggleTreeFields(cropIndex);
                
                // Add change event listener
                cropCommoditySelect.addEventListener('change', function() {
                    toggleTreeFields(cropIndex);
                });
            }
        }
    });

    // Modify the form submission to use AJAX
    document.getElementById('editParcelForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.href = 'farmersFP.php';
            } else {
                alert('Error updating parcel. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    });
    </script>
</body>
</html>
