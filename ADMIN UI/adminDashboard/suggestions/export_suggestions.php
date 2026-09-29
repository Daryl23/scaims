<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
$suggestionsLoginPath = defined('SCAIMS_SUGGESTIONS_LOGIN_PATH')
    ? SCAIMS_SUGGESTIONS_LOGIN_PATH
    : '../../adminLogin/adminLogin.html';
scaims_require_admin($suggestionsLoginPath);
require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

// Get the selected month-year from the URL
$monthYear = isset($_GET['months']) ? $_GET['months'] : null;

// Prepare the SQL query
if ($monthYear) {
    // Parse the year and month from the selected value
    list($year, $month) = explode('-', $monthYear);
    $sql = "SELECT name, suggestion, submission_date, status 
            FROM suggestions 
            WHERE YEAR(submission_date) = '$year' 
            AND MONTH(submission_date) = '$month' 
            ORDER BY submission_date DESC";
} else {
    // If no month selected, get all suggestions
    $sql = "SELECT name, suggestion, submission_date, status 
            FROM suggestions 
            ORDER BY submission_date DESC";
}

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Generate filename with the selected month and year
    if ($monthYear) {
        $filename = "suggestions_{$year}_{$month}.csv";
    } else {
        $filename = "suggestions_all_" . date('Ymd') . ".csv";
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, array('Name', 'Suggestion', 'Submission Date', 'Status'));

    // Write data rows
    while ($row = $result->fetch_assoc()) {
        // Format the date to be more readable
        $row['submission_date'] = date('F j, Y g:i A', strtotime($row['submission_date']));
        
        // If name is empty, set it to 'Anonymous'
        $row['name'] = empty($row['name']) ? 'Anonymous' : $row['name'];
        
        fputcsv($output, $row);
    }
    fclose($output);
} else {
    // Return a JSON response indicating no suggestions were found
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No suggestions found for the selected time period.']);
}

$conn->close();
?> 
