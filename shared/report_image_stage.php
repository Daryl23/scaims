<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['UName']) && !isset($_SESSION['AdminID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in before uploading an image.']);
    exit();
}

require_once __DIR__ . '/report_image_storage.php';
require_once __DIR__ . '/gps_photo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Session locking also prevents duplicate requests for the same image from running OCR twice.
@set_time_limit(65);
$upload = $_FILES['report_image'] ?? [];
$hash = !empty($upload['tmp_name']) && is_uploaded_file($upload['tmp_name']) ? hash_file('sha256', $upload['tmp_name']) : null;
foreach (($_SESSION['pending_report_images'] ?? []) as $token => $entry) {
    if ($hash && ($entry['sha256'] ?? '') === $hash && ($cached = scaims_pending_gps_photo($token))) {
        echo json_encode(['success' => true, 'token' => $token, 'photo' => $cached['photo']]);
        exit();
    }
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

$extraction = scaims_extract_gps_photo($result['path']);
if (!$extraction['ok']) {
    scaims_delete_report_image($result['path']);
    unset($_SESSION['pending_report_images'][$result['token']]);
    http_response_code(in_array($extraction['reason'], ['timeout', 'ocr_unavailable'], true) ? 503 : 422);
    echo json_encode(['success' => false, 'code' => $extraction['reason'], 'message' => 'GPS information could not be read. Please upload a clearer GPS Camera photo.']);
    exit();
}
$_SESSION['pending_report_images'][$result['token']]['gps_photo'] = $extraction['photo'];
$_SESSION['pending_report_images'][$result['token']]['sha256'] = $hash;

echo json_encode([
    'success' => true,
    'token' => $result['token'],
    'photo' => $extraction['photo'],
]);
