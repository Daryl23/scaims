# SCAIMS Phase 0 Codebase Audit

**Audit date:** 2026-09-28  
**Scope:** Inspection and architecture audit only; no application code, behavior, route, database, or UI changes.  
**Repository state at start:** branch `main`, tracking `origin/main`; `git status --short --branch` reported no staged, modified, or untracked files. The local ignored files `.dev-bypass` and `shared/gps-ocr/node_modules/` existed but were not repository changes.

## A. Executive Summary

SCAIMS is a server-rendered PHP/MySQL application with three main surfaces: a public website, a Farmer portal, and an Admin/Personnel portal. The frontend is primarily plain HTML, CSS, and JavaScript. Public/login pages use Tailwind through the CDN; older screens also use bundled Bootstrap assets. Mapping uses Leaflet and OpenStreetMap. Reporting adds a local Node-based OCR subprocess using Tesseract.js and Sharp.

The system is suitable for **incremental refactoring without a rewrite**. There are already useful shared seams: `conn.php`, Admin authorization helpers, shared sidebars, crop catalog helpers, shared report image storage, a shared GPS/OCR pipeline, and shared report-image UI. The main obstacle is not an absence of structure but uneven adoption: old and new implementations coexist, large mixed-responsibility files remain, Harvest and Damage features have parallel stacks, and Farmer/Admin flows repeat business rules.

The safest first refactoring phase is a **safety and characterization-test phase**. It should capture current login/session behavior, report create/update ownership, upload finalization/deletion, Admin-on-behalf reporting, map filtering, and post uploads before extracting more shared logic. This is especially important because several request paths perform runtime schema changes, report image files and database rows are coordinated manually, and Admin logout/session behavior is currently inconsistent.

### Audit size and method

- Approximately **170 application/source files** were inspected or statically scanned after excluding bundled vendor trees, `node_modules`, upload contents, images, source maps, and generated test/stress results.
- **185 first-party text files** were scanned when tests, stress scripts, documentation, and small text artifacts are included.
- First-party application distribution: 69 Admin files, 60 Farmer files, 22 shared files, 8 root files, 6 root-level suggestion files, and 5 database/migration files.
- There are 96 PHP, 31 CSS, 31 JavaScript, 10 HTML, and 4 SQL files in the primary filtered source inventory (plus CommonJS test/OCR files and documentation).
- Static inspection was used; no database mutation, endpoint submission, upload, test run, or application behavior change was performed.

## B. Technology Stack

### Frontend

- HTML5 and server-rendered PHP templates.
- Vanilla JavaScript; there is no React, Vue, Angular, or frontend bundler.
- CSS split across root/page stylesheets, shared stylesheets, and inline `<style>` blocks.
- Tailwind CSS loaded from `https://cdn.tailwindcss.com` on the public site and Admin/Farmer login pages.
- Bundled Bootstrap CSS/JS and Bootstrap Icons on registration and legacy-oriented screens.
- Leaflet 1.9.4 and Leaflet.markercluster 1.5.3 from `unpkg.com`; OpenStreetMap tiles are the street-map source.
- Chart.js and jsPDF-style report/export code are used by Admin report JavaScript. Bundled asset trees also contain Swiper, GLightbox, Isotope, PureCounter, imagesLoaded, and template support files; presence does not mean every library is active on every page.
- Google Fonts are loaded remotely.

### Backend

- PHP with both MySQLi and PDO. The use of `str_starts_with()` makes **PHP 8.0+** an actual minimum for the public map endpoint; typed returns, nullable types, and `Throwable` are also used.
- No Composer manifest or PHP framework was found.
- Routes are direct `.php` scripts using HTML responses, redirects, JSON responses, and Excel-compatible HTML exports.
- Sessions use PHP's standard session mechanism and `PHPSESSID` cookie.

### Database

- MySQL/MariaDB semantics (`mysqli`, MySQL PDO DSN, `ENUM`, `ON DUPLICATE KEY UPDATE`, `FIELD`, `INFORMATION_SCHEMA`, `ADD COLUMN IF NOT EXISTS`).
- `conn.php` provides centralized MySQLi/PDO factories and UTF-8 configuration. Credentials are still defined in application source; values are intentionally not reproduced here.
- Four additive SQL migration files exist, but there is no full baseline schema dump or general migration framework.

### OCR, testing, and other dependencies

- Node.js subprocess invoked by PHP `proc_open`.
- Pinned `tesseract.js` 7.0.0, `@tesseract.js-data/eng` 1.0.0, and `sharp` 0.35.4 under `shared/gps-ocr/`.
- k6 stress/load scripts.
- Playwright-based CommonJS browser tests (Playwright supplied externally through `NODE_PATH`).
- Node's built-in `assert` is used for parser and browser workflow checks.
- No PostGIS, spatial database extension, boundary shapefile, or external OCR service is present.

## C. Repository Architecture

```text
SCAIMS/
├── Public website
│   ├── index.html, style.css, landing.js, landing_data.php
│   └── FARMERS UI/farmerLanding/ (About, FAQ, Contact, public Crop Map)
├── Farmer identity and onboarding
│   ├── FARMERS UI/farmerLogin/
│   ├── FARMERS UI/registration/ (current two-part wizard)
│   └── FARMERS UI/accountCreation/ (legacy/redirected flow)
├── Farmer portal
│   └── FARMERS UI/farmersDashboard/
│       ├── home/        announcements and report-photo launcher
│       ├── parcel/      farm/parcel/crop CRUD and views
│       ├── profile/     Farmer/account editing
│       ├── report/      Harvest/Damage submit, list, view, and edit
│       └── shared/      Farmer sidebar, language, shell, logout
├── Admin portal
│   ├── ADMIN UI/adminLogin/
│   ├── ADMIN UI/adminDashboard/
│   │   ├── home/        dashboard counts
│   │   ├── user/        Farmer/user administration
│   │   ├── parcel/      farm/parcel browsing and export
│   │   ├── report/      analytics, on-behalf submission, Harvest/Damage views
│   │   ├── crops/       Super Admin crop catalog
│   │   ├── post/        announcements/posts
│   │   └── suggestions/ suggestion inbox/export
│   └── ADMIN UI/shared/ Admin navigation, language, responsive shell
├── Shared application code
│   └── shared/ (auth, crop catalog, image storage, GPS/OCR, nav, UI helpers)
├── Database evolution
│   └── database/migrations/ plus GPS migration runner
├── File storage
│   └── uploads/posts and uploads/reports/{pending,harvest,damage}
├── Tests
│   └── tests/ (registration and GPS/OCR workflow coverage)
├── Stress tests
│   └── stress-tests/ (k6 read and guarded low-concurrency write scenarios)
└── Legacy/duplicate areas
    ├── suggestions/ (second suggestion implementation)
    ├── repeated vendor trees under many UI directories
    └── tracked previews, logs, and generated results
```

