<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Suggestions</title>
    <link rel="stylesheet" href="suggestions.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- Sidebar Section -->
        <?php include '../../shared/admin-sidebar.php'; ?>

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
                    <h2>NEW SUGGESTIONS</h2>
                </div>
                <div id="new-suggestions" class="suggestions-container">
                    <!-- New suggestions will be dynamically loaded here -->
                </div>
            </div>

            <!-- Previous Suggestions Section -->
            <div class="suggestions-section">
                <div class="section-header">
                    <div class="header-with-filter">
                        <h2>PREVIOUS SUGGESTIONS</h2>
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
