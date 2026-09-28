# SCAIMS Phase 2 — Configuration, Database Bootstrap, and Schema Discipline

Date: 2026-09-28  
Branch: `main`  
Status: Complete

## Scope and starting state

Phase 2 changes configuration, connection/bootstrap compatibility, schema documentation, migrations, and tests only. Authentication behavior, report semantics, GPS/OCR, uploads, UI, routes, and known Phase 1 defects remain unchanged.

At the start, `main` tracked `origin/main`. The working tree already contained the Phase 1 additions, the Phase 0 audit, and refreshed tracked GPS result files. Nothing was staged and all existing work was preserved.

## Before and after

Previously, `conn.php` directly contained deployment values and both factories. Six feature wrappers repeated include/connect/die logic. Tests overrode constants. Four SQL migrations existed without an ordered executable ledger, five helpers could perform request-time DDL, and there was no structure-only baseline.

The resulting boundary is:

```text
OS / Apache / test environment
             |
             v
     config/database.php
             |
             v
 conn.php constants + legacy globals
             |
       +-----+-----+
       v           v
scaims_db_mysqli() scaims_db_pdo()
             |
             v
 existing routes and compatibility wrappers
```

`config/database.php` reads and validates settings but never connects. `conn.php` preserves all public constants, legacy variables, and factory signatures. Predefined constants take priority so Phase 1 disposable routers remain isolated. Both factories establish `utf8mb4`; failures expose generic messages and never log passwords or raw DSNs.

## Environment configuration

| Variable | Purpose | Compatibility fallback |
|---|---|---|
| `SCAIMS_DB_HOST` | Host | `localhost` |
| `SCAIMS_DB_PORT` | Port, validated as 1–65535 | `3306` |
| `SCAIMS_DB_NAME` | Database | Historical XAMPP database name |
| `SCAIMS_DB_USER` | User | `root` |
| `SCAIMS_DB_PASSWORD` | Password | empty |
| `SCAIMS_DB_PASS` | Legacy environment alias | Used only when the primary password variable is absent |

`.env.example` contains placeholders. No dotenv package was added and SCAIMS does not parse `.env`. `.gitignore` protects common local `.env` names. Windows/XAMPP instructions are in `README.md`.

## Factory and wrapper compatibility

- `scaims_db_mysqli(): mysqli` remains usable.
- `scaims_db_pdo(array $options = []): PDO` remains usable; caller options retain precedence.
- Existing `SCAIMS_DB_*` constants and both groups of legacy globals remain populated.
- Six active wrapper paths were retained and now include `shared/db_connection_compat.php`:
  - Admin parcel and user wrappers
  - Admin Harvest and Damage wrappers
  - Farmer parcel wrapper
  - Farmer registration wrapper
- The shared compatibility include still creates the expected `$conn` MySQLi variable and terminates on failure.

No wrapper or route-visible directory was renamed or deleted.

## Schema baseline and migration ledger

- `database/schema.sql` contains the current 12 application table structures and no data.
- Generated next-ID values were removed. Foreign-key checks are disabled only during import and restored afterward.
- `database/SCHEMA.md` documents tables, setup, migration order, dependencies, and runtime DDL.
- `database/migrations.php` declares order, automatic/manual policy, and idempotency.
- `database/migrate.php --status` reports applied, pending, manual, and changed-checksum migrations without creating the ledger.
- `database/migrate.php --apply` applies only automatic migrations and records SHA-256 checksums in `schema_migrations`.
- The destructive/non-idempotent plaintext-password migration is always manual.
- New migrations cover `admin.AdminRole` and `crop_catalog`.

## Runtime DDL inventory

