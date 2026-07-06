<?php
require_once dirname(__DIR__, 2) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

if (isset($_POST['rsbsa_number'])) {
    $rsbsa_number = mysqli_real_escape_string($conn, $_POST['rsbsa_number']);

    $sql = "SELECT COUNT(*) as count FROM farmer WHERE RSBSA_Number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $rsbsa_number);
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
