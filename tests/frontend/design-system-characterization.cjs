const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..', '..');
const read = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8');

const tokens = read('shared/design-tokens.css');
const components = read('shared/components.css');
const rootStyles = read('style.css');
const adminSidebar = read('ADMIN UI/shared/admin-sidebar.php');
const adminSidebarStyles = read('ADMIN UI/shared/admin-sidebar.css');
const dashboardShell = read('shared/dashboard-shell.js');
const farmerMobile = read('FARMERS UI/farmersDashboard/shared/farmer-mobile.css');
const registrationSteps = read('FARMERS UI/registration/registration-steps.css');
const reportView = read('shared/admin-report-view/report-view.css');
const reportImage = read('shared/report-image-capture.css');
const harvestPage = read('ADMIN UI/adminDashboard/report/crop/cropReports.php');
const damagePage = read('ADMIN UI/adminDashboard/report/damage/damageReports.php');

for (const contract of [
    '--color-olive: #3b4918',
    '--color-olive-light: #576f1b',
    '--color-brown: #674636',
    '--color-primary: var(--color-olive)',
    '--color-background: var(--color-cream)',
    '--color-surface: var(--color-white)',
    '--color-accent: var(--color-harvest)',
    '--color-success: #198754',
    '--color-cream: #fffcef',
    '--space-4: 1rem',
    '--radius-md: 10px',
    '--transition-fast: 150ms ease'
]) {
    assert.ok(tokens.includes(contract), `Missing design token: ${contract}`);
}

for (const selector of [
    '.scaims-button',
    '.scaims-card',
    '.scaims-field',
    '.cropreport-table',
    '.damagereport-table',
    '.pagination button'
]) {
    assert.ok(components.includes(selector), `Missing shared component contract: ${selector}`);
}

assert.match(rootStyles, /^@import url\("shared\/design-tokens\.css"\);/);
assert.ok(!rootStyles.includes('--color-olive: #3b4918'), 'Root stylesheet must consume the canonical tokens');

assert.ok(adminSidebar.includes('admin-sidebar.css'));
assert.ok(!adminSidebar.includes('<style>'), 'Active Admin sidebar must not embed its reusable CSS');
assert.match(adminSidebarStyles, /^@import url\("\.\.\/\.\.\/shared\/design-tokens\.css"\);/);
assert.ok(adminSidebarStyles.includes('.sidebar'));
assert.ok(adminSidebarStyles.includes('@media (max-width: 768px)'));
assert.match(adminSidebarStyles, /\.sidebar\s*\{[\s\S]*?pointer-events:\s*auto;[\s\S]*?z-index:\s*1100;/);
assert.match(adminSidebarStyles, /\.sidebar-overlay\s*\{[\s\S]*?pointer-events:\s*none;[\s\S]*?z-index:\s*1090;/);
assert.match(adminSidebarStyles, /\.sidebar-overlay\.active\s*\{[\s\S]*?pointer-events:\s*auto;/);
assert.match(adminSidebarStyles, /\.menu-toggle\s*\{[\s\S]*?z-index:\s*1110;/);
assert.ok(adminSidebar.includes('aria-controls="adminSidebar"'));
assert.ok(adminSidebar.includes('aria-expanded="false"'));
assert.ok(adminSidebar.includes('<aside class="sidebar" id="adminSidebar">'));
assert.ok(dashboardShell.includes("setAttribute('aria-expanded', 'true')"));
assert.ok(dashboardShell.includes("event.key === 'Escape'"));

for (const stylesheet of [adminSidebarStyles, farmerMobile, registrationSteps]) {
    assert.ok(stylesheet.includes('var(--color-primary'), 'Active portal CSS must consume the homepage-derived primary role');
    assert.ok(stylesheet.includes('var(--color-'), 'Active portal CSS must consume canonical design tokens');
}

assert.match(reportView, /^@import url\("\.\.\/design-tokens\.css"\);\s+@import url\("\.\.\/components\.css"\);/);
for (const page of [harvestPage, damagePage]) {
    assert.ok(!page.includes('<style>'), 'Admin report page shell CSS must remain shared');
    assert.ok(page.includes('admin-report-view/report-view.css'));
}

assert.match(reportImage, /\.report-image-preview-wrap img,[\s\S]*?\.report-image-detail img[\s\S]*?max-height:\s*min\(420px,\s*60vh\);/);
assert.match(reportImage, /max-width:\s*100%;[\s\S]*?object-fit:\s*contain;[\s\S]*?width:\s*auto;[\s\S]*?margin-inline:\s*auto;/);
assert.match(reportImage, /object-fit:\s*contain;/);

console.log('PASS: homepage-derived tokens, shared components, responsive portal styles, sidebar interaction layers, and report-page CSS contracts.');
