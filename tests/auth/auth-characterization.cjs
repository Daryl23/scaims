const assert = require('node:assert/strict');
const {
  Phase1Harness,
  assertRedirect,
  loginAdmin,
  loginFarmer,
} = require('../helpers/phase1-harness.cjs');

const harness = new Phase1Harness('auth', 8161);

async function bodyIncludes(response, text) {
  return (await response.text()).includes(text);
}

(async () => {
  try {
    await harness.start();

    const anonymousFarmer = await harness.newContext();
    assertRedirect(assert, await anonymousFarmer.get('/FARMERS%20UI/farmersDashboard/home/home.php', { maxRedirects: 0 }), '../../farmerLogin/farmerlogin.html');

    const farmer = await harness.newContext();
    const farmerPreAuthSessionId = (await harness.session(farmer)).session_id;
    assertRedirect(assert, await loginFarmer(farmer), '../farmersDashboard/home/home.php');
    assert.equal((await farmer.get('/FARMERS%20UI/farmersDashboard/home/home.php')).status(), 200);
    const farmerSession = await harness.session(farmer);
    assert.notEqual(farmerSession.session_id, farmerPreAuthSessionId);
    assert.deepEqual(farmerSession, {
      session_id: farmerSession.session_id,
      UserID: 1001,
      UName: 'TEST-FARMER-001',
      AdminID: null,
      AdminName: null,
      AdminRole: null,
      IsSuperAdmin: null,
      pending: [],
    });

    for (const [username, password] of [
      ['TEST-FARMER-001', 'wrong-password'],
      ['TEST-FARMER-NOT-FOUND', 'SCAIMS-Test-Only#2026'],
      ['TEST-FARMER-INACTIVE', 'SCAIMS-Test-Only#2026'],
    ]) {
      const context = await harness.newContext();
      const response = await loginFarmer(context, username, password);
      assert.equal(response.status(), 200);
      assert.equal(await bodyIncludes(response, 'Invalid username or password.'), true);
      assert.equal((await harness.session(context)).UserID, null);
    }

    const reportPage = await farmer.get('/FARMERS%20UI/farmersDashboard/report/report.php');
    const reportHtml = await reportPage.text();
    assert.equal(reportHtml.includes('Phase One Own Harvest'), true);
    assert.equal(reportHtml.includes('Phase One Other Harvest'), false);

    const otherBefore = harness.state().crop_reports.find(row => Number(row.id) === 6002);
    const forbiddenUpdate = await farmer.post('/FARMERS%20UI/farmersDashboard/report/report_update.php', {
      form: {
        'report-id': '6002',
        'report-type': 'crop',
        location: 'adya',
        'crop-commodity': 'fruits',
        'crop-type': 'Mango',
        'crop-variety': 'Forbidden Cross Owner Update',
        'date-planted': '2026-01-01',
        'harvest-date': '2026-09-01',
        'estimated-harvest': '50',
        'farm-gate-price': '60',
      },
    });
    assert.equal(forbiddenUpdate.status(), 404);
    assert.equal((await forbiddenUpdate.json()).success, false);
    const otherAfter = harness.state().crop_reports.find(row => Number(row.id) === 6002);
    assert.equal(otherAfter.crop_variety, otherBefore.crop_variety);

    assertRedirect(assert, await farmer.get('/FARMERS%20UI/farmersDashboard/shared/logout.php', { maxRedirects: 0 }), '../../farmerLogin/farmerlogin.html');
    assert.equal((await harness.session(farmer)).UserID, null);
    assertRedirect(assert, await farmer.get('/FARMERS%20UI/farmersDashboard/home/home.php', { maxRedirects: 0 }), '../../farmerLogin/farmerlogin.html');

    const anonymousAdmin = await harness.newContext();
    assertRedirect(assert, await anonymousAdmin.get('/ADMIN%20UI/adminDashboard/home/home.php', { maxRedirects: 0 }), '../../adminLogin/adminLogin.html');

    for (const [username, password, message] of [
      ['TEST-ADMIN-001', 'wrong-password', 'Invalid username or password.'],
      ['TEST-ADMIN-NOT-FOUND', 'SCAIMS-Test-Only#2026', 'Invalid username or password.'],
      ['TEST-ADMIN-INACTIVE', 'SCAIMS-Test-Only#2026', 'This account is inactive.'],
    ]) {
      const context = await harness.newContext();
      const response = await loginAdmin(context, username, password);
      assert.equal(response.status(), 200);
      assert.equal(await bodyIncludes(response, message), true);
      assert.equal((await harness.session(context)).AdminID, null);
    }

    const admin = await harness.newContext();
    const adminPreAuthSessionId = (await harness.session(admin)).session_id;
    assertRedirect(assert, await loginAdmin(admin), '../adminDashboard/home/home.php');
    assert.equal((await admin.get('/ADMIN%20UI/adminDashboard/home/home.php')).status(), 200);
    let session = await harness.session(admin);
    assert.notEqual(session.session_id, adminPreAuthSessionId);
    assert.equal(session.AdminID, 'TEST-ADMIN-001');
    assert.equal(session.AdminRole, 'admin');
    assert.equal(session.IsSuperAdmin, false);

    const catalogCount = harness.state().crop_catalog.length;
    assertRedirect(assert, await admin.get('/ADMIN%20UI/adminDashboard/crops/cropManagement.php', { maxRedirects: 0 }), '../home/home.php');
    assertRedirect(assert, await admin.post('/ADMIN%20UI/adminDashboard/crops/cropActions.php', {
      form: { action: 'create', commodity: 'fruits', crop_name: 'Forbidden Crop', is_active: '1' },
      maxRedirects: 0,
    }), '../home/home.php');
    assert.equal(harness.state().crop_catalog.length, catalogCount);

    const superAdmin = await harness.newContext();
    assertRedirect(assert, await loginAdmin(superAdmin, 'TEST-SUPERADMIN-001'), '../adminDashboard/home/home.php');
    assert.equal((await superAdmin.get('/ADMIN%20UI/adminDashboard/crops/cropManagement.php')).status(), 200);
    session = await harness.session(superAdmin);
    assert.equal(session.AdminRole, 'super_admin');
    assert.equal(session.IsSuperAdmin, true);

    assertRedirect(assert, await admin.get('/ADMIN%20UI/adminLogin/logout.php', { maxRedirects: 0 }), 'adminLogin.html');
    assert.equal((await harness.session(admin)).AdminID, null);
    assertRedirect(assert, await admin.get('/ADMIN%20UI/adminDashboard/home/home.php', { maxRedirects: 0 }), '../../adminLogin/adminLogin.html');

    console.log('PASS: Farmer/Admin login failures, session rotation, guards, roles, real logout, report visibility, and ownership-qualified update.');
  } finally {
    const cleanup = await harness.stop();
    console.log(`CLEANUP: ${cleanup}`);
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
