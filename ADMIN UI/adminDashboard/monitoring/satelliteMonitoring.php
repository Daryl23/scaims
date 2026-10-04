<?php
require_once dirname(__DIR__, 3) . '/shared/admin_auth.php';
require_once dirname(__DIR__, 3) . '/shared/satellite_config.php';
scaims_require_admin('../../adminLogin/adminLogin.html');

$activePage = 'satellite';
$satelliteConfigured = scaims_satellite_service_configured();
$parcels = [];

try {
    $conn = scaims_db_mysqli();
    $result = $conn->query(
        "SELECT pd.id AS parcel_id,
                pd.parcel_number,
                pd.location,
                f.FarmerID,
                CONCAT_WS(' ', f.FirstName, NULLIF(f.MiddleName, ''), f.Surname, NULLIF(f.Suffix, '')) AS farmer_name
         FROM parcel_details pd
         INNER JOIN farm_parcels fp ON fp.id = pd.farm_parcel_id
         INNER JOIN user u ON u.UName = fp.created_by
         INNER JOIN farmer f ON f.UserID = u.UserID
         ORDER BY f.Surname, f.FirstName, pd.parcel_number, pd.id"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $parcels[] = $row;
        }
    }
    $conn->close();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$pageCssVersion = @filemtime(__DIR__ . '/satellite-monitoring.css') ?: time();
$pageScriptVersion = @filemtime(__DIR__ . '/satellite-monitoring.js') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satellite Monitoring | SCAIMS Admin</title>
    <link rel="stylesheet" href="../../../shared/design-tokens.css">
    <link rel="stylesheet" href="../../../shared/components.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="satellite-monitoring.css?v=<?php echo $pageCssVersion; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body
    data-satellite-configured="<?php echo $satelliteConfigured ? 'true' : 'false'; ?>"
    data-parcel-endpoint="getParcel.php"
