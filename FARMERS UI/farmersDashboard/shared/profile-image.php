<?php
require_once __DIR__ . '/farmer-auth.php';
require_once dirname(__DIR__, 3) . '/conn.php';

function scaims_output_farmer_profile_image(string $fallbackImagePath): void
{
    scaims_farmer_session_start();
    try { $conn = scaims_db_mysqli(); } catch (RuntimeException $e) { die($e->getMessage()); }
    if (isset($_SESSION['UserID'])) {
        $userID = $_SESSION['UserID'];
        $stmt = $conn->prepare('SELECT ProfileImage FROM user WHERE UserID = ?');
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $stmt->bind_result($profileImage);
        $stmt->fetch();
        header('Content-Type: image/jpeg');
        if ($profileImage) echo $profileImage;
        else readfile($fallbackImagePath);
        $stmt->close();
    }
    $conn->close();
}
