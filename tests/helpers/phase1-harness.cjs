const { request } = require('playwright');
const { spawn, execFileSync } = require('node:child_process');
const crypto = require('node:crypto');
const http = require('node:http');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const fixtureScript = path.join(__dirname, 'phase1-fixtures.php');
const router = path.join(__dirname, 'phase1-router.php');

class Phase1Harness {
  constructor(name, port) {
    this.name = name;
    this.port = port;
    this.base = `http://127.0.0.1:${port}`;
    this.database = `scaims_phase1_test_${Date.now()}_${process.pid}`;
    this.key = crypto.randomBytes(24).toString('hex');
    this.server = null;
    this.ready = false;
    this.contexts = [];
    this.log = '';
  }

  fixture(action, ...args) {
    return execFileSync(php, [fixtureScript, action, this.database, ...args.map(String)], {
      cwd: root,
      encoding: 'utf8',
    }).trim();
  }

  state() {
    return JSON.parse(this.fixture('state'));
  }

  async start() {
    this.fixture('setup');
    this.ready = true;
    this.server = spawn(php, [
      '-d', 'upload_max_filesize=6M',
      '-d', 'post_max_size=16M',
      '-S', `127.0.0.1:${this.port}`,
      '-t', root,
      router,
    ], {
      cwd: root,
      env: {
        ...process.env,
        SCAIMS_PHASE1_TEST_DB: this.database,
        SCAIMS_PHASE1_TEST_KEY: this.key,
      },
      windowsHide: true,
      stdio: ['ignore', 'ignore', 'pipe'],
    });
    this.server.stderr.on('data', data => { this.log += data.toString(); });

    let started = false;
    for (let attempt = 0; attempt < 40; attempt++) {
      try {
        const status = await new Promise((resolve, reject) => {
          const probe = http.get(this.base + '/index.html', response => {
            response.resume();
            response.on('end', () => resolve(response.statusCode));
          });
          probe.on('error', reject);
          probe.setTimeout(1000, () => probe.destroy(new Error('probe timeout')));
        });
        if (status >= 200 && status < 500) { started = true; break; }
      } catch {}
      await new Promise(resolve => setTimeout(resolve, 200));
    }
    if (!started) throw new Error(`Phase 1 PHP server did not start on ${this.port}.\n${this.log}`);
  }

  async newContext() {
    const context = await request.newContext({ baseURL: this.base });
    this.contexts.push(context);
    return context;
  }

  async session(context) {
    const response = await context.get('/__phase1/session', {
      headers: { 'X-Phase1-Test-Key': this.key },
    });
    return response.json();
  }

  async createExpiredToken(context) {
    const response = await context.get('/__phase1/create-expired-token', {
      headers: { 'X-Phase1-Test-Key': this.key },
    });
    return response.json();
  }

  async stop() {
    for (const context of this.contexts.splice(0)) {
      await context.dispose().catch(() => {});
    }
    if (this.server) {
      this.server.kill();
      this.server = null;
    }
    if (this.ready) {
      const result = this.fixture('cleanup');
      this.ready = false;
      return result;
    }
    return 'not-started';
  }
}

async function loginFarmer(context, username = 'TEST-FARMER-001', password = 'SCAIMS-Test-Only#2026') {
  return context.post('/FARMERS%20UI/farmerLogin/login.php', {
    form: { username, password },
    maxRedirects: 0,
  });
}

async function loginAdmin(context, username = 'TEST-ADMIN-001', password = 'SCAIMS-Test-Only#2026') {
  return context.post('/ADMIN%20UI/adminLogin/submitLogin.php', {
    form: { adminID: username, password },
    maxRedirects: 0,
  });
}

function assertRedirect(assert, response, fragment) {
  assert.equal(response.status(), 302);
  assert.match(String(response.headers().location || ''), new RegExp(fragment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
}

module.exports = {
  Phase1Harness,
  assertRedirect,
  loginAdmin,
  loginFarmer,
  root,
};

