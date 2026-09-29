const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const fixture = path.join(__dirname, 'migration-fixtures.php');
const migrationRunner = path.join(root, 'database/migrate.php');
const probe = path.join(root, 'tests/config/database-probe.php');
const database = `scaims_phase2_test_${Date.now()}_${process.pid}`;
const baselineDatabase = `scaims_phase2_test_${Date.now()}_${process.pid + 1}`;
const mysql = process.env.MYSQL_BINARY || 'C:/xampp/mysql/bin/mysql.exe';
const env = {
  ...process.env,
  SCAIMS_DB_HOST: '127.0.0.1',
  SCAIMS_DB_PORT: '3306',
  SCAIMS_DB_NAME: database,
  SCAIMS_DB_USER: 'root',
  SCAIMS_DB_PASSWORD: '',
};

function fixtureAction(action) {
  return execFileSync(php, [fixture, action, database], { cwd: root, encoding: 'utf8' }).trim();
}

(async () => {
  let ready = false;
  let baselineReady = false;
  try {
    assert.equal(fixtureAction('setup'), 'ready');
    ready = true;

    const status = execFileSync(php, [migrationRunner, '--status'], { cwd: root, env, encoding: 'utf8' });
    assert.match(status, /\[PENDING\] 2026_09_04_add_report_image_paths/);
    assert.match(status, /\[MANUAL\] 2026_09_06_drop_plain_password/);
    assert.match(status, /\[PENDING\] 2026_09_12_create_crop_catalog/);
    assert.match(status, /\[PENDING\] 2026_09_29_add_query_performance_indexes/);

    const applied = execFileSync(php, [migrationRunner, '--apply'], { cwd: root, env, encoding: 'utf8' });
    assert.match(applied, /\[APPLIED\] 2026_09_11_add_admin_role/);
    assert.match(applied, /\[APPLIED\] 2026_09_12_create_crop_catalog/);
    assert.match(applied, /\[APPLIED\] 2026_09_29_add_query_performance_indexes/);

    const state = JSON.parse(fixtureAction('state'));
    for (const table of ['schema_migrations', 'farmer_report_defaults', 'crop_catalog']) {
      assert.equal(state.tables.includes(table), true, `${table} was not created`);
    }
    assert.equal(state.columns.admin.includes('AdminRole'), true);
    for (const table of ['crop_reports', 'damage_reports']) {
      for (const column of ['image_path', 'photo_capture_date', 'photo_capture_time', 'photo_location', 'photo_latitude', 'photo_longitude', 'photo_timezone', 'photo_ocr_version']) {
        assert.equal(state.columns[table].includes(column), true, `${table}.${column} missing`);
      }
    }
    assert.equal(state.migrations.length, 6);
    assert.equal(state.migrations.some(row => row.migration_id === '2026_09_06_drop_plain_password'), false);
    assert.deepEqual(state.indexes.crop_reports.idx_crop_reports_report_date, ['reportDate', 'id']);
    assert.deepEqual(state.indexes.damage_reports.idx_damage_reports_report_date, ['reportDate', 'id']);
    assert.deepEqual(state.indexes.damage_reports.idx_damage_reports_occurrence_date, ['date_occurrence', 'id']);
    assert.deepEqual(state.indexes.damage_reports.idx_damage_farmer_crop, ['FarmerID', 'dmgCropType']);
    assert.deepEqual(state.indexes.farm_parcels.idx_farm_parcels_created_by, ['created_by']);
    assert.deepEqual(state.indexes.user.idx_user_username, ['UName']);
    assert.deepEqual(state.indexes.admin.idx_admin_name, ['AdminName']);

    const secondRun = execFileSync(php, [migrationRunner, '--apply'], { cwd: root, env, encoding: 'utf8' });
    assert.equal(secondRun.includes('[APPLYING]'), false);
    assert.equal(JSON.parse(fixtureAction('state')).migrations.length, 6);

    const mysqli = JSON.parse(execFileSync(php, [probe, 'mysqli'], { cwd: root, env, encoding: 'utf8' }));
    const pdo = JSON.parse(execFileSync(php, [probe, 'pdo'], { cwd: root, env, encoding: 'utf8' }));
    assert.deepEqual(mysqli, { database, charset: 'utf8mb4' });
    assert.deepEqual(pdo, { database, charset: 'utf8mb4' });

    assert.equal(execFileSync(php, [fixture, 'create-empty', baselineDatabase], { cwd: root, encoding: 'utf8' }).trim(), 'ready');
    baselineReady = true;
    execFileSync(mysql, ['-u', 'root', baselineDatabase], {
      cwd: root,
      input: fs.readFileSync(path.join(root, 'database/schema.sql')),
    });
    const baseline = JSON.parse(execFileSync(php, [fixture, 'baseline-state', baselineDatabase], { cwd: root, encoding: 'utf8' }));
    for (const table of ['admin', 'user', 'farmer', 'farm_parcels', 'parcel_details', 'crop_details', 'crop_reports', 'damage_reports', 'farmer_report_defaults', 'crop_catalog', 'posts', 'suggestions']) {
      assert.equal(baseline.tables.includes(table), true, `baseline table ${table} missing`);
      assert.equal(baseline.counts[table], 0, `baseline unexpectedly contains ${table} data`);
    }

    console.log('PASS: structure-only baseline import, ordered safe migrations, required schema, manual destructive migration exclusion, idempotent rerun, and factory connectivity.');
  } finally {
    if (baselineReady) console.log(`BASELINE CLEANUP: ${execFileSync(php, [fixture, 'cleanup', baselineDatabase], { cwd: root, encoding: 'utf8' }).trim()}`);
    if (ready) console.log(`CLEANUP: ${fixtureAction('cleanup')}`);
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
