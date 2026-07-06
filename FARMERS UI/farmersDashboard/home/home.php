<?php
session_start();
if (!isset($_SESSION['UName'])) {
    header("Location: ../../farmerLogin/farmerlogin.html");
    exit();
}

$dashboardCurrentPage = 'home';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmers Dashboard</title>
    <link rel="stylesheet" href="../shared/dashboard-shell.css">
    <link rel="stylesheet" href="home.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <?php include '../shared/sidebar.php'; ?>

        <div class="main-content">
            <h1 data-i18n="home_title">LATEST UPDATES</h1>
            <div class="dashboard-help-note">
                <p data-i18n="home_help">Check the newest announcements here before opening another menu.</p>
            </div>
            <div id="posts-container"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../shared/dashboard-language.js"></script>
    <script src="../shared/dashboard-shell.js"></script>
    
    <script>
    $(document).ready(function() {
        var currentPosts = [];

        function renderPosts(posts) {
            var postsHtml = '';

            if (!posts.length) {
                postsHtml = '<div class="post"><div class="post-content">' + window.dashboardI18n.t('no_updates') + '</div></div>';
            }

            posts.forEach(function(post) {
                postsHtml += '<div class="post">';
                postsHtml += '<div class="post-content">' + post.content.replace(/\n/g, '<br>') + '</div>';
                
                if (post.image_paths && post.image_paths.length > 0) {
                    postsHtml += '<div class="post-images">';
                    post.image_paths.forEach(function(imagePath) {
                        postsHtml += '<img src="../../../' + imagePath + '" alt="Post image">';
                    });
                    postsHtml += '</div>';
                }
                
                postsHtml += '<p class="post-date">' + window.dashboardI18n.t('posted_on') + ' ' + post.created_at + '</p>';
                postsHtml += '</div>';
            });

            $('#posts-container').html(postsHtml);
        }

        function loadPosts() {
            $.ajax({
                url: 'fetch_posts.php',
                type: 'GET',
                dataType: 'json',
                success: function(posts) {
                    currentPosts = Array.isArray(posts) ? posts : [];
                    renderPosts(currentPosts);
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching posts:", error);
                    console.log(xhr.responseText);
                }
            });
        }

        loadPosts();
        document.addEventListener('dashboard:language-changed', function() {
            renderPosts(currentPosts);
        });

    });
    </script>
</body>
</html>
