const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const { chromium } = require('playwright');
const {
  Phase1Harness,
  assertRedirect,
  loginAdmin,
  loginFarmer,
  root,
} = require('../helpers/phase1-harness.cjs');

const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const route = '/ADMIN%20UI/adminDashboard/monitoring/satelliteMonitoring.php';
const endpoint = '/ADMIN%20UI/adminDashboard/monitoring/getParcel.php?id=4001';
const harness = new Phase1Harness('satellite_s2a', 8174);
let browser;

function configProbe(clientId, clientSecret) {
  const output = execFileSync(php, [path.join(root, 'tests/satellite/config-probe.php')], {
    cwd: root,
    encoding: 'utf8',
    env: {
      ...process.env,
      COPERNICUS_CLIENT_ID: clientId,
      COPERNICUS_CLIENT_SECRET: clientSecret,
    },
  });
  return JSON.parse(output);
}

async function browserLogin(context, username = 'TEST-ADMIN-001') {
  const response = await context.request.post(
    harness.base + '/ADMIN%20UI/adminLogin/submitLogin.php',
    { form: { adminID: username, password: 'SCAIMS-Test-Only#2026' }, maxRedirects: 0 }
  );
  assert.equal(response.status(), 302);
}

(async () => {
  const oldClientId = process.env.COPERNICUS_CLIENT_ID;
  const oldClientSecret = process.env.COPERNICUS_CLIENT_SECRET;
  delete process.env.COPERNICUS_CLIENT_ID;
  delete process.env.COPERNICUS_CLIENT_SECRET;

  try {
    assert.deepEqual(configProbe('', '').configured, false, 'both credentials are required');
    assert.deepEqual(configProbe('test-client', '').configured, false, 'a client ID alone is insufficient');
    const configuredProbe = configProbe('test-client', 'test-secret');
    assert.equal(configuredProbe.configured, true);
    assert.equal(configuredProbe.provider, 'Copernicus Data Space Ecosystem / Sentinel-2 L2A');
    assert.equal(JSON.stringify(configuredProbe).includes('test-secret'), false, 'credential value must not be returned');

    await harness.start();

    const anonymous = await harness.newContext();
    assertRedirect(assert, await anonymous.get(route, { maxRedirects: 0 }), '../../adminLogin/adminLogin.html');
    const anonymousApi = await anonymous.get(endpoint, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      maxRedirects: 0,
    });
    assert.equal(anonymousApi.status(), 401);

    const farmer = await harness.newContext();
    assert.equal((await loginFarmer(farmer)).status(), 302);
    assertRedirect(assert, await farmer.get(route, { maxRedirects: 0 }), '../../adminLogin/adminLogin.html');

    const admin = await harness.newContext();
    assert.equal((await loginAdmin(admin)).status(), 302);
    const pageResponse = await admin.get(route);
    assert.equal(pageResponse.status(), 200);
    const pageHtml = await pageResponse.text();
    assert.match(pageHtml, /Satellite Monitoring/);
    assert.match(pageHtml, /value="4001"/);
    assert.match(pageHtml, /value="4002"/);
    assert.match(pageHtml, /data-satellite-configured="false"/);
    assert.match(pageHtml, /Satellite service is not configured/);
    assert.match(pageHtml, /No satellite history yet/);
    assert.match(pageHtml, /No satellite observations currently require review/);
    for (const field of ['requested_date_window', 'max_cloud_threshold', 'execution_seconds']) {
      assert.match(pageHtml, new RegExp(`data-satellite-field="${field}"`));
    }
    assert.equal(pageHtml.includes('0.61'), false, 'production HTML must not contain illustrative NDVI');
    assert.equal(pageHtml.includes('S2C_example'), false, 'production HTML must not contain an illustrative scene');
    assert.equal(pageHtml.includes('test-secret'), false);

    const parcelResponse = await admin.get(endpoint, { headers: { Accept: 'application/json' } });
    assert.equal(parcelResponse.status(), 200);
    const parcelJson = await parcelResponse.json();
    assert.equal(parcelJson.ok, true);
    assert.equal(parcelJson.parcel.parcel_id, 4001);
    assert.equal(parcelJson.parcel.farmer_id, 2001);
    assert.equal(parcelJson.parcel.farmer_name, 'Test Phase Farmer One');
    assert.equal(parcelJson.parcel.location, 'adya');
    assert.equal(parcelJson.parcel.area_hectares, 1.25);
    assert.deepEqual(parcelJson.parcel.crops.map(crop => crop.type), ['Mango']);
    assert.equal(parcelJson.parcel.municipality, null);
    assert.equal(parcelJson.parcel.status, null);
    assert.equal(parcelJson.parcel.latitude, null);
    assert.equal(parcelJson.parcel.longitude, null);
    assert.equal(parcelJson.parcel.boundary_geojson, null);
    assert.equal(parcelJson.satellite.observation, null);
    assert.deepEqual(parcelJson.satellite.history, []);
    const serializedParcel = JSON.stringify(parcelJson);
    assert.equal(serializedParcel.includes('COPERNICUS_CLIENT_ID'), false);
    assert.equal(serializedParcel.includes('COPERNICUS_CLIENT_SECRET'), false);

    const superAdmin = await harness.newContext();
    assert.equal((await loginAdmin(superAdmin, 'TEST-SUPERADMIN-001')).status(), 302);
    const superHtml = await (await superAdmin.get(route)).text();
    const regularIds = [...pageHtml.matchAll(/<option value="(400[12])">/g)].map(match => match[1]);
    const superIds = [...superHtml.matchAll(/<option value="(400[12])">/g)].map(match => match[1]);
    assert.deepEqual(superIds, regularIds, 'S2A preserves the existing shared Admin parcel visibility');

    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
    await browserLogin(context);
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    await page.goto(harness.base + route, { waitUntil: 'domcontentloaded' });

    await page.locator('#parcel-selector').selectOption('4001');
    await page.locator('#parcel-load-status').filter({ hasText: 'loaded' }).waitFor();
    assert.equal(await page.locator('[data-parcel-field="farmer_name"]').textContent(), 'Test Phase Farmer One');
    assert.equal(await page.locator('[data-parcel-field="crop"]').textContent(), 'Mango');
    assert.equal(await page.locator('[data-parcel-field="location"]').textContent(), 'adya');
    assert.equal(await page.locator('[data-parcel-field="area"]').textContent(), '1.25 ha');
    assert.equal(await page.locator('[data-parcel-field="municipality"]').textContent(), '—');
    assert.equal(await page.locator('[data-parcel-field="status"]').textContent(), '—');
    assert.equal(await page.locator('#map-empty-state strong').textContent(), 'No parcel coordinates available');
    assert.equal(await page.locator('#run-analysis-button').isDisabled(), true);
    assert.equal(await page.locator('[data-satellite-field="ndvi_mean"]').textContent(), '—');

    for (const width of [1366, 1024, 768, 430, 390]) {
      await page.setViewportSize({ width, height: width <= 430 ? 844 : 900 });
      await page.waitForTimeout(100);
      const layout = await page.evaluate(() => {
        const tableWrap = document.querySelector('.satellite-history-wrap');
        const map = document.querySelector('.parcel-map-wrap');
        return {
          viewport: innerWidth,
          documentWidth: document.documentElement.scrollWidth,
          bodyWidth: document.body.scrollWidth,
          tableOverflow: getComputedStyle(tableWrap).overflowX,
          mapWidth: map.getBoundingClientRect().width,
          hasHorizontalOverflow: document.documentElement.scrollWidth > innerWidth + 1
            || document.body.scrollWidth > innerWidth + 1,
        };
      });
      assert.equal(layout.viewport, width);
      assert.equal(layout.hasHorizontalOverflow, false, `overflow at ${width}px: ${JSON.stringify(layout)}`);
      assert.ok(['auto', 'scroll'].includes(layout.tableOverflow));
      assert.ok(layout.mapWidth <= width, `map stays within ${width}px viewport`);
    }

    await page.setViewportSize({ width: 390, height: 844 });
    const menuToggle = page.locator('.menu-toggle');
    await menuToggle.click();
    await page.waitForTimeout(350);
    assert.equal(await menuToggle.getAttribute('aria-expanded'), 'true');
    assert.equal(await page.locator('#adminSidebar').evaluate(element => element.classList.contains('active')), true);
    assert.equal(await page.locator('a[href*="satelliteMonitoring.php"]').getAttribute('aria-current'), 'page');
    await page.locator('.sidebar-overlay').click({ position: { x: 385, y: 420 } });
    await page.waitForTimeout(350);
    assert.equal(await menuToggle.getAttribute('aria-expanded'), 'false');
    assert.deepEqual(pageErrors, []);

    console.log('PASS: S2A Admin guard, Farmer denial, canonical parcel data, credential privacy/status, truthful empty satellite states, responsive layout, and sidebar behavior.');
  } finally {
    if (oldClientId === undefined) delete process.env.COPERNICUS_CLIENT_ID;
    else process.env.COPERNICUS_CLIENT_ID = oldClientId;
    if (oldClientSecret === undefined) delete process.env.COPERNICUS_CLIENT_SECRET;
    else process.env.COPERNICUS_CLIENT_SECRET = oldClientSecret;
    if (browser) await browser.close().catch(() => {});
    console.log('CLEANUP: ' + await harness.stop());
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
