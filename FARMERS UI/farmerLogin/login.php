<?php
session_start();

require_once dirname(__DIR__, 2) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);

    if (empty($user) || empty($pass)) {
        echo "<script>alert('Please fill in all fields.'); window.location.href='farmerlogin.html';</script>";
        exit();
    }

    // Your existing login validation logic
    $stmt = $conn->prepare("
        SELECT user.UserID, user.UName, user.UPass, user.ProfileImage, farmer.FirstName, farmer.Surname 
        FROM user 
        JOIN farmer ON user.UserID = farmer.UserID 
        WHERE user.UName = ? AND farmer.is_active = 1
    ");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($pass, $row['UPass'])) {
            // Set session variables
            $_SESSION['UserID'] = $row['UserID'];
            $_SESSION['UName'] = $row['UName'];
            $_SESSION['ProfileImage'] = $row['ProfileImage'];
            $_SESSION['FirstName'] = $row['FirstName'];
            $_SESSION['Surname'] = $row['Surname'];

            header("Location: ../farmersDashboard/home/home.php");
            exit();
        } else {
            echo "<script>alert('Invalid username or password.'); window.location.href='farmerlogin.html';</script>";
        }
    } else {
        echo "<script>alert('Invalid username or password.'); window.location.href='farmerlogin.html';</script>";
    }
    $stmt->close();
}
$conn->close();
?>
