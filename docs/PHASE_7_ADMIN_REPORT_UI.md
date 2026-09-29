# Phase 7 — Admin Harvest/Damage Read UI Consolidation

## Scope and baseline

Phase 7 was limited to the Admin Harvest and Damage read/presentation pages. The authentication, report/image lifecycle, GPS Farmer/Admin workflow, and Phase 5 frontend suites passed before source changes. Report creation/update services, GPS/OCR, authentication, database configuration/schema, and read-endpoint SQL were treated as stable boundaries.

The targeted comparison found three categories:

- Identical infrastructure: page tracking, pagination controls, debounced search, AJAX request wiring, detail-image state, modal lifecycle, Leaflet initialization/layers/coordinate handling, Chart.js replacement/destruction, and export-control creation.
- Configuration differences: endpoint URLs, selectors, table column counts, map colors/messages, public-map parameters, and export transport (Harvest download versus Damage POST form).
- Type-specific behavior: all Harvest/Damage field mappings, table HTML returned by their endpoints, popup content, chart datasets/metrics, PDF/Excel contents, and backend SQL.

## Shared modules introduced

`shared/admin-report-view/report-view-core.js` now provides the common list request, pagination, search, detail request, report-image display, modal lifecycle, Chart.js lifecycle, export-control shell, blob download, and form-submit mechanics.

`shared/admin-report-view/report-map.js` now provides the common Leaflet map/tile/layer setup, coordinate validation, marker lifecycle, icon integration, public-map navigation, directions navigation, and map resizing. Harvest and Damage supply explicit colors, field accessors, popup markup, messages, and URL parameters.

`shared/admin-report-view/report-view.css` contains the formerly duplicated report-map card, controls, statistics, Leaflet popup, map container, and responsive rules. The orange Harvest and red Damage icon accents remain in their type stylesheets.

## Page migration

The following pages load the shared CSS and JavaScript before their type script:

- `ADMIN UI/adminDashboard/report/crop/cropReports.php`
- `ADMIN UI/adminDashboard/report/damage/damageReports.php`

The existing `cropReports.js` and `cropDamage.js` files remain the type configurations/controllers. They continue to own:

- Harvest and Damage endpoint names and payloads
- table result rendering and count text
- type-specific detail fields
- popup contents and marker palettes
- Harvest bar-chart and Damage pie-chart data/labels
- all PDF and spreadsheet export contents
- existing language/GPS detail integration

The previous-report heading received a stable DOM ID used by the shared pagination controller for the existing smooth-scroll behavior. Existing page, asset, AJAX, detail, graph, and export URLs were not renamed.

## Shared behavior

### List, filters, and pagination

Both type controllers use the shared request wrapper, page state/controller, previous/next button state, page label, and debounced search handler. Their response parsing, empty/error wording, filters, table column counts, and full-result-set backend pagination semantics remain explicit and unchanged.

### Detail modal

Both pages use the shared detail request, image/no-image display, modal open/close/outside-click lifecycle, and map invalidation. Harvest and Damage still map response fields separately.

### Map

Leaflet creation, OpenStreetMap/Esri layers, layer buttons, coordinate checks, marker replacement, icon lookup through `shared/leaflet-crop-icons.js`, public-map path resolution through `shared/app-path.js`, directions, and no-coordinate state are shared. The saved coordinates, popup data, public-map query parameters, and GIS meaning were preserved.

### Charts and exports

Chart destroy/recreate behavior and graph-modal cleanup are shared. The export button shell, Harvest blob download, and Damage hidden-form submission use shared helpers. Chart calculations, labels, report metrics, filenames, PDF layouts, worksheet data, and server export routes remain type-specific.

## CSS consolidation

Approximately 209 duplicated map/popup/responsive CSS lines were removed from each type stylesheet and replaced by one shared report-view stylesheet. This is deliberately limited to the Admin report read pages; global tokens, buttons, tables, forms, and site-wide design-system work remain deferred to Phase 9.

## Compatibility and deferred work

- No PHP read endpoint or JSON response shape changed.
- No create/update route, report domain service, GPS/OCR file, auth helper, DB configuration, schema, or migration changed.
- SQL pagination, total-count queries, exports, indexes, N+1 behavior, dashboard aggregates, and dataset loading remain deferred to Phase 10.
- Harvest/Damage table HTML and query stacks remain separate because their schemas and visible fields differ.
- Broad translation-dictionary cleanup and global CSS consolidation remain deferred.
- Previously documented issues (cross-Farmer parcel disclosure, missing broad CSRF protection, orphaned post image deletion, and expired pending GPS files) remain unchanged.

## Tests

Added:

- `tests/frontend/admin-report-view-characterization.cjs`: shared pagination, no-coordinate/valid-coordinate map behavior, Chart.js lifecycle, script order, route strings, type-specific chart types, and export controls.
- `tests/admin-report-view.cjs`: disposable-database Playwright coverage for Harvest and Damage page load, list fetch, crop/reporter/search filters, pagination status, detail modal, valid map marker, no-image state, chart rendering, export controls, empty results, and mobile-width layout.

Final regression passed:

- authentication/authorization
- GPS parser and real OCR
- GPS Farmer/Admin workflow (13 groups)
- registration wizard
- report/image lifecycle
- parcel and profile characterization
- posts/suggestions
- public map
- Phase 2 configuration and migration/schema suites
- Phase 5 shared frontend suite
- both Phase 7 suites
- JavaScript and PHP syntax checks
- `git diff --check`

The existing known-issue assertions remained unchanged and expected. No flaky Phase 7 test was observed.

## Phase 8 readiness

Phase 8 is safe to begin. The Admin read/presentation boundary now consumes the existing GPS/report data without changing GPS/OCR semantics, and the full behavioral baseline remains green.
