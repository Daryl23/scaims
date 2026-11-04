<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
$suggestionsLoginPath = defined('SCAIMS_SUGGESTIONS_LOGIN_PATH')
    ? SCAIMS_SUGGESTIONS_LOGIN_PATH
    : '../../adminLogin/adminLogin.html';
scaims_require_admin($suggestionsLoginPath);
require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die(json_encode(['success' => false, 'error' => $e->getMessage()]));
}

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$id = (string) $data['id'];
$action = (string) $data['action'];

if ($action === 'mark_read') {
    $stmt = $conn->prepare("UPDATE suggestions SET status = 'read' WHERE id = ?");
} elseif ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM suggestions WHERE id = ?");
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

$stmt->bind_param('s', $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    error_log('SCAIMS update_suggestion failed: ' . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Could not update the suggestion. Please try again.']);
}
$stmt->close();

$conn->close();
?> 
