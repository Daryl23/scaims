# SCAIMS Phase 1 Test Baseline

Date: 2026-09-28  
Branch: `main`  
Status: Complete for the Phase 1 critical/high-priority baseline described below

## 1. Purpose and boundaries

This phase records and protects current SCAIMS behavior before refactoring. It adds black-box and integration characterization tests; it does not refactor application code, change routes, alter the production schema, or fix defects found by the tests.

The baseline is intentionally risk-weighted. It concentrates on authentication and authorization, report ownership, Harvest/Damage writes, GPS evidence, Admin-on-behalf reporting, Farmer data writes, public map data, and basic content workflows. It is not an attempt at 100% code coverage or presentational snapshot coverage.

## 2. Git state before Phase 1

- Current branch: `main`, tracking `origin/main`.
- Pre-existing working-tree item: untracked `docs/CODEBASE_AUDIT.md` from Phase 0.
- No staged files and no other modified or untracked files were present when Phase 1 began.
- The Phase 0 audit was preserved and was not edited during Phase 1.

## 3. Existing test infrastructure discovered

| File / area | Type | Existing purpose |
|---|---|---|
| `tests/gps-stamp-parser.cjs` | Node assertions | GPS stamp formats, dates, time conversion, coordinates, ambiguity, confidence |
| `tests/gps-stamp-ocr.cjs` | Node + real Tesseract OCR | Generates two stamped fixtures and verifies OCR/parser results and original bytes |
| `tests/gps-photo-workflow.cjs` | Playwright browser/integration | Farmer/Admin GPS upload, report creation, ownership, Admin-on-behalf, image lifecycle, edit/cancel, responsive views |
| `tests/gps-photo-fixtures.php` | PHP fixture helper | Clones schema into a disposable database, seeds GPS workflow accounts, and cleans records/files/database |
| `tests/gps-photo-router.php` | PHP test router | Routes an isolated PHP server to the disposable GPS database and provides fixture sessions |
| `tests/registration-wizard.cjs` | Playwright browser test | Registration wizard validation, navigation, payload, retry, redirect, mobile behavior |
| `stress-tests/*.js` | k6 | Baseline home, realistic read-only traffic, low-concurrency image upload and report submission |
| `stress-tests/seed-test-accounts.php` | PHP helper | Explicit load-test account setup/cleanup |

Installed k6 was detected as `v2.2.0`. No k6 write scenario was run in Phase 1.

## 4. Pre-Phase-1 baseline results

All existing relevant suites passed before adding the new tests:

| Suite | Result before changes |
|---|---|
| GPS parser | PASS — 21 assertions |
| GPS OCR | PASS — both Format A and Format B through real Tesseract |
| GPS Farmer/Admin workflow | PASS — 13 workflow groups |
| Registration wizard | PASS |

The first attempts to launch the browser/PHP child-process tests inside the restricted runner returned `spawn EPERM`. This was an execution-sandbox limitation, not an application failure. The same unchanged suites passed when allowed to launch their local processes.

## 5. Phase 1 test architecture

The new suites extend the existing disposable-database pattern rather than introduce a competing framework:

```text
Node characterization test
        |
        +-- Phase1Harness (Playwright API request contexts)
        |       |
        |       +-- isolated PHP built-in server
        |       +-- isolated PHP session directory
        |
        +-- phase1-fixtures.php
                |
                +-- clone table structures from configured SCAIMS DB
                +-- seed TEST-* identities and minimum domain data
                +-- inspect test DB state
                +-- remove owned files/sessions
                +-- drop disposable database
```

The tests call the real PHP routes through HTTP and authenticate through the real login handlers. Test-only endpoints expose only the current isolated session and create an expired token fixture; both require a random per-run header key and only exist in the isolated test router.

### New shared helpers

- `tests/helpers/phase1-harness.cjs`
- `tests/helpers/phase1-fixtures.php`
- `tests/helpers/phase1-router.php`

### New domain suites

- `tests/auth/auth-characterization.cjs`
- `tests/reports/report-lifecycle-characterization.cjs`
- `tests/parcels/parcel-characterization.cjs`
- `tests/profile/profile-characterization.cjs`
- `tests/content/posts-suggestions-characterization.cjs`
- `tests/map/public-map-characterization.cjs`

