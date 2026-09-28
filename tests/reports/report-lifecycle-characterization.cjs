const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { Phase1Harness, loginFarmer, root } = require('../helpers/phase1-harness.cjs');

const harness = new Phase1Harness('reports', 8166);
const ocrFixture = path.join(root, 'tests', 'gps-photo-results', 'ocr-layout-a.png');

function absoluteUpload(relativePath) {
  return path.join(root, relativePath.replaceAll('/', path.sep));
}

function harvestFields(token) {
  return {
    'report-type': 'crop',
    'pending-report-image-token': token,
    'crop-commodity': 'fruits',
    'crop-type': 'Mango',
    'crop-variety': 'Phase One GPS Harvest',
    'date-planted': '2026-01-01',
    'harvest-date': '2026-09-20',
    'estimate-harvest': '88',
    'crop-status': 'Bearing',
    'farm-gate-price': '65',
    'number-of-trees': '11',
  };
}

function damageFields(token) {
  return {
    'report-type': 'damage',
    'pending-report-image-token': token,
    'damage-crop-commodity': 'fruits',
    'damage-crop-type': 'Mango',
    'damage-crop-variety': 'Phase One Reuse Attempt',
    'damage-cause': 'Typhoon',
    'damage-percentage': '20',
    'estimated-loss': '30',
    'damage-farm-gate-price': '65',
    'damage-number-of-trees': '2',
  };
}

(async () => {
  try {
    assert.equal(fs.existsSync(ocrFixture), true, 'Run gps-stamp-ocr.cjs first to generate the OCR fixture.');
    await harness.start();
    const farmer = await harness.newContext();
    assert.equal((await loginFarmer(farmer)).status(), 302);

    const sourceBytes = fs.readFileSync(ocrFixture);
    const stage = await farmer.post('/shared/report_image_stage.php', {
      multipart: {
        report_image: { name: 'phase1-gps.png', mimeType: 'image/png', buffer: sourceBytes },
      },
      timeout: 70000,
    });
    assert.equal(stage.status(), 200);
    const staged = await stage.json();
    assert.equal(staged.success, true, JSON.stringify(staged));
    assert.equal(staged.photo.date, '2026-09-09');
    assert.equal(Number(staged.photo.latitude).toFixed(6), '13.841768');
    let session = await harness.session(farmer);
    assert.equal(Object.hasOwn(session.pending, staged.token), true);
    const pendingPath = session.pending[staged.token].path;
    assert.equal(fs.existsSync(absoluteUpload(pendingPath)), true);

    const initial = harness.state();
    const invalidFields = harvestFields(staged.token);
    delete invalidFields['harvest-date'];
    const invalid = await farmer.post('/FARMERS%20UI/farmersDashboard/report/report_submit.php', {
      multipart: invalidFields,
      maxRedirects: 0,
    });
    assert.equal(invalid.status(), 302);
    assert.equal(harness.state().crop_reports.length, initial.crop_reports.length);
    session = await harness.session(farmer);
    assert.equal(Object.hasOwn(session.pending, staged.token), true);
    assert.equal(fs.existsSync(absoluteUpload(pendingPath)), true);

    const valid = await farmer.post('/FARMERS%20UI/farmersDashboard/report/report_submit.php', {
      multipart: harvestFields(staged.token),
      maxRedirects: 0,
    });
    assert.equal(valid.status(), 302);
    let state = harness.state();
    assert.equal(state.crop_reports.length, initial.crop_reports.length + 1);
    const report = state.crop_reports.find(row => row.crop_variety === 'Phase One GPS Harvest');
    assert.ok(report);
    assert.equal(Number(report.FarmerID), 2001);
    assert.equal(report.created_by, 'TEST-FARMER-001');
    assert.equal(report.photo_capture_date, '2026-09-09');
    assert.equal(Number(report.latitude).toFixed(6), '13.841768');
    assert.match(report.image_path, /^uploads\/reports\/harvest\//);
    assert.equal(fs.existsSync(absoluteUpload(report.image_path)), true);
    assert.deepEqual(fs.readFileSync(absoluteUpload(report.image_path)), sourceBytes);
    assert.equal(fs.existsSync(absoluteUpload(pendingPath)), false);
    assert.equal(Object.hasOwn((await harness.session(farmer)).pending, staged.token), false);
    const history = await (await farmer.get('/FARMERS%20UI/farmersDashboard/report/report.php')).text();
    assert.equal(history.includes('Phase One GPS Harvest'), true);

    const damageCount = state.damage_reports.length;
    await farmer.post('/FARMERS%20UI/farmersDashboard/report/report_submit.php', {
      multipart: damageFields(staged.token),
      maxRedirects: 0,
    });
    assert.equal(harness.state().damage_reports.length, damageCount);

    const expired = await harness.createExpiredToken(farmer);
    assert.equal(fs.existsSync(absoluteUpload(expired.path)), true);
    await farmer.post('/FARMERS%20UI/farmersDashboard/report/report_submit.php', {
      multipart: harvestFields(expired.token),
      maxRedirects: 0,
    });
    assert.equal(harness.state().crop_reports.length, initial.crop_reports.length + 1);
    assert.equal(Object.hasOwn((await harness.session(farmer)).pending, expired.token), false);
    assert.equal(fs.existsSync(absoluteUpload(expired.path)), true);
    console.log('KNOWN ISSUE (confirmed): expiry invalidates a pending image token but does not delete its pending file.');

    const legacyRelative = harness.fixture('legacy-image', 'damage', '7001', ocrFixture);
    const legacyAbsolute = absoluteUpload(legacyRelative);
    assert.equal(fs.existsSync(legacyAbsolute), true);
    const damageCountBeforeUpdate = harness.state().damage_reports.length;
    const updateFields = {
      'report-type': 'damage',
      'report-id': '7001',
      location: 'adya',
      'crop-commodity': 'fruits',
      'crop-type': 'Mango',
      'crop-variety': 'Phase One Damage Edited',
      'date-occurrence': '2026-09-01',
      'damage-cause': 'Heavy Rain',
      'damage-percentage': '30',
      'estimated-loss': '125',
      'farm-gate-price': '62',
      'number-of-trees': '3',
      latitude: '13.918',
      longitude: '121.145',
    };
    let update = await farmer.post('/FARMERS%20UI/farmersDashboard/report/report_update.php', { multipart: updateFields });
    assert.equal(update.status(), 200);
    assert.equal((await update.json()).success, true);
    state = harness.state();
    assert.equal(state.damage_reports.length, damageCountBeforeUpdate);
    let damage = state.damage_reports.find(row => Number(row.id) === 7001);
    assert.equal(damage.dmgCropVariety, 'Phase One Damage Edited');
    assert.equal(damage.image_path, legacyRelative);
    assert.equal(fs.existsSync(legacyAbsolute), true);

    update = await farmer.post('/FARMERS%20UI/farmersDashboard/report/report_update.php', {
      multipart: { ...updateFields, 'remove-report-image': '1' },
    });
    assert.equal((await update.json()).success, true);
    damage = harness.state().damage_reports.find(row => Number(row.id) === 7001);
    assert.equal(damage.image_path, null);
    assert.equal(fs.existsSync(legacyAbsolute), false);

    console.log('PASS: GPS staging, validation retry, Harvest finalization/DB/filesystem/history, token reuse/expiry, Damage same-ID update, image preservation/removal.');
  } finally {
    const cleanup = await harness.stop();
    console.log(`CLEANUP: ${cleanup}`);
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
