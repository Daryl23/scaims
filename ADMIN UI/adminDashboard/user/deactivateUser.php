<?php
require_once 'db_connection.php';

$userId = $_POST['userId'] ?? '';
$userType = $_POST['userType'] ?? '';

if (!$userId || !$userType) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
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