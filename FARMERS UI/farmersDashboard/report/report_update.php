<?php
require_once dirname(__DIR__) . '/shared/farmer-auth.php';
header('Content-Type: application/json');
scaims_require_farmer_json('Please log in before editing a report.');

require_once dirname(__DIR__, 3) . '/conn.php';
require_once dirname(__DIR__, 3) . '/shared/crop_catalog.php';
require_once dirname(__DIR__, 3) . '/shared/reporting/report-service.php';

function respond_report_update(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_report_update(false, 'Invalid request method.', [], 405);
}

$reportType = scaims_reporting_clean($_POST, 'report-type');
$reportId = isset($_POST['report-id']) ? (int) $_POST['report-id'] : 0;
if (!scaims_reporting_is_type($reportType) || $reportId <= 0) {
    respond_report_update(false, 'Invalid report selection.', [], 400);
}

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_report_image_columns($conn);
    scaims_ensure_gps_photo_columns($conn);
    scaims_ensure_farmer_report_defaults_table($conn);
} catch (RuntimeException $e) {
    respond_report_update(false, 'The report database is unavailable right now. Please try again later.', [], 500);
}

$owner = scaims_reporting_find_farmer_by_username($conn, (string) ($_SESSION['UName'] ?? ''));
if (!$owner) {
    $conn->close();
    respond_report_update(false, 'Unable to find your farmer profile. Please sign in again.', [], 403);
}

$result = scaims_reporting_update_owned(
    $conn,
    $_POST,
    $_FILES,
    (int) $owner['FarmerID'],
    '../../../'
);
$conn->close();

$extra = $result;
unset($extra['ok'], $extra['message'], $extra['status']);
respond_report_update($result['ok'], $result['message'], $extra, $result['status']);
