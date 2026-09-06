<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
session_start();
require_once 'db_connection.php';

$pin = $_POST['pin'] ?? '';

// In a real application, you should hash the PIN and compare with a stored hash
// This is a simplified example - replace with your actual PIN
$correctPin = "123456"; // Store this securely, preferably in environment variables

if ($pin === $correctPin) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>