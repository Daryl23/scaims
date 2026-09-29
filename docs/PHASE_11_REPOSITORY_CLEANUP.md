# Phase 11: Repository Cleanup

## Starting state and safety baseline

Phase 11 started on `main` with the expected accumulated Phase 5-10 working-tree changes and no staged files. The Phase 10 pagination, aggregate, map, export, and index changes were present. Before cleanup, the complete requested characterization baseline passed, including authentication, registration, report/image lifecycle, GPS workflow, parcels, profile, posts/suggestions, public map, configuration/migrations, shared frontend, Admin report views, design-system checks, and performance checks.

Cleanup used the prior audit and targeted reference searches. Candidates were classified as definitely unused, superseded, generated, compatibility-sensitive, or requiring further verification before any removal.

## Cleanup performed

### Generated and runtime artifacts

The following tracked runtime output was removed:

- `test-temp.txt`
- `ADMIN UI/adminDashboard/parcel/export_log.txt`
- `FARMERS UI/farmersDashboard/parcel/error.log`
- generated k6 NDJSON, summaries, and cookie jars under `stress-tests/results/`
- generated OCR images, browser screenshots, result JSON, and server log under `tests/gps-photo-results/`

Both result directories retain `.gitkeep` markers. The tests still create the same local output when run; Git now ignores it. Source tests and OCR fixture-generation code remain tracked.

### Development-only and superseded files

The five `manual_preview_*.php` entry points were removed after targeted searches found no caller. These files injected authenticated session identities and included production pages, so retaining them as web-accessible preview routes was unnecessary and unsafe.

The unused `ADMIN UI/shared/sidebar.php` was removed. All active Admin pages use `ADMIN UI/shared/admin-sidebar.php`.

The unreferenced `shared/dev_mode.php` helper was removed. No active application or stress-test route called it. The ignored local `.dev-bypass` marker was not deleted because ignored local files are user-owned; it has no active application consumer.

Production debug dumps were removed from active Farmer/Admin parcel, profile, home, and report-view code. User-facing error behavior and legitimate `console.error` handling were retained.

## Legacy registration

The canonical Farmer flow remains:

1. `FARMERS UI/registration/rsbsa_check.html`
2. `FARMERS UI/registration/registration_form.html`
3. `FARMERS UI/registration/complete_registration.php`

The old `FARMERS UI/accountCreation/accountCreation.html` route is now a small HTML/JavaScript compatibility redirect. Its old `checkUsername.php` and `submitAccount.php` URLs, plus `registration/submit_registration.php`, are small HTTP 303 adapters to the canonical form. The obsolete account-creation JavaScript, page CSS, route-specific logo, and unused `registration.js` were removed.

Non-vendor images remaining under `FARMERS UI/accountCreation/assets/` were classified as **needs verification** and retained because direct external asset URLs cannot be disproved from repository references alone.

## Suggestions canonicalization

`ADMIN UI/adminDashboard/suggestions/` is the canonical implementation. The duplicate root implementation was reduced as follows:

- `suggestions/suggestions.php` redirects to the canonical Admin page.
- root `get_suggestions.php`, `update_suggestion.php`, and `export_suggestions.php` are thin compatibility adapters to the canonical handlers.
- the duplicate root `suggestions.js` and `suggestions.css` were removed.

Canonical endpoints accept a narrowly scoped login-path override so unauthenticated requests through an old root URL still redirect to the correct Admin login page. Table, request, response, and authorization behavior are unchanged. Characterization coverage now verifies the old page redirect, unauthenticated redirect, and authenticated list adapter.

## Vendor cleanup

Ten vendor trees were compared by relative path and SHA-256. Every tree contained the same 81 files and the same 11,125,305 bytes. The root `assets/vendor/` tree is now canonical.

Nine byte-identical feature-local vendor trees were removed from Admin, Farmer landing/login/dashboard, registration, and legacy account-creation locations. The five active local Bootstrap references in registration and Farmer parcel pages were changed to the equivalent root asset URLs. Ten unreferenced numbered duplicates such as `bootstrap.bundle(1).js`, `glightbox.min(1).js`, and `index(1).html` were also removed from the canonical tree.

Unnumbered distribution variants within the canonical third-party tree were retained; removing individual library variants without a build manifest would be unnecessarily aggressive. Large duplicated first-party image trees were also retained because several are route-relative active assets and direct external asset compatibility remains uncertain.

## Compatibility files deliberately retained

- the three Farmer `displayImage.php` route wrappers
- the former Admin/Farmer dashboard-shell JavaScript URL loaders
- `farmerLanding/get_crop_reports_new.php`
- active `db_connection.php` / `db_conn.php` compatibility includes
- the new legacy registration redirects and root Suggestions adapters
- all database schema and migration history
- all real upload directories and user-uploaded content

## `.gitignore` and documentation

`.gitignore` now excludes runtime logs, `export_log.txt`, `test-temp.txt`, k6 results, GPS/browser result output, and `tests/phase1-results/`, while explicitly retaining result-directory `.gitkeep` files. Environment files and the legacy local bypass marker remain ignored. The stress-test README no longer instructs developers to create an application bypass that is not used by the current login handlers. The root README records canonical registration, Suggestions, vendor, and generated-output locations.

## Repository impact

- **781 tracked paths removed** in the current cleanup diff.
- Approximate tracked worktree footprint reduction versus `HEAD`: **153,167,805 bytes (146.07 MiB)**.
- The majority is nine byte-identical vendor copies plus generated k6/browser/OCR output.

Line-count reduction is incidental; canonical routes and safer repository hygiene are the goal.

## Verification

The final regression passed:

- Farmer/Admin authentication and authorization
- registration wizard, including the legacy registration URL
- report/image lifecycle
- GPS parser (21 assertions)
- real OCR for both characterized stamp formats
- GPS Farmer/Admin workflow (13 groups, desktop/tablet/mobile)
- parcel characterization
- profile characterization
- posts and canonical/legacy Suggestions lifecycle
- public map
- Phase 2 database configuration and migration/schema suites
- Phase 5 shared frontend characterization
- Phase 7 static and browser Admin report-view suites
- Phase 9 static and browser design-system suites at 1366, 768, and 390 px
- Phase 10 structural performance suite
- PHP lint for all changed/new PHP files
- JavaScript syntax checks for all changed/new JS/CJS files
- `git diff --check`

The Phase 10 structural results remain intact: SQL-paginated report endpoints returned page-sized result sets, parcel export totals remained correct, public-map filtering reduced the dataset correctly, and all migration-managed indexes remained present.

## Known issues intentionally unchanged

- broad CSRF protection remains future security work
- the characterized cross-Farmer parcel-detail disclosure remains unresolved
- deleting a post still leaves its physical image
- OCR worker pooling/concurrency architecture remains deferred
- ignored local `.dev-bypass` files may still exist on developer machines but have no active application consumer
- uncertain direct URLs to duplicated first-party images were retained
- user uploads already tracked or stored under `uploads/` were not altered

## Final assessment

The planned incremental refactoring roadmap is complete through Phase 11. Canonical application boundaries from Phases 2-10 remain intact, legacy route compatibility is preserved where uncertainty exists, generated output no longer pollutes version control, and the repository is materially smaller and easier to navigate. Future work should be scheduled as explicit security or product-maintenance phases rather than folded into repository cleanup.
