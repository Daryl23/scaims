<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
header('Content-Type: application/json');

if (!scaims_can_validate_farmers()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only a superadmin can change a farmer status.']);
    exit();
}

require_once 'db_connection.php';

$farmerId = $_POST['farmerId'] ?? '';
$status = $_POST['status'] ?? '';
$allowedStatuses = ['Active', 'Inactive', 'Deceased'];

if ($farmerId === '' || !ctype_digit((string) $farmerId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid farmer id.']);
    exit();
}

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status. Use Active, Inactive, or Deceased.']);
    exit();
}

$sql = "UPDATE farmer SET FarmerStatus = ? WHERE FarmerID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $status, $farmerId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Farmer status updated.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not update status: ' . $conn->error]);
}

$stmt->close();
$conn->close();
