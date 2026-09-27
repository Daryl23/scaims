import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || 'http://localhost/scaims').replace(/\/+$/, '');
const SMOKE = __ENV.SMOKE === '1' || __ENV.SMOKE === 'true';

export const options = {
  scenarios: {
    baseline_home: SMOKE
      ? {
          executor: 'constant-vus',
          vus: Number(__ENV.VUS || 1),
          duration: __ENV.DURATION || '15s',
        }
      : {
          executor: 'constant-vus',
          vus: Number(__ENV.VUS || 1),
          duration: __ENV.DURATION || '1m',
        },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{kind:page}': ['p(95)<2000'],
    checks: ['rate>0.99'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'max', 'count'],
};

export default function () {
  const res = http.get(`${BASE_URL}/index.html`, {
    tags: {
      endpoint: 'public_home',
      kind: 'page',
      name: 'public_home',
    },
  });

  check(res, {
    'public home returned HTTP 200': (r) => r.status === 200,
    'public home contains SCAIMS/APGORRS content': (r) =>
      /SCAIMS|APGORRS|Agricultural/i.test(r.body || ''),
  });

  sleep(1);
}
