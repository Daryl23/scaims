<?php
session_start();
if (!isset($_SESSION['UName'])) {
    header("Location: ../../farmerLogin/farmerlogin.html");
    exit();
}

require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

// Fetch user data
$uname = $_SESSION['UName'];
$sql = "SELECT f.*, u.ProfileImage, u.UName 
        FROM farmer f 
        JOIN user u ON f.UserID = u.UserID 
        WHERE u.UName = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $uname);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

$dashboardCurrentPage = 'profile';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmers Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../shared/dashboard-shell.css">
    <link rel="stylesheet" href="editProfile.css">
    <link rel="stylesheet" href="../../../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../shared/farmer-mobile.css?v=<?php echo @filemtime(__DIR__ . '/../shared/farmer-mobile.css') ?: time(); ?>">
    <link rel="stylesheet" href="../../../shared/choice-cards.css?v=20260908">
    <script src="../../../shared/choice-cards.js?v=20260908" defer></script>
</head>
<body>
    <div class="container">
        <?php include '../shared/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <header class="header"> 
                <h1 data-i18n="profile_title">EDIT PROFILE</h1> 
            </header>
            <div class="dashboard-help-note">
                <p data-i18n="profile_help">Update your account details here. Only change the fields you need.</p>
            </div>
            <div class="edit-profile-container">
                <form id="editProfileForm" enctype="multipart/form-data">
                    <div class="profile-image-section">
                        <img id="profilePreview" src="data:image/jpeg;base64,<?php echo base64_encode($userData['ProfileImage']); ?>" alt="Profile Picture">
                        <div class="file-input-container">
                            <label for="profileImage" class="file-input-label">Choose Profile Image</label>
                            <input type="file" id="profileImage" name="profileImage" accept="image/*">
                        </div>
                    </div>

                    <div class="account-section">
                        <h3>Account Information</h3>
                        <div class="flex-container-2">
                            <div>
                                <label for="username">Username*</label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($userData['UName']); ?>" required>
                            </div>
                            <div>
                                <label for="currentPassword">Current Password*</label>
                                <div class="password-field">
                                    <input type="password" id="currentPassword" name="currentPassword" autocomplete="current-password" placeholder="Enter your current password">
                                    <span onclick="togglePasswordVisibility('currentPassword', 'currentPasswordIcon')" class="show-password-icon">
                                        <i id="currentPasswordIcon" class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex-container-2">
                            <div>
                                <label for="newPassword">New Password</label>
                                <div class="password-field">
                                    <input type="password" id="newPassword" name="newPassword">
                                    <span onclick="togglePasswordVisibility('newPassword', 'newPasswordIcon')" class="show-password-icon">
                                        <i id="newPasswordIcon" class="bi bi-eye"></i>
                                    </span>
                                </div>
                                <small class="password-hint">Leave blank if you don't want to change password</small>
                            </div>
                            <div>
                                <label for="confirmPassword">Confirm New Password</label>
                                <div class="password-field">
                                    <input type="password" id="confirmPassword" name="confirmPassword">
                                    <span onclick="togglePasswordVisibility('confirmPassword', 'confirmPasswordIcon')" class="show-password-icon">
                                        <i id="confirmPasswordIcon" class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr><br>
                    <div class="flex-container-2">
                        <div>
                            <label for="surname">Surname*</label>
                            <input type="text" id="surname" name="surname" value="<?php echo htmlspecialchars($userData['Surname']); ?>" required>
                        </div>
                        <div>
                            <label for="firstName">First Name*</label>
                            <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($userData['FirstName']); ?>" required>
                        </div>
                    </div>

                    <div class="flex-container-2">
                        <div>
                            <label for="middleName">Middle Name</label>
                            <input type="text" id="middleName" name="middleName" value="<?php echo htmlspecialchars($userData['MiddleName']); ?>">
                        </div>
                        <div>
                            <label for="extensionName">Extension Name</label>
                            <input type="text" id="extensionName" name="extensionName" value="<?php echo htmlspecialchars($userData['Suffix']); ?>">
                        </div>
                    </div>

                    <div class="flex-container">
                        <div>
                            <label for="dob">Date of Birth*</label>
                            <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($userData['BirthDate']); ?>" required>
                        </div>
                        <div>
                            <label for="sex">Sex*</label>
                            <select id="sex" name="sex" data-choice-cards required>
                                <option value="Male" <?php echo ($userData['Sex'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($userData['Sex'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div>
                            <label for="contactNumber">Contact Number*</label>
                            <input type="text" id="contactNumber" name="contactNumber" value="<?php echo htmlspecialchars($userData['ContactNo']); ?>" required>
                        </div>
                    </div>

                    <div class="flex-container-1">
                        <div>
                            <label for="address">Address*</label>
                            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($userData['Address']); ?>" required>
                        </div>

                        <div>
                            <label for="barangay">Barangay*</label>
                            <input type="text" id="barangay" name="barangay" value="<?php echo htmlspecialchars($userData['Barangay']); ?>" required>
                        </div>

                        <div>
                            <label for="rsbsaNumber">RSBSA Number (if registered)</label>
                            <input type="text" id="rsbsaNumber" name="rsbsaNumber" value="<?php echo htmlspecialchars($userData['RSBSA_Number']); ?>" placeholder="Kung mayroon ay maglagay, kung wala ay iwanang blanko">
                        </div>
                    </div>
                    
                    <hr>
                    <div class="button-container">
                        <button type="submit" class="fm-action"><i class="bi bi-save"></i>Save Changes</button>
                        <button type="button">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="../shared/dashboard-language.js"></script>
    <script src="../shared/dashboard-shell.js"></script>
    <script src="editProfile.js"></script>
</body>
</html>
