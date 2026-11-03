<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
header('Content-Type: application/json');

if (!scaims_can_validate_farmers()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only a superadmin can validate a farmer.']);
    exit();
}

require_once 'db_connection.php';

$farmerId = $_POST['farmerId'] ?? '';
$action = $_POST['action'] ?? 'validate'; // 'validate' | 'unvalidate'

if ($farmerId === '' || !ctype_digit((string) $farmerId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid farmer id.']);
    exit();
}

$isValidated = $action === 'unvalidate' ? 0 : 1;
$validatedBy = $_SESSION['AdminName'] ?? null;

if ($isValidated) {
    $sql = "UPDATE farmer SET IsValidated = 1, ValidatedBy = ?, ValidatedAt = NOW() WHERE FarmerID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $validatedBy, $farmerId);
} else {
    $sql = "UPDATE farmer SET IsValidated = 0, ValidatedBy = NULL, ValidatedAt = NULL WHERE FarmerID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $farmerId);
}

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => $isValidated ? 'Farmer validated.' : 'Farmer validation removed.',
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not update validation status: ' . $conn->error]);
}

$stmt->close();
$conn->close();
