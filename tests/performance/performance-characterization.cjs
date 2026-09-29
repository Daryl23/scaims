const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { performance } = require('node:perf_hooks');
const { Phase1Harness, loginAdmin, root } = require('../helpers/phase1-harness.cjs');

const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const fixture = path.join(__dirname, 'performance-fixtures.php');
const baselineMode = process.argv.includes('--baseline');

function median(values) {
  const sorted = [...values].sort((a, b) => a - b);
  return sorted[Math.floor(sorted.length / 2)];
}

async function timed(label, callback, runs = 3) {
  await callback();
  const samples = [];
  let last;
  for (let index = 0; index < runs; index++) {
    const started = performance.now();
    last = await callback();
    samples.push(performance.now() - started);
  }
  return { label, medianMs: Number(median(samples).toFixed(1)), samplesMs: samples.map(value => Number(value.toFixed(1))), value: last };
}

function rowCount(html) {
  return (String(html).match(/<tr\s+data-id=/g) || []).length;
}

async function responseJson(response) {
  const body = await response.text();
  try {
    return JSON.parse(body);
  } catch {
    throw new Error(`Expected JSON from ${response.url()}, received: ${body.slice(0, 1200)}`);
  }
}

(async () => {
  const harness = new Phase1Harness('phase10-performance', 8140);
  try {
    await harness.start();
    const seed = JSON.parse(execFileSync(php, [fixture, 'seed', harness.database, '1200'], { cwd: root, encoding: 'utf8' }));

    if (!baselineMode) {
      execFileSync(php, [path.join(root, 'database/migrate.php'), '--apply'], {
        cwd: root,
        env: {
          ...process.env,
          SCAIMS_DB_HOST: '127.0.0.1',
          SCAIMS_DB_PORT: '3306',
          SCAIMS_DB_NAME: harness.database,
          SCAIMS_DB_USER: 'root',
          SCAIMS_DB_PASSWORD: '',
        },
      });
    }

    const context = await harness.newContext();
    const login = await loginAdmin(context);
    assert.equal(login.status(), 302, 'Admin fixture login failed');

    const harvest = await timed('harvest-page-2', async () => {
      const response = await context.post('/ADMIN%20UI/adminDashboard/report/crop/fetchCropReports.php', {
        form: { page: '2', limit: '10' },
      });
      assert.equal(response.status(), 200);
      return response.json();
    });
    assert.equal(harvest.value.total, seed.crop_reports + 5);
    assert.equal(harvest.value.previousCount, seed.crop_reports - 3);
    assert.equal(rowCount(harvest.value.previousOutput), 10);
    assert.equal(harvest.value.currentPage, 2);

    const damage = await timed('damage-page-2', async () => {
      const response = await context.post('/ADMIN%20UI/adminDashboard/report/damage/fetchDamageReports.php', {
        form: { page: '2' },
      });
      assert.equal(response.status(), 200);
      return response.json();
    });
    assert.equal(damage.value.total, seed.damage_reports + 3);
    assert.equal(damage.value.previousCount, seed.damage_reports - 5);
    assert.equal(rowCount(damage.value.previousOutput), 10);
    assert.equal(damage.value.currentPage, 2);

    const filteredHarvestResponse = await context.post('/ADMIN%20UI/adminDashboard/report/crop/fetchCropReports.php', {
      form: { page: '1', limit: '10', filter: 'Mango', barangay: 'adya' },
    });
    const filteredHarvest = await filteredHarvestResponse.json();
    assert.equal(filteredHarvest.total > 0 && filteredHarvest.total < harvest.value.total, true);
    assert.equal(filteredHarvest.total, filteredHarvest.newCount + filteredHarvest.previousCount);

    const nullDateHarvest = await responseJson(await context.post('/ADMIN%20UI/adminDashboard/report/crop/fetchCropReports.php', {
      form: { page: '1', limit: '10', search: 'NULL-DATE-FIXTURE' },
    }));
    const nullDateDamage = await responseJson(await context.post('/ADMIN%20UI/adminDashboard/report/damage/fetchDamageReports.php', {
      form: { page: '1', search: 'NULL-DATE-FIXTURE' },
    }));
    assert.equal(nullDateHarvest.previousCount, 1, 'NULL Harvest reportDate must remain a previous report');
    assert.equal(nullDateDamage.previousCount, 1, 'NULL Damage reportDate must remain a previous report');

    const parcelExport = await timed('parcel-export', async () => {
      const response = await context.post('/ADMIN%20UI/adminDashboard/parcel/handleExport.php', { form: {} });
      assert.equal(response.status(), 200);
      return response.text();
    });
    assert.match(parcelExport.value, /Performance Farmer 1/);
    assert.match(parcelExport.value, /Total Farm Area:<\/strong> 1\.1000 hectares/);

    const landing = await timed('landing-data', async () => {
      const response = await context.get('/landing_data.php');
      assert.equal(response.status(), 200);
      return response.json();
    });
    assert.equal(landing.value.success, true);
    assert.deepEqual(landing.value.stats, {
      registeredFarmers: 43,
      hectaresMonitored: 125.75,
      cropTypesTracked: 2,
      totalParcels: 42,
      cropReports: seed.crop_reports + 5,
      damageReports: seed.damage_reports + 3,
      suggestionsReceived: 0,
      publishedPosts: 0,
    });

    const dashboard = await timed('admin-dashboard', async () => {
      const response = await context.get('/ADMIN%20UI/adminDashboard/home/home.php');
      assert.equal(response.status(), 200);
      return response.text();
    });
    assert.match(dashboard.value, />43<\/h2>/);
    assert.match(dashboard.value, />42<\/h2>/);
    assert.match(dashboard.value, />2,408<\/h2>/);

    const mapAll = await timed('public-map-all', async () => {
      const response = await context.get('/FARMERS%20UI/farmerLanding/get_crop_reports.php');
      assert.equal(response.status(), 200);
      return response.json();
    });
    assert.equal(mapAll.value.type, 'FeatureCollection');
    assert.equal(mapAll.value.features.length >= seed.crop_reports, true);

    const mapFilteredResponse = await context.get('/FARMERS%20UI/farmerLanding/get_crop_reports.php?crop_type=Mango&commodity=fruits&has_damage=yes&damage_min=20');
    const mapFiltered = await mapFilteredResponse.json();
    assert.equal(mapFiltered.type, 'FeatureCollection');
    assert.equal(mapFiltered.features.length > 0 && mapFiltered.features.length < mapAll.value.features.length, true);
    for (const feature of mapFiltered.features) {
      assert.equal(feature.properties.crop_type, 'Mango');
      assert.equal(feature.properties.crop_commodity, 'fruits');
      assert.equal(feature.properties.has_damage, true);
      assert.equal(feature.properties.max_damage >= 20, true);
    }

    if (!baselineMode) {
      const source = fs.readFileSync(path.join(root, 'ADMIN UI/adminDashboard/parcel/handleExport.php'), 'utf8');
      assert.equal(/while\s*\([^)]*fetch_assoc[^)]*\)[\s\S]*?totalAreaQuery/.test(source), false, 'Parcel export still queries totals inside the row loop');
    }

    const indexes = JSON.parse(execFileSync(php, [fixture, 'indexes', harness.database], { cwd: root, encoding: 'utf8' }));
    const explain = JSON.parse(execFileSync(php, [fixture, 'explain', harness.database], { cwd: root, encoding: 'utf8' }));
    if (!baselineMode) {
      assert.deepEqual(indexes.crop_reports.idx_crop_reports_report_date, ['reportDate', 'id']);
      assert.deepEqual(indexes.damage_reports.idx_damage_reports_report_date, ['reportDate', 'id']);
      assert.deepEqual(indexes.damage_reports.idx_damage_reports_occurrence_date, ['date_occurrence', 'id']);
      assert.deepEqual(indexes.damage_reports.idx_damage_farmer_crop, ['FarmerID', 'dmgCropType']);
      assert.deepEqual(indexes.farm_parcels.idx_farm_parcels_created_by, ['created_by']);
      assert.deepEqual(indexes.user.idx_user_username, ['UName']);
      assert.deepEqual(indexes.admin.idx_admin_name, ['AdminName']);
    }

    console.log(JSON.stringify({
      mode: baselineMode ? 'before' : 'after',
      fixture: seed,
      structural: {
        harvestTotal: harvest.value.total,
        harvestNewRows: rowCount(harvest.value.newOutput),
        harvestPreviousPageRows: rowCount(harvest.value.previousOutput),
        damageTotal: damage.value.total,
        damageNewRows: rowCount(damage.value.newOutput),
        damagePreviousPageRows: rowCount(damage.value.previousOutput),
        parcelFarmers: 43,
        mapFeatures: mapAll.value.features.length,
        filteredMapFeatures: mapFiltered.features.length,
      },
      timings: [harvest, damage, parcelExport, landing, dashboard, mapAll].map(({ label, medianMs, samplesMs }) => ({ label, medianMs, samplesMs })),
      indexes,
      explain,
    }, null, 2));
    console.log('PASS: Phase 10 performance fixtures preserve pagination, export totals, landing contracts, and map filters.');
  } finally {
    console.log(`CLEANUP: ${await harness.stop()}`);
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
