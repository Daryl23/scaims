<?php
require_once __DIR__ . '/conn.php';

header('Content-Type: application/json; charset=utf-8');

function suggestion_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    suggestion_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
$suggestion = isset($_POST['suggestion']) && is_string($_POST['suggestion']) ? trim($_POST['suggestion']) : '';

// Name is optional on the form; the admin view shows blank names as "Anonymous".
if ($suggestion === '') {
    suggestion_response(422, ['success' => false, 'error' => 'Pakilagay ang inyong mungkahi.']);
}

// This endpoint is public, so cap sizes to keep spam from bloating the table.
if (mb_strlen($name) > 100 || mb_strlen($suggestion) > 2000) {
    suggestion_response(422, ['success' => false, 'error' => 'Masyadong mahaba ang pangalan o mungkahi.']);
}

try {
    $conn = scaims_db_pdo();
    $stmt = $conn->prepare("INSERT INTO suggestions (name, suggestion, submission_date) VALUES (:name, :suggestion, NOW())");
    $stmt->execute([':name' => $name, ':suggestion' => $suggestion]);
    suggestion_response(200, ['success' => true]);
} catch (Throwable $e) {
    error_log('SCAIMS submit_suggestion failed: ' . $e->getMessage());
    suggestion_response(500, ['success' => false, 'error' => 'Hindi naipadala ang mungkahi. Pakisubukang muli.']);
}
