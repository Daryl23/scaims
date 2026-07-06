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

if (isset($_POST['rsbsa_number'])) {
    $rsbsa_number = mysqli_real_escape_string($conn, $_POST['rsbsa_number']);
    $current_user = $_SESSION['UName'];

    // Check for duplicate RSBSA, excluding the current user
    $sql = "SELECT COUNT(*) as count FROM farmer f 
            JOIN user u ON f.UserID = u.UserID 
            WHERE f.RSBSA_Number = ? AND u.UName != ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $rsbsa_number, $current_user);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    // Explicitly set the content type to text/plain
    header('Content-Type: text/plain');
    
    // Echo 'unique' if count is 0, 'duplicate' otherwise
    echo ($row['count'] == 0) ? 'unique' : 'duplicate';
} else {
    echo 'error';
}

$conn->close();
?> 