## 6. Test database and fixture strategy

Every new suite creates a uniquely named database matching:

```text
scaims_phase1_test_<timestamp>_<process-id>
```

The fixture manager:

1. opens the normal configured database only to read its table definitions;
2. creates a new database;
3. uses `CREATE TABLE ... LIKE` to preserve the current schema contract;
4. switches all tested application connections to that disposable database through constants set by the isolated router;
5. seeds only named Phase 1 fixtures;
6. inspects assertions against that test database;
7. removes files created by the run; and
8. drops only the validated disposable database name.

The cleanup helper rejects database names outside the strict Phase 1 prefix. It never truncates or drops the development database.

### Fixture identities

| Identity | Current behavior under test |
|---|---|
| `TEST-FARMER-001` | active owner Farmer |
| `TEST-FARMER-002` | active different owner |
| `TEST-FARMER-INACTIVE` | inactive Farmer |
| `TEST-ADMIN-001` | normal Admin |
| `TEST-SUPERADMIN-001` | Super Admin |
| `TEST-ADMIN-INACTIVE` | inactive Admin |

All use a test-only password hash compatible with `password_verify()`. No real account credentials or records are used.

## 7. Filesystem isolation and cleanup

- PHP sessions are stored in `tests/phase1-results/<database>-sessions`, not the normal PHP session directory.
- GPS uploads pass through the real `uploads/reports/pending`, `harvest`, and `damage` paths because that path behavior is itself under test.
- Test-created legacy images and post images contain the disposable database marker in their filename.
- Cleanup removes only paths referenced by the disposable database, pending paths referenced by its isolated sessions, or files bearing that unique marker.
- It then removes the isolated sessions and drops the disposable database.
- Final verification found no Phase 1/GPS/registration disposable databases, listening test servers, or marker upload files left behind.

## 8. Workflows protected

### Authentication and authorization

- Farmer valid login.
- Farmer invalid password, nonexistent username, and inactive Farmer.
- Authenticated Farmer dashboard access and unauthenticated redirect.
- Farmer logout, session clearing, and post-logout guard.
- Farmer report-list ownership filtering.
- Cross-Farmer report update rejection and unchanged database row.
- Admin valid login, invalid password, nonexistent Admin, and inactive Admin.
- Normal Admin dashboard access.
- Super Admin session role and Crop Management access.
- Normal Admin denial for Crop Management page and mutation endpoint.
- Current Admin “logout” behavior (login-page navigation does not clear the session).

### Reports and GPS evidence

- Farmer Harvest creation and owner resolution.
- Farmer Damage creation.
- Admin-on-behalf Harvest and Damage, selected `FarmerID`, Admin `created_by`, and visibility in both portals (existing GPS workflow).
- Invalid/missing Farmer in Admin-on-behalf submission.
- GPS OCR success/failure and no manual metadata injection.
- JPG, PNG, and WEBP upload paths.
- MIME spoof, oversized input, invalid/corrupt image, missing image, and unauthenticated staging.
- Session ownership for pending tokens and wrong-session rejection.
- Pending token preservation after a validation failure, enabling retry.
- Finalization from pending to Harvest/Damage storage.
- Original file bytes and persisted image path.
- GPS date/time/address/latitude/longitude persistence.
- Used-token rejection.
- Expired-token rejection and current orphan-file behavior.
- Same-ID report update with no duplicate row.
- Cross-Farmer edit rejection.
- Image unchanged when not replaced.
- GPS image replacement and old-image cleanup.
- Current refusal to remove modern GPS evidence.
- Legacy image removal and file cleanup.
- UI Cancel restoring the original preview without saving.
- Farmer report history and Admin detail visibility.

Harvest and Damage remain separate assertions. Damage occurrence date is characterized as coming from the GPS capture date; Harvest retains its separate planted/harvest form dates.

### Parcel and profile writes

- Farm/parcel/crop creation through the real transaction path.
- Parcel load and edit with the same parcel identity.
- Crop association replacement.
- Required-field failure without new records.
- Current cross-owner parcel edit-page read behavior.
- Basic Farmer profile update.
- Profile image BLOB update.
- Farmer/User relationship preservation.
- Password update and old/new password login behavior.
- Username change, session update, and `created_by` rewrite across farm parcels, parcel details, crop details, Harvest reports, and Damage reports.

