<?php
session_start();
if (!isset($_SESSION['UName'])) {
    die('Unauthorized access');
}

require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$uname = $_SESSION['UName'];

// Verify current password if changing username or password
if ((!empty($_POST['newPassword']) || 
    (isset($_POST['usernameChanged']) && $_POST['usernameChanged'] === 'true')) && 
    empty($_POST['currentPassword'])) {
    die('Current password is required for security changes');
}

// Verify current password if provided
if (!empty($_POST['currentPassword'])) {
    $stmt = $conn->prepare("SELECT UPass FROM user WHERE UName = ?");
    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!password_verify($_POST['currentPassword'], $user['UPass'])) {
        die('Current password is incorrect');
    }
}

// Get UserID and FarmerID
$stmt = $conn->prepare("SELECT u.UserID, f.FarmerID FROM user u JOIN farmer f ON u.UserID = f.UserID WHERE u.UName = ?");
$stmt->bind_param("s", $uname);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die('User not found');
}

// Add this function after database connection
function isStrongPassword($password) {
    // At least 8 characters long
    if (strlen($password) < 8) {
        return false;
    }
    
    // Contains at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    
    // Contains at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    
    // Contains at least one number
    if (!preg_match('/\d/', $password)) {
        return false;
    }
    
    // Contains at least one special character
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        return false;
    }
    
    return true;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Add validation checks before processing updates
    // Validate birth date
    $birthDate = new DateTime($_POST['dob']);
    $today = new DateTime();
    
    // Reset time components to midnight for accurate date comparison
    $birthDate->setTime(0, 0, 0);
    $today->setTime(0, 0, 0);
    
    if ($birthDate >= $today) {
        throw new Exception('Birth date cannot be today or a future date');
    }

    // Validate contact number
    if (!preg_match('/^09\d{9}$/', $_POST['contactNumber'])) {
        throw new Exception('Contact number must start with 09 and contain 11 digits');
    }

    // Update username if changed
    if ($_POST['username'] !== $uname) {
        // Check if new username is available
        $stmt = $conn->prepare("SELECT UserID FROM user WHERE UName = ? AND UserID != ?");
        $stmt->bind_param("si", $_POST['username'], $user['UserID']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Username already taken');
        }

        // Update username in user table
        $stmt = $conn->prepare("UPDATE user SET UName = ? WHERE UserID = ?");
        $stmt->bind_param("si", $_POST['username'], $user['UserID']);
        $stmt->execute();

        // Update created_by in related tables
        $tables = ['crop_details', 'crop_reports', 'damage_reports', 'farm_parcels', 'parcel_details'];
        foreach ($tables as $table) {
            $stmt = $conn->prepare("UPDATE $table SET created_by = ? WHERE created_by = ?");
            $stmt->bind_param("ss", $_POST['username'], $uname);
            $stmt->execute();
        }
    }

    // Update password if provided
    if (!empty($_POST['newPassword'])) {
        if (!isStrongPassword($_POST['newPassword'])) {
            throw new Exception('Password must be at least 8 characters long and contain uppercase, lowercase, numbers, and special characters');
        }
        
        $hashedPassword = password_hash($_POST['newPassword'], PASSWORD_DEFAULT);
        $plainPassword = $_POST['newPassword'];
        
        $sql = "UPDATE user SET 
                UPass = ?,
                plain_password = ?
                WHERE UserID = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $hashedPassword, $plainPassword, $user['UserID']);
        $stmt->execute();
    }

    // Update farmer information
    $sql = "UPDATE farmer SET 
            Surname = ?,
            FirstName = ?,
            MiddleName = ?,
            Suffix = ?,
            Sex = ?,
            BirthDate = ?,
            ContactNo = ?,
            Address = ?,
            Barangay = ?,
            RSBSA_Number = ?
            WHERE FarmerID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssi", 
        $_POST['surname'],
        $_POST['firstName'],
        $_POST['middleName'],
        $_POST['extensionName'],
        $_POST['sex'],
        $_POST['dob'],
        $_POST['contactNumber'],
        $_POST['address'],
        $_POST['barangay'],
        $_POST['rsbsaNumber'],
        $user['FarmerID']
    );
    $stmt->execute();

    // Update profile image if provided
    if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
        $image = file_get_contents($_FILES['profileImage']['tmp_name']);
        $img_stmt = $conn->prepare("UPDATE user SET ProfileImage = ? WHERE UserID = ?");
        $img_stmt->bind_param("si", $image, $user['UserID']);
        $img_stmt->execute();
    }

    // Commit transaction
    $conn->commit();
    
    // Update session username if changed
    if ($_POST['username'] !== $uname) {
        $_SESSION['UName'] = $_POST['username'];
    }
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
