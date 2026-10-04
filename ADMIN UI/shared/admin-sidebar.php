<?php
require_once __DIR__ . '/../../shared/admin_auth.php';
scaims_session_start();

$activePage = $activePage ?? '';
$assetPath = $assetPath ?? '../../assets/';
$loginPath = $loginPath ?? '../../adminLogin/adminLogin.html';
$sharedPath = $sharedPath ?? '../../shared/';
$pagePrefix = strpos(str_replace('\\', '/', $assetPath), '../../../assets/') !== false ? '../../../' : '../../';
$currentScript = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$languageScriptVersion = @filemtime(__DIR__ . '/admin-dashboard-language.js') ?: time();
$i18nScriptVersion = @filemtime(dirname(__DIR__, 2) . '/shared/i18n.js') ?: time();
$shellScriptVersion = @filemtime(dirname(__DIR__, 2) . '/shared/dashboard-shell.js') ?: time();

if ($activePage === '') {
    if (strpos($currentScript, '/adminDashboard/home/') !== false) {
        $activePage = 'home';
    } elseif (strpos($currentScript, '/adminDashboard/user/') !== false) {
        $activePage = 'users';
    } elseif (strpos($currentScript, '/adminDashboard/parcel/') !== false) {
        $activePage = 'parcel';
    } elseif (strpos($currentScript, '/adminDashboard/report/') !== false) {
        $activePage = 'reports';
    } elseif (strpos($currentScript, '/adminDashboard/monitoring/') !== false) {
        $activePage = 'satellite';
    } elseif (strpos($currentScript, '/adminDashboard/crops/') !== false) {
        $activePage = 'crops';
    } elseif (strpos($currentScript, '/adminDashboard/post/') !== false) {
        $activePage = 'posts';
    } elseif (strpos($currentScript, '/adminDashboard/suggestions/') !== false) {
        $activePage = 'suggestions';
    }
}

$adminName = trim($_SESSION['AdminName'] ?? '');
if ($adminName === '') {
    $adminName = 'APGORRS Admin';
}

$navItems = [
    [
        'id' => 'home',
        'label' => 'Home',
        'translationKey' => 'nav_home',
        'icon' => 'home.png',
        'href' => $pagePrefix . 'adminDashboard/home/home.php',
    ],
    [
        'id' => 'users',
        'label' => 'User Management',
        'translationKey' => 'nav_users',
        'icon' => 'users.png',
        'href' => $pagePrefix . 'adminDashboard/user/userManagement.php',
    ],
    [
        'id' => 'parcel',
        'label' => 'Farm Parcel',
        'translationKey' => 'nav_parcel',
        'icon' => 'parcel.png',
        'href' => $pagePrefix . 'adminDashboard/parcel/farmParcel.php',
    ],
    [
        'id' => 'reports',
        'label' => 'Reports',
        'translationKey' => 'nav_reports',
        'icon' => 'reports.png',
        'href' => $pagePrefix . 'adminDashboard/report/reportAnalytics.php',
    ],
    [
        'id' => 'satellite',
        'label' => 'Satellite Monitoring',
        'translationKey' => 'nav_satellite',
        'icon' => 'lipa-loc.png',
        'href' => $pagePrefix . 'adminDashboard/monitoring/satelliteMonitoring.php',
    ],
];

if (scaims_is_super_admin()) {
    $navItems[] = [
        'id' => 'crops',
        'label' => 'Crop Management',
        'translationKey' => 'nav_crops',
        'icon' => 'crop.png',
        'href' => $pagePrefix . 'adminDashboard/crops/cropManagement.php',
    ];
}

$navItems = array_merge($navItems, [
    [
        'id' => 'posts',
        'label' => 'Post Updates',
        'translationKey' => 'nav_posts',
        'icon' => 'post.png',
        'href' => $pagePrefix . 'adminDashboard/post/post.php',
    ],
    [
        'id' => 'suggestions',
        'label' => 'Suggestions',
        'translationKey' => 'nav_suggestions',
        'icon' => 'suggestions.png',
        'href' => $pagePrefix . 'adminDashboard/suggestions/suggestions.php',
    ],
]);
?>

