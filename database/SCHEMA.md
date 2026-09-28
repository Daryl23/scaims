# SCAIMS Database Schema and Migration Ledger

This is the schema ledger for the current PHP/MySQL application. [`schema.sql`](schema.sql) is a structure-only baseline generated from the development schema on 2026-09-28. It contains no rows, credentials, password hashes, Farmer records, posts, suggestions, or uploads.

## Supported database

- MySQL/MariaDB semantics are required; MariaDB 10.4.32 under XAMPP was verified.
- Application code requires PHP 8.0+; PHP 8.2.12 was verified.
- Connections use `utf8mb4`. Existing tables retain their current `utf8mb4_general_ci` or `utf8mb4_unicode_ci` collation.

## Baseline tables

| Table | Purpose | Important current relationship/constraint |
|---|---|---|
| `admin` | Admin identity, password hash, role, active state | Primary key `AdminID`; role defaults to `admin` |
| `user` | Farmer login identity and profile image | Primary key `UserID` |
| `farmer` | Farmer identity, contact, RSBSA, activation | `UserID` foreign key to `user` with cascade delete |
| `farm_parcels` | Farm-level location/area/type | Username ownership in `created_by` |
| `parcel_details` | Parcels under farms | Foreign key to `farm_parcels` |
| `crop_details` | Registered crops per parcel | Foreign key to `parcel_details` |
| `crop_reports` | Harvest reports and GPS/photo evidence | Current Farmer ownership, evidence, and indexes |
| `damage_reports` | Damage reports and GPS/photo evidence | Current Farmer ownership, evidence, and indexes |
| `farmer_report_defaults` | Saved Harvest/Damage defaults | Unique `(FarmerID, report_type)` |
| `crop_catalog` | Managed crop names | Unique `(commodity, crop_name)` plus active/order indexes |
| `posts` | Announcements and JSON image paths | Current structure |
| `suggestions` | Public feedback and status | Current structure |

`schema_migrations` is infrastructure owned by `database/migrate.php`; `--apply` creates it when absent, while `--status` remains read-only. It is not application data and is not in the baseline dump.

### Legacy plaintext column

The current development structure still contains nullable `user.plain_password`, although login uses the `UPass` hash. `2026_09_06_drop_plain_password.sql` clears and drops it. Because that migration is destructive and non-idempotent, the automated runner reports it as manual and never executes it. Applying it requires an explicit retention decision and separate verification.

## Create a local database

Create an empty database, import the structure, and apply the safe ledger:

```powershell
& 'C:/xampp/mysql/bin/mysql.exe' -u root -e "CREATE DATABASE scaims CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
& 'C:/xampp/mysql/bin/mysql.exe' -u root scaims -e "source database/schema.sql"
$env:SCAIMS_DB_NAME='scaims'
& 'C:/xampp/php/php.exe' database/migrate.php --apply
```

The baseline creates no accounts or content.

## Migration commands

```powershell
& 'C:/xampp/php/php.exe' database/migrate.php --status
& 'C:/xampp/php/php.exe' database/migrate.php --apply
```

The runner records SHA-256 checksums. A changed already-applied file is reported as `[CHANGED]` and returns nonzero. Applied migration files are immutable; add a new migration instead. MySQL/MariaDB DDL can auto-commit, so back up important databases before deployment migrations.

## Ordered migration ledger

| Order | Migration | Schema | Automatic | Idempotent | Dependencies / notes |
|---:|---|---|---|---|---|
| 1 | `2026_09_04_add_report_image_paths.sql` | Report `image_path` columns | Yes | Yes | Report tables and `longitude` must exist |
| 2 | `2026_09_05_add_farmer_report_defaults.sql` | Creates defaults table/indexes | Yes | Yes | Mirrors retained runtime assurance |
| 3 | `2026_09_06_drop_plain_password.sql` | Clears/drops `user.plain_password` | **No** | **No** | Destructive/manual; excluded from runner |
| 4 | `2026_09_10_add_gps_photo_information.sql` | Seven GPS/OCR columns on both report tables | Yes | Yes | Report tables must exist |
| 5 | `2026_09_11_add_admin_role.sql` | `admin.AdminRole` | Yes | Yes | Replaces login-time `ALTER TABLE` |
| 6 | `2026_09_12_create_crop_catalog.sql` | Creates crop catalog/indexes | Yes | Yes | Existing helper still seeds defaults only when empty |

`database/migrate_gps_photo.php` remains as the older GPS-specific verifier. It snapshots report rows, invokes the retained GPS assurance helper, and checks that non-GPS report data did not change.

## Runtime schema inventory

### DDL removed from requests in Phase 2

| Helper | Previous mutation | Triggering routes | Migration | Current behavior |
|---|---|---|---|---|
| `scaims_ensure_admin_role_column()` | `ALTER TABLE admin ADD AdminRole` | Admin login | `2026_09_11` | Read-only `INFORMATION_SCHEMA` validation; generic migration-required failure if absent |
| `scaims_ensure_crop_catalog_table()` | `CREATE TABLE crop_catalog` | Catalog API and Super Admin crop actions | `2026_09_12` | Reads table and preserves empty-table default seeding; no DDL |

### DDL intentionally retained in requests

| Helper | Schema | Request paths | Migration | Why retained |
|---|---|---|---|---|
| `scaims_ensure_report_image_columns()` | Report `image_path` | Farmer report page/submit/update; Admin submit/detail APIs | `2026_09_04` | High-risk report/file compatibility path |
| `scaims_ensure_farmer_report_defaults_table()` | Defaults table/indexes | Farmer report/default routes; Admin prefill | `2026_09_05` | Crosses report create/update/prefill behavior |
| `scaims_ensure_gps_photo_columns()` | Seven OCR columns | Farmer/Admin report routes | `2026_09_10` | GPS/OCR is explicitly high risk |

`ADMIN UI/adminDashboard/parcel/getFarmers.php` also reads `INFORMATION_SCHEMA.COLUMNS` for diagnostics. It does not mutate schema and remains unchanged.

## Regenerate the baseline safely

Use a structure-only dump; never commit a dump containing rows.

```powershell
& 'C:/xampp/mysql/bin/mysqldump.exe' -u root --no-data --skip-comments --skip-add-locks --skip-disable-keys --compact --default-character-set=utf8mb4 --result-file=database/schema.sql scaims
```

Remove generated `AUTO_INCREMENT=<next-id>` table options, add the structure-only header and foreign-key import guards, and run `tests/database/migration-schema-characterization.cjs`. Review the diff for data before committing.