### Directory purposes

- `ADMIN UI/`: authenticated administration/personnel pages and handlers.
- `FARMERS UI/`: public Farmer-facing pages, login/registration, and authenticated dashboard.
- `assets/`: root public assets and a full bundled vendor tree; similar trees are copied under multiple feature directories.
- `database/`: four schema migrations and a GPS-specific CLI migration/verifier.
- `docs/`: feature documentation and this audit.
- `shared/`: cross-surface helpers and the strongest existing refactoring boundary.
- `stress-tests/`: k6 scenarios, fixture account seeding, analysis script, and tracked results.
- `suggestions/`: a near-duplicate of `ADMIN UI/adminDashboard/suggestions/`.
- `tests/`: Node/Playwright/PHP GPS and registration test harnesses plus tracked generated screenshots/results.
- `uploads/`: runtime post and report image storage. At audit time it contained 9 Damage images and 9 pending images; contents were not modified.

## D. Application Modules

### Public website

- Landing page with dynamic counts/latest post from `landing_data.php` and public suggestion form to `submit_suggestion.php`.
- About, FAQ, and Contact pages.
- Public Crop Map with filters, clustering, details sheet, device geolocation for distance, and outbound Google Maps navigation.
- Public navigation to Admin login, Farmer login, and RSBSA-gated registration.

### Farmer onboarding and authentication

- Farmer login against `user` joined to active `farmer`.
- Registration starts at `rsbsa_check.html`, then uses `registration_form.html` plus `registration-wizard.js` and `complete_registration.php` to create `user` and `farmer` in one transaction.
- An older two-stage `submit_registration.php` -> `accountCreation/submitAccount.php` implementation remains but the account-creation page immediately redirects to the current registration form.

### Farmer portal

- Home: navigation launcher, GPS Camera upload launcher, announcements.
- Sakahan/Farm Parcel: farm overview, parcel details, crop details, add/edit parcel workflow.
- Mga Ulat/Reports: Harvest and Damage report selection, default/prefill data, GPS image staging, submission, combined history, read-only details, and Farmer-owned update.
- Profile: account and Farmer demographic/RSBSA updates, password change, profile image storage in DB.
- English/Tagalog dashboard switching via `localStorage`.
- Farmer logout destroys the PHP session and expires the session cookie.

### Admin portal

- Dashboard count cards.
- User Management: Farmer list/search/filter, detail, edit, activation/deactivation, export, and PIN verification.
- Farm Parcel: Farmer/farm/parcel/crop browsing and Excel-compatible export.
- Reports dashboard: counts, top-damage chart, and Submit Report for Farmer.
- Harvest and Damage report pages: filters, tables, details, graphs, exports, and Leaflet point maps.
- Crop Management: Super Admin-only catalog CRUD/activation.
- Posts/announcements: create with multiple images, edit text, delete DB row.
- Suggestions inbox: new/read filtering, mark read/delete, export.
- Admin and Super Admin roles are present; only Crop Management is explicitly Super Admin-gated.

## E. Database Architecture

### Connection model

- `conn.php` defines `scaims_db_mysqli()` and `scaims_db_pdo()` and sets `utf8mb4`.
- Five small `db_connection.php`/`db_conn.php` wrappers repeat the same try/connect/die pattern at different relative depths. Three are byte-identical; the Harvest and Damage wrappers are byte-identical.
- Most newer code includes `conn.php` directly, while parcel/report legacy code still includes wrappers.

### Verified tables referenced by repository SQL

| Table | Verified role |
|---|---|
| `admin` | Admin credentials, name, active state, and `AdminRole`. |
| `user` | Farmer login identity, password hash, role, profile image, creation date. |
| `farmer` | Farmer identity, address/barangay, RSBSA, activation, link to `user`. |
| `farm_parcels` | Farm-level location/area/type/organic data, linked by `created_by` username. |
| `parcel_details` | Parcel number, location, area, ownership, farm link. |
| `crop_details` | Registered crops per parcel, variety/tree information. |
| `crop_catalog` | Managed crop/commodity catalog and active/sort state. |
| `crop_reports` | Harvest reports, owner, creator, business data, coordinates, image, GPS metadata. |
| `damage_reports` | Damage reports, owner, creator, damage data, coordinates, image, GPS metadata. |
| `farmer_report_defaults` | Saved Farmer defaults for Harvest/Damage entry. |
| `posts` | Announcement content, JSON image paths, creation date. |
| `suggestions` | Public suggestion, submitter name, date, read/new status. |

No complete schema snapshot exists in the repository, so constraints and indexes outside the four migrations and request-time `CREATE TABLE` statements cannot be audited from source alone.

### Query and migration patterns

- Prepared statements are used in most login, registration, ownership, report, profile, catalog, and user-management writes.
- Raw queries are used for fixed dashboard aggregates and some list/export logic.
- Suggestion filters/actions build escaped values into SQL instead of binding parameters.
- Admin parcel export constructs filter SQL with escaped interpolation and performs an extra totals query for each distinct Farmer.
- Transactions exist for combined registration, farm/parcel creation/edit, profile update, Admin user update, and report insertion plus GPS metadata. They are not used consistently for file/database coordination outside reporting.
- Schema evolution is split between SQL migrations and runtime checks. `admin_auth.php`, `crop_catalog.php`, `farmer_report_defaults.php`, `report_image_storage.php`, and `gps_photo.php` can inspect or change schema during normal requests.
- Similar Farmer lookup (`user.UName` -> `farmer.FarmerID`) appears in report page, submit, update, and defaults endpoints.
- Harvest and Damage table queries are implemented separately across detail, list, graph, and export endpoints.

### N+1 and repeated access

- Confirmed N+1: `ADMIN UI/adminDashboard/parcel/handleExport.php` queries Farmer totals inside the result-processing loop, once per distinct Farmer.
- Admin home issues four separate count queries; `landing_data.php` issues eight scalar aggregate queries plus the latest-post query.
- Admin Harvest/Damage list endpoints fetch all matching rows, group them in PHP, and only then `array_slice()` previous reports for pagination.
- Public map loads the entire supported report feature set once and filters client-side.

