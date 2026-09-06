<?php
$dashboardCurrentPage = $dashboardCurrentPage ?? '';
$dashboardProfileImageSrc = $dashboardProfileImageSrc ?? '../home/displayImage.php';
$dashboardIconBase = $dashboardIconBase ?? '../home/assets/nav-icons';
$dashboardDisplayName = trim(($_SESSION['FirstName'] ?? '') . ' ' . ($_SESSION['Surname'] ?? ''));
$dashboardUserName = $_SESSION['UName'] ?? '';

$dashboardNavItems = [
    [
        'key' => 'home',
        'href' => '../home/home.php',
        'icon' => 'home.png',
        'label' => 'Home',
    ],
    [
        'key' => 'parcel',
        'href' => '../parcel/farmersFP.php',
        'icon' => 'farm.png',
        'label' => 'Farm Parcel',
    ],
    [
        'key' => 'report',
        'href' => '../report/report.php',
        'icon' => 'report.png',
        'label' => 'Report Forms',
    ],
    [
        'key' => 'profile',
        'href' => '../profile/editProfile.php',
        'icon' => 'settings.png',
        'label' => 'Account Settings',
    ],
];
?>
<button class="menu-toggle" type="button" aria-label="Toggle navigation">
    <span></span>
    <span></span>
    <span></span>
</button>
<div class="sidebar-overlay"></div>
<aside class="sidebar">
    <div class="profile-section">
        <div class="profile-pic">
            <img src="<?php echo htmlspecialchars($dashboardProfileImageSrc); ?>" alt="Profile Image">
        </div>
        <h2><?php echo htmlspecialchars($dashboardDisplayName); ?></h2>
        <p>@<?php echo htmlspecialchars($dashboardUserName); ?></p>
    </div>
    <div class="language-switcher">
        <p class="language-switcher-label" data-i18n="language_label">Language / Wika</p>
        <div class="language-switcher-buttons">
            <button type="button" class="language-toggle-button active" data-language="en">English</button>
            <button type="button" class="language-toggle-button" data-language="tl">Tagalog</button>
        </div>
    </div>
    <nav>
        <ul>
            <?php foreach ($dashboardNavItems as $item): ?>
                <?php $isActive = $dashboardCurrentPage === $item['key']; ?>
                <li>
                    <a
                        href="<?php echo htmlspecialchars($item['href']); ?>"
                        class="menu-item<?php echo $isActive ? ' is-active' : ''; ?>"
                        <?php echo $isActive ? 'aria-current="page"' : ''; ?>
                    >
                        <img
                            src="<?php echo htmlspecialchars($dashboardIconBase . '/' . $item['icon']); ?>"
                            class="icon"
                            alt=""
                        >
                        <span data-i18n="nav_<?php echo htmlspecialchars($item['key']); ?>">
                            <?php echo htmlspecialchars($item['label']); ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
            <li>
                <a href="../shared/logout.php" class="menu-item">
                    <img src="<?php echo htmlspecialchars($dashboardIconBase . '/logout.png'); ?>" class="icon" alt="">
                    <span data-i18n="nav_logout">Logout</span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="sidebar-help">
        <p data-i18n="sidebar_tip">Tap a menu item to continue.</p>
    </div>
    <footer class="footer">
        <p data-i18n-html="footer_text">Lipa City Agriculture Office <br> &copy; 2024 All rights reserved</p>
    </footer>
</aside>
