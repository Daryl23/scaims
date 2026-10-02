const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { Phase1Harness } = require('../helpers/phase1-harness.cjs');

const harness = new Phase1Harness('design-system', 8165);
let browser;

async function login(context, path, form) {
    const response = await context.request.post(harness.base + path, { form, maxRedirects: 0 });
    assert.equal(response.status(), 302, `${path} login status`);
}

async function checkPages(context, paths, portal) {
    for (const pagePath of paths) {
        const page = await context.newPage();
        const cssFailures = [];
        const pageErrors = [];
        page.on('pageerror', error => pageErrors.push(error.message));
        page.on('response', response => {
            if (response.request().resourceType() === 'stylesheet'
                && new URL(response.url()).origin === new URL(harness.base).origin
                && !response.ok()) {
                cssFailures.push(`${response.status()} ${response.url()}`);
            }
        });

        await page.goto(harness.base + pagePath, { waitUntil: 'domcontentloaded' });
        assert.equal(await page.locator('body').isVisible(), true, `${pagePath} body`);

        for (const width of [1366, 1024, 768, 430, 390]) {
            await page.setViewportSize({ width, height: width <= 430 ? 844 : 900 });
            await page.waitForTimeout(75);
            const layout = await page.evaluate(() => ({
                viewport: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                bodyWidth: document.body.scrollWidth,
                bodyOverflowX: getComputedStyle(document.body).overflowX,
                hasHorizontalOverflow: document.documentElement.scrollWidth > innerWidth + 1
                    || (document.body.scrollWidth > innerWidth + 1
                        && !['hidden', 'clip'].includes(getComputedStyle(document.body).overflowX)),
            }));
            assert.equal(layout.viewport, width);
            assert.equal(
                layout.hasHorizontalOverflow,
                false,
                `${portal} overflow at ${width}px on ${pagePath}: ${JSON.stringify(layout)}`
            );
        }

        assert.deepEqual(cssFailures, [], cssFailures.join('\n'));
        assert.deepEqual(pageErrors, [], `${pagePath}\n${pageErrors.join('\n')}`);
        await page.close();
    }
}

async function checkAdminMobileSidebar(context) {
    const page = await context.newPage();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(harness.base + '/ADMIN%20UI/adminDashboard/home/home.php', { waitUntil: 'domcontentloaded' });

    const toggle = page.locator('.menu-toggle');
    const sidebar = page.locator('#adminSidebar');
    const overlay = page.locator('.sidebar-overlay');
    assert.equal(await toggle.isVisible(), true, 'Admin mobile menu button is visible');

    const toggleBox = await toggle.boundingBox();
    assert.ok(toggleBox && toggleBox.width >= 44 && toggleBox.height >= 44, 'Admin menu button has a usable touch target');
    assert.ok(toggleBox.x >= 0 && toggleBox.y >= 0 && toggleBox.x + toggleBox.width <= 390, 'Admin menu button stays inside the viewport');

    await toggle.click();
    await page.waitForTimeout(350);
    assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
    assert.equal(await sidebar.evaluate(element => element.classList.contains('active')), true);

    const layers = await page.evaluate(() => {
        const drawer = document.querySelector('#adminSidebar');
        const backdrop = document.querySelector('.sidebar-overlay');
        const link = drawer.querySelector('a[href*="userManagement.php"]');
        const box = link.getBoundingClientRect();
        const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
        return {
            sidebarZ: Number(getComputedStyle(drawer).zIndex),
            overlayZ: Number(getComputedStyle(backdrop).zIndex),
            sidebarPointerEvents: getComputedStyle(drawer).pointerEvents,
            overlayPointerEvents: getComputedStyle(backdrop).pointerEvents,
            hitHref: hit && hit.closest('a') ? hit.closest('a').getAttribute('href') : null,
        };
    });
    assert.ok(layers.sidebarZ > layers.overlayZ, `Sidebar must sit above backdrop: ${JSON.stringify(layers)}`);
    assert.equal(layers.sidebarPointerEvents, 'auto');
    assert.equal(layers.overlayPointerEvents, 'auto');
    assert.match(layers.hitHref || '', /userManagement\.php$/, 'Backdrop must not intercept sidebar links');

    await Promise.all([
        page.waitForURL(/userManagement\.php/),
        page.locator('a[href*="userManagement.php"]').click(),
    ]);

    await page.goto(harness.base + '/ADMIN%20UI/adminDashboard/home/home.php', { waitUntil: 'domcontentloaded' });
    await page.locator('.menu-toggle').click();
    await page.waitForTimeout(350);
    await page.locator('.menu-toggle').click();
    await page.waitForTimeout(350);
    assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'), 'false');
    assert.equal(await page.locator('#adminSidebar').evaluate(element => element.classList.contains('active')), false);
    assert.equal(await page.locator('.sidebar-overlay').evaluate(element => getComputedStyle(element).pointerEvents), 'none');

    await page.locator('.menu-toggle').click();
    await page.waitForTimeout(350);
    await page.locator('.sidebar-overlay').click({ position: { x: 385, y: 420 } });
    await page.waitForTimeout(350);
    assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'), 'false');
    assert.equal(await page.locator('.menu-toggle').isEnabled(), true, 'Page controls remain interactive after closing the drawer');

    await page.setViewportSize({ width: 1366, height: 900 });
    await page.waitForTimeout(100);
    assert.equal(await page.locator('.menu-toggle').isVisible(), false, 'Desktop keeps the persistent sidebar without the mobile toggle');
    assert.equal(await page.locator('#adminSidebar').isVisible(), true, 'Desktop sidebar remains visible');
    await page.close();
}

