<?php
// Only active for the dedicated loopback test server launched by gps-photo-workflow.cjs.
if (PHP_SAPI !== 'cli-server' || !getenv('SCAIMS_GPS_TEST_DB')) { http_response_code(404); exit; }
define('SCAIMS_DB_HOST', 'localhost'); define('SCAIMS_DB_NAME', getenv('SCAIMS_GPS_TEST_DB'));
define('SCAIMS_DB_USER', 'root'); define('SCAIMS_DB_PASS', ''); define('SCAIMS_DB_PORT', 3306);
require dirname(__DIR__) . '/conn.php';
$sessionDir = __DIR__ . '/gps-photo-results/' . SCAIMS_DB_NAME . '-sessions';
if (!is_dir($sessionDir)) mkdir($sessionDir, 0700, true);
session_save_path($sessionDir);
ini_set('session.serialize_handler', 'php_serialize');
// conn.php allows constants to be supplied before inclusion; this router is auto-prepended below.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path === '/__test/session') {
    if (!hash_equals(getenv('SCAIMS_GPS_TEST_KEY'), $_SERVER['HTTP_X_TEST_KEY'] ?? '')) { http_response_code(403); exit; }
    session_start(); $_SESSION = [];
    if (($_GET['as'] ?? '') === 'admin') { $_SESSION['AdminID'] = 'gps-test-admin'; $_SESSION['AdminName'] = 'GPS Test Personnel'; }
    else { $_SESSION['UName'] = ($_GET['as'] ?? '') === 'other' ? 'gps-test-other' : 'gps-test-farmer'; $_SESSION['UserID'] = ($_GET['as'] ?? '') === 'other' ? 2 : 1; $_SESSION['FirstName'] = 'GPS'; $_SESSION['Surname'] = 'Fixture'; }
    echo 'ready'; return;
}
$root = realpath(dirname(__DIR__)); $file = realpath($root . $path);
if (!$file || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0) { http_response_code(404); return; }
if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') return false;
chdir(dirname($file)); require $file;
