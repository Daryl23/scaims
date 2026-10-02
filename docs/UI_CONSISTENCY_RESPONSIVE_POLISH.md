# SCAIMS UI Consistency and Responsive Polish

## Scope and starting state

Work started on `main` with a clean working tree. This pass was limited to first-party presentation styles, the shared dashboard shell, responsive characterization, and the active public Crop Map viewport. Routes, database code, authentication rules, reporting logic, GPS/OCR, and form contracts were not changed.

## Homepage palette and 60/30/10 interpretation

The active homepage remains the visual source of truth:

- Dominant surfaces (approximately 60%): cream `#fffcef`, warm cream `#f7f2e8`, and white `#ffffff`.
- Primary product identity (approximately 30%): olive `#3b4918`, light olive `#576f1b`, and dark olive `#2a3311`.
- Accent (approximately 10%): harvest orange `#f7931e` / `#d97b0d` and existing gold accents `#c8bd44` / `#f6b73c`.
- Supporting color: brown `#674636`.
- Semantic feedback colors remain distinct. Bootstrap green `#198754` is retained as success, while established danger colors remain unchanged.

`shared/design-tokens.css` now exposes these values through semantic background, surface, primary, secondary, accent, success, warning, danger, border, and on-primary roles. The homepage palette itself was not replaced.

## Shared visual consistency

- `shared/components.css` now uses the semantic roles for shared buttons, cards, fields, report tables, pagination, secondary actions, accent actions, and danger actions.
- `ADMIN UI/shared/admin-sidebar.css` provides a late-loaded Admin presentation boundary for equivalent page surfaces, headings, form controls, tables, primary actions, secondary actions, and focus states while leaving feature layout rules in place.
- `FARMERS UI/farmersDashboard/shared/farmer-mobile.css` applies the same roles to active Farmer dashboard surfaces, cards, headings, forms, primary actions, and the dashboard sidebar without changing feature geometry or selectors used by JavaScript.
- `FARMERS UI/registration/registration-steps.css` replaces repeated literal progress, border, accent-hover, and validation colors with the shared semantic tokens.
- Existing specialized map, chart, statistics, GPS evidence, Harvest/Damage, warning, success, and danger treatments were deliberately retained where they communicate domain meaning.

## Admin mobile sidebar root cause and repair

The drawer was visible but unclickable because its stacking order was inverted:

- sidebar: `z-index: 1000`
- backdrop: `z-index: 1090`

The backdrop therefore sat above the drawer and captured pointer input. The shared repair establishes this order:

- menu toggle: `z-index: 1110`
- sidebar: `z-index: 1100`, `pointer-events: auto`
- backdrop: `z-index: 1090`, pointer events enabled only while active
- page content below those layers

The mobile drawer now uses a viewport-bounded width, `100dvh`, vertical scrolling, and contained overscroll. The 48px hamburger uses safe-area-aware placement, visible keyboard focus, and moves to the drawer edge while open so it does not cover navigation labels. `aria-controls` and `aria-expanded` reflect drawer state. Escape closes the drawer and returns focus to the toggle; the existing toggle, backdrop, navigation-link, and resize close behavior remains.

## Responsive improvements

- Active Farmer/Admin forms retain full-width controls and collapse their established multi-column layouts at mobile widths.
- Wide first-party tables remain in responsive scrolling containers rather than expanding the document.
- Admin and Farmer cards and modals use shared surfaces and borders while retaining feature-specific dimensions.
- The public Crop Map now clips Leaflet's translated internal panes at the document boundary, preventing the map implementation from creating horizontal page overflow while preserving pan/zoom behavior.
- No routes, input names, IDs, report maps, chart dimensions, or modal business behavior changed.

## Verification

Static and database-independent checks completed successfully:

- Phase 5 shared frontend characterization: pass
- Phase 7 Admin report-view characterization: pass
- Phase 9 design-system characterization (extended): pass
- GPS parser regression: pass
- XAMPP PHP syntax check for `admin-sidebar.php`: pass
- JavaScript syntax checks for the shared shell and frontend tests: pass
- New database-independent Admin mobile-sidebar Playwright test: pass at 390px and desktop recheck at 1366px
- `git diff --check`: pass

The browser suite now covers 1366, 1024, 768, 430, and 390 pixel widths for the homepage, public Crop Map, Farmer/Admin login, registration, Farmer dashboard/report/parcel/profile, Admin dashboard/users/parcels, and both Admin report pages. It also performs an authenticated real-link mobile drawer click-through when the database is available.

The database-backed suite could not complete in this run. Local MariaDB stopped during the responsive suite, and `mysql_error.log` reports pre-existing InnoDB tablespace/log-sequence corruption plus an unwritable `ibdata1`. No recovery, repair, or database-file mutation was attempted. Authentication, registration, GPS workflow, live Admin report-view, and public-map database regressions therefore remain pending until the local MariaDB data directory is safely repaired or replaced with a disposable healthy instance.

## Remaining visual considerations

Legacy feature styles still contain literal colors and older component selectors. They were left in place where a semantic mapping was unclear or the color carries feature meaning. Bootstrap and Tailwind pages continue to coexist with the shared first-party token layer; neither framework was removed or restyled globally.