>
    <div class="satellite-layout">
        <?php include '../../shared/admin-sidebar.php'; ?>

        <main class="satellite-main" id="main-content">
            <header class="satellite-page-header">
                <div>
                    <p class="satellite-eyebrow">Analytics / Monitoring</p>
                    <h1>Satellite Monitoring</h1>
                    <p>Review existing parcel records and prepare them for verified Sentinel-2 observations.</p>
                </div>
                <section class="provider-status-card" aria-label="Satellite service status">
                    <span class="provider-status-icon" aria-hidden="true">◎</span>
                    <div>
                        <span>Satellite Service</span>
                        <strong>Copernicus Sentinel-2</strong>
                        <small class="<?php echo $satelliteConfigured ? 'is-configured' : 'is-unconfigured'; ?>">
                            <?php echo $satelliteConfigured ? 'Configured' : 'Not configured'; ?>
                        </small>
                    </div>
                </section>
            </header>

            <section class="satellite-toolbar scaims-card" aria-labelledby="parcel-selection-title">
                <div class="parcel-select-field">
                    <label for="parcel-selector" id="parcel-selection-title">Select an existing parcel</label>
                    <select id="parcel-selector" class="scaims-field">
                        <option value="">Choose a parcel</option>
                        <?php foreach ($parcels as $parcel): ?>
                            <?php
                                $parcelLabel = sprintf(
                                    'Parcel #%d — %s — %s',
                                    (int) $parcel['parcel_id'],
                                    trim((string) $parcel['farmer_name']),
                                    trim((string) $parcel['location']) ?: 'No location'
                                );
                            ?>
                            <option value="<?php echo (int) $parcel['parcel_id']; ?>">
                                <?php echo htmlspecialchars($parcelLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="parcel-load-status" class="field-help" role="status" aria-live="polite">
                        <?php echo $parcels ? 'Select a parcel to load its registered information.' : 'No parcel records are available.'; ?>
                    </span>
                </div>
                <div class="analysis-action">
                    <button
                        type="button"
                        id="run-analysis-button"
                        class="scaims-button scaims-button--accent"
                        aria-describedby="analysis-action-help"
                        disabled
                    >Run Satellite Analysis</button>
                    <span id="analysis-action-help" class="field-help">
                        <?php if ($satelliteConfigured): ?>
                            Credentials detected. The secure execution adapter is scheduled for S2B.
                        <?php else: ?>
                            Satellite service is not configured.
                        <?php endif; ?>
                    </span>
                </div>
            </section>

            <section class="summary-grid" aria-label="Satellite monitoring summary">
                <article class="summary-card summary-card--olive">
                    <span>Monitored Parcels</span>
                    <strong><?php echo number_format(count($parcels)); ?></strong>
                    <small>Existing parcel records</small>
                </article>
                <article class="summary-card summary-card--orange">
                    <span>Analyzed This Week</span>
                    <strong>0</strong>
                    <small>No persisted observations</small>
                </article>
                <article class="summary-card summary-card--gold">
                    <span>Stable / Increasing</span>
                    <strong>0</strong>
                    <small>Trend history not available</small>
                </article>
                <article class="summary-card summary-card--review">
                    <span>Needs Review</span>
                    <strong>0</strong>
                    <small>No verification items</small>
                </article>
            </section>

            <?php if (!$parcels): ?>
                <section class="satellite-empty satellite-empty--page scaims-card" id="no-parcels-state">
                    <span aria-hidden="true">◇</span>
                    <h2>No parcels available</h2>
                    <p>Add parcel records through the existing Farmer parcel workflow before using Satellite Monitoring.</p>
                </section>
            <?php endif; ?>

            <div class="monitoring-split">
                <section class="parcel-panel scaims-card" aria-labelledby="parcel-info-heading">
                    <div class="panel-heading">
                        <div>
                            <p class="section-kicker">Existing SCAIMS data</p>
                            <h2 id="parcel-info-heading">Parcel Information</h2>
                        </div>
                        <span class="record-badge" id="parcel-record-badge">No parcel selected</span>
                    </div>

                    <dl class="parcel-details" id="parcel-details">
                        <div><dt>Parcel ID</dt><dd data-parcel-field="parcel_id">—</dd></div>
                        <div><dt>Farmer</dt><dd data-parcel-field="farmer_name">—</dd></div>
                        <div><dt>Crop</dt><dd data-parcel-field="crop">—</dd></div>
                        <div><dt>Barangay / Location</dt><dd data-parcel-field="location">—</dd></div>
                        <div><dt>Municipality / City</dt><dd data-parcel-field="municipality">—</dd></div>
                        <div><dt>Area</dt><dd data-parcel-field="area">—</dd></div>
                        <div><dt>Current Status</dt><dd data-parcel-field="status">—</dd></div>
                        <div><dt>Recorded</dt><dd data-parcel-field="created_at">—</dd></div>
                    </dl>

                    <div class="map-heading">
                        <div>
                            <h3>Farm / Parcel Location</h3>
                            <p id="map-location-label">Select a parcel to inspect its stored location.</p>
                        </div>
                        <button type="button" class="scaims-button scaims-button--secondary" disabled>
                            Define Farm Boundary
                        </button>
                    </div>
                    <div class="parcel-map-wrap">
                        <div id="parcel-map" role="img" aria-label="Selected parcel map"></div>
                        <div class="map-empty-state" id="map-empty-state">
                            <strong>No mapped parcel selected</strong>
                            <span>Select a parcel to check its available map data.</span>
                        </div>
                    </div>
                    <p class="boundary-note" id="boundary-note">Farm boundary not yet defined. Boundary persistence is planned for S2B.</p>
                </section>

                <section class="observation-panel scaims-card" aria-labelledby="latest-observation-heading">
                    <div class="panel-heading">
                        <div>
                            <p class="section-kicker">Copernicus Sentinel-2 L2A</p>
                            <h2 id="latest-observation-heading">Latest Satellite Observation</h2>
                        </div>
                        <span class="observation-status">Not analyzed yet</span>
                    </div>

                    <div class="observation-hero">
                        <span>Mean NDVI</span>
                        <strong data-satellite-field="ndvi_mean">—</strong>
                        <small>No real observation is stored for this parcel.</small>
                    </div>

                    <dl class="observation-details">
                        <div><dt>Previous NDVI</dt><dd data-satellite-field="previous_ndvi">—</dd></div>
                        <div><dt>Change</dt><dd data-satellite-field="change">—</dd></div>
                        <div><dt>Trend</dt><dd data-satellite-field="trend">—</dd></div>
                        <div><dt>Imagery Date</dt><dd data-satellite-field="observation_date">—</dd></div>
                        <div><dt>Scene ID</dt><dd data-satellite-field="scene_id">—</dd></div>
                        <div><dt>Scene Cloud Cover</dt><dd data-satellite-field="cloud_percentage">—</dd></div>
                        <div><dt>Parcel Cloud Cover</dt><dd data-satellite-field="parcel_cloud_percentage">—</dd></div>
                        <div><dt>Requested Date Window</dt><dd data-satellite-field="requested_date_window">—</dd></div>
                        <div><dt>Maximum Cloud Threshold</dt><dd data-satellite-field="max_cloud_threshold">—</dd></div>
                        <div><dt>Processing Time</dt><dd data-satellite-field="execution_seconds">—</dd></div>
                        <div><dt>Provider</dt><dd data-satellite-field="provider"><?php echo htmlspecialchars(SCAIMS_SATELLITE_PROVIDER, ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    </dl>

                    <div class="ndvi-detail-grid" aria-label="NDVI details">
                        <article><span>Mean NDVI</span><strong data-satellite-field="ndvi_mean_card">—</strong></article>
                        <article><span>Minimum NDVI</span><strong data-satellite-field="ndvi_min">—</strong></article>
                        <article><span>Maximum NDVI</span><strong data-satellite-field="ndvi_max">—</strong></article>
                        <article><span>Valid Pixels</span><strong data-satellite-field="valid_pixel_count">—</strong></article>
                    </div>
                </section>
            </div>

            <section class="dashboard-section scaims-card" aria-labelledby="trend-heading">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Observation history</p>
                        <h2 id="trend-heading">NDVI Trend</h2>
                    </div>
                </div>
                <div class="chart-empty-state">
                    <p>No trend is available yet. At least two satellite observations are required.</p>
                </div>
            </section>

            <section class="dashboard-section scaims-card" aria-labelledby="history-heading">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Verified readings only</p>
                        <h2 id="history-heading">Satellite History</h2>
                    </div>
                </div>
                <div class="scaims-table-wrap satellite-history-wrap">
                    <table class="satellite-history-table">
                        <thead>
                            <tr>
                                <th>Imagery Date</th>
                                <th>NDVI</th>
                                <th>Change</th>
                                <th>Trend</th>
                                <th>Scene Cloud</th>
                                <th>Parcel Cloud</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="history-empty-row">
                                <td colspan="6">No satellite history yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard-section scaims-card" aria-labelledby="verification-heading">
                <div class="panel-heading">
                    <div>
                        <p class="section-kicker">Admin review</p>
                        <h2 id="verification-heading">Verification Queue</h2>
                    </div>
                    <span class="queue-count">0 items</span>
                </div>
                <div class="simple-empty-state">
                    <span aria-hidden="true">✓</span>
                    <p>No satellite observations currently require review.</p>
                </div>
            </section>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="satellite-monitoring.js?v=<?php echo $pageScriptVersion; ?>"></script>
</body>
</html>