## F. Authentication Architecture

### Farmer

- Login: `FARMERS UI/farmerLogin/login.php`; prepared lookup by username, `password_verify`, active Farmer requirement.
- Session keys: `UserID`, `UName`, `ProfileImage`, `FirstName`, `Surname`.
- Guards: repeated `session_start(); if (!isset($_SESSION['UName'])) { redirect... }` across home, parcel, profile, and report pages/handlers.
- Ownership: report detail/update SQL includes `FarmerID`; current Farmer is resolved from the authenticated username. Parcel code primarily links ownership through `created_by = UName`.
- Logout: `farmersDashboard/shared/logout.php` clears data, expires the cookie, and destroys the session.

### Admin and Super Admin

- Login: `ADMIN UI/adminLogin/submitLogin.php`; prepared lookup by `AdminName`, active state check, password verification.
- Session keys: `AdminID`, `AdminName`, `AdminRole`, `IsSuperAdmin`.
- Shared helpers: `scaims_require_admin()`, `scaims_require_super_admin()`, and role normalization in `shared/admin_auth.php`.
- Super Admin is enforced for crop catalog page/actions. Other Admin modules use standard Admin access.
- Some Admin pages (`home.php`, Harvest page, Damage page) still implement manual session checks instead of the shared helper.
- **Important inconsistency:** the Admin sidebar's “Logout” link goes directly to `adminLogin.html`; no Admin logout handler or Admin `session_destroy()` was found. A user can see the login page without the server session necessarily being ended.

### Cross-cutting observations

- No CSRF token implementation was found in application forms or AJAX handlers.
- No `session_regenerate_id()` call was found after successful login.
- Authentication failure responses vary: redirects, inline JavaScript alerts, JSON 401, raw error text, and HTML.
- `shared/report_image_stage.php` intentionally accepts either Farmer or Admin session, but does not itself distinguish Admin role beyond `AdminID` presence.
- Stress-test documentation mentions reCAPTCHA and a local bypass, but the current login HTML/PHP contains no reCAPTCHA integration. `shared/dev_mode.php` is not referenced by application PHP. Documentation and implementation have diverged.

Future shared Farmer middleware/helper logic is justified, but behavior should first be characterized because redirects and response formats differ by page/API context.

## G. Frontend Architecture

### HTML/layout

- Public navigation is centralized in `shared/public-nav-config.js`, `public-nav.js`, and `public-nav.css`, though `index.html` retains fallback nav markup.
- Farmer sidebar markup is centralized in `farmersDashboard/shared/sidebar.php`.
- Admin pages use `ADMIN UI/shared/admin-sidebar.php`; `ADMIN UI/shared/sidebar.php` is an older unreferenced alternative.
- Reusable report photo capture and choice-card components already exist under `shared/`.
- 19 inline `<style>` blocks, 43 inline script blocks, and approximately 88 `style=` occurrences were found across first-party HTML/PHP (counts are occurrence-oriented, not unique-rule counts).

### CSS

- Root `style.css` has a real token set (59 custom property declarations), and shared public/Admin/mobile styles introduce additional local tokens.
- Most page CSS has no variables and repeats literals. Across first-party CSS, `#198754` appears about 146 times, `#ffffff` 69 times, `#674636` 47 times, `#3b4918` 45 times, and `#fffcef` 36 times.
- Harvest `crop.css` and Damage `damages.css` have about 73% line-set similarity, including table, modal, map popup, controls, and responsive styling.
- Farmer report, parcel, profile, home, and registration styles repeat cards, form controls, buttons, responsive rules, and brand colors.
- `admin-sidebar.php` contains roughly 370 lines of embedded CSS while separate Admin layout/mobile styles also exist.
- Design tokens exist, but are **not centralized or consistently consumed**.

### JavaScript

- Shared modules cover app paths, public navigation, choice cards, crop catalog, crop icons, GPS upload/rendering, and dashboard shell behavior.
- Admin and Farmer dashboard shell JS files are byte-identical.
- Admin Harvest and Damage JS are parallel implementations (885 and 847 lines) with matching responsibilities: crop options, map initialization/layers, pagination, table/detail loading, image details, chart/export behavior.
- Farmer `report.js` and Admin `admin-report-form.js` repeat commodity normalization, select/field setters, report snapshots, prefill/edit mode, and report type switching.
- Admin and Farmer language modules repeat the same DOM translation engine/localStorage/event mechanism but carry separate dictionaries.
- App base-path detection is separately implemented in `app-path.js`, `crop-catalog.js`, `leaflet-crop-icons.js`, and legacy account-creation JS.

### Translation

- Dashboard text uses `data-i18n`, `data-i18n-html`, placeholder/ARIA attributes, and portal-specific JS dictionaries.
- State is kept in `localStorage`; language changes emit `dashboard:language-changed`.
- Translation behavior is semi-centralized per portal, but dictionaries are separate and hardcoded bilingual strings remain in PHP/HTML/JS. Public pages are primarily hardcoded English, while some Farmer report labels default to Tagalog.
- The shared GPS image component consults dashboard translation hooks, but its strings are also embedded in JS.

## H. Backend Architecture

The backend is route-oriented rather than layered. A typical PHP file performs several of these responsibilities directly: session guard, connection creation, validation, query construction, HTML/JSON rendering, redirect/flash message, and filesystem coordination.

Existing shared backend boundaries:

- `conn.php`: DB factories.
- `shared/admin_auth.php`: Admin authorization/role.
- `shared/crop_catalog.php`: catalog defaults, table bootstrap, lookup/filter helpers.
- `shared/farmer_report_defaults.php`: default schema, load/save, registered crop prefill.
- `shared/report_image_storage.php`: report-image validation, paths, staging/finalization/deletion.
- `shared/gps_photo.php`: Node OCR invocation, GPS metadata validation/persistence, report transaction wrapper.

Areas still route-coupled:

- Farmer and Admin report validation/insertion.
- Harvest and Damage list/detail/export/graph stacks.
- Profile/user update cascades that rewrite username-based `created_by` fields.
- Post upload/database handling.
- Suggestion implementations in two locations.
- Mixed response policies and database error disclosure.

### Important page and route inventory

