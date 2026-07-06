<?php
$role = $_GET['role'] ?? 'farmer';
$sessionId = $role === 'admin' ? 'manualadminsession' : 'manualfarmersession';

session_id($sessionId);
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'httponly' => false,
    'samesite' => 'Lax',
]);
session_start();
setcookie(session_name(), session_id(), time() + 3600, '/');

if ($role === 'admin') {
    $_SESSION['AdminID'] = 'manual-preview-admin';
    $_SESSION['AdminName'] = 'APGORRS Admin';
} else {
    $_SESSION['UName'] = 'manual-preview-farmer';
    $_SESSION['UserID'] = 'manual-preview-farmer';
    $_SESSION['FirstName'] = 'Juan';
    $_SESSION['Surname'] = 'Dela Cruz';
}

header('Content-Type: text/plain');
echo 'Session ready for ' . $role;
