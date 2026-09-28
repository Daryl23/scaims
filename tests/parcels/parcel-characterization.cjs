const assert = require('node:assert/strict');
const { Phase1Harness, loginFarmer } = require('../helpers/phase1-harness.cjs');

const harness = new Phase1Harness('parcels', 8162);

(async () => {
  try {
    await harness.start();
    const farmer = await harness.newContext();
    assert.equal((await loginFarmer(farmer)).status(), 302);

    const initial = harness.state();
    const create = await farmer.post('/FARMERS%20UI/farmersDashboard/parcel/submitFarmInfo.php', {
      form: {
        farmType: 'Owned',
        location_1: 'adya',
        totalArea_1: '3.75',
        organic: 'Yes',
        ownership_1: 'Registered Owner',
        numCrops_1: '1',
        crop_1_1: 'fruits',
        cropType_1_1: 'Mango',
        cropVariety_1_1: 'Phase One Created Variety',
        numTrees_1_1: '12',
        treeStatus_1_1: 'Bearing',
      },
    });
    assert.equal(create.status(), 200);
    assert.equal((await create.json()).success, true);

    let state = harness.state();
    assert.equal(state.farm_parcels.length, initial.farm_parcels.length + 1);
    assert.equal(state.parcel_details.length, initial.parcel_details.length + 1);
    assert.equal(state.crop_details.length, initial.crop_details.length + 1);
    const parcel = state.parcel_details.at(-1);
    const farm = state.farm_parcels.find(row => Number(row.id) === Number(parcel.farm_parcel_id));
    const crop = state.crop_details.find(row => Number(row.parcel_detail_id) === Number(parcel.id));
    assert.equal(farm.created_by, 'TEST-FARMER-001');
    assert.equal(parcel.created_by, 'TEST-FARMER-001');
    assert.equal(crop.created_by, 'TEST-FARMER-001');
    assert.equal(crop.crop_variety, 'Phase One Created Variety');
    assert.equal((await farmer.get(`/FARMERS%20UI/farmersDashboard/parcel/editParcel.php?parcel_id=${parcel.id}`)).status(), 200);

    const edit = await farmer.post(`/FARMERS%20UI/farmersDashboard/parcel/editParcel.php?parcel_id=${parcel.id}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      form: {
        location: 'balintawak',
        farm_location: 'balintawak',
        totalArea: '4.25',
        farm_area: '4.25',
        ownership: 'Tenant',
        numCrops: '1',
        crop_1: 'vegetables',
        cropType_1: 'Tomato',
        cropVariety_1: 'Phase One Edited Variety',
        numTrees_1: '',
        treeStatus_1: '',
      },
    });
    assert.equal(edit.status(), 200);
    assert.equal((await edit.json()).success, true);
    state = harness.state();
    assert.equal(state.parcel_details.filter(row => Number(row.id) === Number(parcel.id)).length, 1);
    assert.equal(state.parcel_details.find(row => Number(row.id) === Number(parcel.id)).location, 'balintawak');
    assert.equal(state.crop_details.find(row => Number(row.parcel_detail_id) === Number(parcel.id)).crop_variety, 'Phase One Edited Variety');

    const beforeInvalid = harness.state();
    const invalid = await farmer.post('/FARMERS%20UI/farmersDashboard/parcel/submitFarmInfo.php', {
      form: { location_1: 'adya', totalArea_1: '1' },
    });
    assert.equal(invalid.status(), 200);
    assert.equal((await invalid.json()).success, false);
    const afterInvalid = harness.state();
    assert.equal(afterInvalid.farm_parcels.length, beforeInvalid.farm_parcels.length);
    assert.equal(afterInvalid.parcel_details.length, beforeInvalid.parcel_details.length);

    const otherParcel = await farmer.get('/FARMERS%20UI/farmersDashboard/parcel/editParcel.php?parcel_id=4002');
    const otherHtml = await otherParcel.text();
    assert.equal(otherHtml.includes('Other Secret Variety'), true);
    console.log('KNOWN ISSUE (confirmed): editParcel GET renders another Farmer\'s crop detail when its parcel ID is supplied.');

    console.log('PASS: parcel create/load/edit transaction, crop association, validation rollback, and current ownership behavior.');
  } finally {
    const cleanup = await harness.stop();
    console.log(`CLEANUP: ${cleanup}`);
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