| File / route | Portal | Purpose | Reads DB | Writes DB/files | Authentication |
|---|---|---|---:|---:|---|
| `index.html` | Public | Landing UI | No | No | Public |
| `landing_data.php` | Public | Counts and latest post JSON | Yes | No | Public |
| `submit_suggestion.php` | Public | Suggestion POST | No | Yes | Public |
| `farmerLanding/{about,faq,contact,map}.html` | Public | Information and Crop Map | Map fetches API | No | Public |
| `farmerLanding/get_crop_reports.php` | Public API | Supported-area GeoJSON FeatureCollection | Yes | No | Public |
| `shared/crop_catalog_api.php` | Public API | Active crop catalog JSON | Yes | May bootstrap table | Public |
| `farmerLogin/login.php` | Farmer | Login POST | Yes | Session | Public |
| `registration/complete_registration.php` | Farmer | Atomic user+Farmer registration | Yes | Yes, profile BLOB | Public |
| `registration/check_rsbsa.php` | Farmer | RSBSA uniqueness check | Yes | No | Public |
| `farmersDashboard/home/home.php` | Farmer | Home/announcement/report launcher | No direct query | No | Farmer |
| `home/fetch_posts.php` | Farmer API | Announcement list | Yes | No | **No guard found** |
| `farmersDashboard/parcel/farmersFP.php` | Farmer | Parcel/crop overview | Yes | No | Farmer |
| `parcel/farmParcel.php` | Farmer | Add-farm form | Yes | No | Farmer |
| `parcel/submitFarmInfo.php` | Farmer API | Farm, parcel, crop insert | Yes | Yes | Farmer |
| `parcel/editParcel.php` | Farmer | Parcel/crop read and transactional edit | Yes | Yes | Farmer |
| `profile/editProfile.php` | Farmer | Profile page | Yes | No | Farmer |
| `profile/updateProfile.php` | Farmer API | Account/Farmer/password/image update | Yes | Yes | Farmer |
| `report/report.php` | Farmer | Combined report form/history/detail/edit UI | Yes | No | Farmer |
| `report/report_submit.php` | Farmer | Harvest/Damage create | Yes | Yes, report image | Farmer |
| `report/report_update.php` | Farmer API | Owned Harvest/Damage read/update | Yes | Yes, report image | Farmer |
| `report/report_defaults_save.php` | Farmer API | Save per-type defaults | Yes | Yes | Farmer |
| `shared/report_image_stage.php` | Shared API | Validate/stage image and run OCR | No | Yes, pending image/session | Farmer or Admin |
| `adminLogin/submitLogin.php` | Admin | Admin login POST | Yes | Session/schema check | Public |
| `adminDashboard/home/home.php` | Admin | Dashboard totals | Yes | No | Admin manual guard |
| `user/userManagement.php` + user endpoints | Admin | Farmer list/detail/edit/status/export | Yes | Some endpoints write | Admin; some Super Admin UI behavior |
| `parcel/farmParcel.php` + parcel endpoints | Admin | Farmer parcel browsing/export | Yes | No | Admin |
| `report/reportAnalytics.php` | Admin | Analytics and on-behalf form | Yes | No | Admin |
| `report/report_submit.php` | Admin | Harvest/Damage submission for selected Farmer | Yes | Yes, report image | Admin |
| `report/get_farmer_report_prefill.php` | Admin API | Farmer registered data/prefill | Yes | May bootstrap defaults/catalog | Admin |
| `report/crop/*.php` | Admin | Harvest list/detail/graph/year/export/page | Yes | No | Admin (page manual guard) |
| `report/damage/*.php` | Admin | Damage list/detail/graph/export/page | Yes | No | Admin (page manual guard) |
| `crops/cropManagement.php`, `cropActions.php` | Admin | Catalog management | Yes | Yes | Super Admin |
| `post/{post,create_post,update_post,delete_post}.php` | Admin | Announcement CRUD and image upload | Yes | Yes | Admin |
| `suggestions/*` under Admin dashboard | Admin | Suggestion inbox/actions/export | Yes | Mark/delete | Admin |
| `farmersDashboard/shared/logout.php` | Farmer | Destroy session | No | Session/cookie | Session |

Compatibility/legacy routes are documented under Potential Dead Code rather than treated as primary routes.

### Image and file upload architecture

- **Report images:** centralized in `shared/report_image_storage.php`. Actual MIME and image headers are checked, size is capped at 6 MB, accepted types are JPEG/PNG/WEBP, filenames use time plus cryptographically random bytes, and originals move through `pending` to `harvest` or `damage`. Replacement/removal code deletes prior files after successful updates. Pending entries are session-owned and one-hour-expiring, but no background cleanup process for abandoned pending files was found.
- **Post images:** `post/create_post.php` accepts multiple JPEG/PNG/GIF files, checks MIME with `mime_content_type`, retains `basename()` of the client filename with a seconds-resolution timestamp prefix, and stores JSON paths in `posts.image_paths`. No explicit application size/dimension cap was found. `delete_post.php` deletes only the DB row; no corresponding image deletion was found. `update_post.php` updates text only.
- **Profile images:** stored as BLOB data in `user.ProfileImage`, not under `uploads/`. Current registration requires a successful upload and `getimagesize`; it does not set a separate application byte limit or explicit MIME allowlist. Profile update reads any successful uploaded file directly into the BLOB without repeating registration's image validation.
- **Directory state at audit:** `uploads/posts/` had no files; `uploads/reports/damage/` had 9 files (~10.4 MB); `uploads/reports/harvest/` had none; `uploads/reports/pending/` had 9 files (~6.9 MB). Files were not opened, changed, or deleted.

### Security-related structure (not a penetration test)

- Database connection constants, including credentials, are defined in `conn.php` alongside connection logic. Secret values are intentionally omitted from this report.
- Prepared statements are common but not universal; suggestion actions/filters and parcel export use escaped string interpolation.
- CSRF tokens were not found on state-changing forms/AJAX routes.
- Validation/sanitization and JSON/error response creation are route-local and inconsistent. Several endpoints return exception, connection, or statement messages to the client.
- Report upload validation is comparatively strong and centralized; post/profile validation is separate and weaker/inconsistent.
- Authorization is centralized for most Admin endpoints but repeated manually for Farmer pages and several Admin page views.
- Schema mutation privileges are required by normal web requests because helpers may run `ALTER TABLE` or `CREATE TABLE`.
- This section identifies refactoring-relevant structure only; it does not claim exploitability or replace a security review.

## I. Reporting Architecture

### Harvest

