# SCAIMS Phase 10 — Performance Optimization and Query Efficiency

Date: 2026-09-29  
Branch: `main`  
Status: Complete

## Scope and baseline

Phase 10 changes read/query efficiency only. Routes, POST/GET field names, JSON/HTML response shapes, report ownership, write workflows, GPS/OCR, authentication, and UI presentation remain unchanged. The working tree already contained the completed Phase 5–9 changes; those changes were preserved.

All required pre-change characterization suites passed. A disposable database then added 40 Farmers, 40 parcels, 1,200 Harvest reports, and 1,200 Damage reports on top of the normal Phase 1 fixtures. Measurements are local medians of three requests after one warm-up and are directional, not production capacity claims.

| Path | Original baseline | Main observed bottleneck |
|---|---:|---|
| Admin Harvest page 2 | 59.1 ms | Fetched 1,204 rows, then sliced 10 historical rows in PHP |
| Admin Damage page 2 | 52.6 ms | Fetched 1,202 rows, then sliced 10 historical rows in PHP |
| Parcel export | 19.4 ms | One main query plus one totals query per returned Farmer |
| Landing data | 8.9 ms | Nine database round trips |
| Public Crop Map | 406.2 ms | Raw Crop-to-Damage join expanded matching rows before PHP received grouped reports |

The local machine hosted both PHP/MariaDB and the test client. Timing variance was visible, so structural assertions, response correctness, fetched-row bounds, query counts, and `EXPLAIN` plans are the primary regression evidence.

## Admin Harvest and Damage pagination

Both list endpoints now apply all existing filters before pagination and use:

1. one filtered count query for total/new/previous counts;
2. one query for all reports from the existing seven-day “new” section; and
3. one `LIMIT ? OFFSET ?` query for only the requested historical page.

The deliberately unpaginated “new” section is preserved. Historical reports with a `NULL reportDate` remain classified as previous, matching the former `CASE` behavior. Harvest keeps its client-provided page size; Damage keeps its fixed page size of 10. Existing HTML fragments, count fields, page fields, and Harvest debug fields remain intact.

On the final 1,200-row fixture, both endpoints returned eight new rows plus ten requested historical rows rather than loading roughly 1,200 historical rows into PHP. Final-regression local medians were 34.1 ms for Harvest and 29.4 ms for Damage. Filtered totals and the `NULL reportDate` case are explicitly tested.

The Damage month filter now uses an indexable half-open date range for valid `YYYY-MM` input. Invalid legacy input retains the previous `DATE_FORMAT(...)=?` behavior rather than being silently reinterpreted.

## Parcel export N+1 removal

`ADMIN UI/adminDashboard/parcel/handleExport.php` now joins one grouped parcel-total derived table into the existing export query. The row loop reads `TotalFarmArea` and `TotalParcels` directly and performs no SQL.

For the representative fixture, the query pattern changes from one export query plus 43 per-Farmer totals queries (44 total) to one query. Export headings, Farmers, parcels, crops, ordering, total area, parcel count, and `.xls` HTML format remain unchanged. Filter inputs are now bound prepared-statement parameters. Local timing was noise-bound (19.4 ms before, 31.9 ms in the final three-sample run); removal of query growth with Farmer count is the meaningful result.

## Aggregate round trips

- Admin dashboard counts: four scalar round trips became one `SELECT` containing four explicit scalar subqueries.
- Public landing stats: eight scalar round trips became one explicit stats query; the separate latest-post query remains. Total round trips changed from nine to two.

The Admin card values and all `landing_data.php` JSON property names and PHP number types are regression-tested. No response caching was introduced.

## Public Crop Map

The endpoint already accepted server-side crop, commodity, status, location, price, yield, value, and Damage filters. Phase 10 preserves and tests those inputs.

The expensive raw join was replaced by a derived Damage summary grouped once by `(FarmerID, dmgCropType)`, then joined one summary row to each Harvest report. `max_damage`, `has_damage`, and `total_loss` retain their existing meanings. The GeoJSON `FeatureCollection`, feature properties, supported-area checks, fallback coordinates, metadata, ordering, and client-side filter UX are unchanged.

