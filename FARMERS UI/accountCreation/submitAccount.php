<?php
session_start();

require_once dirname(__DIR__, 2) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$uname = trim($_POST['username']);

// Check if username exists
$check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user WHERE UName = ?");
$check_stmt->bind_param("s", $uname);
$check_stmt->execute();
$result = $check_stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo "<script>alert('Username already exists. Please choose a different username.'); window.history.back();</script>";
    exit();
}

$upass = trim($_POST['password']);
$userRole = 'regular';
$creationDate = date('Y-m-d H:i:s');

$hashed_password = password_hash($upass, PASSWORD_BCRYPT);

if (isset($_FILES['profileImage'])) {
    if ($_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
        $image = file_get_contents($_FILES['profileImage']['tmp_name']);

        $stmt = $conn->prepare("INSERT INTO user (UName, UPass, UserRole, creationDate, ProfileImage, plain_password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $uname, $hashed_password, $userRole, $creationDate, $image, $upass);

        if ($stmt->execute()) {
            $userID = $conn->insert_id;

            $farmerID = $_SESSION['farmer_id'];
            $update_sql = "UPDATE farmer SET UserID = ? WHERE FarmerID = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $userID, $farmerID);
            $update_stmt->execute();

            $_SESSION['uname'] = $uname;

            header("Location: ../farmerLogin/farmerlogin.html");
            exit();
        } else {
            echo "<script>alert('Error creating account: " . htmlspecialchars($stmt->error) . "'); window.location.href = '../registration/registration_form.html';</script>";
        }

        $stmt->close();
    } else {
        echo "Error uploading file.";
    }
} else {
    echo "<script>alert('Error: No image uploaded.'); window.location.href = '../registration/registration_form.html';</script>";
}

$conn->close();
?>
