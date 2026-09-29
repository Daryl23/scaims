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

        for (const width of [1366, 768, 390]) {
            await page.setViewportSize({ width, height: width === 390 ? 844 : 900 });
            await page.waitForTimeout(75);
            const layout = await page.evaluate(() => ({
                viewport: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                bodyWidth: document.body.scrollWidth,
            }));
            assert.equal(layout.viewport, width);
            assert.equal(
                Math.max(layout.documentWidth, layout.bodyWidth) <= width + 1,
                true,
                `${portal} overflow at ${width}px on ${pagePath}: ${JSON.stringify(layout)}`
            );
        }

        assert.deepEqual(cssFailures, [], cssFailures.join('\n'));
        assert.deepEqual(pageErrors, [], `${pagePath}\n${pageErrors.join('\n')}`);
        await page.close();
    }
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
        ], 'admin');

        console.log('PASS: public, Farmer and Admin pages have no local stylesheet failures or viewport overflow at 1366/768/390px.');
    } finally {
        if (browser) await browser.close().catch(() => {});
        console.log('CLEANUP: ' + await harness.stop());
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
