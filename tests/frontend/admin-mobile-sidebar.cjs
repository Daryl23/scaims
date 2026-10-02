const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..', '..');
const css = fs.readFileSync(path.join(root, 'ADMIN UI', 'shared', 'admin-sidebar.css'), 'utf8');
const shell = fs.readFileSync(path.join(root, 'shared', 'dashboard-shell.js'), 'utf8');

let browser;

(async () => {
    try {
        browser = await chromium.launch({ channel: 'chrome', headless: true });
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });

        await page.setContent(`<!doctype html>
            <html><head><meta name="viewport" content="width=device-width, initial-scale=1">
            <style>${css}</style></head>
            <body>
                <button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-controls="adminSidebar" aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>
                <div class="sidebar-overlay" aria-hidden="true"></div>
                <aside class="sidebar" id="adminSidebar">
                    <nav><ul>
                        <li><a href="#dashboard" class="menu-item is-active">Dashboard</a></li>
                        <li><a href="#farmers" class="menu-item">Farmers</a></li>
                        <li><a href="#parcels" class="menu-item">Parcels</a></li>
                        <li><a href="#reports" class="menu-item">Reports</a></li>
                    </ul></nav>
                </aside>
                <main class="main-content"><button id="pageAction" type="button">Page action</button></main>
                <script>${shell}</script>
            </body></html>`, { waitUntil: 'domcontentloaded' });

        const toggle = page.locator('.menu-toggle');
        const sidebar = page.locator('#adminSidebar');
        const overlay = page.locator('.sidebar-overlay');
        assert.equal(await toggle.isVisible(), true);

        const toggleBox = await toggle.boundingBox();
        assert.ok(toggleBox && toggleBox.width >= 44 && toggleBox.height >= 44);
        assert.ok(toggleBox.x >= 0 && toggleBox.y >= 0 && toggleBox.x + toggleBox.width <= 390);

        await toggle.click();
        await page.waitForTimeout(300);
        assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
        assert.equal(await sidebar.evaluate(element => element.classList.contains('active')), true);

        const layerState = await page.evaluate(() => {
            const drawer = document.querySelector('#adminSidebar');
            const backdrop = document.querySelector('.sidebar-overlay');
            const link = document.querySelector('a[href="#farmers"]');
            const box = link.getBoundingClientRect();
            const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
            return {
                sidebarZ: Number(getComputedStyle(drawer).zIndex),
                overlayZ: Number(getComputedStyle(backdrop).zIndex),
                sidebarPointerEvents: getComputedStyle(drawer).pointerEvents,
                hitHref: hit && hit.closest('a') ? hit.closest('a').getAttribute('href') : null,
            };
        });
        assert.ok(layerState.sidebarZ > layerState.overlayZ, JSON.stringify(layerState));
        assert.equal(layerState.sidebarPointerEvents, 'auto');
        assert.equal(layerState.hitHref, '#farmers', 'The backdrop must not intercept a visible navigation link');

        await page.locator('a[href="#farmers"]').click();
        assert.equal(new URL(page.url()).hash, '#farmers');

        await toggle.click();
        await page.waitForTimeout(300);
        assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
        await toggle.click();
        await page.waitForTimeout(300);
        assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
        assert.equal(await overlay.evaluate(element => getComputedStyle(element).pointerEvents), 'none');

        await toggle.click();
        await page.waitForTimeout(300);
        await page.keyboard.press('Escape');
        assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
        assert.equal(await page.evaluate(() => document.activeElement === document.querySelector('.menu-toggle')), true);

        await page.locator('#pageAction').click();
        assert.equal(await page.locator('#pageAction').isEnabled(), true);

        await page.setViewportSize({ width: 1366, height: 900 });
        await page.waitForTimeout(100);
        assert.equal(await toggle.isVisible(), false);
        assert.equal(await sidebar.isVisible(), true);

        console.log('PASS: Admin mobile drawer opens above its backdrop, links receive clicks, close paths restore interaction, and desktop sidebar remains intact.');
    } finally {
        if (browser) await browser.close().catch(() => {});
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
