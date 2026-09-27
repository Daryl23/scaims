# GPS Camera OCR reporting

Updated September 10, 2026. This supersedes the previous visual-upload-only implementation.

## Implemented workflow

Farmer Home or Mga Ulat → upload GPS Camera photo → preview and server OCR → read-only GPS Photo Information → existing Harvest/Damage cards → additional crop/report inputs → submit. Personnel use Reports → Submit Report for Farmer and select an active existing Farmer before uploading. Admin Home is unchanged.

The shared uploader runs OCR immediately, displays a loading indicator, disables progression/submission while pending, and retains the same photo and extraction when switching report type. Replace and Remove work on drafts. Failure clears the submission token and asks for another clear photo; there is no manual coordinate fallback. All five values (date, time, address, latitude and longitude) are required for the two supported templates.

## OCR and parsing

One server implementation uses pinned Tesseract.js 7.0.0, local English language data 1.0.0 and Sharp 0.35.4. No external OCR service receives the image. The worker tries the lower half, then the full image with temporary grayscale/contrast/sharpening, then full-image automatic layout. Temporary image processing never changes the saved original. EXIF is not required or used as a source of report values. Optional orientation metadata only helps rotate the temporary OCR copy.

Supported visible layouts:

```text
09/09/2026 08:52 PM GMT+8
Garcia - Rosario - San Juan - Candelaria Road
Rosario Calabarzon
Lat: 13.841768
Long: 121.2782
```

```text
09/09/2026 20:49:24
13.841767, 121.278198 ±20 m
Candelaria Road, Rosario, Calabarzon, Philippines
```

These values are test examples, not hardcoded report defaults. Parsing rules:

- Date order is explicitly month/day/year, including ambiguous dates: 04/05/2026 means April 5. Slash or hyphen numeric dates are accepted and calendar-validated, then stored as YYYY-MM-DD.
- Captured time is stored as HH:mm or HH:mm:ss. Display is always 12-hour AM/PM: 20:49:24 → 08:49:24 PM, 13:05:10 → 01:05:10 PM. Seconds are retained only when detected. GMT/UTC offsets are preserved separately when present; no timezone conversion is invented.
- Latitude/longitude accept Lat/LAT/Latitude and Long/Lon/Longitude labels or a comma-separated decimal pair. Accuracy text such as ±20 m is ignored. Range checks enforce latitude ±90 and longitude ±180. Contradictory values are rejected. O/0 and I/l/1 substitutions are confined to decimal coordinate tokens.
- Address lines adjacent to the stamp are joined with commas. Known camera/telemetry labels are excluded. Addresses must be present and fit the validated length limit; no reverse geocoding or profile lookup supplies missing text.
- Each used OCR line must meet confidence thresholds (mean at least 65, lowest word at least 35). Missing, invalid, conflicting or low-confidence results fail. OCR confidence does not establish authenticity or eliminate plausible recognition errors.

