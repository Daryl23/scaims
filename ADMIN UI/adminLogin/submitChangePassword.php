<?php

require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/shared/admin_auth.php';

// An admin with an expired password must still be able to reach this page.
scaims_require_admin('../../adminLogin/adminLogin.html', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: changePassword.html');
    exit();
}

$currentPassword = $_POST['currentPassword'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    echo "<script>alert('Please fill in all fields.'); window.location.href='changePassword.html';</script>";
    exit();
}

if ($newPassword !== $confirmPassword) {
    echo "<script>alert('New password and confirmation do not match.'); window.location.href='changePassword.html';</script>";
    exit();
}

if (strlen($newPassword) < 8) {
    echo "<script>alert('New password must be at least 8 characters.'); window.location.href='changePassword.html';</script>";
    exit();
}

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$adminId = $_SESSION['AdminID'];

$stmt = $conn->prepare('SELECT AdminPass FROM admin WHERE AdminID = ?');
$stmt->bind_param('s', $adminId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($currentPassword, $row['AdminPass'])) {
    echo "<script>alert('Current password is incorrect.'); window.location.href='changePassword.html';</script>";
    exit();
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$update = $conn->prepare('UPDATE admin SET AdminPass = ?, PasswordChangedAt = NOW() WHERE AdminID = ?');
$update->bind_param('ss', $newHash, $adminId);

if ($update->execute()) {
    header('Location: ../adminDashboard/home/home.php');
    exit();
}

echo "<script>alert('Could not update password. Please try again.'); window.location.href='changePassword.html';</script>";

$update->close();
$conn->close();
