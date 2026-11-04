<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
require_once 'db_connection.php';

$userId = $_POST['userId'] ?? '';
$userType = $_POST['userType'] ?? '';

if (!$userId || !$userType) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

if ($userType !== 'Farmer' && !scaims_is_super_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only a superadmin can deactivate an admin account.']);
    exit;
}

if ($userType !== 'Farmer' && (string) $userId === (string) ($_SESSION['AdminID'] ?? '')) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account.']);
    exit;
}

$table = $userType === 'Farmer' ? 'farmer' : 'admin';
$idField = $userType === 'Farmer' ? 'FarmerID' : 'AdminID';

$sql = "UPDATE $table SET is_active = 0 WHERE $idField = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $userId);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'User has been deactivated successfully',
        'requiresLogout' => true,
        'logoutReason' => 'account deactivation'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error deactivating user: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?> 