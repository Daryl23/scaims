<?php
require_once __DIR__ . '/conn.php';

try {
    $conn = scaims_db_pdo();

    // Prepare SQL statement
    $stmt = $conn->prepare("INSERT INTO suggestions (name, suggestion, submission_date) VALUES (:name, :suggestion, NOW())");

    // Bind parameters
    $stmt->bindParam(':name', $_POST['name']);
    $stmt->bindParam(':suggestion', $_POST['suggestion']);

    // Execute query
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn = null;
?> 
