<?php
require_once dirname(__DIR__, 3) . '/shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');

require_once dirname(__DIR__, 3) . '/conn.php';
require_once dirname(__DIR__, 3) . '/shared/reporting/report-service.php';

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_report_image_columns($conn);
    scaims_ensure_gps_photo_columns($conn);
} catch (RuntimeException $e) {
    $_SESSION['message'] = 'The report database is unavailable right now. Please try again later.';
    header('Location: reportAnalytics.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
    header('Location: reportAnalytics.php');
    exit();
}

$farmerId = isset($_POST['reporting-for']) ? (int) $_POST['reporting-for'] : 0;
if ($farmerId <= 0) {
    $_SESSION['message'] = 'Please select the farmer you are reporting for.';
    $conn->close();
    header('Location: reportAnalytics.php');
    exit();
}

$owner = scaims_reporting_find_active_farmer($conn, $farmerId);
if (!$owner) {
    $_SESSION['message'] = 'Selected farmer was not found.';
    $conn->close();
    header('Location: reportAnalytics.php');
    exit();
}

$result = scaims_reporting_create($conn, $_POST, $_FILES, [
    'owner_id' => (int) $owner['FarmerID'],
    'actor' => (string) ($_SESSION['AdminName'] ?? $_SESSION['AdminID']),
    'validation_policy' => 'admin_strict',
    'save_defaults' => false,
]);

$_SESSION['message'] = $result['message'];
$conn->close();
header('Location: reportAnalytics.php');
exit();
