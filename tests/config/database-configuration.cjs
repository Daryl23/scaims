const assert = require('node:assert/strict');
const { execFileSync, spawnSync } = require('node:child_process');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const probe = path.join(__dirname, 'database-probe.php');
const fixture = path.join(root, 'tests/database/migration-fixtures.php');
const database = `scaims_phase2_test_${Date.now()}_${process.pid}`;

function cleanEnvironment(extra = {}) {
  const env = { ...process.env };
  for (const key of ['SCAIMS_DB_HOST', 'SCAIMS_DB_PORT', 'SCAIMS_DB_NAME', 'SCAIMS_DB_USER', 'SCAIMS_DB_PASSWORD', 'SCAIMS_DB_PASS']) delete env[key];
  return { ...env, ...extra };
}

function runProbe(mode, env) {
  return JSON.parse(execFileSync(php, [probe, mode], { cwd: root, env, encoding: 'utf8' }));
}

(async () => {
  let ready = false;
  try {
    execFileSync(php, [fixture, 'setup', database], { cwd: root });
    ready = true;

    const defaults = runProbe('config', cleanEnvironment());
    assert.equal(defaults.host, 'localhost');
    assert.equal(defaults.port, 3306);
    assert.equal(defaults.user, 'root');
    assert.equal(defaults.password_set, false);

    const overridden = cleanEnvironment({
      SCAIMS_DB_HOST: '127.0.0.1',
      SCAIMS_DB_PORT: '3306',
      SCAIMS_DB_NAME: database,
      SCAIMS_DB_USER: 'root',
      SCAIMS_DB_PASSWORD: '',
    });
    const config = runProbe('config', overridden);
    assert.deepEqual(config, { host: '127.0.0.1', port: 3306, name: database, user: 'root', password_set: false });
    assert.deepEqual(runProbe('mysqli', overridden), { database, charset: 'utf8mb4' });
    assert.deepEqual(runProbe('pdo', overridden), { database, charset: 'utf8mb4' });

    const constants = runProbe('mysqli', cleanEnvironment({
      SCAIMS_DB_NAME: 'environment_value_must_not_win',
      SCAIMS_DB_PORT: 'invalid-environment-port-must-not-win',
      SCAIMS_PROBE_CONSTANT_DB: database,
      SCAIMS_PROBE_CONSTANT_HOST: '127.0.0.1',
      SCAIMS_PROBE_CONSTANT_PORT: '3306',
      SCAIMS_PROBE_CONSTANT_USER: 'root',
      SCAIMS_PROBE_CONSTANT_PASSWORD: '',
    }));
    assert.equal(constants.database, database);

    const invalidPort = spawnSync(php, [probe, 'config'], {
      cwd: root,
      env: cleanEnvironment({ SCAIMS_DB_PORT: 'not-a-port' }),
      encoding: 'utf8',
    });
    assert.notEqual(invalidPort.status, 0);
    assert.match(invalidPort.stderr, /Invalid database port configuration/);

    const secretMarker = 'phase2-secret-must-not-appear';
    const badConnection = spawnSync(php, [probe, 'mysqli'], {
      cwd: root,
      env: cleanEnvironment({
        SCAIMS_DB_HOST: '127.0.0.1',
        SCAIMS_DB_PORT: '3306',
        SCAIMS_DB_NAME: `missing_${secretMarker}`,
        SCAIMS_DB_USER: 'root',
        SCAIMS_DB_PASSWORD: secretMarker,
      }),
      encoding: 'utf8',
    });
    assert.notEqual(badConnection.status, 0);
    assert.equal((badConnection.stdout + badConnection.stderr).includes(secretMarker), false);
    assert.match(badConnection.stderr, /Database connection failed/);

    console.log('PASS: defaults, environment/constant overrides, MySQLi/PDO utf8mb4 factories, and redacted invalid configuration behavior.');
  } finally {
    if (ready) {
      const result = execFileSync(php, [fixture, 'cleanup', database], { cwd: root, encoding: 'utf8' }).trim();
      console.log(`CLEANUP: ${result}`);
    }
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
