<?php
    session_start();
    if (!isset($_SESSION['AdminID'])) {
        header('Location: ../../adminLogin/adminLogin.html');
        exit();
    }

    $activePage = 'reports';
    $statusMessage = $_SESSION['message'] ?? '';
    unset($_SESSION['message']);

    require_once dirname(__DIR__, 3) . '/conn.php';

    try {
        $conn = scaims_db_mysqli();
    } catch (RuntimeException $e) {
        die($e->getMessage());
    }

    // Query to get the total number of crop reports
    $queryCropReports = "SELECT COUNT(*) as total FROM crop_reports"; 
    $resultCropReports = $conn->query($queryCropReports);
    $totalCropReports = 0; // Default value in case of error
    if ($resultCropReports) {
        $row = $resultCropReports->fetch_assoc();
        $totalCropReports = $row['total'];
    } else {
        echo "Error fetching crop report data: " . $conn->error; // Display any error
    }

    // Query to get the total number of damage reports
    $queryDamageReports = "SELECT COUNT(*) as total FROM damage_reports"; 
    $resultDamageReports = $conn->query($queryDamageReports);
    $totalDamageReports = 0; // Default value in case of error
    if ($resultDamageReports) {
        $row = $resultDamageReports->fetch_assoc();
        $totalDamageReports = $row['total'];
    } else {
        echo "Error fetching damage report data: " . $conn->error; // Display any error
    }

    // Query to get top 5 barangays with highest damage percentage
    $queryTopDamage = "SELECT 
        dmgLocation, 
        AVG(percent_damage) as avg_damage_percent,
        COUNT(*) as report_count
        FROM damage_reports 
        GROUP BY dmgLocation 
        HAVING report_count >= 1
        ORDER BY avg_damage_percent DESC 
        LIMIT 5";
    
    $resultTopDamage = $conn->query($queryTopDamage);
    $topDamageData = [];
    
    if ($resultTopDamage) {
        while($row = $resultTopDamage->fetch_assoc()) {
            $topDamageData[] = [
                'barangay' => $row['dmgLocation'],
                'damage' => round($row['avg_damage_percent'], 2)
            ];
        }
    }

    $farmers = [];
    $farmersQuery = "SELECT f.FarmerID, CONCAT(f.FirstName, ' ', f.Surname) AS FarmerName, f.Barangay, u.UName
        FROM farmer f
        LEFT JOIN user u ON f.UserID = u.UserID
        WHERE f.is_active = 1 OR f.is_active IS NULL
        ORDER BY f.FirstName ASC, f.Surname ASC";
    $farmersResult = $conn->query($farmersQuery);
    if ($farmersResult) {
        while ($row = $farmersResult->fetch_assoc()) {
            $farmers[] = $row;
        }
    }

    // Close the database connection
    $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports & Analytics</title>
    <?php $reportCssVersion = @filemtime(__DIR__ . '/reportAnalytics.css') ?: time(); ?>
    <link rel="stylesheet" href="reportAnalytics.css?v=<?php echo $reportCssVersion; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

    <div class="container">
        <?php include '../../shared/admin-sidebar.php'; ?>
        
        <div class="main-content">
            <?php if ($statusMessage): ?>
                <div class="status-message">
                    <?php echo htmlspecialchars($statusMessage); ?>
                </div>
            <?php endif; ?>

            <div class="cards-container">
                <div class="card card-1">
                    <div class="card-content">
                        <h3>CROP REPORTS</h3>
                        <h2><?php echo $totalCropReports; ?></h2>
                        <p>Reports</p>
                        <a href="../report/crop/cropReports.php" class="arrow"></a>
                    </div>
                </div>

                <div class="card card-2">
                    <div class="card-content">
                        <h3>DAMAGE REPORTS</h3>
                        <h2><?php echo $totalDamageReports; ?></h2>
                        <p>Reports</p>
                        <a href="../report/damage/damageReports.php" class="arrow"></a>
                    </div>
                </div>
            </div>

            <section class="admin-report-form-panel">
                <div class="panel-heading">
                    <h2>Submit Report For A Farmer</h2>
                    <p>Admins can file a crop or damage report on behalf of a farmer who is already registered in the database.</p>
                </div>

                <form action="report_submit.php" method="POST" class="admin-report-form" id="admin-report-form">
                    <div class="form-grid reporting-grid">
                        <div class="form-group form-group-wide">
                            <label for="farmer-search">Search Farmer</label>
                            <input type="text" id="farmer-search" placeholder="Type farmer name, barangay, or username">
                        </div>
                        <div class="form-group form-group-wide">
                            <label for="reporting-for">Reporting For</label>
                            <select id="reporting-for" name="reporting-for" required size="6">
                                <?php foreach ($farmers as $farmer): ?>
                                    <?php
                                        $farmerLabel = trim($farmer['FarmerName']) . ' - ' . ($farmer['Barangay'] ?: 'No barangay');
                                        if (!empty($farmer['UName'])) {
                                            $farmerLabel .= ' (@' . $farmer['UName'] . ')';
                                        }
                                    ?>
                                    <option
                                        value="<?php echo (int) $farmer['FarmerID']; ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower($farmerLabel)); ?>"
                                    >
                                        <?php echo htmlspecialchars($farmerLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="helper-text">Pick the farmer first before submitting the report.</small>
                        </div>
                        <div class="form-group">
                            <label for="report-type">Type of Report</label>
                            <select id="report-type" name="report-type" required>
                                <option value="" disabled selected>Select a type of report</option>
                                <option value="crop">Crop Harvest Report</option>
                                <option value="damage">Crop Damage Report</option>
                            </select>
                        </div>
                    </div>

                    <div id="crop-report-fields" class="report-fields" hidden>
                        <h3>Crop Harvest Report</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="crop-location">Location</label>
                                <input type="text" id="crop-location" name="crop-location" required>
                            </div>
                            <div class="form-group">
                                <label for="crop-commodity">Crop Commodity</label>
                                <select id="crop-commodity" name="crop-commodity" required>
                                    <option value="" disabled selected>Select a crop commodity</option>
                                    <option value="grains">Grains</option>
                                    <option value="plantation">Plantation Crops</option>
                                    <option value="root">Root Crops</option>
                                    <option value="vegetables">Vegetables</option>
                                    <option value="fruits">Fruits</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="crop-type">Crop Type</label>
                                <select id="crop-type" name="crop-type" required>
                                    <option value="" disabled selected>Select a crop type</option>
                                </select>
                                <input type="text" id="other-crop-type" name="other-crop-type" class="conditional-input" placeholder="Please specify">
                            </div>
                            <div class="form-group">
                                <label for="crop-variety">Crop Variety</label>
                                <input type="text" id="crop-variety" name="crop-variety" required>
                            </div>
                            <div class="form-group">
                                <label for="date-planted">Date Planted</label>
                                <input type="date" id="date-planted" name="date-planted" required>
                            </div>
                            <div class="form-group">
                                <label for="harvest-date">Harvest Date</label>
                                <input type="date" id="harvest-date" name="harvest-date" required>
                            </div>
                            <div class="form-group">
                                <label for="estimate-harvest">Estimated Harvest (kg)</label>
                                <input type="number" id="estimate-harvest" name="estimate-harvest" min="0.01" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="crop-status">Crop Status</label>
                                <select id="crop-status" name="crop-status" required>
                                    <option value="" disabled selected>Select crop status</option>
                                    <option value="Seedling/Vegetative">Seedling/Vegetative</option>
                                    <option value="Bearing">Bearing</option>
                                    <option value="Non-Bearing">Non-Bearing</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="number-of-trees">Number of Trees</label>
                                <input type="text" id="number-of-trees" name="number-of-trees" placeholder="If none, enter N/A">
                            </div>
                            <div class="form-group">
                                <label for="farm-gate-price">Farm Gate Price (PHP/kg)</label>
                                <input type="number" id="farm-gate-price" name="farm-gate-price" min="0.01" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="crop-coordinate-mode">Coordinates</label>
                                <select id="crop-coordinate-mode" name="crop-coordinate-mode">
                                    <option value="" selected>Do not include coordinates</option>
                                    <option value="auto">Auto via GPS</option>
                                    <option value="manual">Manual input</option>
                                </select>
                            </div>
                            <div class="form-group coordinate-action" id="crop-coordinate-action" hidden>
                                <label>GPS Access</label>
                                <button type="button" class="gps-button" id="crop-use-gps">Use Current Location</button>
                                <small class="helper-text" id="crop-coordinate-status">Allow location access to fill latitude and longitude automatically.</small>
                            </div>
                            <div class="form-group coordinate-field-group">
                                <label for="crop-coordinate-input">Coordinates</label>
                                <input type="text" id="crop-coordinate-input" placeholder="Paste from Google Maps or Waze: 13°50'11.2&quot;N 121°16'50.5&quot;E">
                                <small class="helper-text">Use the Google Maps or Waze format: 13°50'11.2&quot;N 121°16'50.5&quot;E</small>
                                <input type="hidden" id="crop-latitude" name="crop-latitude">
                                <input type="hidden" id="crop-longitude" name="crop-longitude">
                            </div>
                        </div>
                    </div>

                    <div id="damage-report-fields" class="report-fields" hidden>
                        <h3>Crop Damage Report</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="damage-location">Location</label>
                                <input type="text" id="damage-location" name="damage-location" required>
                            </div>
                            <div class="form-group">
                                <label for="damage-crop-commodity">Crop Commodity</label>
                                <select id="damage-crop-commodity" name="damage-crop-commodity" required>
                                    <option value="" disabled selected>Select a crop commodity</option>
                                    <option value="grains">Grains</option>
                                    <option value="plantation">Plantation Crops</option>
                                    <option value="root">Root Crops</option>
                                    <option value="vegetables">Vegetables</option>
                                    <option value="fruits">Fruits</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="damage-crop-type">Crop Type</label>
                                <select id="damage-crop-type" name="damage-crop-type" required>
                                    <option value="" disabled selected>Select a crop type</option>
                                </select>
                                <input type="text" id="other-damage-crop-type" name="other-damage-crop-type" class="conditional-input" placeholder="Please specify">
                            </div>
                            <div class="form-group">
                                <label for="damage-crop-variety">Crop Variety</label>
                                <input type="text" id="damage-crop-variety" name="damage-crop-variety" required>
                            </div>
                            <div class="form-group">
                                <label for="date-occurrence">Date of Occurrence</label>
                                <input type="date" id="date-occurrence" name="date-occurrence" required>
                            </div>
                            <div class="form-group">
                                <label for="damage-cause">Cause of Damage</label>
                                <input type="text" id="damage-cause" name="damage-cause" required>
                            </div>
                            <div class="form-group">
                                <label for="damage-percentage">Damage Percentage (%)</label>
                                <input type="number" id="damage-percentage" name="damage-percentage" min="0" max="100" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="estimated-loss">Estimated Loss (kg)</label>
                                <input type="number" id="estimated-loss" name="estimated-loss" min="0.01" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="damage-number-of-trees">Number of Trees</label>
                                <input type="text" id="damage-number-of-trees" name="damage-number-of-trees" placeholder="If none, enter N/A">
                            </div>
                            <div class="form-group">
                                <label for="damage-farm-gate-price">Farm Gate Price (PHP/kg)</label>
                                <input type="number" id="damage-farm-gate-price" name="damage-farm-gate-price" min="0.01" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="damage-coordinate-mode">Coordinates</label>
                                <select id="damage-coordinate-mode" name="damage-coordinate-mode">
                                    <option value="" selected>Do not include coordinates</option>
                                    <option value="auto">Auto via GPS</option>
                                    <option value="manual">Manual input</option>
                                </select>
                            </div>
                            <div class="form-group coordinate-action" id="damage-coordinate-action" hidden>
                                <label>GPS Access</label>
                                <button type="button" class="gps-button" id="damage-use-gps">Use Current Location</button>
                                <small class="helper-text" id="damage-coordinate-status">Allow location access to fill latitude and longitude automatically.</small>
                            </div>
                            <div class="form-group coordinate-field-group">
                                <label for="damage-coordinate-input">Coordinates</label>
                                <input type="text" id="damage-coordinate-input" placeholder="Paste from Google Maps or Waze: 13°50'11.2&quot;N 121°16'50.5&quot;E">
                                <small class="helper-text">Use the Google Maps or Waze format: 13°50'11.2&quot;N 121°16'50.5&quot;E</small>
                                <input type="hidden" id="damage-latitude" name="damage-latitude">
                                <input type="hidden" id="damage-longitude" name="damage-longitude">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="submit-report-button">Submit Report</button>
                    </div>
                </form>
            </section>

            <!-- Bar chart container -->
            <div class="chart-container">
                <h3>TOP 5 BARANGAYS WITH HIGHEST DAMAGE PERCENTAGE</h3>
                <canvas id="damageChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Add Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php
    $reportScriptVersion = @filemtime(__DIR__ . '/admin-report-form.js') ?: time();
    $cropCatalogScriptVersion = @filemtime(__DIR__ . '/../../../shared/crop-catalog.js') ?: time();
    ?>
    <script src="../../../shared/crop-catalog.js?v=<?php echo $cropCatalogScriptVersion; ?>"></script>
    <script src="admin-report-form.js?v=<?php echo $reportScriptVersion; ?>"></script>
    <script>
        // Parse PHP data to JavaScript
        const damageData = <?php echo json_encode($topDamageData); ?>;
        
        // Prepare data for chart
        const labels = damageData.map(item => item.barangay);
        const damages = damageData.map(item => item.damage);

        // Create bar chart with improved styling
        const ctx = document.getElementById('damageChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Average Damage Percentage',
                    font: {
                        size: 14,
                        weight: 'bold',
                        family: 'Poppins'
                    },
                    data: damages,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)',
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 2,
                    borderRadius: 5,
                    barThickness: 50
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        title: {
                            display: true,
                            text: 'Damage Percentage (%)',
                            font: {
                                size: 14,
                                weight: 'bold',
                                family: 'Poppins'

                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Barangay',
                            font: {
                                size: 14,
                                weight: 'bold',
                                family: 'Poppins'
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(25, 135, 84, 0.9)',
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 12,
                        cornerRadius: 6
                    }
                }
            }
        });
    </script>
</body>
</html>
