<?php
/**
 * sidebar.php — Shared Admin Sidebar
 * 
 * Usage:  <?php include 'PATH_TO/sidebar.php'; ?>
 * 
 * Each page passes $activePage before including, e.g.:
 *   $activePage = 'home';        // home.php
 *   $activePage = 'users';       // userManagement.php
 *   $activePage = 'parcel';      // farmParcel.html
 *   $activePage = 'reports';     // reportAnalytics.php
 *   $activePage = 'posts';       // post.php
 *   $activePage = 'suggestions'; // suggestions.php
 *
 * Also pass $assetPath (relative path to /assets/) so icons load correctly
 * regardless of how deep the current page is:
 *   $assetPath = '../../assets/';    // 2 levels deep
 *   $assetPath = '../../../assets/'; // 3 levels deep (crop/damage sub-pages)
 *
 * And $loginPath (path back to adminLogin.html):
 *   $loginPath = '../../adminLogin/adminLogin.html';
 *   $loginPath = '../../../adminLogin/adminLogin.html';
 */

$activePage ??= '';
$assetPath  ??= '../../assets/';
$loginPath  ??= '../../adminLogin/adminLogin.html';

/* Nav items definition — edit once, applies everywhere */
$navItems = [
    [
        'id'    => 'home',
        'label' => 'Home',
        'icon'  => 'home.png',
        'href'  => ($assetPath === '../../../assets/' ? '../../../' : '../../') . 'adminDashboard/home/home.php',
    ],
    [
        'id'    => 'users',
        'label' => 'User Management',
        'icon'  => 'users.png',
        'href'  => ($assetPath === '../../../assets/' ? '../../../' : '../../') . 'adminDashboard/user/userManagement.php',
    ],
    [
        'id'    => 'parcel',
        'label' => 'Farm Parcel',
        'icon'  => 'parcel.png',
        'href'  => ($assetPath === '../../../assets/' ? '../../../' : '../../') . 'adminDashboard/parcel/farmParcel.php',
    ],
    [
        'id'    => 'reports',
        'label' => 'Reports',
        'icon'  => 'reports.png',
        'href'  => ($assetPath === '../../../assets/' ? '../../../' : '../../') . 'adminDashboard/report/reportAnalytics.php',
    ],
    [
        'id'    => 'posts',
        'label' => 'Post Updates',
        'icon'  => 'post.png',
        'href'  => ($assetPath === '../../../assets/' ? '../../../' : '../../') . 'adminDashboard/post/post.php',
    ],
    [
        'id'    => 'suggestions',
        'label' => 'Suggestions',
        'icon'  => 'suggestions.png',
        'href'  => ($assetPath === '../../../assets/' ? '../../../' : '../../') . 'adminDashboard/suggestions/suggestions.php',
    ],
];
?>

<style>
/* ── Sidebar design tokens & styles ───────────────────── */
:root {
  --sb-olive:        #3b4918;
  --sb-olive-light:  #576f1b;
  --sb-olive-dark:   #2a3311;
  --sb-harvest:      #f7931e;
  --sb-gold:         #c8bd44;
  --sb-gold-light:   #ccc14f;
  --sb-cream:        #fffcef;
  --sb-width:        260px;
}

.admin-sidebar {
  width: var(--sb-width);
  min-width: var(--sb-width);
  background: linear-gradient(180deg, var(--sb-olive-dark) 0%, #1e2a0d 100%);
  display: flex;
  flex-direction: column;
  height: 100vh;
  position: sticky;
  top: 0;
  overflow-y: auto;
  border-right: 1px solid rgba(200,189,68,0.12);
  font-family: 'Inter', 'Poppins', system-ui, sans-serif;
  flex-shrink: 0;
}

/* Scrollbar inside sidebar */
.admin-sidebar::-webkit-scrollbar { width: 4px; }
.admin-sidebar::-webkit-scrollbar-track { background: transparent; }
.admin-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 4px; }

