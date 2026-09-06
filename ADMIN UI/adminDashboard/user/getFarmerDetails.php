<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
require_once 'db_connection.php';

$farmerId = $_POST['id'] ?? '';

if (is_numeric($farmerId)) {
    $sql = "SELECT f.FarmerID,
                   f.FirstName,
                   f.Surname,
                   f.MiddleName,
                   f.Suffix,
                   f.Sex,
                   f.BirthDate,
                   f.ContactNo,
                   f.Barangay,
                   f.Address,
                   f.RSBSA_Number,
                   u.UName
            FROM farmer f
            JOIN user u ON f.UserID = u.UserID
            WHERE f.FarmerID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $farmerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Capitalize the first letter of Barangay
        $row['Barangay'] = ucfirst(strtolower($row['Barangay']));
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Farmer not found']);
    }

    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid Farmer ID']);
}

$conn->close();
?>
