<?php
require_once dirname(__DIR__) . '/farmersDashboard/shared/farmer-auth.php';
scaims_farmer_session_start();

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

    // Login identifier can be the account username or the farmer's RSBSA number.
    // RSBSA numbers are not guaranteed unique in existing data, so every
    // matching account is checked and only the one whose password verifies
    // signs in (exact username matches are tried first).
    $stmt = $conn->prepare("
        SELECT user.UserID, user.UName, user.UPass, user.ProfileImage, farmer.FirstName, farmer.Surname
        FROM user
        JOIN farmer ON user.UserID = farmer.UserID
        WHERE (user.UName = ? OR farmer.RSBSA_Number = ?)
          AND farmer.is_active = 1
          AND farmer.FarmerStatus <> 'Deceased'
        ORDER BY (user.UName = ?) DESC
    ");
    $stmt->bind_param("sss", $user, $user, $user);
    $stmt->execute();
    $result = $stmt->get_result();

    $row = null;
    while ($candidate = $result->fetch_assoc()) {
        if (password_verify($pass, $candidate['UPass'])) {
            $row = $candidate;
            break;
        }
    }

    if ($row !== null) {
        session_regenerate_id(true);
        $_SESSION['UserID'] = $row['UserID'];
        $_SESSION['UName'] = $row['UName'];
        $_SESSION['ProfileImage'] = $row['ProfileImage'];
        $_SESSION['FirstName'] = $row['FirstName'];
        $_SESSION['Surname'] = $row['Surname'];

        header("Location: ../farmersDashboard/home/home.php");
        exit();
    }

    echo "<script>alert('Invalid username or password.'); window.location.href='farmerlogin.html';</script>";
    $stmt->close();
}
$conn->close();
?>