/* Logo strip */
.sb-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 18px 18px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.07);
  text-decoration: none;
}
.sb-logo-icon {
  width: 34px; height: 34px;
  background: rgba(255,255,255,0.09);
  border-radius: 8px;
  border: 1px solid rgba(200,189,68,0.22);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.sb-logo-icon svg { width: 17px; height: 17px; color: #fffcef; }
.sb-logo-text strong {
  display: block;
  font-size: 14px; font-weight: 700;
  color: #fffcef; letter-spacing: 0.02em; line-height: 1.1;
}
.sb-logo-text span {
  font-size: 10px; color: rgba(255,255,255,0.38); font-weight: 400;
}

/* Profile */
.sb-profile {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20px 16px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.07);
}
.sb-avatar {
  width: 72px; height: 72px;
  border-radius: 50%;
  background: rgba(200,189,68,0.12);
  border: 2px solid rgba(200,189,68,0.28);
  overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 10px;
}
.sb-avatar img { width: 100%; height: 100%; object-fit: cover; }
.sb-profile h2 {
  font-size: 13px; font-weight: 600;
  color: #fffcef; text-align: center; margin: 0 0 6px;
}
.sb-badge {
  display: inline-flex; align-items: center; gap: 4px;
  background: rgba(247,147,30,0.14);
  border: 1px solid rgba(247,147,30,0.28);
  border-radius: 999px;
  padding: 2px 9px;
  font-size: 9px; font-weight: 700;
  color: var(--sb-harvest);
  letter-spacing: 0.08em; text-transform: uppercase;
}
.sb-badge svg { width: 7px; height: 7px; }

/* Nav */
.sb-nav { flex: 1; padding: 10px 10px 0; }
.sb-nav-label {
  font-size: 9px; font-weight: 700;
  color: rgba(255,255,255,0.22);
  letter-spacing: 0.12em; text-transform: uppercase;
  padding: 8px 8px 5px;
}
.sb-item {
  display: flex; align-items: center; gap: 9px;
  padding: 8px 9px;
  border-radius: 8px;
  font-size: 13px; font-weight: 500;
  color: rgba(255,255,255,0.60);
  text-decoration: none;
  transition: background 0.18s, color 0.18s;
  margin-bottom: 1px;
  border: 1px solid transparent;
}
.sb-item:hover {
  background: rgba(255,255,255,0.07);
  color: #fffcef;
}
.sb-item.active {
  background: rgba(200,189,68,0.13);
  color: var(--sb-gold-light);
  border-color: rgba(200,189,68,0.18);
}
.sb-icon {
  width: 30px; height: 30px;
  border-radius: 7px;
  background: rgba(255,255,255,0.06);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: background 0.18s;
}
.sb-item.active .sb-icon { background: rgba(200,189,68,0.18); }
.sb-item:hover  .sb-icon { background: rgba(255,255,255,0.10); }
.sb-icon img {
  width: 15px; height: 15px;
  filter: brightness(0) invert(1);
  opacity: 0.65;
  transition: opacity 0.18s;
}
.sb-item.active .sb-icon img,
.sb-item:hover  .sb-icon img { opacity: 1; }

/* Logout button uses svg inline instead of img */
.sb-icon-svg { color: rgba(255,255,255,0.65); }
.sb-item:hover  .sb-icon-svg,
.sb-item.active .sb-icon-svg { color: #fff; }

.sb-divider { height: 1px; background: rgba(255,255,255,0.06); margin: 8px 8px; }

/* Footer */
.sb-footer {
  padding: 12px 16px;
  border-top: 1px solid rgba(255,255,255,0.06);
  font-size: 9px;
  color: rgba(255,255,255,0.22);
  text-align: center;
  line-height: 1.7;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .admin-sidebar {
    position: fixed;
    left: 0; top: 0; z-index: 500;
    transform: translateX(-100%);
    transition: transform 0.28s ease;
  }
  .admin-sidebar.sb-open { transform: translateX(0); }
}
</style>

<aside class="admin-sidebar" id="adminSidebar">

  <!-- Logo -->
  <a href="<?= $navItems[0]['href'] ?>" class="sb-logo">
    <div class="sb-logo-icon">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
      </svg>
    </div>
    <div class="sb-logo-text">
      <strong>APGORRS</strong>
      <span>Admin Portal</span>
    </div>
  </a>

  <!-- Profile -->
  <div class="sb-profile">
    <div class="sb-avatar">
      <img src="<?= $assetPath ?>admin.png" alt="Admin"
        onerror="this.parentElement.innerHTML='<svg viewBox=\'0 0 24 24\' fill=\'rgba(255,255,255,0.5)\' width=\'40\' height=\'40\'><path d=\'M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z\'/></svg>'">
    </div>
    <h2>APGORRS Admin</h2>
    <div class="sb-badge">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
      </svg>
      Administrator
    </div>
  </div>

  <!-- Nav -->
  <nav class="sb-nav">
    <div class="sb-nav-label">Main Menu</div>

    <?php foreach ($navItems as $item): ?>
    <a href="<?= htmlspecialchars($item['href']) ?>"
       class="sb-item<?= $activePage === $item['id'] ? ' active' : '' ?>">
      <span class="sb-icon">
        <img src="<?= $assetPath ?><?= $item['icon'] ?>" alt="<?= $item['label'] ?>">
      </span>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>

    <div class="sb-divider"></div>
    <div class="sb-nav-label">Account</div>

    <a href="<?= htmlspecialchars($loginPath) ?>" class="sb-item">
      <span class="sb-icon">
        <svg class="sb-icon-svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
      </span>
      Logout
    </a>
  </nav>

  <footer class="sb-footer">
    Lipa City Agriculture Office<br>
    &copy; 2024 All rights reserved
  </footer>

</aside>