// Disposable-database browser coverage for the shared Admin Harvest/Damage read UI.
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { Phase1Harness } = require('./helpers/phase1-harness.cjs');

const harness = new Phase1Harness('admin-report-view', 8162);
let browser;

async function waitForList(page, endpoint, action) {
    const response = page.waitForResponse(value => value.url().includes(endpoint));
    await action();
    assert.equal((await response).ok(), true);
}

async function loginAdmin(context) {
    const response = await context.request.post(harness.base + '/ADMIN%20UI/adminLogin/submitLogin.php', {
        form: { adminID: 'TEST-ADMIN-001', password: 'SCAIMS-Test-Only#2026' },
        maxRedirects: 0
    });
    assert.equal(response.status(), 302);
}

async function exerciseReportPage(page, config) {
    const errors = [];
    const stylesheetFailures = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
        if (response.request().resourceType() === 'stylesheet'
            && new URL(response.url()).origin === new URL(harness.base).origin
            && !response.ok()) {
            stylesheetFailures.push(`${response.status()} ${response.url()}`);
        }
    });
    page.on('dialog', dialog => dialog.accept());
    const initialListResponse = page.waitForResponse(response => response.url().includes(config.listEndpoint));
    await page.goto(harness.base + config.path, { waitUntil: 'domcontentloaded' });
    assert.equal((await initialListResponse).ok(), true);
    await page.locator('#new-reports-body .action-button, #previous-reports-body .action-button').first().waitFor();

    assert.equal(await page.evaluate(() => innerWidth), 1366);
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim()), '#3b4918');

    assert.match(await page.locator('#current-page').innerText(), /^Page \d+ of \d+$/);
    assert.equal(await page.locator('#export-button').isVisible(), true);

    await waitForList(page, config.listEndpoint, () => page.locator('#crop-filter').selectOption(config.primaryCrop));
    await waitForList(page, config.listEndpoint, () => page.locator('#reporter-filter').selectOption('farmer'));
    await waitForList(page, config.listEndpoint, () => page.locator('#search-bar').fill('Test'));
    assert.equal(await page.locator('#new-reports-body .action-button, #previous-reports-body .action-button').count() > 0, true);

    const detailResponse = page.waitForResponse(response => response.url().includes(config.detailEndpoint));
    await page.locator('#new-reports-body .action-button, #previous-reports-body .action-button').first().click();
    assert.equal((await detailResponse).ok(), true);
    await page.locator(config.modal).waitFor({ state: 'visible' });
    await page.locator(config.map).waitFor({ state: 'visible' });
    assert.equal(await page.locator(config.map + ' .leaflet-marker-icon').count(), 1);
    assert.equal(await page.locator(config.noImage).isVisible(), true);

    await page.locator(config.modal + ' .close-button').click();
    await page.locator(config.modal).waitFor({ state: 'hidden' });

    await page.waitForFunction(() => typeof window.Chart === 'function');
    const chartResponse = page.waitForResponse(response => response.url().includes(config.chartEndpoint));
    await page.locator('#view-graph-button').click();
    assert.equal((await chartResponse).ok(), true);
    await page.locator('#graphModal').waitFor({ state: 'visible' });
    await page.waitForTimeout(300);
    const chartSize = await page.locator(config.chart).evaluate(canvas => ({ width: canvas.width, height: canvas.height }));
    assert.equal(chartSize.width > 0 && chartSize.height > 0, true, JSON.stringify(chartSize));
    assert.equal(await page.locator('#export-pdf-button').isVisible(), true);
    assert.equal(await page.locator('#export-excel-button').isVisible(), true);
    await page.locator('#graphModal .close-button').click();

    await waitForList(page, config.listEndpoint, () => page.locator('#search-bar').fill('NO-SUCH-SCAIMS-REPORT'));
    assert.match(await page.locator('#new-reports-body').innerText(), /No new reports/i);

    for (const width of [768, 390]) {
        await page.setViewportSize({ width, height: 844 });
        const bodyWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        assert.equal(bodyWidth <= width, true, `Unexpected horizontal overflow at ${width}px: ${bodyWidth}px`);
    }
    assert.deepEqual(stylesheetFailures, [], stylesheetFailures.join('\n'));
    assert.deepEqual(errors, [], errors.join('\n'));
}

(async () => {
    try {
        await harness.start();
        browser = await chromium.launch({ channel: 'chrome', headless: true });
        const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
        await loginAdmin(context);
        const harvest = await context.newPage();
        await exerciseReportPage(harvest, {
            path: '/ADMIN%20UI/adminDashboard/report/crop/cropReports.php',
            listEndpoint: 'fetchCropReports.php',
            detailEndpoint: 'getCropReports.php',
            chartEndpoint: 'fetchGraphData.php',
            modal: '#cropReportModal',
            map: '#crop-report-map',
            chart: '#harvestGraph',
            noImage: '#cropReportNoImage',
            primaryCrop: 'Mango'
        });
        await harvest.close();

        const damage = await context.newPage();
        await exerciseReportPage(damage, {
            path: '/ADMIN%20UI/adminDashboard/report/damage/damageReports.php',
            listEndpoint: 'fetchDamageReports.php',
            detailEndpoint: 'getDamageReports.php',
            chartEndpoint: 'getGraphData.php',
            modal: '#damageReportModal',
            map: '#damage-report-map',
            chart: '#damageChart',
            noImage: '#damageReportNoImage',
            primaryCrop: 'Mango'
        });
        await damage.close();
        console.log('PASS: Admin Harvest/Damage list, filters, detail, map, charts, exports, empty state, and mobile layout.');
    } finally {
        if (browser) await browser.close().catch(() => {});
        console.log('CLEANUP: ' + await harness.stop());
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
