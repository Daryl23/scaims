# SCAIMS k6 Stress Tests

These scripts were built from the repository routes and handlers, not guessed names.

Covered read-only routes:

- Public: `/index.html`, `/landing_data.php`, `/shared/crop_catalog_api.php`, `/FARMERS%20UI/farmerLanding/map.html`, `/FARMERS%20UI/farmerLanding/get_crop_reports.php`
- Farmer auth and pages: `/FARMERS%20UI/farmerLogin/farmerlogin.html`, `/FARMERS%20UI/farmerLogin/login.php`, `/FARMERS%20UI/farmersDashboard/home/home.php`, `/FARMERS%20UI/farmersDashboard/home/fetch_posts.php`, `/FARMERS%20UI/farmersDashboard/parcel/farmersFP.php`, `/FARMERS%20UI/farmersDashboard/parcel/farmParcel.php`, `/FARMERS%20UI/farmersDashboard/report/report.php`
- Admin auth and pages: `/ADMIN%20UI/adminLogin/adminLogin.html`, `/ADMIN%20UI/adminLogin/submitLogin.php`, `/ADMIN%20UI/adminDashboard/home/home.php`, `/ADMIN%20UI/adminDashboard/parcel/farmParcel.php`, `/ADMIN%20UI/adminDashboard/parcel/getFarmers.php`, `/ADMIN%20UI/adminDashboard/parcel/getParcelDetails.php`, `/ADMIN%20UI/adminDashboard/report/reportAnalytics.php`
- Admin reports: crop and damage report pages, table endpoints, detail endpoints, and graph endpoints under `/ADMIN%20UI/adminDashboard/report/`

Authentication notes:

- Login uses PHP sessions and the `PHPSESSID` cookie.
- No CSRF token was found in the inspected login/report AJAX callers.
- The current Farmer/Admin login handlers accept the seeded fixture credentials directly; no application-side development bypass is required.

Setup:

```powershell
Set-Location C:\xampp\htdocs\scaims
& "C:\xampp\php\php.exe" .\stress-tests\seed-test-accounts.php
```

Default seeded credentials:

```text
Farmer username: loadtest.farmer
Admin username: loadtest.admin
Password: ScaimsLoadTest#2026
```

Baseline home test:

```powershell
& "C:\Program Files\k6\k6.exe" run `
  --out json=.\stress-tests\results\baseline-home.ndjson `
  --summary-export .\stress-tests\results\baseline-home-summary.json `
  .\stress-tests\baseline-home.js

node .\stress-tests\analyze-k6-json.js .\stress-tests\results\baseline-home.ndjson
```

One-user realistic smoke test:

```powershell
& "C:\Program Files\k6\k6.exe" run `
  -e SMOKE=1 `
  --out json=.\stress-tests\results\readonly-smoke.ndjson `
  --summary-export .\stress-tests\results\readonly-smoke-summary.json `
  .\stress-tests\scaims-readonly-realistic.js

node .\stress-tests\analyze-k6-json.js .\stress-tests\results\readonly-smoke.ndjson
```

Realistic 50-user staged test:

```powershell
& "C:\Program Files\k6\k6.exe" run `
  --out json=.\stress-tests\results\readonly-staged.ndjson `
  --summary-export .\stress-tests\results\readonly-staged-summary.json `
  .\stress-tests\scaims-readonly-realistic.js

node .\stress-tests\analyze-k6-json.js .\stress-tests\results\readonly-staged.ndjson
```

The staged profile is:

- 5 users for 30s
- 10 users for 1m
- 25 users for 2m
- 50 users for 3m
- 10 users for 1m
- 0 users for 30s

Optional writer tests:

```powershell
& "C:\Program Files\k6\k6.exe" run -e ENABLE_WRITES=1 -e VUS=1 -e DURATION=30s .\stress-tests\report-submission-low-concurrency.js
& "C:\Program Files\k6\k6.exe" run -e ENABLE_WRITES=1 -e VUS=1 -e DURATION=30s .\stress-tests\image-upload-low-concurrency.js
```

The writer scripts are disabled unless `ENABLE_WRITES=1` is set. They use the isolated `loadtest.*` accounts. Start with one user before raising concurrency, especially for image uploads because the endpoint writes files under `uploads/reports/pending`.