API behavior follows the [Tesseract.js API documentation](https://github.com/naptha/tesseract.js/blob/master/docs/api.md).

## Storage, server validation and source integrity

The existing `shared/report_image_stage.php` accepts authenticated uploads, validates the image, saves the original in the existing pending store and invokes the Node worker. On success it stores parsed values and the original file SHA-256 in the user's session and returns an opaque staging token plus display data. Repeated identical uploads in the same session reuse a valid result. Failed extraction removes its staged image. Existing pending uploads expire after one hour.

Both existing submit endpoints require a valid session-owned extraction token. They reject raw image submission as a way to bypass extraction and ignore client-supplied captured date, time, location or coordinates. The token is bound to the image bytes and consumed when finalized. Metadata and report insertion/update commit together in a database transaction.

The current image rules remain JPG/JPEG, PNG or WEBP, up to 6 MB. PHP validates upload status, size, actual MIME with finfo and image headers with getimagesize; Sharp decodes the image with a 50-million-pixel processing limit. Original filenames are ignored. Random keys and existing `image_path`/URL helpers are reused under `uploads/reports/pending`, `harvest` and `damage`. Saved files remain byte-for-byte originals and persist independently of browser state. Browser APIs do not return internal filesystem paths.

## Database and ownership

Both `crop_reports` and `damage_reports` now have seven nullable columns:

| Column | Purpose |
| --- | --- |
| photo_capture_date | OCR capture date, DATE |
| photo_capture_time | OCR capture time, VARCHAR(8), preserving seconds precision |
| photo_location | Complete OCR address, TEXT |
| photo_latitude | OCR latitude, DECIMAL(12,9) |
| photo_longitude | OCR longitude, DECIMAL(12,9) |
| photo_timezone | Optional visible timezone, VARCHAR(16) |
| photo_ocr_version | Extraction provenance/version, VARCHAR(32) |

Migration: `database/migrations/2026_09_10_add_gps_photo_information.sql`. The CLI runner `database/migrate_gps_photo.php` applies the same additive, idempotent definitions and checks that historical report values/counts are unchanged. It was applied locally: 59 Harvest and 63 Damage reports were preserved; all historical photo columns remain NULL. Runtime schema checks follow the existing report schema initialization pattern.

`reportDate` remains the existing structured submission date. Planting and harvest dates remain crop/business inputs; the photo capture date/time is shown separately. For new Damage reports the existing `date_occurrence` is populated from the photo capture date. Existing report `latitude`/`longitude` and location columns mirror photo-derived values for current GIS consumers (legacy coordinates retain their existing precision; the full address is kept in photo_location). Farm, Farmer, Parcel and registered barangay data remain separate and are never substituted for extracted photo data. No farm or map module was changed.

`FarmerID` is the report owner. Farmer submission resolves it through the authenticated user. Personnel must select an active existing Farmer; the existing `created_by` field records the Admin name/ID. Admin Reports and Farmer Mga Ulat read one underlying report and one image. No new ownership schema or duplicate report store was introduced.

## Viewing, editing and compatibility

Both portal detail views render saved metadata through the shared `renderDetails` component, so viewing does not rerun OCR. Information is displayed as text, not editable inputs. New form location/date-occurrence/Google Maps/Waze/browser-GPS controls and their unused JavaScript were removed.

Farmer View remains locked → Edit → Save or Cancel. For an OCR report, only additional report data is editable; photo replacement runs OCR first. Save updates that same report; Cancel restores original image and metadata. Stale extraction responses cannot change a reopened edit. An OCR report cannot discard its evidence through the update API. Historical reports retain their original dates/coordinates and existing optional-image edit semantics; no historical record is automatically converted. Missing images render an empty state rather than broken URLs.

## Reused and new components

Reused: existing image uploader/storage/staging, report-type choice cards, Farmer/Admin form field sets, crop catalog and registered crop prefill, report submit/update endpoints, report tables and detail dialogs, session authorization, and both English/Tagalog translation systems. Existing form files were adapted in place; no separate Farmer/Admin OCR implementations were created.

New: shared GPS parser/worker, PHP extraction/provenance helper, shared GPS Photo Information renderer inside the existing uploader script, and additive migration/CLI runner.

## Files changed for this reporting feature

Shared:

- `shared/report-image-capture.js`
- `shared/report-image-capture.css`
- `shared/report_image_stage.php`
- `shared/report_image_storage.php` (original-byte preservation from the preceding visual-upload work)
- `shared/gps_photo.php` (new)
- `shared/gps-ocr/stamp-parser.cjs` (new)
- `shared/gps-ocr/extract.cjs` (new)
- `shared/gps-ocr/package.json`, `package-lock.json`, `.gitignore`, `.htaccess` (new)

Farmer:

- `FARMERS UI/farmersDashboard/home/home.php`
- `FARMERS UI/farmersDashboard/report/report.php`
- `FARMERS UI/farmersDashboard/report/report.js`
- `FARMERS UI/farmersDashboard/report/report_submit.php`
- `FARMERS UI/farmersDashboard/report/report_update.php`
- `FARMERS UI/farmersDashboard/shared/dashboard-language.js`

Admin:

- `ADMIN UI/adminDashboard/report/reportAnalytics.php`
- `ADMIN UI/adminDashboard/report/reportAnalytics.css`
- `ADMIN UI/adminDashboard/report/admin-report-form.js`
- `ADMIN UI/adminDashboard/report/report_submit.php`
- `ADMIN UI/adminDashboard/report/get_farmer_report_prefill.php` (registered-data-only prefill from preceding work)
- `ADMIN UI/adminDashboard/report/crop/cropReports.php`
- `ADMIN UI/adminDashboard/report/crop/cropReports.js`
- `ADMIN UI/adminDashboard/report/crop/getCropReports.php`
- `ADMIN UI/adminDashboard/report/crop/fetchCropReports.php` (remove duplicate inline detail click handler)
- `ADMIN UI/adminDashboard/report/damage/damageReports.php`
- `ADMIN UI/adminDashboard/report/damage/cropDamage.js`
- `ADMIN UI/adminDashboard/report/damage/getDamageReports.php`
- `ADMIN UI/shared/admin-dashboard-language.js`

Migration, verification and documentation:

- `database/migrations/2026_09_10_add_gps_photo_information.sql`
- `database/migrate_gps_photo.php`
- `tests/gps-stamp-parser.cjs`
- `tests/gps-stamp-ocr.cjs`
- `tests/gps-photo-workflow.cjs`
- `tests/gps-photo-fixtures.php`
- `tests/gps-photo-router.php`
- `docs/gps-camera-reporting.md`
- Generated test results/screenshots under `tests/gps-photo-results/` (not application assets).

Other preexisting working-tree changes were retained.

## Responsiveness

Shared previews use max-width:100%, height:auto and object-fit:contain, retaining the complete stamp. Enlarge Photo opens the original for zooming. GPS information uses a two-column grid on larger screens, a full-width wrapping address and a single column on phones. Controls use existing responsive form styles. The same component is used in both portals, including personnel on mobile/tablet. Desktop/tablet/mobile browser checks cover widths 1366, 768 and 390; no horizontal overflow is allowed by the tests.

## Running and deploying

This is PHP/vanilla JavaScript; no reporting bundle build step exists. Install Node.js 22.12+ (tested Node 24.18.1), run `npm ci --prefix shared/gps-ocr`, and allow PHP proc_open to launch Node. Set SCAIMS_NODE_BINARY when Node is not at the default Windows or Linux path. The worker uses locally installed language data; no runtime language download is required. Ensure PHP upload_max_filesize is at least 6M, post_max_size exceeds the upload, and web/proxy timeouts permit the bounded OCR request (approximately 65 seconds). The OCR directory must be denied over HTTP (included Apache .htaccess; equivalent configuration required for other web servers).

Run the migration using `php database/migrate_gps_photo.php` or the SQL file against the deployment database. The installed npm dependencies are intentionally ignored by Git; deploy them with npm ci rather than copying platform-specific native packages from another OS.

Verification commands:

```text
node tests/gps-stamp-parser.cjs
node tests/gps-stamp-ocr.cjs
node tests/gps-photo-workflow.cjs
```

The browser suite needs Playwright on NODE_PATH and PHP_BINARY (defaults to C:/xampp/php/php.exe). It creates a disposable scaims_gps_test_<timestamp> database and isolated PHP server/sessions, then cleans its records and uploads. It never submits test reports to the application database. The OCR test generates no-EXIF fixtures for both layouts, including a top-of-image stamp, and verifies original bytes remain unchanged.

## Verification and limits

Passed: 21 parser assertions, actual Tesseract OCR on both layouts, 18 PHP syntax checks, 9 JavaScript syntax checks, and 13 isolated browser workflow groups with zero browser JavaScript errors. Scoped git diff --check also passed. Browser coverage includes both portals/types, image replacement/removal, translations, read-only saved metadata, pending/failure states, invalid uploads, session-token ownership, posted-value tampering, historical editing, same-record updates and responsive layouts.

Actual OCR has been tested against generated representative images for both supplied text formats. Real client photographs were not attached, so template fonts, blur, shadows, compressed messaging-app photos, unusual stamp placement and overlays still need client-photo acceptance testing. Address detection is a conservative nearby-text heuristic; unrelated scene text can still be recognized as address text. High-confidence OCR can still be wrong, and OCR does not verify authentic capture location/time. The UI therefore says information was detected, never GPS Verified. There is no manual override for extraction failures.

Physical phone gallery selection and performance were not tested on hardware; responsive Chrome uses the normal file picker without a capture attribute. The test account without a profile image produces an existing missing home/assets/default-profile.jpg warning; profile behavior is outside this task. OCR uses one bounded worker per authenticated upload; high-volume deployments should size server resources accordingly.
