<?php
require_once dirname(__DIR__) . '/shared/farmer-auth.php';
scaims_require_farmer_page('../../farmerLogin/farmerlogin.html');

require_once dirname(__DIR__, 3) . '/conn.php';
require_once dirname(__DIR__, 3) . '/shared/reporting/report-service.php';

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_report_image_columns($conn);
    scaims_ensure_gps_photo_columns($conn);
    scaims_ensure_farmer_report_defaults_table($conn);
} catch (RuntimeException $e) {
    $_SESSION['message'] = 'The report database is unavailable right now. Please try again later.';
    header('Location: report.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner = scaims_reporting_find_farmer_by_username($conn, (string) ($_SESSION['UName'] ?? ''));
    if (!$owner) {
        $_SESSION['message'] = 'Unable to find your farmer profile. Please sign in again.';
        $conn->close();
        header('Location: report.php');
        exit();
    }

    $result = scaims_reporting_create($conn, $_POST, $_FILES, [
        'owner_id' => (int) $owner['FarmerID'],
        'actor' => (string) $_SESSION['UName'],
        'validation_policy' => 'farmer_legacy',
        'save_defaults' => true,
    ]);

    $_SESSION['message'] = $result['message'];
    $conn->close();
    header('Location: report.php');
    exit();
}

$conn->close();
