const assert = require('node:assert/strict');
const { Phase1Harness } = require('../helpers/phase1-harness.cjs');

const harness = new Phase1Harness('map', 8165);

function ids(payload) {
  return payload.features.map(feature => Number(feature.properties.id)).sort((a, b) => a - b);
}

(async () => {
  try {
    await harness.start();
    const publicContext = await harness.newContext();
    const response = await publicContext.get('/FARMERS%20UI/farmerLanding/get_crop_reports.php');
    assert.equal(response.status(), 200);
    assert.match(response.headers()['content-type'], /application\/json/);
    const payload = await response.json();
    assert.equal(payload.type, 'FeatureCollection');
    assert.deepEqual(ids(payload), [6001, 6002, 6004]);
    assert.equal(ids(payload).includes(6003), false);

    const required = ['id', 'location', 'location_key', 'lgu_key', 'coordinate_source', 'crop_type', 'crop_variety', 'farmer_id', 'max_damage', 'has_damage'];
    for (const feature of payload.features) {
      assert.equal(feature.type, 'Feature');
      assert.equal(feature.geometry.type, 'Point');
      const [longitude, latitude] = feature.geometry.coordinates;
      assert.equal(longitude >= -180 && longitude <= 180, true);
      assert.equal(latitude >= -90 && latitude <= 90, true);
      for (const property of required) assert.equal(Object.hasOwn(feature.properties, property), true, `missing ${property}`);
    }

    const harvest = payload.features.find(feature => Number(feature.properties.id) === 6001);
    assert.equal(harvest.properties.max_damage, 25);
    assert.equal(harvest.properties.has_damage, true);
    assert.equal(harvest.properties.coordinate_source, 'saved');
    const fallback = payload.features.find(feature => Number(feature.properties.id) === 6004);
    assert.equal(fallback.properties.coordinate_source, 'location_center');
    assert.equal(payload.meta.scope.supportedAreaLabel, 'Lipa City + Mataas na Kahoy, Batangas');
    assert.equal(payload.meta.scope.boundaryGeometryAvailable, false);

    const adya = await (await publicContext.get('/FARMERS%20UI/farmerLanding/get_crop_reports.php?location=barangay%3Aadya')).json();
    assert.deepEqual(ids(adya), [6001]);
    const mataas = await (await publicContext.get('/FARMERS%20UI/farmerLanding/get_crop_reports.php?location=lgu%3Amataas_na_kahoy')).json();
    assert.deepEqual(ids(mataas), [6002]);
    const tomato = await (await publicContext.get('/FARMERS%20UI/farmerLanding/get_crop_reports.php?crop_type=Tomato')).json();
    assert.deepEqual(ids(tomato), [6004]);
    const unsupported = await (await publicContext.get('/FARMERS%20UI/farmerLanding/get_crop_reports.php?location=lgu%3Aunsupported')).json();
    assert.deepEqual(ids(unsupported), []);

    console.log('PASS: public GeoJSON contract, supported-area scope, damage association, valid/fallback coordinates, and filters.');
  } finally {
    const cleanup = await harness.stop();
    console.log(`CLEANUP: ${cleanup}`);
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
