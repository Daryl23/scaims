const assert = require('node:assert/strict');
const { Phase1Harness, loginFarmer } = require('../helpers/phase1-harness.cjs');

const harness = new Phase1Harness('profile', 8163);
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4ZkAAAAASUVORK5CYII=', 'base64');

function profileFields(username, extra = {}) {
  return {
    username,
    usernameChanged: 'false',
    currentPassword: '',
    newPassword: '',
    surname: 'Farmer One Updated',
    firstName: 'Test',
    middleName: 'Phase',
    extensionName: '',
    sex: 'Male',
    dob: '1990-01-01',
    contactNumber: '09179990001',
    address: 'Updated Phase 1 address',
    barangay: 'balintawak',
    rsbsaNumber: '10-10-10-001-000001',
    ...extra,
  };
}

(async () => {
  try {
    await harness.start();
    const farmer = await harness.newContext();
    assert.equal((await loginFarmer(farmer)).status(), 302);

    const basic = await farmer.post('/FARMERS%20UI/farmersDashboard/profile/updateProfile.php', {
      multipart: {
        ...profileFields('TEST-FARMER-001'),
        profileImage: { name: 'phase1-profile.png', mimeType: 'image/png', buffer: png },
      },
    });
    assert.equal(basic.status(), 200);
    assert.equal((await basic.json()).success, true);
    let state = harness.state();
    let fixtureFarmer = state.farmers.find(row => Number(row.FarmerID) === 2001);
    let fixtureUser = state.users.find(row => Number(row.UserID) === 1001);
    assert.equal(fixtureFarmer.Surname, 'Farmer One Updated');
    assert.equal(fixtureFarmer.Barangay, 'balintawak');
    assert.equal(Number(fixtureFarmer.UserID), Number(fixtureUser.UserID));
    assert.equal(Number(fixtureUser.profile_bytes) > 0, true);

    const newPassword = 'SCAIMS-Phase1-New#2026';
    const passwordUpdate = await farmer.post('/FARMERS%20UI/farmersDashboard/profile/updateProfile.php', {
      form: profileFields('TEST-FARMER-001', {
        currentPassword: 'SCAIMS-Test-Only#2026',
        newPassword,
      }),
    });
    assert.equal((await passwordUpdate.json()).success, true);
    const oldPasswordContext = await harness.newContext();
    assert.equal((await loginFarmer(oldPasswordContext)).status(), 200);
    const newPasswordContext = await harness.newContext();
    assert.equal((await loginFarmer(newPasswordContext, 'TEST-FARMER-001', newPassword)).status(), 302);

    const renamed = 'TEST-FARMER-001-RENAMED';
    const usernameUpdate = await farmer.post('/FARMERS%20UI/farmersDashboard/profile/updateProfile.php', {
      form: profileFields(renamed, {
        usernameChanged: 'true',
        currentPassword: newPassword,
      }),
    });
    assert.equal((await usernameUpdate.json()).success, true);
    state = harness.state();
    fixtureUser = state.users.find(row => Number(row.UserID) === 1001);
    assert.equal(fixtureUser.UName, renamed);
    assert.equal((await harness.session(farmer)).UName, renamed);

    for (const table of ['farm_parcels', 'parcel_details', 'crop_details', 'crop_reports', 'damage_reports']) {
      const ownedRows = state[table].filter(row => {
        if (table === 'crop_reports' || table === 'damage_reports') return Number(row.FarmerID) === 2001;
        return Number(row.id) === ({ farm_parcels: 3001, parcel_details: 4001, crop_details: 5001 }[table]);
      });
      assert.equal(ownedRows.length > 0, true, `${table} fixture row missing`);
      assert.equal(ownedRows.every(row => row.created_by === renamed), true, `${table} created_by was not rewritten`);
    }

    const relogin = await harness.newContext();
    assert.equal((await loginFarmer(relogin, renamed, newPassword)).status(), 302);
    console.log('PASS: profile fields/image, User-Farmer relationship, password update, username update, session update, and created_by propagation.');
  } finally {
    const cleanup = await harness.stop();
    console.log(`CLEANUP: ${cleanup}`);
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
