<?php
require_once dirname(__DIR__, 2) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$username = trim($_POST['username']);

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user WHERE UName = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo $row['count'] > 0 ? 'exists' : 'available';

$stmt->close();
$conn->close();
?> 
