<?php
require_once __DIR__ . '/../shared/admin_auth.php';
scaims_require_admin('../ADMIN UI/adminLogin/adminLogin.html');
require_once dirname(__DIR__) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die(json_encode(['error' => $e->getMessage()]));
}

// Get the type of suggestions to fetch (new or previous)
$type = $_GET['type'] ?? 'new';

// Get the selected month-year
$monthYear = isset($_GET['months']) ? $_GET['months'] : null;

// Prepare the query based on type and month filter
if ($type === 'new') {
    $sql = "SELECT id, name, suggestion, submission_date, status 
            FROM suggestions 
            WHERE status = 'new' 
            ORDER BY submission_date DESC";
} else {
    $sql = "SELECT id, name, suggestion, submission_date, status 
            FROM suggestions 
            WHERE status = 'read'";

    if ($monthYear) {
        // Parse the year and month from the selected value
        list($year, $month) = explode('-', $monthYear);
        $sql .= " AND YEAR(submission_date) = '$year' AND MONTH(submission_date) = '$month'";
    }

    $sql .= " ORDER BY submission_date DESC";
}

$result = $conn->query($sql);

if ($result) {
    $suggestions = array();
    while ($row = $result->fetch_assoc()) {
        // Format the date to be more readable
        $row['submission_date'] = date('F j, Y g:i A', strtotime($row['submission_date']));
        
        // If name is empty, set it to 'Anonymous'
        $row['name'] = empty($row['name']) ? 'Anonymous' : htmlspecialchars($row['name']);
        
        // Ensure suggestion text is properly escaped
        $row['suggestion'] = htmlspecialchars($row['suggestion']);
        
        $suggestions[] = $row;
    }
    echo json_encode($suggestions);
} else {
    echo json_encode(['error' => "Error fetching suggestions: " . $conn->error]);
}

$conn->close();
?> 