| Helper/area | Schema operation | Request triggers | Migration coverage | Phase 2 decision |
|---|---|---|---|---|
| `scaims_ensure_admin_role_column()` | Previously added `admin.AdminRole` | Admin login | `2026_09_11` | **DDL removed**; read-only validation retained |
| `scaims_ensure_crop_catalog_table()` | Previously created `crop_catalog` | Catalog API, crop actions | `2026_09_12` | **DDL removed**; empty-table default seeding retained as DML |
| `scaims_ensure_report_image_columns()` | Checks/adds `image_path` on both report tables | Farmer page/submit/update; Admin submit/detail | `2026_09_04` | Retained: high-risk DB/filesystem compatibility |
| `scaims_ensure_farmer_report_defaults_table()` | Creates defaults table/indexes | Farmer report/default routes; Admin prefill | `2026_09_05` | Retained: shared report create/update/prefill behavior |
| `scaims_ensure_gps_photo_columns()` | Checks/adds seven GPS columns to both report tables | Farmer/Admin report routes | `2026_09_10` | Retained: GPS/OCR behavior is high risk |
| Admin parcel diagnostic query | Reads `INFORMATION_SCHEMA.COLUMNS` | Admin Farmer/parcel listing | None needed | Retained: read-only, no schema mutation |

The exact route list and migration details are in `database/SCHEMA.md`. Report/GPS/default DDL should be removed one area at a time only after migration use is proven outside the test environment.

## Tests added

- `tests/config/database-configuration.cjs`
- `tests/config/database-probe.php`
- `tests/database/migration-schema-characterization.cjs`
- `tests/database/migration-fixtures.php`

Coverage includes local defaults, environment override, constant precedence, both factories, `utf8mb4`, invalid port, redacted connection failure, clean baseline import, ordered safe migrations, destructive migration exclusion, required schema, idempotent rerun, checksum ledger behavior, post-migration factory connectivity, and cleanup.

Every migration test database matches `scaims_phase2_test_<timestamp>_<pid>`. Cleanup rejects other names and drops only its own database.

## Test record

### Before changes

All Phase 1 suites passed: GPS parser (21 assertions), real OCR (two formats), GPS Farmer/Admin workflow (13 groups), registration, auth, reports, parcels, profile, posts/suggestions, and public map.

### During implementation

- Direct MySQLi/PDO charset probe: PASS.
- Authentication after configuration/wrapper consolidation: PASS.
- Authentication and public map after Admin/catalog DDL removal: PASS.
- Configuration suite: PASS.
- Schema/migration suite, including structure-only import: PASS.

### Final verification

All Phase 1 suites passed again after the completed Phase 2 changes: parser (21 assertions), real OCR (two layouts), GPS workflow (13 groups), registration, auth, reports, parcels, profile, posts/suggestions, and public map. Both new Phase 2 suites and every modified/new PHP syntax check also passed. Commands for Phase 1 are in `docs/PHASE_1_TEST_BASELINE.md`.

```powershell
$env:PHP_BINARY='C:/xampp/php/php.exe'
$node='C:/Users/masil/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node.exe'
& $node tests/config/database-configuration.cjs
& $node tests/database/migration-schema-characterization.cjs
& 'C:/xampp/php/php.exe' database/migrate.php --status
```

## Manual setup and compatibility concerns

1. Existing databases missing `AdminRole` or `crop_catalog` must run safe migrations before those routes; requests no longer create them.
2. Existing XAMPP defaults remain, but explicit environment configuration is recommended.
3. `.env` is not automatically loaded; configure Apache/service environment variables and restart Apache.
4. The baseline truthfully retains mixed collations and nullable `plain_password`; neither was silently changed.
5. DDL auto-commit prevents fully transactional multi-statement migrations.
6. Existing databases can safely run `--apply`: idempotent statements confirm schema and are then recorded.
7. Three high-risk runtime-DDL helpers remain intentionally.
8. All confirmed Phase 1 defects remain intentionally unchanged.

## Rollback notes

- Configuration and wrapper changes are code-only; environment variables do not mutate data.
- Restoring prior Admin/catalog helper code would re-enable runtime DDL, but tables/columns added by migrations should not be dropped.
- Do not remove application schema or truncate data to undo additive migrations. Prefer a reviewed forward migration.
- Never delete `schema_migrations` casually; it is the checksum/audit ledger.

## Phase 3 readiness

Phase 3 authentication consolidation is safe to begin incrementally after the final green run. Treat the centralized factories/configuration as stable boundaries. Admin logout and session changes must remain explicit tested behavior changes, not incidental cleanup.
