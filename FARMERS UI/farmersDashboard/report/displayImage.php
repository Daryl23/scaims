<?php
session_start();
require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
} 

if (isset($_SESSION['UserID'])) {
    $userID = $_SESSION['UserID'];

    $stmt = $conn->prepare("SELECT ProfileImage FROM user WHERE UserID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $stmt->bind_result($profileImage);
    $stmt->fetch();

    if ($profileImage) {
        header("Content-Type: image/jpeg"); 
        echo $profileImage; 
    } else {
        header("Content-Type: image/jpeg");
        readfile('assets/default-profile.jpg'); 
    }

    $stmt->close();
}

$conn->close();
?>
