<?php
session_start();
if (!isset($_SESSION['UName'])) {
    header("Location: ../../farmerLogin/farmerlogin.html");
    exit();
}

$dashboardCurrentPage = 'home';
$homeCssVersion = @filemtime(__DIR__ . '/home.css') ?: time();
$sharedImageCssVersion = @filemtime(dirname(__DIR__, 3) . '/shared/report-image-capture.css') ?: time();
$sharedImageJsVersion = @filemtime(dirname(__DIR__, 3) . '/shared/report-image-capture.js') ?: time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmers Dashboard</title>
    <link rel="stylesheet" href="../shared/dashboard-shell.css">
    <link rel="stylesheet" href="home.css?v=<?php echo $homeCssVersion; ?>">
    <link rel="stylesheet" href="../../../shared/report-image-capture.css?v=<?php echo $sharedImageCssVersion; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../shared/farmer-mobile.css?v=<?php echo @filemtime(__DIR__ . '/../shared/farmer-mobile.css') ?: time(); ?>">
</head>
<body>
    <div class="container">
        <?php include '../shared/sidebar.php'; ?>

        <div class="main-content">
            <!-- Quick actions: the four things a farmer opens the dashboard to do.
                 Icons are the existing nav artwork, so the tiles read the same
                 as the sidebar items they lead to. -->
            <h1 class="fm-launcher-title" data-i18n="home_quick_title">What would you like to do?</h1>
            <nav class="fm-launcher" aria-label="Quick actions">
                <a class="fm-tile" href="../report/report.php">
                    <img src="assets/nav-icons/report.png" alt="">
                    <span data-i18n="nav_report">Report Forms</span>
                </a>
                <a class="fm-tile" href="../parcel/farmersFP.php">
                    <img src="assets/nav-icons/farm.png" alt="">
                    <span data-i18n="nav_parcel">Farm Parcel</span>
                </a>
                <a class="fm-tile" href="../../farmerLanding/map.html">
                    <img src="assets/nav-icons/location.png" alt="">
                    <span data-i18n="nav_map">Crop Map</span>
                </a>
                <a class="fm-tile" href="../profile/editProfile.php">
                    <img src="assets/nav-icons/settings.png" alt="">
                    <span data-i18n="nav_profile">Account Settings</span>
                </a>
            </nav>
            <section class="home-report-image-panel" aria-labelledby="home-report-image-title">
                <div class="home-report-image-heading">
                    <h2 id="home-report-image-title">Capture / Upload Crop Image</h2>
                </div>
                <div
                    data-report-image-capture
                    data-input-name="home-report-image"
                    data-label="Larawan ng Pananim"
                    data-capture-label="Take Photo / Capture Image"
                    data-upload-label="Choose from Gallery / Upload Image"
                    data-replace-label="Replace image"
                    data-remove-label="Remove image"
                ></div>
                <div id="home-report-type-panel" class="home-report-type-panel" aria-labelledby="home-report-type-title" hidden>
                    <h3 id="home-report-type-title" data-i18n="home_report_choose">Uri ng Ulat</h3>
                    <div class="home-report-type-actions">
                        <button type="button" data-home-report-type="crop">
                            <i class="bi bi-basket2" aria-hidden="true"></i>
                            <span data-i18n="home_report_crop">Crop Harvest Report</span>
                        </button>
                        <button type="button" data-home-report-type="damage">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            <span data-i18n="home_report_damage">Crop Damage Report</span>
                        </button>
                    </div>
                    <div id="home-report-upload-status" class="home-report-upload-status" role="alert" aria-live="polite"></div>
                </div>
            </section>
            <section class="fm-updates" aria-labelledby="home-updates-title">
                <h2 class="fm-section-title" id="home-updates-title">
                    <i class="bi bi-broadcast" aria-hidden="true"></i>
                    <span data-i18n="home_title">LATEST UPDATES</span>
                </h2>
                <div class="dashboard-help-note">
                    <p data-i18n="home_help">Check the newest announcements here before opening another menu.</p>
                </div>
                <div id="posts-container"></div>
            </section>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../shared/dashboard-language.js"></script>
    <script src="../shared/dashboard-shell.js"></script>
    <script src="../../../shared/report-image-capture.js?v=<?php echo $sharedImageJsVersion; ?>"></script>
    
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

    document.addEventListener('DOMContentLoaded', function () {
        const imagePanel = document.querySelector('.home-report-image-panel [data-report-image-capture]');
        const typePanel = document.getElementById('home-report-type-panel');
        const status = document.getElementById('home-report-upload-status');
        const typeButtons = document.querySelectorAll('[data-home-report-type]');

        if (!imagePanel || !typePanel || !status) {
            return;
        }

        function setHomeUploading(isUploading) {
            typeButtons.forEach(function (button) {
                button.disabled = isUploading;
            });
            imagePanel.querySelectorAll('button').forEach(function (button) {
                button.disabled = isUploading;
            });
        }

        function setStatus(message, isError) {
            status.textContent = message || '';
            status.classList.toggle('is-error', Boolean(isError && message));
        }

        imagePanel.addEventListener('scaims:report-image-changed', function (event) {
            typePanel.hidden = !event.detail.hasImage;
            if (!event.detail.hasImage) {
                setStatus('', false);
            }
        });

        typeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const selectedType = button.dataset.homeReportType;
                const fileInput = imagePanel.querySelector('[data-report-image-input]');
                const file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;

                if (!file) {
                    setStatus('Please capture or choose an image first.', true);
                    return;
                }

                const formData = new FormData();
                formData.append('report_image', file);

                setHomeUploading(true);
                setStatus('Uploading image...', false);

                fetch('../../../shared/report_image_stage.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok || !data.success) {
                                throw new Error(data.message || 'Upload failed. Please try again.');
                            }
                            return data;
                        });
                    })
                    .then(function (data) {
                        const params = new URLSearchParams({
                            report_type: selectedType,
                            image_token: data.token
                        });
                        window.location.href = '../report/report.php?' + params.toString();
                    })
                    .catch(function (error) {
                        setStatus(error.message || 'Network interruption. Please try uploading again.', true);
                        setHomeUploading(false);
                    });
            });
        });
    });
    </script>
</body>
</html>
