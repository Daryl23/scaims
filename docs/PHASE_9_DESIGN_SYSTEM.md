# Phase 9 — CSS and Design-System Consolidation

## Status

Phase 9 is complete. The work was limited to first-party styling, active page stylesheet loading, and CSS-focused tests. Routes, JavaScript behavior, authentication, database behavior, reporting writes, and GPS/OCR behavior were not changed.

The working tree was already dirty at the start with the expected uncommitted Phase 5–8 work. Those changes were preserved.

## CSS Architecture Before Phase 9

- The public `style.css` contained the most complete token set, but the values were not available as a standalone shared layer.
- Farmer shell/mobile, Admin shell/sidebar, public navigation, registration, image capture, and report views repeated the same olive, brown, cream, green, radius, and spacing values.
- The active Admin sidebar embedded approximately 370 lines of reusable CSS in `ADMIN UI/shared/admin-sidebar.php`.
- Admin Harvest and Damage stylesheets repeated table, action-button, and pagination rules.
- Both Admin report pages embedded the same `.container` / `.content` page-shell `<style>` block.
- Bootstrap and Tailwind are used by existing pages and had to remain in place.

## Shared Files Introduced

### `shared/design-tokens.css`

The canonical token layer now owns the existing SCAIMS values for:

- olive, brown, harvest orange, gold, green, danger, cream, white, and neutral colors;
- primary, background, text, border, and accent semantic aliases;
- the existing Inter, Poppins, and Playfair font families;
- the existing 4/8-pixel spacing scale;
- radii, shadows, transitions, layout widths, and z-index levels.

No palette values were redesigned. Consumer styles now resolve the same literal values through CSS custom properties.

### `shared/components.css`

This contains small, namespaced/targeted foundations for:

- `.scaims-button`, shared report-image buttons, and shared map buttons;
- `.scaims-card`;
- `.scaims-field` and focus behavior;
- `.scaims-table-wrap` / `.table-scroll`;
- the existing Harvest and Damage report tables;
- Admin report pagination and action buttons.

The file deliberately does not redefine Bootstrap `.btn` or global form elements.

### `ADMIN UI/shared/admin-sidebar.css`

The active Admin sidebar's existing CSS was moved verbatim from the PHP include into this stylesheet, then connected to shared tokens. `admin-sidebar.php` remains the active markup/role boundary and loads the new stylesheet after `admin-mobile.css`, preserving the previous cascade order.

## Token Adoption

The following first-party boundaries now consume the shared token layer:

- root public `style.css`;
- `shared/public-nav.css`;
- `shared/choice-cards.css`;
- `shared/report-image-capture.css`;
- `shared/admin-report-view/report-view.css`;
- Farmer dashboard shell and mobile CSS;
- Admin layout and sidebar CSS;
- registration step/progress CSS;
- Admin Harvest and Damage report CSS for repeated green states.

Approximately 100 consumer-side literal color references or local palette aliases were routed through the canonical tokens in the touched stylesheets. Literal values remain in the token definitions and in feature-specific rules whose semantic role was not sufficiently clear.

## Component Consolidation

### Buttons

Shared interaction, font, disabled, and focus-visible behavior is used by image-capture and Admin map buttons. Admin report action buttons share one definition. Bootstrap button rules were not changed.

### Cards and Forms

Namespaced `.scaims-card` and `.scaims-field` primitives were added for incremental adoption. Existing specialized report, map, registration, and dashboard card/form selectors remain where their dimensions or behavior differ.

### Tables and Pagination

Harvest and Damage now share their base table shell, headers/cells, row states, responsive overflow, pagination, and action-button styling. Type-specific column widths, report metrics, and feature rules remain in `crop.css` and `damages.css`.

Approximately 33 repeated selector blocks were removed from the two report stylesheets and replaced by the shared component layer.

## Sidebar and Layout Changes

- Extracted the active Admin sidebar's approximately 376 CSS lines from PHP into a cache-versioned stylesheet.
- Kept all existing markup, class names, role-dependent items, mobile drawer behavior, icons, and script loading.
- Farmer and Admin shells remain separate because their presentation is intentionally different; both now consume the same palette tokens.
- Admin layout palette aliases now point to canonical tokens.

## Inline CSS Reduction

Three active reusable `<style>` blocks were removed:

1. Admin sidebar shell styling;
2. Harvest report page `.container` / `.content` shell;
3. Damage report page `.container` / `.content` shell.

The report page-shell rules now live in the existing shared Admin report-view stylesheet. One-off export markup styles and unrelated active-page inline rules were intentionally retained.

## Feature CSS Deliberately Retained

- Farmer report, parcel, profile, and home-specific layout rules;
- Admin users, parcels, posts, suggestions, charts, export, and modal-specific rules;
- Harvest-only and Damage-only fields, chart colors, and metric presentation;
- Leaflet and other vendor styles;
- Bootstrap registration styling and Tailwind public/login page usage;
- legacy or uncertain styles where route compatibility was not proven.

No vendor, minified, Bootstrap, Leaflet, FontAwesome, or Tailwind source was edited.

## Responsive and Accessibility Verification

Automated browser checks covered representative public, Farmer, and Admin pages at:

- 1366 px desktop;
- 768 px tablet;
- 390 px mobile.

They verified page visibility, no failed same-origin stylesheet requests, and no document-level horizontal overflow. The Admin report suites separately exercised Harvest/Damage tables, modals, maps, charts, filters, and responsive layouts. Existing focus-visible styling was retained, and the shared component layer adds an explicit keyboard focus ring without removing existing ARIA or button semantics.

## Tests Run

All passed:

- `tests/frontend/shared-frontend-characterization.cjs`
- `tests/auth/auth-characterization.cjs`
- `tests/registration-wizard.cjs`
- `tests/reports/report-lifecycle-characterization.cjs`
- `tests/gps-photo-workflow.cjs` — 13 workflow groups
- `tests/frontend/admin-report-view-characterization.cjs`
- `tests/admin-report-view.cjs`
- `tests/map/public-map-characterization.cjs`
- `tests/frontend/design-system-characterization.cjs` — new
- `tests/frontend/design-system-browser.cjs` — new
- PHP syntax checks for every PHP file changed in Phase 9
- JavaScript syntax checks for changed/new test files
- `git diff --check`

No flaky test was observed.

## Known Issues Unchanged

The following remain outside Phase 9:

- broad CSRF protection;
- cross-Farmer parcel detail disclosure;
- orphaned post images after deletion;
- duplicate Suggestions implementations;
- SQL pagination, N+1, aggregate, map-loading, and index work;
- general cross-session pending-file garbage collection;
- Tesseract worker startup/concurrency performance.

## Phase 10 Readiness

Phase 10 performance work is safe to begin. The CSS changes are isolated behind shared stylesheets, active routes are unchanged, the write-path characterization suites remain green, and responsive coverage now protects the principal page shells and Admin report presentation.
