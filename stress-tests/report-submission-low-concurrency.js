import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || 'http://localhost/scaims').replace(/\/+$/, '');
const FARMER_USERNAME = __ENV.FARMER_USERNAME || 'loadtest.farmer';
const FARMER_PASSWORD = __ENV.FARMER_PASSWORD || 'ScaimsLoadTest#2026';
const ENABLE_WRITES = __ENV.ENABLE_WRITES === '1' || __ENV.ENABLE_WRITES === 'true';

export const options = {
  scenarios: {
    isolated_report_submission: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 1),
      duration: __ENV.DURATION || '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{kind:page}': ['p(95)<2000'],
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
      'This script writes isolated load-test report rows. Re-run with -e ENABLE_WRITES=1 after running seed-test-accounts.php.',
    );
  }
}

export default function () {
  if (!login()) {
    return;
  }

  const unique = `LOADTEST-${Date.now()}-${__VU}-${__ITER}`;
  const res = http.post(
    url('/FARMERS%20UI/farmersDashboard/report/report_submit.php'),
    {
      'report-type': 'crop',
      'crop-location': 'adya',
      'crop-commodity': 'grains',
      'crop-type': 'Rice',
      'other-crop-type': '',
      'crop-variety': unique,
      'date-planted': '2026-08-01',
      'harvest-date': '2026-09-01',
      'estimate-harvest': '12.5',
      'crop-status': 'Bearing',
      'farm-gate-price': '30.25',
      'number-of-trees': 'N/A',
      'crop-latitude': '13.9180000',
      'crop-longitude': '121.1450000',
    },
    {
      redirects: 0,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      tags: { endpoint: 'farmer_crop_report_submit', kind: 'api', name: 'farmer_crop_report_submit' },
    },
  );

  check(res, {
    'isolated crop report submit redirected back to report page': (r) =>
      r.status === 302 && String(r.headers.Location || '').includes('report.php'),
  });

  http.get(url('/FARMERS%20UI/farmersDashboard/shared/logout.php'), {
    redirects: 0,
    tags: { endpoint: 'session_logout', kind: 'auth', name: 'session_logout' },
  });

  sleep(2);
}