### Posts and suggestions

- Admin post creation with PNG upload.
- Post list/view route.
- Post text update.
- Post deletion and current physical-file behavior.
- Public suggestion submission through root `submit_suggestion.php`.
- Canonical Admin implementation under `ADMIN UI/adminDashboard/suggestions/`.
- New/read list, mark-read, and delete.

### Public map

- Valid GeoJSON `FeatureCollection` response.
- Lipa City/Mataas na Kahoy scope enforcement.
- Unsupported Manila fixture exclusion.
- Required feature properties.
- Valid point coordinates.
- Saved coordinate source and current barangay-center fallback behavior.
- Harvest/Damage association (`max_damage`, `has_damage`).
- Barangay, LGU, crop-type, and unsupported-location filters.
- Current absence of boundary geometry in the response contract.

### Registration and responsive behavior

Existing tests continue to cover the registration wizard and the Farmer/Admin GPS report interfaces at desktop (1366 px), tablet (768 px), and mobile (390 px) widths. Phase 1 does not add pixel-perfect UI snapshots outside these high-risk flows.

## 9. Route and response contract snapshot

This table records the contracts relied on by the tests. Response inconsistency is current behavior, not a recommendation.

| Route | Method / required input | Auth | Current success | Current failure | Important side effects |
|---|---|---|---|---|---|
| `FARMERS UI/farmerLogin/login.php` | POST `username`, `password` | Public | 302 to Farmer home; sets `UserID`, `UName`, name/image session values | HTTP 200 script alert/redirect text | Reads `user` + active `farmer` |
| `FARMERS UI/farmersDashboard/shared/logout.php` | GET | Farmer session | 302 to Farmer login | N/A | Clears session array/cookie and destroys session |
| `ADMIN UI/adminLogin/submitLogin.php` | POST `adminID`, `password` | Public | 302 to Admin home; sets `AdminID`, `AdminName`, `AdminRole`, `IsSuperAdmin` | HTTP 200 script alert/redirect text | Reads active Admin and may ensure role column |
| `ADMIN UI/adminLogin/adminLogin.html` | GET | Public | HTML login page | N/A | Does **not** clear an existing Admin session |
| `ADMIN UI/adminDashboard/crops/cropManagement.php` | GET | Super Admin | HTML page | 302 normal Admin to Admin home | Reads crop catalog |
| `ADMIN UI/adminDashboard/crops/cropActions.php` | POST/GET action fields | Super Admin | JSON `{success,...}` | Normal Admin 302; validation JSON | Mutates crop catalog for authorized actions |
| `shared/report_image_stage.php` | POST multipart `report_image` | Farmer or Admin | JSON `{success:true, token, photo}` | 400 validation, 401 auth, 422 OCR, or 503 OCR unavailable | Writes pending image and session token; invokes OCR |
| `FARMERS UI/farmersDashboard/report/report_submit.php` | POST form; `report-type`, pending token, type-specific fields | Farmer | 302 to `report.php` | 302 to `report.php` with session message | Resolves Farmer from session; finalizes image; inserts Harvest/Damage and GPS metadata |
| `FARMERS UI/farmersDashboard/report/report_update.php` | POST `report-type`, `report-id`, fields; optional edit token/removal | Farmer owner | JSON `{success:true,message,report}` | JSON + HTTP 400/403/404/500 | Same-ID update; optional image replace/removal; ownership-qualified lookup/update |
| `ADMIN UI/adminDashboard/report/report_submit.php` | POST `reporting-for`, `report-type`, pending token, type fields | Admin | 302 to `reportAnalytics.php` | 302 with session message | Inserts selected Farmer as owner and Admin name as `created_by`; finalizes image |
| `FARMERS UI/farmersDashboard/parcel/submitFarmInfo.php` | POST farm/parcel/crop indexed fields | Farmer | JSON `{success:true,message}` | JSON `{success:false,message}` | Transaction inserts farm, parcel, crops |
| `FARMERS UI/farmersDashboard/parcel/editParcel.php?parcel_id=` | GET/POST parcel and crop fields | Farmer | HTML on GET; JSON for XHR POST | Redirect if unauthenticated; in-page/HTML behavior otherwise | Updates parcel/farm and replaces associated crop rows |
| `FARMERS UI/farmersDashboard/profile/updateProfile.php` | POST identity/contact fields; optional image/password/username | Farmer | JSON success | Plain-text early auth/password errors or JSON validation error | Updates Farmer/User; may rewrite related `created_by` values |
| `ADMIN UI/adminDashboard/post/create_post.php` | POST `post_content`, multipart `post_images[]` | Admin | 302 to `post.php` | Inline/plain error output | Inserts post; writes allowed JPEG/PNG/GIF files |
| `ADMIN UI/adminDashboard/post/update_post.php` | POST `post_id`, `post_content` | Admin | 302 to `post.php` | No stable JSON error contract | Updates escaped/formatted content |
| `ADMIN UI/adminDashboard/post/delete_post.php` | POST `post_id` | Admin | JSON `{success:true}` | JSON `{success:false}` | Deletes row; currently does not delete image file |
| `submit_suggestion.php` | POST `name`, `suggestion` | Public | JSON `{success:true}` | JSON `{success:false,error}` | Inserts suggestion with default/new status |
| `ADMIN UI/adminDashboard/suggestions/get_suggestions.php` | GET `type`, optional `months` | Admin | JSON array | JSON error object | Reads new/read suggestions |
| `ADMIN UI/adminDashboard/suggestions/update_suggestion.php` | POST JSON `id`, `action` | Admin | JSON `{success:true}` | JSON `{success:false,error}` | Marks read or deletes |
| `FARMERS UI/farmerLanding/get_crop_reports.php` | GET optional filter query | Public | GeoJSON FeatureCollection with `features` and `meta` | JSON error + 500 for DB failure | Reads reports/Farmers/Damage and applies supported-area scope/fallback coordinates |

