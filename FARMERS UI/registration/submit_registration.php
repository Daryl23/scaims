<?php
session_start();

require_once dirname(__DIR__, 2) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

$surname = mysqli_real_escape_string($conn, $_POST['surname']);
$first_name = mysqli_real_escape_string($conn, $_POST['firstName']);
$middle_name = mysqli_real_escape_string($conn, $_POST['middleName'] ?? '');
$extension_name = mysqli_real_escape_string($conn, $_POST['extensionName'] ?? '');
$dob = mysqli_real_escape_string($conn, $_POST['dob']);
$sex = mysqli_real_escape_string($conn, $_POST['sex']);
$contact_number = mysqli_real_escape_string($conn, $_POST['contactNumber']);
$address = mysqli_real_escape_string($conn, $_POST['address']);
$barangay = mysqli_real_escape_string($conn, $_POST['barangay']);
$rsbsa_number = mysqli_real_escape_string($conn, $_POST['rsbsaNumber'] ?? '');
$terms = isset($_POST['terms']) ? 1 : 0;

if (empty($surname) || empty($first_name) || empty($dob) || empty($contact_number) || empty($address)) {
    die("<script>alert('Please fill out all required fields.'); window.history.back();</script>");
}

$sql = "INSERT INTO farmer (FirstName, Surname, MiddleName, Suffix, Sex, BirthDate, ContactNo, Address, Barangay, RSBSA_Number, registration_date) 
        VALUES ('$first_name', '$surname', '$middle_name', '$extension_name', '$sex', '$dob', '$contact_number', '$address', '$barangay', '$rsbsa_number', NOW())";

if ($conn->query($sql) === TRUE) {
    $_SESSION['farmer_id'] = $conn->insert_id; 
    echo "success"; 
} else {
    http_response_code(500); 
    echo "Error: " . $conn->error;
}

$conn->close();
?>
