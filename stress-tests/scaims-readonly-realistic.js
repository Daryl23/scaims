import http from 'k6/http';
import { check, group, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || 'http://localhost/scaims').replace(/\/+$/, '');
const FARMER_USERNAME = __ENV.FARMER_USERNAME || 'loadtest.farmer';
const FARMER_PASSWORD = __ENV.FARMER_PASSWORD || 'ScaimsLoadTest#2026';
const ADMIN_USERNAME = __ENV.ADMIN_USERNAME || 'loadtest.admin';
const ADMIN_PASSWORD = __ENV.ADMIN_PASSWORD || 'ScaimsLoadTest#2026';
const SMOKE = __ENV.SMOKE === '1' || __ENV.SMOKE === 'true';
const PUBLIC_ONLY = __ENV.PUBLIC_ONLY === '1' || __ENV.PUBLIC_ONLY === 'true';

export const options = {
  scenarios: {
    scaims_readonly: SMOKE
      ? {
          executor: 'constant-vus',
          vus: Number(__ENV.VUS || 1),
          duration: __ENV.DURATION || '45s',
        }
      : {
          executor: 'ramping-vus',
          stages: [
            { duration: '30s', target: 5 },
            { duration: '1m', target: 10 },
            { duration: '2m', target: 25 },
            { duration: '3m', target: 50 },
            { duration: '1m', target: 10 },
            { duration: '30s', target: 0 },
          ],
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

function pause(min = 0.4, max = 1.4) {
  sleep(min + Math.random() * (max - min));
}

function getPage(path, endpoint, bodyPattern) {
  const res = http.get(url(path), {
    tags: { endpoint, kind: 'page', name: endpoint },
  });

  const checks = {
    [`${endpoint} returned HTTP 200`]: (r) => r.status === 200,
  };

  if (bodyPattern) {
    checks[`${endpoint} returned expected page content`] = (r) =>
      bodyPattern.test(r.body || '');
  }

  check(res, checks);
  return res;
}

function getApi(path, endpoint, bodyPattern) {
  const res = http.get(url(path), {
    headers: { Accept: 'application/json' },
    tags: { endpoint, kind: 'api', name: endpoint },
  });

  const checks = {
    [`${endpoint} returned HTTP 200`]: (r) => r.status === 200,
  };

  if (bodyPattern) {
    checks[`${endpoint} returned expected JSON/content`] = (r) =>
      bodyPattern.test(r.body || '');
  }

  check(res, checks);
  return res;
}

function postForm(path, endpoint, data, expectJson = true, redirects = undefined) {
  const params = {
    headers: {
      Accept: expectJson ? 'application/json' : 'text/html,application/xhtml+xml',
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': expectJson ? 'XMLHttpRequest' : '',
    },
    tags: { endpoint, kind: expectJson ? 'api' : 'auth', name: endpoint },
  };

  if (redirects !== undefined) {
    params.redirects = redirects;
  }

  const res = http.post(url(path), data, params);
  check(res, {
    [`${endpoint} returned expected status`]: (r) =>
      expectJson ? r.status === 200 : r.status >= 200 && r.status < 400,
  });
  return res;
}

function parseJson(res, endpoint) {
  try {
    return res.json();
  } catch (error) {
    check(res, {
      [`${endpoint} body is valid JSON`]: () => false,
    });
    return null;
  }
}

function firstReportIdFromTableResponse(data) {
  if (!data) {
    return null;
  }

  const html = `${data.newOutput || ''}\n${data.previousOutput || ''}`;
  const match = html.match(/data-id=['"]?(\d+)['"]?/i);
  return match ? match[1] : null;
}

function clearSession() {
  http.get(url('/FARMERS%20UI/farmersDashboard/shared/logout.php'), {
    redirects: 0,
    tags: { endpoint: 'session_logout', kind: 'auth', name: 'session_logout' },
  });
}

function farmerLogin() {
  getPage('/FARMERS%20UI/farmerLogin/farmerlogin.html', 'farmer_login_page', /Farmer|Password/i);

  const res = postForm(
    '/FARMERS%20UI/farmerLogin/login.php',
    'farmer_login_post',
    {
      username: FARMER_USERNAME,
      password: FARMER_PASSWORD,
      'g-recaptcha-response': '',
    },
    false,
    0,
  );

  const ok = check(res, {
    'farmer login redirected to dashboard': (r) =>
      r.status === 302 &&
      String(r.headers.Location || '').includes('farmersDashboard/home/home.php'),
  });

  return ok;
}

function adminLogin() {
  getPage('/ADMIN%20UI/adminLogin/adminLogin.html', 'admin_login_page', /Admin|Password/i);

  const res = postForm(
    '/ADMIN%20UI/adminLogin/submitLogin.php',
    'admin_login_post',
    {
      adminID: ADMIN_USERNAME,
      password: ADMIN_PASSWORD,
      'g-recaptcha-response': '',
    },
    false,
    0,
  );

  const ok = check(res, {
    'admin login redirected to dashboard': (r) =>
      r.status === 302 &&
      String(r.headers.Location || '').includes('adminDashboard/home/home.php'),
  });

  return ok;
}

function publicScenario() {
  group('public home and map', () => {
    getPage('/index.html', 'public_home', /SCAIMS|APGORRS|Agricultural/i);
    pause();
    getApi('/landing_data.php', 'landing_data', /stats|posts|suggestions|success/i);
    pause();
    getApi('/shared/crop_catalog_api.php', 'crop_catalog_api', /success|allCrops|groups/i);
    pause();
    getPage('/FARMERS%20UI/farmerLanding/map.html', 'public_crop_map_page', /Crop Map/i);
    pause();

    const mapData = getApi(
      '/FARMERS%20UI/farmerLanding/get_crop_reports.php',
      'public_crop_map_data',
      /FeatureCollection|features|meta/i,
    );
    const parsed = parseJson(mapData, 'public_crop_map_data');
    check(mapData, {
      'public crop map data has feature array': () =>
        Boolean(parsed && Array.isArray(parsed.features)),
    });
  });
}

function farmerScenario() {
  group('farmer login dashboard sakahan and reports', () => {
    if (!farmerLogin()) {
      return;
    }

    pause();
    getPage('/FARMERS%20UI/farmersDashboard/home/home.php', 'farmer_dashboard_home', /Farmers Dashboard|LATEST UPDATES/i);
    pause();

    const posts = getApi('/FARMERS%20UI/farmersDashboard/home/fetch_posts.php', 'farmer_dashboard_posts', /^\s*\[/);
    check(posts, {
      'farmer posts response is JSON array': (r) => Array.isArray(parseJson(r, 'farmer_dashboard_posts')),
    });
    pause();

    getPage('/FARMERS%20UI/farmersDashboard/parcel/farmersFP.php', 'farmer_sakahan_list', /Farmers Parcel|Parcel/i);
    pause();
    getPage('/FARMERS%20UI/farmersDashboard/parcel/farmParcel.php', 'farmer_sakahan_form', /ADD FARM PARCEL|Parcel Information/i);
    pause();
    getPage('/FARMERS%20UI/farmersDashboard/report/report.php', 'farmer_reports_page', /REPORT FORM|Submitted Reports|Mga Ulat/i);
    pause();
    getPage('/FARMERS%20UI/farmersDashboard/report/report.php?sort=oldest', 'farmer_reports_oldest', /REPORT FORM|Submitted Reports|Mga Ulat/i);
    pause();
    clearSession();
  });
}

function adminScenario() {
  group('admin reports map data and parcel viewing', () => {
    if (!adminLogin()) {
      return;
    }

    pause();
    getPage('/ADMIN%20UI/adminDashboard/home/home.php', 'admin_dashboard_home', /Admin Dashboard|TOTAL REPORTS/i);
    pause();
    getPage('/ADMIN%20UI/adminDashboard/parcel/farmParcel.php', 'admin_sakahan_page', /Admin Farm Parcel|Number of Parcels/i);
    pause();

    const farmersRes = getApi('/ADMIN%20UI/adminDashboard/parcel/getFarmers.php', 'admin_sakahan_farmers', /farmers/i);
    const farmers = parseJson(farmersRes, 'admin_sakahan_farmers');
    const farmerId = farmers && Array.isArray(farmers.farmers) && farmers.farmers.length
      ? farmers.farmers[0].FarmerID
      : 1;
    pause();
    getApi(`/ADMIN%20UI/adminDashboard/parcel/getParcelDetails.php?farmerID=${farmerId}`, 'admin_sakahan_details', /farmerData|totalFarmArea|error/i);
    pause();

    getPage('/ADMIN%20UI/adminDashboard/report/reportAnalytics.php', 'admin_reports_overview', /Admin Reports|CROP REPORTS|DAMAGE REPORTS/i);
    pause();

    getPage('/ADMIN%20UI/adminDashboard/report/crop/cropReports.php', 'admin_crop_reports_page', /Crop Harvest Reports|NEW CROP/i);
    pause();
    const cropTableRes = postForm('/ADMIN%20UI/adminDashboard/report/crop/fetchCropReports.php', 'admin_crop_reports_table', {
      filter: '',
      barangay: '',
      reporterType: '',
      search: '',
      month: '',
      year: '',
      page: '1',
    });
    const cropTable = parseJson(cropTableRes, 'admin_crop_reports_table');
    check(cropTableRes, {
      'admin crop table includes report counts': () =>
        Boolean(cropTable && Number.isFinite(Number(cropTable.total))),
    });
    const cropReportId = firstReportIdFromTableResponse(cropTable);
    if (cropReportId) {
      pause();
      const detailRes = postForm('/ADMIN%20UI/adminDashboard/report/crop/getCropReports.php', 'admin_crop_report_details', {
        id: cropReportId,
      });
      const detail = parseJson(detailRes, 'admin_crop_report_details');
      check(detailRes, {
        'admin crop detail contains id': () => Boolean(detail && String(detail.id) === String(cropReportId)),
      });
    }
    pause();
    postForm('/ADMIN%20UI/adminDashboard/report/crop/fetchAvailableYears.php', 'admin_crop_available_years_rice', {
      cropType: 'Rice',
    });
    pause();
    const cropGraphRes = postForm('/ADMIN%20UI/adminDashboard/report/crop/fetchGraphData.php', 'admin_crop_graph_rice', {
      cropType: 'Rice',
      year: '2026',
    });
    check(cropGraphRes, {
      'admin crop graph returns success payload': (r) => /success|yearlyData/.test(r.body || ''),
    });
    pause();

    getPage('/ADMIN%20UI/adminDashboard/report/damage/damageReports.php', 'admin_damage_reports_page', /Crop Damage Reports|NEW CROP DAMAGE/i);
    pause();
    const damageTableRes = postForm('/ADMIN%20UI/adminDashboard/report/damage/fetchDamageReports.php', 'admin_damage_reports_table', {
      filter: '',
      barangay: '',
      reporterType: '',
      search: '',
      page: '1',
      filterDate: '',
    });
    const damageTable = parseJson(damageTableRes, 'admin_damage_reports_table');
    check(damageTableRes, {
      'admin damage table includes report counts': () =>
        Boolean(damageTable && Number.isFinite(Number(damageTable.total))),
    });
    const damageReportId = firstReportIdFromTableResponse(damageTable);
    if (damageReportId) {
      pause();
      const damageDetailRes = postForm('/ADMIN%20UI/adminDashboard/report/damage/getDamageReports.php', 'admin_damage_report_details', {
        id: damageReportId,
      });
      const damageDetail = parseJson(damageDetailRes, 'admin_damage_report_details');
      check(damageDetailRes, {
        'admin damage detail contains id': () =>
          Boolean(damageDetail && String(damageDetail.id) === String(damageReportId)),
      });
    }
    pause();
    const damageGraphRes = postForm('/ADMIN%20UI/adminDashboard/report/damage/getGraphData.php', 'admin_damage_graph', {
      barangay: '',
      filterDate: '',
      filter: '',
    });
    check(damageGraphRes, {
      'admin damage graph returns success payload': (r) => /success|data/.test(r.body || ''),
    });

    clearSession();
  });
}

export default function () {
  clearSession();
  publicScenario();

  if (!PUBLIC_ONLY) {
    farmerScenario();
    adminScenario();
  }

  pause(1, 2);
}
