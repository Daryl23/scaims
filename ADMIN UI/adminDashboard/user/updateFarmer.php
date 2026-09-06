<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
// Ensure no output before headers
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Remove any UTF-8 BOM or whitespace
if (ob_get_length()) ob_clean();

require_once 'db_connection.php';

$farmerId = $_POST['farmerId'];
$firstName = $_POST['firstName'];
$surname = $_POST['surname'];
$middleName = $_POST['middlename'];
$suffix = $_POST['suffix'];
$sex = $_POST['sex'];
$birthdate = $_POST['birthdate'];
$contactNo = $_POST['contactNo'];
$barangay = $_POST['barangay'];
$address = $_POST['address'];
$username = $_POST['username'];
$password = $_POST['password'];
$rsbsaNumber = $_POST['rsbsaNumber'];

// A blank password field means "leave the current password alone".
$checkSql = "SELECT u.UName, u.UPass FROM user u 
             JOIN farmer f ON u.UserID = f.UserID 
             WHERE f.FarmerID = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $farmerId);
$checkStmt->execute();
$result = $checkStmt->get_result();
$currentUser = $result->fetch_assoc();
$checkStmt->close();

// Password validation — only when the admin actually typed a new one.
if (trim($password) !== '') {
    // Check password requirements
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if (!preg_match("/[A-Z]/", $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match("/[a-z]/", $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match("/[0-9]/", $password)) {
        $errors[] = "Password must contain at least one number";
    }
    if (!preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $password)) {
        $errors[] = "Password must contain at least one special character";
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'Password validation failed', 'errors' => $errors]);
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Update user credentials including password
    $sql2 = "UPDATE user u 
             JOIN farmer f ON u.UserID = f.UserID 
             SET u.UName = ?, 
                 u.UPass = ?
             WHERE f.FarmerID = ?";
    
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("ssi", $username, $hashedPassword, $farmerId);
    $success = $stmt2->execute();
    $stmt2->close();
} else {
    // Update only username if no password change
    $sql2 = "UPDATE user u 
             JOIN farmer f ON u.UserID = f.UserID 
             SET u.UName = ?
             WHERE f.FarmerID = ?";
    
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("si", $username, $farmerId);
    $success = $stmt2->execute();
    $stmt2->close();
}

// Update created_by in related tables if username changed
if ($username !== $currentUser['UName']) {
    $tables = ['crop_details', 'crop_reports', 'damage_reports', 'farm_parcels', 'parcel_details'];
    foreach ($tables as $table) {
        $updateSql = "UPDATE $table SET created_by = ? WHERE created_by = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ss", $username, $currentUser['UName']);
        $updateStmt->execute();
        $updateStmt->close();
    }
}

// Validate RSBSA number format
if ($rsbsaNumber !== '') {  // Only validate if RSBSA is provided
    // Check format (XX-XX-XX-XXX-XXXXXX where X is a digit)
    if (!preg_match('/^\d{2}-\d{2}-\d{2}-\d{3}-\d{6}$/', $rsbsaNumber)) {
        die(json_encode([
            'success' => false, 
            'message' => 'Invalid RSBSA number format. Please use the format: XX-XX-XX-XXX-XXXXXX (e.g., 12-34-56-789-012345)'
        ]));
    }

    // Check if RSBSA number already exists (excluding current farmer)
    $checkRsbsaSql = "SELECT COUNT(*) as count FROM farmer WHERE RSBSA_Number = ? AND FarmerID != ?";
    $checkRsbsaStmt = $conn->prepare($checkRsbsaSql);
    $checkRsbsaStmt->bind_param("si", $rsbsaNumber, $farmerId);
    $checkRsbsaStmt->execute();
    $rsbsaResult = $checkRsbsaStmt->get_result();
    $rsbsaCount = $rsbsaResult->fetch_assoc()['count'];
    $checkRsbsaStmt->close();

    if ($rsbsaCount > 0) {
        die(json_encode([
            'success' => false, 
            'message' => 'This RSBSA number is already registered to another farmer.'
        ]));
    }
}

// Start transaction
$conn->begin_transaction();

try {
    // Update farmer table
    $sql = "UPDATE farmer f
            SET f.FirstName = ?, 
                f.Surname = ?, 
                f.MiddleName = ?, 
                f.Suffix = ?, 
                f.Sex = ?, 
                f.BirthDate = ?, 
                f.ContactNo = ?, 
                f.Barangay = ?, 
                f.Address = ?, 
                f.RSBSA_Number = ?
            WHERE f.FarmerID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssi", $firstName, $surname, $middleName, $suffix, $sex, $birthdate, $contactNo, $barangay, $address, $rsbsaNumber, $farmerId);

    $success = $stmt->execute();

    if ($success) {
        $conn->commit();
        die(json_encode(['success' => true, 'message' => 'Farmer details updated successfully!']));
    } else {
        $conn->rollback();
        die(json_encode(['success' => false, 'message' => 'Failed to update farmer details.']));
    }
} catch (Exception $e) {
    $conn->rollback();
    die(json_encode(['success' => false, 'message' => 'Failed to update farmer details: ' . $e->getMessage()]));
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>
