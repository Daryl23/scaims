<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
require_once 'db_connection.php';

$userId = $_POST['userId'] ?? '';
$userType = $_POST['userType'] ?? '';
$action = $_POST['action'] ?? '';

if (!$userId || !$userType || !$action) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

if ($userType !== 'Farmer' && !scaims_is_super_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only a superadmin can activate or deactivate an admin account.']);
    exit;
}

$table = $userType === 'Farmer' ? 'farmer' : 'admin';
$idField = $userType === 'Farmer' ? 'FarmerID' : 'AdminID';
$newStatus = $action === 'activate' ? 1 : 0;

$sql = "UPDATE $table SET is_active = ? WHERE $idField = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $newStatus, $userId);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'User has been ' . ($newStatus ? 'activated' : 'deactivated') . ' successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error ' . ($newStatus ? 'activating' : 'deactivating') . ' user: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?> 