## 10. Commands to run

These commands assume PowerShell from the repository root. The bundled Node runtime can be replaced with a system Node installation if it has Playwright available.

```powershell
$env:NODE_PATH='C:/Users/masil/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'
$env:PHP_BINARY='C:/xampp/php/php.exe'
$node='C:/Users/masil/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node.exe'

& $node tests/gps-stamp-parser.cjs
& $node tests/gps-stamp-ocr.cjs
& $node tests/gps-photo-workflow.cjs
& $node tests/registration-wizard.cjs
& $node tests/auth/auth-characterization.cjs
& $node tests/reports/report-lifecycle-characterization.cjs
& $node tests/parcels/parcel-characterization.cjs
& $node tests/profile/profile-characterization.cjs
& $node tests/content/posts-suggestions-characterization.cjs
& $node tests/map/public-map-characterization.cjs
```

Run OCR before `report-lifecycle-characterization.cjs`, because OCR creates the stamped test fixture used by the lifecycle suite.

PHP helper syntax checks:

```powershell
& 'C:/xampp/php/php.exe' -l tests/helpers/phase1-fixtures.php
& 'C:/xampp/php/php.exe' -l tests/helpers/phase1-router.php
```

The process-launching suites may need permission to start local PHP/Chrome processes in a sandboxed agent environment.

## 11. Final test results

Final run after all Phase 1 additions:

| Suite | Final result |
|---|---|
| GPS parser | PASS — 21 assertions |
| GPS OCR | PASS — 2 real OCR layouts |
| GPS Farmer/Admin workflow | PASS — 13 workflow groups |
| Registration wizard | PASS |
| Authentication/authorization | PASS |
| Report/image lifecycle | PASS |
| Parcel characterization | PASS |
| Profile characterization | PASS |
| Posts/suggestions | PASS |
| Public map | PASS |
| PHP helper syntax | PASS |

No flaky assertion was observed. OCR and browser suites are naturally slower and depend on local Tesseract/Chrome/PHP/MySQL availability. The only runner-specific problem observed was the initial `EPERM` child-process restriction described above.

## 12. Known Existing Issues

### Confirmed by automated characterization

1. **Admin logout is missing/incomplete.** Visiting the Admin login page leaves the prior Admin session valid; the protected Admin home remains accessible. A real server-side logout belongs in the later authentication phase.
2. **Cross-Farmer parcel detail disclosure.** `editParcel.php?parcel_id=<other owner>` ownership-qualifies the parcel query but separately loads crop rows using only `parcel_detail_id`, so another Farmer's crop detail is rendered.
3. **Post deletion leaves physical images.** The database row is deleted while the file under `uploads/posts/` remains.
4. **Expired pending GPS files are not deleted.** Token lookup removes the expired session entry but leaves the pending file on disk.

