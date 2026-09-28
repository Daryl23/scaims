<?php

// Auto-prepended only by the isolated Phase 1 PHP development server.
if (PHP_SAPI !== 'cli-server' || !getenv('SCAIMS_PHASE1_TEST_DB')) {
    http_response_code(404);
    exit;
}

define('SCAIMS_DB_HOST', 'localhost');
define('SCAIMS_DB_NAME', getenv('SCAIMS_PHASE1_TEST_DB'));
define('SCAIMS_DB_USER', 'root');
define('SCAIMS_DB_PASS', '');
define('SCAIMS_DB_PORT', 3306);
require dirname(__DIR__, 2) . '/conn.php';

$sessionDir = dirname(__DIR__) . '/phase1-results/' . SCAIMS_DB_NAME . '-sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}
session_save_path($sessionDir);
ini_set('session.serialize_handler', 'php_serialize');

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$testKey = $_SERVER['HTTP_X_PHASE1_TEST_KEY'] ?? '';
$authorizedTestRoute = hash_equals((string) getenv('SCAIMS_PHASE1_TEST_KEY'), $testKey);

if ($path === '/__phase1/session') {
    if (!$authorizedTestRoute) {
        http_response_code(403);
        exit;
    }
    session_start();
    header('Content-Type: application/json');
    echo json_encode([
        'session_id' => session_id(),
        'UserID' => $_SESSION['UserID'] ?? null,
        'UName' => $_SESSION['UName'] ?? null,
        'AdminID' => $_SESSION['AdminID'] ?? null,
        'AdminName' => $_SESSION['AdminName'] ?? null,
        'AdminRole' => $_SESSION['AdminRole'] ?? null,
        'IsSuperAdmin' => $_SESSION['IsSuperAdmin'] ?? null,
        'pending' => array_map(static function (array $entry): array {
            return [
                'path' => $entry['path'] ?? null,
                'created_at' => $entry['created_at'] ?? null,
                'has_photo' => isset($entry['gps_photo']),
            ];
        }, $_SESSION['pending_report_images'] ?? []),
    ]);
    return;
}

if ($path === '/__phase1/create-expired-token') {
    if (!$authorizedTestRoute) {
        http_response_code(403);
        exit;
    }
    session_start();
    $token = bin2hex(random_bytes(24));
    $directory = dirname(__DIR__, 2) . '/uploads/reports/pending';
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $filename = 'phase1-' . SCAIMS_DB_NAME . '-expired.png';
    $absolute = $directory . '/' . $filename;
    file_put_contents($absolute, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4ZkAAAAASUVORK5CYII='));
    $relative = 'uploads/reports/pending/' . $filename;
    $_SESSION['pending_report_images'][$token] = ['path' => $relative, 'created_at' => 0];
    $_SESSION['phase1_created_files'][] = $relative;
    header('Content-Type: application/json');
    echo json_encode(['token' => $token, 'path' => $relative]);
    return;
}

$root = realpath(dirname(__DIR__, 2));
$file = realpath($root . $path);
if (!$file || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    return;
}
if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
    return false;
}
chdir(dirname($file));
require $file;

