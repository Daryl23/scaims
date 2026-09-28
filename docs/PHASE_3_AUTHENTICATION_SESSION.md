# SCAIMS Phase 3 — Authentication and Session Consolidation

Date: 2026-09-28  
Branch: `main`  
Status: Complete

## Farmer authentication

Previously, Farmer pages directly called `session_start()` and repeated `UName` checks with route-specific redirects, JSON errors, or plain-text termination. Farmer login and logout also managed sessions independently.

`FARMERS UI/farmersDashboard/shared/farmer-auth.php` now provides safe session start, authentication detection, and separate page, JSON, and plain-text guards. This preserves each route's existing unauthorized response contract rather than forcing one global response format.

Migrated active routes:

- Home page and profile-image endpoint
- Farm/parcel pages, edit/add handler session startup, and image endpoint
- Profile page, update endpoint, and RSBSA check
- Report page, submit, update, defaults-save, and image endpoint

`submitFarmInfo.php` retains its existing in-handler JSON “not logged in” behavior; only safe session startup was centralized. Manual preview files were intentionally left unchanged because they are test/preview entry points rather than active portal routes.

## Shared session lifecycle

`shared/session.php` provides two small framework-free utilities:

- start a session only when none is active;
- clear session data, expire the PHP session cookie, and destroy the session.

Farmer logout now uses the shared destruction utility without changing its redirect. Existing Farmer session keys remain unchanged: `UserID`, `UName`, `ProfileImage`, `FirstName`, and `Surname`.

After successful Farmer credential and active-account validation, login calls `session_regenerate_id(true)` before populating the authenticated session.

## Admin consolidation and logout

`shared/admin_auth.php` now uses the shared safe-session starter. The remaining active manually guarded Admin pages—home, Harvest reports, and Damage reports—now call `scaims_require_admin()` with their original login paths. Redundant `session_start()` calls were removed from Admin report-detail APIs and PIN verification.

Super Admin logic remains in the existing helper and is unchanged: normal Admins cannot access Crop Management; Super Admins can.

`ADMIN UI/adminLogin/logout.php` is the real logout endpoint. It destroys the server session, expires the cookie, and redirects to `adminLogin.html`. The active Admin sidebar now links to this endpoint using its existing depth-aware path calculation.

Admin login regenerates the session ID only after successful password and active-account validation. Existing keys remain unchanged: `AdminID`, `AdminName`, `AdminRole`, and `IsSuperAdmin`.

## Test changes and results

`tests/helpers/phase1-router.php` exposes the session ID only through its keyed, isolated test route. `tests/auth/auth-characterization.cjs` now verifies:

- Farmer and Admin session IDs change after successful login;
- invalid/inactive logins do not authenticate;
- Farmer logout still destroys the session;
- Admin logout destroys the session and protected pages redirect afterward;
- Admin/Super Admin roles and Crop Management access remain unchanged;
- Farmer report visibility and ownership-qualified updates remain protected.

The focused auth suite passed before and after the change. The complete Phase 1 suite and Phase 2 configuration/migration tests passed at final verification.

## Compatibility and intentionally unresolved issues

- Redirect paths, JSON bodies/status codes, plain-text failures, and session key names were preserved.
- Database schema, password hashing, roles, reporting rules, GPS/OCR, and ownership models were not changed.
- Broad CSRF protection remains future work.
- The confirmed cross-Farmer parcel detail disclosure remains intentionally unresolved.
- Orphaned post images and expired pending GPS files remain intentionally unresolved.
- No global session-cookie policy changes were introduced.

## Phase 4 readiness

Phase 4 is safe to begin incrementally. Authentication/session infrastructure now has one Farmer guard boundary, the existing Admin guard boundary, safe session lifecycle utilities, tested session rotation, and tested logout behavior.