### Identified by static audit; not converted into behavior-changing fixes

1. No broad CSRF protection is present on state-changing routes.
2. Login handlers do not regenerate the PHP session ID after authentication.
3. Some request paths perform schema assurance/DDL checks.
4. Error contracts mix redirects, script alerts, plain text, JSON, and status codes.
5. The parcel POST update path checks SQL execution, not affected ownership-qualified rows, before deleting crop rows by parcel ID. This is a high-risk authorization case for Phase 2/3 hardening and should receive an explicit expected-security test before any fix.
6. Upload validation and cleanup policies differ between report images, profile images, and post images.
7. Pending-image cleanup is request/session driven rather than a complete age-based garbage-collection process.

## 13. Intentionally skipped or bounded scenarios

- k6 write/load scenarios were not run; they can create load-test data and Phase 1 is a functional safety baseline.
- No test writes to or cleans the primary development database.
- No destructive Admin account-management flow was exercised.
- The normal Admin Crop Management test verifies denial but does not mutate the catalog; the Super Admin test verifies access without changing catalog data.
- Pixel-perfect HTML/CSS snapshots are out of scope. Existing report and registration browser tests cover representative responsive widths.
- Native camera hardware capture, mobile permission prompts, and GPS Camera vendor apps cannot be automated in the repository harness; uploaded stamped-image equivalents are covered.
- OCR engine-unavailable and timeout branches are not forced by altering production configuration; successful OCR, unreadable-image failure, and confidence/parser failures are covered.
- Concurrent write races and transaction deadlocks are not covered by functional tests; the existing low-concurrency k6 scripts remain the appropriate later tool.
- Admin logout cannot be tested as a successful invalidation because no server-side handler currently exists; its current persistence is characterized instead.
- Full farm/parcel deletion is not present as an active Farmer workflow and was not invented for the test suite.

## 14. Remaining high-risk untested behavior

- Admin/Farmer account creation, deactivation, bulk update, and export paths.
- Concurrent submissions using the same report/token beyond sequential token reuse.
- Database failure/rollback injection during final image move and report insert.
- Crash recovery between filesystem finalization and database commit.
- Complete pending-file garbage collection across abandoned sessions.
- Every legacy report shape created before GPS metadata columns were introduced.
- Authorization on all secondary read/export/image endpoints, not only the critical pages and report/parcel cases covered here.
- Profile update rollback when a late related-table rewrite fails.
- Post multi-image partial failure and filename collision behavior.
- Map behavior with very large report sets and malformed legacy coordinates beyond the representative fixtures.

## 15. Source changes and generated artifacts

No application source file, route, schema file, stylesheet, or user-facing JavaScript file was modified for Phase 1.

Changes are limited to:

- this documentation file;
- new files under `tests/auth`, `tests/reports`, `tests/parcels`, `tests/profile`, `tests/content`, `tests/map`, and `tests/helpers`; and
- refreshed generated output from running the existing GPS browser suite (`tests/gps-photo-results/crop-details-mobile.png` and `tests/gps-photo-results/server.log`).

The pre-existing untracked `docs/CODEBASE_AUDIT.md` remains outside the Phase 1 change set.

## 16. Completion assessment and Phase 2 recommendation

Phase 1 now provides automated protection for the required critical paths: both authentication systems, role guards, Farmer logout, current Admin session persistence, report ownership, Harvest/Damage creation, Farmer report edit behavior, Admin-on-behalf submission, GPS staging/OCR/finalization, evidence replacement/removal rules, parcel/profile writes, posts, suggestions, registration, and public map scope/data contracts.

Phase 2 is safe to begin **incrementally**, without a rewrite, provided each small change keeps the current routes and database behavior and reruns this complete baseline. The recommended first Phase 2 slice is shared configuration/database bootstrap work that preserves `scaims_db_mysqli()`/`scaims_db_pdo()` behavior. Authentication/session consolidation should follow only with the auth suite running after each guard change. The confirmed authorization and cleanup defects should be scheduled as explicit behavior changes, not silently folded into structural refactoring.