The representative data created approximately 36,000 raw same-owner/crop matches before grouping. The optimized request joins about 1,204 Crop rows to 40 performance summary groups. The original local map median was 406.2 ms; the final-regression median was 121.5 ms. A combined server-side filter returned 602 matching features and verified crop, commodity, Damage presence, and minimum Damage values.

The browser still intentionally loads the full supported dataset for its current instant local filtering and option/range discovery. Moving every interactive filter to network requests was deferred because it would change that UX and could add request-per-input behavior.

## Index migration and EXPLAIN evidence

Migration `2026_09_29_add_query_performance_indexes.sql` is additive, idempotent on the verified MariaDB 10.4 environment, and managed by the Phase 2 ledger. The structure-only baseline and `database/SCHEMA.md` were updated.

| Index | Before | After / query supported |
|---|---|---|
| `crop_reports(reportDate, id)` | `ALL`, no key, filesort | `range`, named index, covering order for Harvest page query |
| `damage_reports(reportDate, id)` | `ALL`, no key, filesort | `range`, named index for Damage age/page query |
| `damage_reports(date_occurrence, id)` | `ALL`, no key, filesort | `range`, named index for month range |
| `damage_reports(FarmerID, dmgCropType)` | full scan, temporary + filesort for summary | index scan, no temporary/filesort in the representative `EXPLAIN` |
| `farm_parcels(created_by)` | full scan | `ref`, one estimated row in owner join fixture |
| `user(UName)` | full scan + join buffer | `ref`, one estimated row in owner join fixture |
| `admin(AdminName)` | no supporting key | supports report creator classification joins |

No low-cardinality standalone status/role indexes or speculative “index every filter” changes were added. Existing relationship indexes were not dropped.

## Tests added or extended

- `tests/performance/performance-characterization.cjs`
- `tests/performance/performance-fixtures.php`
- Phase 2 migration characterization now verifies all seven new indexes and the sixth automatic migration.

The performance suite proves page-size bounds, filtered totals, new/previous counts, `NULL reportDate`, export totals, aggregate contracts, dashboard output, full/filtered GeoJSON, migration-installed indexes, and before/after `EXPLAIN` plans. All data is created in a uniquely named Phase 1 disposable database and removed afterward.

## Final verification

The final run covered authentication/authorization, registration, report/image lifecycle, GPS Farmer/Admin workflow, parcel, profile, posts/suggestions, public map, Phase 2 configuration and migrations, Phase 5 frontend infrastructure, both Phase 7 Admin report-view suites, both Phase 9 design-system suites, Phase 10 performance/correctness, PHP/JavaScript syntax, and `git diff --check`.

No k6 run was used: destructive write scenarios remain disabled, and the local structural benchmark provided clearer evidence for these query-shape changes. No production/development records were inserted or removed.

## Deliberately deferred

- Tesseract worker pooling, daemon, queue, or OCR concurrency architecture.
- Caching authenticated, mutable, map, report, dashboard, or landing responses.
- Server-request-per-filter public-map UX changes.
- Leading-wildcard search redesign/full-text indexing.
- Pagination of the existing “new reports” section.
- Vendor-tree/build-size cleanup and dead-code deletion (Phase 11).
- Broad SQL/index changes outside measured active paths.

## Deployment and rollback notes

Existing databases should run `php database/migrate.php --apply` during controlled setup/deployment to install the indexes. The application remains correct without the indexes, but does not receive their plan improvements. The migration adds no columns, changes no rows, and drops nothing.

Code rollback can restore the earlier read queries without a data rollback. The added indexes may safely remain; do not drop them during an emergency code rollback without separate query review.

## Phase 11 readiness

Phase 11 is safe to begin. Treat report write services, GPS/OCR, authentication, the Phase 7 presentation contract, the Phase 9 CSS boundary, and the new read-query/index behavior as stable. Phase 11 cleanup should not delete compatibility routes or vendor copies without explicit usage verification.
