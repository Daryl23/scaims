<?php
require_once dirname(__DIR__, 3) . '/shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');

header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/shared/crop_catalog.php';
require_once dirname(__DIR__, 3) . '/shared/farmer_report_defaults.php';
require_once dirname(__DIR__, 3) . '/shared/reporting/report-repository.php';

function respond_admin_farmer_prefill(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit();
}

$farmerId = isset($_GET['farmer_id']) ? (int) $_GET['farmer_id'] : 0;
if ($farmerId <= 0) {
    respond_admin_farmer_prefill(false, 'Please select a valid farmer.', [], 400);
}

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_farmer_report_defaults_table($conn);
} catch (RuntimeException $e) {
    respond_admin_farmer_prefill(false, 'The report database is unavailable right now. Please try again later.', [], 500);
}

$farmer = scaims_reporting_find_active_farmer($conn, $farmerId);

if (!$farmer) {
    $conn->close();
    respond_admin_farmer_prefill(false, 'Selected farmer was not found.', [], 404);
}

$basePrefill = scaims_get_farmer_base_report_prefill($conn, $farmerId);
$prefillByType = [
    'crop' => $basePrefill,
    'damage' => $basePrefill,
];
$cropChoices = scaims_get_farmer_registered_crop_choices($conn, $farmerId);

$conn->close();

respond_admin_farmer_prefill(true, 'Farmer information loaded.', [
    'farmer' => [
        'id' => (int) $farmer['FarmerID'],
        'name' => trim((string) ($farmer['FarmerName'] ?? '')),
        'barangay' => trim((string) ($farmer['Barangay'] ?? '')),
        'username' => trim((string) ($farmer['UName'] ?? '')),
    ],
    'prefill_by_type' => $prefillByType,
    'crop_choices' => $cropChoices,
]);
