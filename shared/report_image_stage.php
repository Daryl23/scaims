<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['UName']) && !isset($_SESSION['AdminID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in before uploading an image.']);
    exit();
}

require_once __DIR__ . '/report_image_storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$result = scaims_stage_report_image('report_image');
if (!$result['ok']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'The image could not be uploaded. Please try again.',
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'token' => $result['token'],
]);
