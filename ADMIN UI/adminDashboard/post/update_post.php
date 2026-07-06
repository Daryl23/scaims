<?php
require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $postId = $_POST['post_id'];
    $content = nl2br(htmlspecialchars($_POST['post_content']));
    
    $sql = "UPDATE posts SET content = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $content, $postId);
    
    if ($stmt->execute()) {
        header("Location: post.php");
        exit();
    }
    
    $stmt->close();
}

$conn->close();
?>
