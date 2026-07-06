<?php
require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$sql = "SELECT * FROM posts ORDER BY created_at DESC";
$result = $conn->query($sql);

$posts = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
        
        if ($row['image_paths']) {
            $row['image_paths'] = json_decode($row['image_paths']);
        }
        
        $posts[] = $row;
    }
}

echo json_encode($posts);
$conn->close();
?>
