<?php
require_once 'db_connection.php';

$barangay = $_GET['barangay'] ?? '';

// Set headers for Excel file
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="Farmers_Report.xls"');

// Start HTML output
echo '
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style>
    table {
        border-collapse: collapse;
        width: 100%;
    }
    th {
        background-color: #198754;
        color: white;
        font-weight: bold;
        padding: 10px;
        border: 1px solid #000;
    }
    td {
        padding: 8px;
        border: 1px solid #000;
    }
    .title {
        font-size: 18px;
        font-weight: bold;
        text-align: center;
        padding: 10px;
    }
    .subtitle {
        font-size: 14px;
        text-align: center;
        padding: 5px;
    }
</style>
</head>
<body>
    <div class="title">LIPA CITY AGRICULTURE OFFICE</div>
    <div class="subtitle">Farmers Report</div>
    <div class="subtitle">Generated on: ' . date('F d, Y') . '</div>
    <table>
        <tr>
            <th>Name</th>
            <th>Middle Name</th>
            <th>Suffix</th>
            <th>Sex</th>
            <th>Birthdate</th>
            <th>Contact Number</th>
            <th>Barangay</th>
            <th>Address</th>
            <th>Registration Date</th>
            <th>Username</th>
            <th>Password</th>
            <th>RSBSA Number</th>
        </tr>';

// Updated SQL query to include username, plain password, and RSBSA number
$sql = "SELECT 
            CONCAT(f.FirstName, ' ', f.Surname) AS Name,
            f.MiddleName,
            f.Suffix,
            f.Sex,
            DATE_FORMAT(f.BirthDate, '%M %d, %Y') as BirthDate,
            f.ContactNo,
            CONCAT_WS(' ',
                CONCAT(UPPER(SUBSTRING(SUBSTRING_INDEX(f.Barangay, ' ', 1), 1, 1)),
                      LOWER(SUBSTRING(SUBSTRING_INDEX(f.Barangay, ' ', 1), 2))),
                CASE 
                    WHEN LOCATE(' ', f.Barangay) > 0 
                    THEN CONCAT(UPPER(SUBSTRING(SUBSTRING_INDEX(f.Barangay, ' ', -1), 1, 1)),
                              LOWER(SUBSTRING(SUBSTRING_INDEX(f.Barangay, ' ', -1), 2)))
                    ELSE ''
                END
            ) as Barangay,
            f.Address,
            DATE_FORMAT(f.registration_date, '%M %d, %Y') as RegistrationDate,
            u.UName as Username,
            u.plain_password as Password,  -- Assuming you have a plain_password column
            f.RSBSA_Number  -- Add RSBSA number here
        FROM farmer f
        JOIN user u ON f.UserID = u.UserID 
        WHERE 1=1";

if ($barangay) {
    $sql .= " AND f.Barangay = ?";
}

$stmt = $conn->prepare($sql);
if ($barangay) {
    $stmt->bind_param('s', $barangay);
}
$stmt->execute();
$result = $stmt->get_result();

$rowCount = 0;
while ($row = $result->fetch_assoc()) {
    $rowCount++;
    echo '<tr>';
    foreach ($row as $value) {
        $safeValue = is_null($value) ? '' : htmlspecialchars($value);
        echo '<td>' . $safeValue . '</td>';
    }
    echo '</tr>';
}

echo '</table>';
echo '<div class="subtitle">Total Farmers: ' . $rowCount . '</div>';
echo '</body></html>';

$conn->close();
?>
