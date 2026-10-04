<?php
require_once dirname(__DIR__, 3) . '/shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');

header('Content-Type: application/json; charset=utf-8');

$parcelId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if (!$parcelId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'invalid_parcel_id']);
    exit;
}

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'reason' => 'database_unavailable']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT pd.id AS parcel_id,
            pd.parcel_number,
            pd.location,
            pd.total_area,
            pd.ownership_type,
            fp.id AS farm_parcel_id,
            fp.farm_location,
            fp.farm_type,
            fp.organic,
            fp.created_at,
            f.FarmerID AS farmer_id,
            CONCAT_WS(' ', f.FirstName, NULLIF(f.MiddleName, ''), f.Surname, NULLIF(f.Suffix, '')) AS farmer_name,
            f.Barangay AS farmer_barangay
     FROM parcel_details pd
     INNER JOIN farm_parcels fp ON fp.id = pd.farm_parcel_id
     INNER JOIN user u ON u.UName = fp.created_by
     INNER JOIN farmer f ON f.UserID = u.UserID
     WHERE pd.id = ?
     LIMIT 1"
);
$stmt->bind_param('i', $parcelId);
$stmt->execute();
$parcel = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$parcel) {
    $conn->close();
    http_response_code(404);
    echo json_encode(['ok' => false, 'reason' => 'parcel_not_found']);
    exit;
}

$cropStmt = $conn->prepare(
    "SELECT id, crop_commodity, crop_type, crop_variety, num_trees, tree_status
     FROM crop_details
     WHERE parcel_detail_id = ?
     ORDER BY id"
);
$cropStmt->bind_param('i', $parcelId);
$cropStmt->execute();
$cropResult = $cropStmt->get_result();
$crops = [];
while ($crop = $cropResult->fetch_assoc()) {
    $crops[] = [
        'id' => (int) $crop['id'],
        'commodity' => $crop['crop_commodity'],
        'type' => $crop['crop_type'],
        'variety' => $crop['crop_variety'],
        'num_trees' => $crop['num_trees'] === null ? null : (int) $crop['num_trees'],
        'tree_status' => $crop['tree_status'] ?: null,
    ];
}
$cropStmt->close();
$conn->close();

echo json_encode([
    'ok' => true,
    'parcel' => [
        'parcel_id' => (int) $parcel['parcel_id'],
        'parcel_number' => (int) $parcel['parcel_number'],
        'farm_parcel_id' => (int) $parcel['farm_parcel_id'],
        'farmer_id' => (int) $parcel['farmer_id'],
        'farmer_name' => trim((string) $parcel['farmer_name']),
        'location' => $parcel['location'] ?: null,
        'farmer_barangay' => $parcel['farmer_barangay'] ?: null,
        'area_hectares' => $parcel['total_area'] === null || $parcel['total_area'] === ''
            ? null
            : (float) $parcel['total_area'],
        'ownership_type' => $parcel['ownership_type'] ?: null,
        'farm_type' => $parcel['farm_type'] ?: null,
        'organic' => $parcel['organic'] ?: null,
        'created_at' => $parcel['created_at'] ?: null,
        'municipality' => null,
        'status' => null,
        'latitude' => null,
        'longitude' => null,
        'boundary_geojson' => null,
        'crops' => $crops,
    ],
    'satellite' => [
        'observation' => null,
        'history' => [],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
