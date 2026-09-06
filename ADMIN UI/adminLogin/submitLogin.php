<?php
session_start();

require_once dirname(__DIR__, 2) . '/conn.php';
require_once dirname(__DIR__, 2) . '/shared/admin_auth.php';
require_once __DIR__ . '/../../shared/dev_mode.php';

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_admin_role_column($conn);
} catch (RuntimeException $e) {
    die($e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $adminID = trim($_POST['adminID']);
    $pass = trim($_POST['password']);
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

    // Verify reCAPTCHA response.
    // Skipped only for local smoke tests — see shared/dev_mode.php. Delete the
    // .dev-bypass marker file in the project root to turn this off everywhere.
    $recaptchaPassed = scaims_dev_bypass_enabled();

    if (!$recaptchaPassed) {
        $secretKey = '6LfJyocqAAAAABLIFEfuK9YxZzHLzG9kv7z4LtME';
        $recaptchaUrl = 'https://www.google.com/recaptcha/api/siteverify';
        $response = file_get_contents($recaptchaUrl . '?secret=' . $secretKey . '&response=' . $recaptchaResponse);
        $responseData = json_decode($response);
        $recaptchaPassed = !empty($responseData->success);
    }

    // Check if reCAPTCHA is valid
    if (!$recaptchaPassed) {
        echo "<script>alert('Please complete the reCAPTCHA.'); window.location.href='adminLogin.html';</script>";
        exit();
    }

    // Validate and sanitize inputs
    if (empty($adminID) || empty($pass)) {
        echo "<script>alert('Please fill in all fields.'); window.location.href='adminLogin.html';</script>";
        exit();
    }

    // Rest of your existing login code...
    $adminID = filter_var($adminID, FILTER_SANITIZE_STRING);

    $stmt = $conn->prepare("SELECT AdminID, AdminName, AdminPass, AdminRole, is_active FROM admin WHERE AdminName = ?");
    $stmt->bind_param("s", $adminID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['is_active'] != 1) {
            echo "<script>alert('This account is inactive.'); window.location.href='adminLogin.html';</script>";
            exit();
        }
        if (password_verify($pass, $row['AdminPass'])) {
            $adminRole = scaims_normalize_admin_role($row['AdminRole'] ?? 'admin');
            $_SESSION['AdminID'] = $row['AdminID'];
            $_SESSION['AdminName'] = $row['AdminName'];
            $_SESSION['AdminRole'] = $adminRole;
            $_SESSION['IsSuperAdmin'] = $adminRole === 'super_admin';
            header("Location: ../adminDashboard/home/home.php");
            exit(); 
        } else {
            echo "<script>alert('Invalid username or password.'); window.location.href='adminLogin.html';</script>";
        }
    } else {
        echo "<script>alert('Invalid username or password.'); window.location.href='adminLogin.html';</script>";
    }
    $stmt->close();
}
$conn->close();
?>