(async () => {
    try {
        await harness.start();
        browser = await chromium.launch({ channel: 'chrome', headless: true });

        const publicContext = await browser.newContext({ viewport: { width: 1366, height: 900 } });
        await checkPages(publicContext, [
            '/index.html',
            '/FARMERS%20UI/farmerLogin/farmerlogin.html',
            '/ADMIN%20UI/adminLogin/adminLogin.html',
            '/FARMERS%20UI/registration/registration_form.html',
            '/FARMERS%20UI/farmerLanding/map.html',
        ], 'public');

        const farmerContext = await browser.newContext({ viewport: { width: 1366, height: 900 } });
        await login(farmerContext, '/FARMERS%20UI/farmerLogin/login.php', {
            username: 'TEST-FARMER-001',
            password: 'SCAIMS-Test-Only#2026',
        });
        await checkPages(farmerContext, [
            '/FARMERS%20UI/farmersDashboard/home/home.php',
            '/FARMERS%20UI/farmersDashboard/parcel/farmParcel.php',
            '/FARMERS%20UI/farmersDashboard/report/report.php',
            '/FARMERS%20UI/farmersDashboard/profile/editProfile.php',
        ], 'farmer');

        const adminContext = await browser.newContext({ viewport: { width: 1366, height: 900 } });
        await login(adminContext, '/ADMIN%20UI/adminLogin/submitLogin.php', {
            adminID: 'TEST-ADMIN-001',
            password: 'SCAIMS-Test-Only#2026',
        });
        await checkPages(adminContext, [
            '/ADMIN%20UI/adminDashboard/home/home.php',
            '/ADMIN%20UI/adminDashboard/user/userManagement.php',
            '/ADMIN%20UI/adminDashboard/parcel/farmParcel.php',
            '/ADMIN%20UI/adminDashboard/report/reportAnalytics.php',
            '/ADMIN%20UI/adminDashboard/report/crop/cropReports.php',
            '/ADMIN%20UI/adminDashboard/report/damage/damageReports.php',
        ], 'admin');
        await checkAdminMobileSidebar(adminContext);

        console.log('PASS: public, Farmer and Admin pages have no local stylesheet failures or viewport overflow at 1366/1024/768/430/390px; Admin mobile drawer links are clickable above the backdrop.');
    } finally {
        if (browser) await browser.close().catch(() => {});
        console.log('CLEANUP: ' + await harness.stop());
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
