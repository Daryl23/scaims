<?php
require_once 'db_connection.php';

$cropType = $_POST['cropType'] ?? '';

if ($cropType) {
    $sql = "SELECT DISTINCT YEAR(harvest_date) as year 
            FROM crop_reports 
            WHERE crop_type = ? 
            ORDER BY year DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cropType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $years = [];
    while ($row = $result->fetch_assoc()) {
        $years[] = $row['year'];
    }
    
    if (empty($years)) {
        $years[] = date('Y');
    }
    
    echo json_encode($years);
    
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing crop type"]);
}

$conn->close();
?> 