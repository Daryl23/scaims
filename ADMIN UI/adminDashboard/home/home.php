<?php
require_once dirname(__DIR__, 3) . '/shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');

$activePage = 'home';

require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$totalRegistrations = 0;
$totalParcels = 0;
$totalDamageReports = 0;
$totalCropReports = 0;

$summaryResult = $conn->query("SELECT
    (SELECT COUNT(*) FROM farmer) AS total_registrations,
    (SELECT COUNT(DISTINCT parcel_detail_id) FROM crop_details) AS total_parcels,
    (SELECT COUNT(*) FROM damage_reports) AS total_damage_reports,
    (SELECT COUNT(*) FROM crop_reports) AS total_crop_reports");
if ($summaryResult) {
    $row = $summaryResult->fetch_assoc();
    $totalRegistrations = (int) $row['total_registrations'];
    $totalParcels = (int) $row['total_parcels'];
    $totalDamageReports = (int) $row['total_damage_reports'];
    $totalCropReports = (int) $row['total_crop_reports'];
}

$totalReports = $totalDamageReports + $totalCropReports;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <?php $homeCssVersion = @filemtime(__DIR__ . '/home.css') ?: time(); ?>
    <link rel="stylesheet" href="home.css?v=<?php echo $homeCssVersion; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <?php include '../../shared/admin-sidebar.php'; ?>

        <main class="main-content">
            <div class="cards-stack">
                <div class="card card-1">
                    <div class="card-content">
                        <h3 data-i18n="admin_stat_registrations_label">TOTAL REGISTRATIONS</h3>
                        <h2><?php echo number_format($totalRegistrations); ?></h2>
                        <p data-i18n="admin_stat_registrations_meta">Farmers</p>
                        <a href="../user/userManagement.php" class="arrow" aria-label="Open user management"></a>
                    </div>
                </div>

                <div class="card card-2">
                    <div class="card-content">
                        <h3 data-i18n="admin_stat_parcels_label">TOTAL FARM PARCELS</h3>
                        <h2><?php echo number_format($totalParcels); ?></h2>
                        <p data-i18n="admin_stat_parcels_meta">Parcels</p>
                        <a href="../parcel/farmParcel.php" class="arrow" aria-label="Open farm parcel"></a>
                    </div>
                </div>

                <div class="card card-3">
                    <div class="card-content">
                        <h3 data-i18n="admin_stat_reports_label">TOTAL REPORTS SUBMITTED</h3>
                        <h2><?php echo number_format($totalReports); ?></h2>
                        <p data-i18n="admin_stat_reports_meta">Reports</p>
                        <a href="../report/reportAnalytics.php" class="arrow" aria-label="Open reports"></a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