1. Farmer or Admin uploads a GPS Camera image to the shared staging endpoint.
2. OCR result and SHA-256 are stored in the current session under an opaque pending token.
3. Farmer chooses Harvest and enters planting/harvest/yield/status/price/tree data; Admin additionally selects an active Farmer.
4. Submit endpoint verifies the token, rejects raw upload bypasses, finalizes the file into `uploads/reports/harvest`, and inserts `crop_reports`.
5. `scaims_execute_photo_report()` wraps report insertion plus GPS metadata update in a transaction.
6. Farmer submit also persists report defaults.
7. Farmer report history combines Harvest and Damage with `UNION ALL`; Admin has dedicated Harvest list/detail/graph/export endpoints.
8. Farmer can edit the same owned report. OCR evidence replacement must pass staging again.

### Damage

The flow is parallel, using `damage_reports`, `dmgCropCommodity`, `dmgCropType`, `dmgCropVariety`, occurrence/cause/damage/loss fields, and `uploads/reports/damage`. For new OCR-backed Damage reports, occurrence date is derived from the photo capture date.

### Farmer versus Admin-on-behalf

- Farmer owner is resolved from authenticated `UName` -> `user` -> `farmer`; `created_by` is the Farmer username.
- Admin must post an active existing `FarmerID`; `created_by` is Admin name/ID.
- Both share image staging, OCR parsing, file path helpers, GPS metadata columns, and transaction wrapper.
- They do **not** share report validation, field mapping, insert construction, coordinate checks, redirect/error policy, or form state code.
- Admin does not expose the Farmer-owned edit endpoint; Admin pages are report views/exports plus new on-behalf submission.

### Consolidation opportunities (future only)

- A report schema/DTO validator for Harvest and Damage.
- A report service accepting actor/owner context (`Farmer self` vs `Admin on behalf`).
- Shared repository functions for detail/list reporter-name joins.
- Shared Harvest/Damage table/map/detail UI primitives.
- Keep routes and database behavior stable while introducing these behind existing endpoints.

## J. GPS/OCR Architecture

### Single shared implementation

- Browser component: `shared/report-image-capture.js` and CSS, used by Farmer and Admin forms.
- Entry point: `shared/report_image_stage.php`.
- Storage/session tokens: `shared/report_image_storage.php`.
- PHP OCR bridge and metadata persistence: `shared/gps_photo.php`.
- OCR worker: `shared/gps-ocr/extract.cjs`.
- Parser: `shared/gps-ocr/stamp-parser.cjs`.
- Farmer and Admin do **not** have separate OCR engines.

### Processing and validation

- JPG/JPEG, PNG, WEBP; maximum 6 MB.
- PHP upload error, actual MIME (`finfo`), and image header (`getimagesize`) validation.
- Random 24-byte-derived filename component; original filename is ignored for report images.
- Original file is preserved; Sharp uses a temporary in-memory oriented PNG and 50-million-pixel limit.
- OCR attempts lower half sparse text, full grayscale/normalize/sharpen sparse text, then full-image automatic layout.
- A new Tesseract worker is created per extraction and terminated afterward; PHP bounds the subprocess and Node has a 55-second timeout.
- Parser validates explicit MM/DD/YYYY, 12/24-hour time with optional seconds/timezone, address adjacency, decimal coordinates/ranges/conflicts, and OCR confidence.
- Stored metadata: capture date/time, address, latitude/longitude, timezone, parser version. Existing legacy location/coordinates are also populated for current consumers.
- Pending entries expire logically after one hour when retrieved. No scheduled filesystem sweeper was found; expired pending files are deleted only in specific failure/finalization/test cleanup paths.

### Risk

OCR parsing and report/file transaction coordination are high risk because they combine external process behavior, image bytes, session ownership, filesystem moves/deletes, DB insertion, and backward compatibility for historical reports.

## K. GIS/Map Architecture

### Public Crop Map

- UI: `FARMERS UI/farmerLanding/map.html` (1,097 lines) with embedded CSS/JS.
- Data: `get_crop_reports.php` queries `crop_reports`, Farmer barangay, and related Damage reports, then emits a GeoJSON `FeatureCollection` of Points.
- Tiles: Leaflet with OpenStreetMap street tiles and an Esri-style satellite tile URL in client configuration.
- Clustering: Leaflet.markercluster; display changes from area -> farm -> listing with zoom.
- Crop icons: shared `leaflet-crop-icons.js` and `assets/icons/`.
- Scope: Lipa City and Mataas na Kahoy. Backend rejects unsupported normalized locations and coordinates outside configured bounds.
- There are no administrative boundary polygons. Lipa/Mataas na Kahoy bounds are navigation/sanity envelopes, not legal boundaries.
- Coordinates come from report latitude/longitude when valid; some hardcoded barangay centers and deterministic offsets are fallback display positions.

### Admin report maps

- Separate Harvest and Damage Leaflet maps live in `cropReports.js` and `cropDamage.js` and use report detail coordinates.
- Map initialization, layer switching, icon setup, and card rendering are parallel and good candidates for a shared future module.

### Location data

- Lipa barangays and aliases are hardcoded in the public map endpoint.
- Barangay `<option>` lists also exist in registration/profile/filter/form markup and scripts, so the location list is scattered/duplicated rather than DB- or JSON-driven.
- Crop catalog is DB-backed with hardcoded bootstrap defaults; barangay catalog is not.
- No PostGIS or stored GeoJSON geometry is used.

## L. Duplication Hotspots

### Top 10

