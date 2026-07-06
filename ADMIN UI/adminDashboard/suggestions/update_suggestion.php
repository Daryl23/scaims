<?php
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

$id = $conn->real_escape_string($data['id']);
$action = $conn->real_escape_string($data['action']);

// Perform the requested action
if ($action === 'mark_read') {
    $sql = "UPDATE suggestions SET status = 'read' WHERE id = '$id'";
} elseif ($action === 'delete') {
    $sql = "DELETE FROM suggestions WHERE id = '$id'";
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

if ($conn->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$conn->close();
?> 
