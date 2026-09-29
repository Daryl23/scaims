# Phase 5: Shared Frontend Utilities

Date: 2026-09-29  
Branch: `main`

## Language engine

Previously, the Admin and Farmer language scripts separately implemented identical localStorage, DOM traversal, active-button, accessibility-attribute, and event behavior. `shared/i18n.js` now owns that engine. The portal files remain the dictionary/configuration boundary:

- `ADMIN UI/shared/admin-dashboard-language.js` retains the Admin English/Tagalog dictionary and `adminDashboardLanguage` storage key.
- `FARMERS UI/farmersDashboard/shared/dashboard-language.js` retains the Farmer English/Tagalog dictionary and `farmersDashboardLanguage` storage key.

The existing `window.adminDashboardI18n` and `window.dashboardI18n` APIs remain available. Text, HTML, placeholder, ARIA-label, `<html lang>`, active-button, default-language, and `dashboard:language-changed` behavior are preserved. Admin and Farmer dashboard pages load the shared engine before their dictionaries.

## Application path boundary

`shared/app-path.js` remains the canonical detector and now exposes `window.SCAIMSAppPath` with `getBasePath()`, `resolve()`, and `toRelative()`. Existing globals (`APP_BASE_PATH`, `resolveAppPath`, and `toAppRelativePath`) remain compatible.

The duplicate `/scaims` detection was removed from:

- `shared/crop-catalog.js`
- `shared/leaflet-crop-icons.js`
- legacy `FARMERS UI/accountCreation/accountCreation.js`

All direct crop-catalog, crop-icon, and legacy account-creation callers now load `shared/app-path.js` first. Resolution remains script-location based, supporting domain-root, `/scaims`, and other nested hosted deployment paths without Windows paths or localhost assumptions.

## Migrations and compatibility

Language-engine consumers migrated: the four active Farmer dashboard pages and the shared Admin sidebar. Path consumers migrated: Farmer report and parcel forms, Admin parcel/report forms, crop management/report maps already using the boundary, the public map already using it, and the legacy account-creation page.

No routes, dictionaries, visible translations, CSS, markup structure, authentication, reporting rules, GPS/OCR, database code, or response contracts changed. Classic-script loading and existing globals were retained.

No modal/loading/alert abstraction was added: targeted implementations have flow-specific semantics and were not proven identical. Login templates, public/footer fragments, public navigation fallbacks, Suggestions, and report-specific frontend logic were intentionally deferred.

## Tests and responsive verification

`tests/frontend/shared-frontend-characterization.cjs` covers:

- Farmer and Admin storage keys/configuration;
- English/Tagalog switching;
- text, HTML, placeholder, and ARIA-label translation;
- active language state and `<html lang>`;
- `dashboard:language-changed` detail;
- translation lookup fallback;
- root, `/scaims`, and arbitrary nested hosted base paths;
- legacy path globals and relative-path behavior.

The frontend characterization and JavaScript syntax checks pass. Modified PHP files pass syntax checks. No CSS was changed, so responsive rules and focus styles are unchanged.

The database-backed pre-change suites could not start because the local MariaDB service was unavailable (`error 2002`). A controlled start attempt exposed an existing InnoDB startup failure (`Missing MLOG_CHECKPOINT`); no database repair or data-file change was attempted. Authentication, registration, reports, posts/suggestions, and public-map integration suites therefore remain environment-blocked for this Phase 5 run, rather than application-test failures.

## Known issues and Phase 6 readiness

Previously documented CSRF, cross-Farmer parcel disclosure, orphaned post image, expired pending GPS file, and deferred Suggestions issues remain unchanged. Database-backed verification should be rerun after MariaDB is recovered safely.

The shared frontend boundaries are ready for Phase 6. Phase 6 may begin after restoring MariaDB and confirming the existing integration baseline; reporting consolidation should continue to treat these shared scripts as stable compatibility APIs.