| Priority | What is duplicated | Files involved | Evidence / risk | Suggested future abstraction |
|---|---|---|---|---|
| **HIGH** | Harvest vs Damage Admin UI stack | `report/crop/{cropReports.php,cropReports.js,crop.css}` and `report/damage/{damageReports.php,cropDamage.js,damages.css}` plus list/detail/export/graph endpoints | CSS ~73% line-set similarity; PHP pages ~48%; parallel 800+ line JS files and SQL joins | Parameterized report table/detail/map modules and shared repository/query builder. |
| **HIGH** | Farmer vs Admin report creation | Both `report_submit.php` files | Same token, coordinate, image finalization, insert, cleanup, and GPS metadata concepts with separately coded validation/fields | Report application service with actor/owner context. |
| **HIGH** | Harvest vs Damage branches inside Farmer submit/update | Farmer `report_submit.php`, `report_update.php`, `report.js`, `report.php` | Large parallel branches repeat validation, image handling, defaults, UI state, rendering | Per-report-type schema/config consumed by shared controller/UI renderer. |
| **HIGH** | Report detail/list reporter joins | Admin `get*Reports.php`, `fetch*Reports.php`, exports | Repeated joins from report owner/creator to Farmer/User/Admin and repeated reporter labeling | Shared report repository/select fragment. |
| **MEDIUM** | Suggestions application copied twice | `suggestions/*` and `ADMIN UI/adminDashboard/suggestions/*` | JS exact duplicate; PHP 79-84% similar; CSS 72%; active Admin nav points to dashboard copy | One canonical module/routes with shared UI assets. |
| **MEDIUM** | DB connection wrappers | Five `db_connection.php`/`db_conn.php` files | Three exact copies, two exact copies; only relative depth/error logging differs | Direct centralized factory/bootstrap include. |
| **MEDIUM** | Dashboard shell and language engines | Admin/Farmer shared shell JS; two language JS files | Shell JS exact duplicate; translation DOM/storage/event engine repeated | Shared shell controller and translation engine with portal dictionaries. |
| **MEDIUM** | Profile image endpoints | `home/`, `parcel/`, `report/displayImage.php` | Three exact/near-exact active copies | One authenticated profile-image endpoint/helper. |
| **MEDIUM** | Public/login/footer markup and styles | `index.html`, About/FAQ/Contact, Farmer/Admin login pages | Repeated footer/social/link blocks; login HTML ~66% similar; many inline styles/scripts | Shared PHP includes or build-free template fragments; shared login shell. |
| **LOW-MEDIUM** | Path resolution and lookup utilities | `app-path.js`, `crop-catalog.js`, `leaflet-crop-icons.js`, account creation JS; repeated `formatDate`, `escapeHtml`, select setters | Same base-path and formatting concepts recur with slight variants | Shared browser utilities with explicit APIs. |

Additional CSS repetition includes buttons/cards/forms/media queries and literal brand colors across Farmer page styles. Navigation markup itself is already partly centralized; the larger issue is two Admin sidebar implementations and embedded sidebar CSS.

## M. Large/Complex Files

| File | Approx. lines | Responsibilities | Concern |
|---|---:|---|---|
| `FARMERS UI/farmerLanding/map.html` | 1,097 | Full page markup, Tailwind config, large CSS block, Leaflet setup, clustering, filtering, location/distance, details UI | **High / God file** |
| `FARMERS UI/farmersDashboard/report/report.js` | 1,030 | Submit confirmation, prefill/defaults, type state, translation, detail modal, edit rendering/update, image state | **High / God file** |
| `FARMERS UI/farmersDashboard/parcel/farmerParcel.css` | 1,015 | Entire parcel form/view/responsive styling | High CSS maintenance |
| `ADMIN UI/adminDashboard/report/crop/crop.css` | 894 | Table, filters, modal, map, responsive report styling | High; duplicates Damage |
| `ADMIN UI/adminDashboard/report/crop/cropReports.js` | 885 | List, filter, pagination, detail, map, graph, PDF/export | **High / God file** |
| `ADMIN UI/adminDashboard/report/damage/cropDamage.js` | 847 | List, filters, map, detail, charts, metrics, PDF/export | **High / God file** |
| `FARMERS UI/farmersDashboard/report/reps.css` | 829 | Forms, report lists/details/edit/upload/responsive | High |
| `ADMIN UI/adminDashboard/report/damage/damages.css` | 662 | Damage report UI/map/responsive styling | High; duplicates Harvest |
| `FARMERS UI/registration/registration.css` | 641 | Registration/page/nav/form/responsive styling | Medium-high; overlaps `regs.css` |
| `FARMERS UI/farmersDashboard/parcel/editParcel.php` | 636 | Guard, DB read/update transaction, HTML, CSS/JS, dynamic crops | **High / God file** |
| `FARMERS UI/farmersDashboard/parcel/farmersFP.php` | 635 | Logging, guard, queries, data selection, UI, inline JS | **High / God file** |
| `FARMERS UI/registration/regs.css` | 630 | Registration page/form responsive styling | Medium-high; overlaps registration CSS |
| `style.css` | 598 | Public landing design and root tokens | Medium |
| `FARMERS UI/farmerLanding/get_crop_reports.php` | 578 | Scope catalog, normalization, SQL filters/query, coordinate fallback, GeoJSON/meta | **High / domain-heavy** |
| `ADMIN UI/adminDashboard/parcel/scripts.js` | 532 | Fetch/filter/paginate Farmer rows, nested parcel/crop modal, export | High |
| `ADMIN UI/adminDashboard/report/admin-report-form.js` | 530 | Admin Farmer selection/prefill, report type state/edit, form orchestration | High |
| `ADMIN UI/shared/admin-sidebar.php` | 496 | Auth/session-aware navigation, role logic, markup, 300+ lines inline CSS | Medium-high |

Line counts use PowerShell line measurement and are approximate for audit prioritization.

## N. Performance Concerns

### Top 5 evidence-based concerns

1. **Admin report pagination occurs after full retrieval.** `fetchCropReports.php` explicitly says “Execute query without pagination first,” loads all rows, groups them, then slices previous reports; Damage does the same. Cost grows with the report tables.
2. **N+1 query in farm-parcel export.** `handleExport.php` executes a totals aggregate inside the loop for each distinct Farmer after already loading joined parcel rows.
3. **Repeated runtime schema checks/DDL.** Report submit/update, crop catalog reads, defaults, and Admin login can issue `SHOW COLUMNS`, `INFORMATION_SCHEMA`, `CREATE TABLE IF NOT EXISTS`, or `ALTER TABLE` during requests. This adds latency/locking risk and mixes deployment with traffic handling.
4. **OCR worker initialization per upload.** Every accepted staged photo launches Node and creates a Tesseract worker, with up to three recognition attempts and roughly 55-65 second bounds. This is CPU/memory intensive under concurrent uploads.
5. **Whole-dataset loading and repeated aggregates.** Public map fetches all supported features before client-side filtering; landing performs nine separate queries; Admin home performs four separate count queries. These are currently simple but scale linearly or add avoidable round trips.

Additional observations: multiple copied vendor trees inflate deployment size; tracked/generated images and logs inflate repository size; some pages load both large page CSS and shared/inline CSS; post images have no repository-enforced size/dimension limit; public map renders up to 80 list rows and all clustered features in one load.

No speculative index recommendations are made because the complete schema/index set is not in the repository.

## O. Maintainability Concerns

### Top 10

