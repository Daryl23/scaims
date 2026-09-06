<?php
require_once __DIR__ . '/../shared/admin_auth.php';
scaims_require_admin('../ADMIN UI/adminLogin/adminLogin.html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>APGORRS Suggestions</title>
    <link rel="stylesheet" href="suggestions.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- Sidebar Section -->
        <div class="sidebar">
            <div class="profile-section">
                <div class="profile-pic">
                    <img src="../../assets/admin.png" alt="Profile Image">
                </div>
                <h2>APGORRS Admin</h2>
            </div>
            <nav>
                <ul>
                    <li><a href="../home/home.php"><img src="../../assets/home.png" class="icon"> Home</a></li>
                    <li><a href="../user/userManagement.php"><img src="../../assets/users.png" class="icon"> User Management</a></li>
                    <li><a href="../parcel/farmParcel.html"><img src="../../assets/parcel.png" class="icon"> Farm Parcel</a></li>
                    <li><a href="../report/reportAnalytics.php"><img src="../../assets/reports.png" class="icon"> Reports</a></li>
                    <li><a href="../post/post.php"><img src="../../assets/post.png" class="icon"> Post Updates</a></li>
                    <li><a href="../suggestions/suggestions.php"><img src="../../assets/suggestions.png" class="icon"> Suggestions</a></li>
                    <li><a href="../../adminLogin/adminLogin.html"><img src="../../assets/out.png" class="icon"> Logout</a></li>
                </ul>
            </nav>
            <footer>
                <p>Lipa City Agriculture Office <br> &copy; 2024 All rights reserved</p>
            </footer>
        </div>

        <!-- Main Content -->
        <div class="content">
            <div class="header-actions">
                <div class="suggestion-count">
                    <span id="new-count">0 New</span> | <span id="previous-count">0 Previous</span> Suggestions
                </div>
                <button id="export-button">Export Suggestions</button>
            </div>

            <!-- New Suggestions Section -->
            <div class="suggestions-section">
                <div class="section-header">
                    <h2>New Suggestions</h2>
                </div>
                <div id="new-suggestions" class="suggestions-container">
                    <!-- New suggestions will be dynamically loaded here -->
                </div>
            </div>

            <!-- Previous Suggestions Section -->
            <div class="suggestions-section">
                <div class="section-header">
                    <div class="header-with-filter">
                        <h2>Previous Suggestions</h2>
                        <input type="month" id="month-filter" name="month-filter">
                    </div>
                </div>
                <div id="previous-suggestions" class="suggestions-container">
                    <!-- Previous suggestions will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="suggestions.js"></script>
</body>
</html> 