<link rel="stylesheet" href="<?php echo htmlspecialchars($sharedPath); ?>admin-mobile.css?v=<?php echo @filemtime(__DIR__ . '/admin-mobile.css') ?: time(); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars($sharedPath); ?>admin-sidebar.css?v=<?php echo @filemtime(__DIR__ . '/admin-sidebar.css') ?: time(); ?>">

<button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-controls="adminSidebar" aria-expanded="false" data-i18n-aria-label="toggle_navigation">
    <span></span>
    <span></span>
    <span></span>
</button>

<div class="sidebar-overlay" aria-hidden="true"></div>

<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-mark">
            <img src="<?php echo htmlspecialchars($assetPath . 'logo.png'); ?>" alt="APGORRS">
        </div>
        <div class="sidebar-brand-copy">
            <strong>APGORRS</strong>
            <span data-i18n="admin_portal">Admin Portal</span>
        </div>
    </div>

    <div class="profile-section">
        <div class="profile-pic">
            <img src="<?php echo htmlspecialchars($assetPath . 'admin.png'); ?>" alt="Admin profile">
        </div>
        <h2><?php echo htmlspecialchars($adminName); ?></h2>
        <div class="admin-badge">
            <span class="admin-badge-dot"></span>
            <span <?php echo scaims_is_super_admin() ? '' : 'data-i18n="admin_role"'; ?>>
                <?php echo htmlspecialchars(scaims_admin_role_label()); ?>
            </span>
        </div>
    </div>

    <div class="language-switcher">
        <p class="language-switcher-label" data-i18n="language_label">Language / Wika</p>
        <div class="language-switcher-buttons">
            <button type="button" class="language-toggle-button active" data-language="en">English</button>
            <button type="button" class="language-toggle-button" data-language="tl">Tagalog</button>
        </div>
    </div>

    <nav>
        <div class="nav-label" data-i18n="nav_section_main">Main Menu</div>
        <ul>
            <?php foreach ($navItems as $item): ?>
                <?php $isActive = $activePage === $item['id']; ?>
                <li>
                    <a
                        href="<?php echo htmlspecialchars($item['href']); ?>"
                        class="menu-item<?php echo $isActive ? ' is-active' : ''; ?>"
                        <?php echo $isActive ? 'aria-current="page"' : ''; ?>
                    >
                        <span class="icon-wrap">
                            <img
                                src="<?php echo htmlspecialchars($assetPath . $item['icon']); ?>"
                                class="icon"
                                alt=""
                            >
                        </span>
                        <span data-i18n="<?php echo htmlspecialchars($item['translationKey']); ?>">
                            <?php echo htmlspecialchars($item['label']); ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>

            <li>
                <a href="<?php echo htmlspecialchars($pagePrefix . 'adminLogin/logout.php'); ?>" class="menu-item">
                    <span class="icon-wrap">
                        <img src="<?php echo htmlspecialchars($assetPath . 'out.png'); ?>" class="icon" alt="">
                    </span>
                    <span data-i18n="nav_logout">Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-help">
        <p data-i18n="sidebar_tip">Open a section to continue managing records and services.</p>
    </div>

    <footer class="footer">
        <p data-i18n-html="footer_text">Lipa City Agriculture Office <br> &copy; 2024 All rights reserved</p>
    </footer>
</aside>

<script src="<?php echo htmlspecialchars($pagePrefix); ?>../shared/i18n.js?v=<?php echo $i18nScriptVersion; ?>"></script>
<script src="<?php echo htmlspecialchars($sharedPath); ?>admin-dashboard-language.js?v=<?php echo $languageScriptVersion; ?>"></script>
<script src="<?php echo htmlspecialchars($pagePrefix); ?>../shared/dashboard-shell.js?v=<?php echo $shellScriptVersion; ?>"></script>