1. **Business rules are duplicated across Farmer/Admin and Harvest/Damage paths**, making behavior drift likely.
2. **Large mixed-responsibility files** combine transport, authentication, DB, HTML, CSS, JS, charting, and map behavior.
3. **Runtime schema mutation** is spread across application helpers rather than controlled deployment migrations.
4. **Authentication is unevenly centralized**: Admin has helpers but some pages bypass them; Farmer has no shared guard; Admin logout does not destroy the session.
5. **Schema and naming are inconsistent**: `FarmerID`/`UserID`/`AdminName`, `farmer_id`, `reportDate`/`report_date`, `cropType`/`crop_type`, and Damage-only `dmgCropType`; filenames mix camelCase, snake_case, and lowercase.
6. **Directory names contain spaces and capitalization** (`ADMIN UI`, `FARMERS UI`) while route code repeatedly compensates with relative-depth paths and URL encoding.
7. **CSS is fragmented** across 31 files plus inline blocks, with incomplete token adoption and repeated brand/form/card/sidebar/report styles.
8. **Response and error handling is inconsistent** (redirect/flash, inline alert scripts, JSON, raw SQL/exception details, `die`).
9. **No complete database schema or general migration ledger** is versioned; only four recent migrations exist.
10. **Legacy, generated, and active code coexist in production directories**, obscuring the canonical flow and making search/results noisier.

Other concerns include username-based ownership (`created_by`) requiring cascaded updates, hardcoded barangay catalogs in multiple layers, stale docs (reCAPTCHA claim), copied vendor libraries, limited automated coverage outside registration/GPS, and public endpoints that depend on runtime table bootstrap.

### Naming consistency examples

- DB/SQL: `FarmerID`, `UserID`, `AdminID`, `AdminName`, `reportDate`, `dmgLocation`, `dmgCropType` alongside snake_case GPS and catalog columns.
- PHP: `$farmerId`, `$farmerID`, `$user_id`, `$report_type`, camelCase validation helpers, and snake_case helper families.
- JS: camelCase is common but endpoint payloads mix kebab-case, snake_case, and DB-shaped Pascal/camel keys.
- Files: `cropDamage.js`, `cropReports.js`, `report_submit.php`, `getCropReports.php`, `farmersFP.php`, `fpStyles.css`.

Renaming should not be an early phase because routes, form names, SQL columns, JS payloads, and tests are tightly coupled to current names.

## P. Potential Dead or Suspicious Code

Nothing was deleted. Classification is conservative.

### Definitely non-production/generated artifacts

- `test-temp.txt` (small temporary text file).
- `FARMERS UI/farmersDashboard/parcel/error.log` (tracked ~1.4 MB runtime log).
- `ADMIN UI/adminDashboard/parcel/export_log.txt` (tracked export log).
- `tests/gps-photo-results/*` and `stress-tests/results/*` are generated outputs/results, though useful as historical evidence.
- `manual_preview_*.php` files are manual preview/test entry points, not linked by production navigation.

### Probably unused / superseded

- `ADMIN UI/shared/sidebar.php`: no include/reference found; all active Admin pages include `admin-sidebar.php`.
- Root `suggestions/*`: active Admin sidebar links to `ADMIN UI/adminDashboard/suggestions/*`; root copy appears superseded.
- `FARMERS UI/accountCreation/*`: `accountCreation.html` immediately redirects to current registration; old handler/assets remain.
- `FARMERS UI/registration/registration.js` and `submit_registration.php`: current form loads `registration-wizard.js` and posts to `complete_registration.php`.
- `shared/dev_mode.php`: no application include/reference found; only its own definition and stress-test documentation mention the bypass.

### Needs verification before removal

- `farmerLanding/get_crop_reports_new.php`: a small compatibility wrapper to `get_crop_reports.php`; no in-repository caller was found, but external bookmarks/integrations may exist.
- Numbered duplicate vendor files (`min(1)`, `min(2)`, `index(1)`, `index (1)`) and complete vendor-tree copies: likely template/copy artifacts, but page references should be mechanically verified before cleanup.
- Legacy `FARMERS UI/accountCreation/checkUsername.php` and `submitAccount.php` may still be called by external links despite the redirect.
- Old comments and commented debug code within large files require per-route verification; this audit did not classify individual selectors/functions as definitely unused without runtime coverage.

## Q. Testing Coverage

### Existing automated tests

- `tests/gps-stamp-parser.cjs`: 21 assertions for date/time/address/coordinate/confidence parsing.
- `tests/gps-stamp-ocr.cjs`: real Sharp/Tesseract OCR against generated template images and original-byte preservation.
- `tests/gps-photo-workflow.cjs`: isolated disposable-database Playwright workflow covering Farmer/Admin, Harvest/Damage, upload replacement/removal, OCR failure, token ownership, translations, editing, responsive layouts, and database results.
- `tests/registration-wizard.cjs`: Playwright registration wizard behavior with network writes stubbed.
- PHP fixture/router support creates a temporary database copied from the local schema and cleans it up.

### Stress/load tests

- k6 public/Farmer/Admin read-only scenarios and staged 50-user profile.
- Baseline home test.
- Guarded low-concurrency report submission and image upload scripts, disabled unless `ENABLE_WRITES=1`.
- Result analyzer and account seeding script.

### Important gaps

- No general PHP unit test framework or unit suite.
- No broad integration coverage for login/logout/session regeneration, Admin role enforcement, Farmer ownership, parcel CRUD, profile update, user management, posts, suggestions, exports, and public map boundary/filter behavior.
- No automated comparison of Harvest and Damage semantics.
- No test protects Admin logout behavior because no logout handler exists.
- No representative real-world phone photo corpus for OCR.
- No schema migration test suite beyond the GPS-specific runner/workflow.
- Existing test/stress result files are tracked, but there is no CI configuration in the repository proving tests run on every change.

## R. Refactoring Risk Matrix

