<?php
// Both registration steps are saved together, only after Create Account.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once dirname(__DIR__, 2) . '/conn.php';

function registration_response(int $status, string $message, bool $success = false): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    registration_response(405, 'Please submit the registration form.');
}

$data = [];
foreach (['surname', 'firstName', 'middleName', 'extensionName', 'dob', 'sex', 'contactNumber', 'address', 'barangay', 'rsbsaNumber', 'username'] as $field) {
    $data[$field] = isset($_POST[$field]) && is_string($_POST[$field]) ? trim($_POST[$field]) : '';
}
foreach (['surname', 'firstName', 'dob', 'sex', 'contactNumber', 'address', 'barangay', 'rsbsaNumber', 'username'] as $field) {
    if ($data[$field] === '') registration_response(422, 'Please complete all required fields in both parts.');
}
if (($_POST['terms'] ?? '') !== 'on') registration_response(422, 'Please agree to the Terms of Service and Privacy Policy.');
foreach (['surname', 'firstName', 'middleName'] as $field) {
    if ($data[$field] !== '' && !preg_match('/^[A-Za-z\s]+$/', $data[$field])) {
        registration_response(422, 'Please use letters and spaces only for names.');
    }
}
$birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $data['dob']);
if (!$birthDate || $birthDate->format('Y-m-d') !== $data['dob'] || $birthDate >= new DateTimeImmutable('today')) {
    registration_response(422, 'Please enter a valid birth date before today.');
}
if (!in_array($data['sex'], ['male', 'female', 'other'], true)
    || !preg_match('/^09\d{9}$/', $data['contactNumber'])
    || !preg_match('/^\d{2}-\d{2}-\d{2}-\d{3}-\d{6}$/', $data['rsbsaNumber'])) {
    registration_response(422, 'Please check your sex, contact number, and RSBSA number in Part 1.');
}
$rawPassword = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
// Keep password normalization consistent with the existing login endpoint.
$password = trim($rawPassword);
if (strlen($password) < 8 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)
    || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9\s]/', $password)) {
    registration_response(422, 'Use a password with at least 8 characters, uppercase, lowercase, a number, and a special character.');
}
if ($rawPassword !== ($_POST['confirmPassword'] ?? '')) registration_response(422, 'Passwords do not match.');
$upload = $_FILES['profileImage'] ?? null;
if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
    registration_response(422, 'Please select a profile image that is within the server upload limit.');
}
if (@getimagesize($upload['tmp_name']) === false) registration_response(422, 'Please choose a valid image file.');
$image = file_get_contents($upload['tmp_name']);
if ($image === false) registration_response(422, 'Unable to read your profile image. Please select it again.');

$db = null;
try {
    $db = scaims_db_pdo();
    $db->beginTransaction();
    $check = $db->prepare('SELECT COUNT(*) FROM user WHERE UName = ?');
    $check->execute([$data['username']]);
    if ($check->fetchColumn() > 0) {
        $db->rollBack();
        registration_response(409, 'Username already exists. Please choose a different username.');
    }
    $check = $db->prepare('SELECT COUNT(*) FROM farmer WHERE RSBSA_Number = ?');
    $check->execute([$data['rsbsaNumber']]);
    if ($check->fetchColumn() > 0) {
        $db->rollBack();
        registration_response(409, 'This RSBSA Number is already registered. Go back to Part 1 to check it.');
    }
    $insertUser = $db->prepare('INSERT INTO user (UName, UPass, UserRole, creationDate, ProfileImage) VALUES (?, ?, ?, NOW(), ?)');
    $insertUser->execute([$data['username'], password_hash($password, PASSWORD_BCRYPT), 'farmer', $image]);
    $userID = $db->lastInsertId();
    $insertFarmer = $db->prepare('INSERT INTO farmer (FirstName, Surname, MiddleName, Suffix, Sex, BirthDate, ContactNo, Address, Barangay, RSBSA_Number, registration_date, UserID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)');
    $insertFarmer->execute([$data['firstName'], $data['surname'], $data['middleName'], $data['extensionName'], $data['sex'], $data['dob'], $data['contactNumber'], $data['address'], $data['barangay'], $data['rsbsaNumber'], $userID]);
    $db->commit();
    registration_response(200, 'Account successfully created.', true);
} catch (Throwable $failure) {
    if ($db && $db->inTransaction()) $db->rollBack();
    error_log('Registration failed: ' . $failure->getMessage());
    registration_response(500, 'Unable to create your account. Your entries are still here; please try again.');
}
