import encoding from 'k6/encoding';
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || 'http://localhost/scaims').replace(/\/+$/, '');
const FARMER_USERNAME = __ENV.FARMER_USERNAME || 'loadtest.farmer';
const FARMER_PASSWORD = __ENV.FARMER_PASSWORD || 'ScaimsLoadTest#2026';
const ENABLE_WRITES = __ENV.ENABLE_WRITES === '1' || __ENV.ENABLE_WRITES === 'true';
const ONE_BY_ONE_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';

export const options = {
  scenarios: {
    isolated_image_upload: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 1),
      duration: __ENV.DURATION || '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{kind:api}': ['p(95)<2000'],
    checks: ['rate>0.99'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'max', 'count'],
};

function url(path) {
  return `${BASE_URL}${path}`;
}

function login() {
  const res = http.post(
    url('/FARMERS%20UI/farmerLogin/login.php'),
    {
      username: FARMER_USERNAME,
      password: FARMER_PASSWORD,
      'g-recaptcha-response': '',
    },
    {
      redirects: 0,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      tags: { endpoint: 'farmer_login_post', kind: 'auth', name: 'farmer_login_post' },
    },
  );

  return check(res, {
    'farmer login redirected to dashboard': (r) =>
      r.status === 302 &&
      String(r.headers.Location || '').includes('farmersDashboard/home/home.php'),
  });
}

export function setup() {
  if (!ENABLE_WRITES) {
    throw new Error(
      'This script writes tiny pending image files under uploads/reports/pending. Re-run with -e ENABLE_WRITES=1 after running seed-test-accounts.php.',
    );
  }
}

export default function () {
  if (!login()) {
    return;
  }

  const imageBytes = encoding.b64decode(ONE_BY_ONE_PNG, 'rawstd');
  const payload = {
    report_image: http.file(imageBytes, `scaims-loadtest-${__VU}-${__ITER}.png`, 'image/png'),
  };

  const res = http.post(url('/shared/report_image_stage.php'), payload, {
    tags: { endpoint: 'report_image_stage', kind: 'api', name: 'report_image_stage' },
  });

  check(res, {
    'image stage returned HTTP 200': (r) => r.status === 200,
    'image stage returned token': (r) => /"success"\s*:\s*true/.test(r.body || '') && /"token"/.test(r.body || ''),
  });

  http.get(url('/FARMERS%20UI/farmersDashboard/shared/logout.php'), {
    redirects: 0,
    tags: { endpoint: 'session_logout', kind: 'auth', name: 'session_logout' },
  });

  sleep(2);
}