| Risk | Area | Why |
|---|---|---|
| **High** | Login/session/authorization and Admin roles | Many guards, mixed response modes, missing Admin session destruction, and security-sensitive behavior. |
| **High** | Harvest/Damage create and Farmer edit | Coordinates, ownership, defaults, DB fields, images, GPS metadata, redirects, and transactions must remain synchronized. |
| **High** | GPS OCR/parser | External process, timeouts, confidence/date rules, exact stored formats, and client/server state. |
| **High** | Report image storage/deletion | Filesystem/DB consistency, pending tokens, historical optional images, replacement and rollback behavior. |
| **High** | Database schema/migrations | Incomplete baseline schema and current request-time DDL; production data compatibility is central. |
| **High** | Profile/user rename cascade | `created_by` stores usernames across multiple tables and update code rewrites them. |
| **Medium-high** | Public/Admin GIS maps | Hardcoded scope, fallback coordinates, multiple map implementations, and no authoritative boundary geometry. |
| **Medium-high** | Farm/parcel CRUD/export | Nested records, transactions, username ownership, large files, confirmed N+1 export logic. |
| **Medium** | Posts/uploads | Separate validation policy, original filename use, JSON paths, and DB/file deletion mismatch. |
| **Medium** | Suggestions/user exports | Duplicate routes, dynamic SQL, spreadsheet output, external consumers possible. |
| **Low-medium** | Shared navigation/layout extraction | Visible on every page but mostly presentational; visual regression coverage is limited. |
| **Low** | Small formatting/path utilities | Localized behavior if characterized and introduced behind existing callers. |

## S. Recommended Refactoring Phases

1. **Safety baseline and characterization tests.** Add tests around Farmer/Admin login and true logout, role guards, report create/update ownership, Admin-on-behalf reports, upload lifecycle, parcel/profile writes, posts/suggestions, and map filtering. Snapshot routes and payload shapes. Do not change schema or UI.
2. **Deployment/configuration and schema discipline.** Move secrets to environment-backed configuration while preserving factory APIs; create a verified baseline schema/migration ledger; stop adding new request-time DDL, then migrate existing ensure-functions carefully.
3. **Shared request/auth foundations.** Introduce Farmer guard and response helpers; route Admin pages through existing helpers; add a real Admin logout endpoint. Preserve redirect and JSON behavior route-by-route.
4. **Canonicalize clearly duplicated low-risk files.** Connection wrappers, three profile-image endpoints, exact dashboard shell JS, and the duplicate suggestion tree after verifying external routes.
5. **Shared frontend primitives.** Translation engine, app-path resolution, modal/loading/alert helpers, public/login/footer templates, while keeping portal-specific dictionaries and URLs.
6. **Reporting domain service behind existing endpoints.** Extract owner/actor resolution, common validation, coordinate rules, file finalization/cleanup, transaction orchestration, and report repositories. Keep current routes and tables.
7. **Harvest/Damage Admin UI consolidation.** Shared report list/detail/map/pagination/export components parameterized by field schema; preserve table markup and behavior.
8. **GPS/OCR hardening and performance.** Only after tests: lifecycle cleanup, resource/concurrency strategy, failure telemetry, and real-photo acceptance corpus. Do not change parsing rules casually.
9. **CSS/design-system consolidation.** Promote existing tokens, merge shared cards/forms/buttons/sidebar/report styles, reduce inline CSS, and perform visual regression checks at desktop/tablet/mobile sizes.
10. **Query/performance work.** SQL pagination/counts, remove export N+1, aggregate dashboard counts where justified, server-side map filters, and index review using the actual production schema/query plans.
11. **Dead-code/vendor/generated-artifact cleanup.** Remove only after route/access-log/reference verification and with separate recoverable commits.

No framework migration is recommended for the immediate plan. A framework could be evaluated as a long-term alternative, but the current application has enough shared seams for incremental improvement with materially lower migration risk.

## T. Files That Should NOT Be Touched First

- `conn.php` and database credential/connection behavior, until environment and deployment tests exist.
- `shared/gps_photo.php`, `shared/gps-ocr/*`, `shared/report_image_storage.php`, and `shared/report_image_stage.php`.
- Farmer/Admin `report_submit.php` and Farmer `report_update.php`.
- `database/migrations/*` and `database/migrate_gps_photo.php`.
- `FARMERS UI/farmerLanding/get_crop_reports.php` and `map.html`.
- `FARMERS UI/farmersDashboard/profile/updateProfile.php` and Admin `user/updateFarmer.php` because of cross-table username ownership updates.
- Farmer parcel create/edit handlers.
- Login/session helpers and Admin role logic until authentication characterization tests exist.
- Upload directories and stored files.

## Dependency Map

```text
Public pages
  -> landing_data.php / submit_suggestion.php / crop_catalog_api.php
  -> MySQL

Public Crop Map
  -> map.html + Leaflet/OSM + shared crop icons
  -> get_crop_reports.php
  -> crop_reports + damage_reports + farmer

Farmer portal
  -> repeated Farmer session guards + shared sidebar/language
  -> parcel/profile/report handlers
  -> shared DB, crop catalog, defaults, image/GPS services
  -> MySQL + uploads

Admin portal
  -> admin_auth.php + admin sidebar/language
  -> users/parcels/reports/posts/suggestions/crop catalog
  -> MySQL + uploads

GPS report photo
  -> shared browser capture
  -> report_image_stage.php
  -> report_image_storage.php
  -> gps_photo.php
  -> Node extract.cjs -> Sharp -> Tesseract.js -> stamp-parser.cjs
  -> session token
  -> Farmer/Admin report submit
  -> report row + GPS metadata + finalized original image
```

## Final Audit Conclusions

1. **Application files inspected:** approximately 170 application/source files; 185 first-party text files including tests, stress scripts, docs, and artifacts.
2. **Largest/most complex:** public `map.html`, Farmer `report.js`, Admin Harvest/Damage JS/CSS stacks, Farmer parcel CSS/PHP, Farmer report CSS, public map endpoint, and Admin sidebar.
3. **Top duplication hotspots:** Admin Harvest/Damage stacks; Farmer/Admin report creation; Farmer Harvest/Damage branches; repeated report joins; two suggestion modules; connection wrappers; shell/language engines; three profile-image endpoints; public/login/footer markup; path/format utilities.
4. **Top maintainability problems:** duplicated business logic; God files; request-time schema mutation; inconsistent auth/logout; naming/schema inconsistency; space/case-heavy paths; fragmented CSS; inconsistent errors/responses; incomplete schema/migrations; legacy/generated code mixed with production.
5. **Top performance concerns:** full-result PHP pagination; parcel export N+1; runtime schema checks/DDL; one OCR/Tesseract worker per upload; whole-dataset/repeated aggregate loading.
6. **Highest-risk refactoring areas:** authentication/session/roles, report create/update, GPS/OCR, image lifecycle, schema migrations, username ownership cascades, GIS scope, and parcel writes.
7. **Recommended first phase:** characterization tests and safety baseline, with route/payload/DB/file behavior snapshots.
8. **Rewrite decision:** the codebase is suitable for incremental refactoring. A rewrite is neither necessary nor the safest immediate strategy.

