# SCAIMS

SCAIMS is a framework-free PHP/MySQL application with public, Farmer, and Admin portals. See [`docs/CODEBASE_AUDIT.md`](docs/CODEBASE_AUDIT.md) for its architecture and [`docs/PHASE_1_TEST_BASELINE.md`](docs/PHASE_1_TEST_BASELINE.md) for the characterization safety net.

## Windows/XAMPP setup

Verified locally with PHP 8.2.12 and MariaDB 10.4.32. Application code requires PHP 8.0+.

SCAIMS reads server/process environment variables; it does not parse `.env` files or require a dotenv package. Use `.env.example` as a variable-name template:

```text
SCAIMS_DB_HOST=127.0.0.1
SCAIMS_DB_PORT=3306
SCAIMS_DB_NAME=scaims
SCAIMS_DB_USER=root
SCAIMS_DB_PASSWORD=
```

For compatibility, unset values retain the existing XAMPP defaults. New installations should set `SCAIMS_DB_NAME` explicitly. Supply values through Apache/vhost configuration or the Windows service environment and restart Apache. Never commit a real `.env`; local environment files are ignored.

## Database setup

[`database/schema.sql`](database/schema.sql) is structure-only. [`database/SCHEMA.md`](database/SCHEMA.md) documents tables, migrations, runtime schema checks, and import commands.

```powershell
& 'C:/xampp/mysql/bin/mysql.exe' -u root -e "CREATE DATABASE scaims CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
& 'C:/xampp/mysql/bin/mysql.exe' -u root scaims -e "source database/schema.sql"
$env:SCAIMS_DB_NAME='scaims'
& 'C:/xampp/php/php.exe' database/migrate.php --status
& 'C:/xampp/php/php.exe' database/migrate.php --apply
```

The runner never automatically applies the destructive plaintext-password removal migration.

Both existing factory APIs remain supported and select `utf8mb4`:

```php
require __DIR__ . '/conn.php';
$mysqli = scaims_db_mysqli();
$pdo = scaims_db_pdo();
```

Legacy `db_connection.php`/`db_conn.php` include paths remain compatible. Phase 1 tests override the database with constants; Phase 2 tests use environment variables and disposable databases. See [`docs/PHASE_2_CONFIGURATION_DATABASE.md`](docs/PHASE_2_CONFIGURATION_DATABASE.md).

## Canonical routes and generated output

- Farmer registration uses `FARMERS UI/registration/rsbsa_check.html` and `registration_form.html`. The older `FARMERS UI/accountCreation/` URLs are compatibility redirects only.
- Admin Suggestions is implemented under `ADMIN UI/adminDashboard/suggestions/`. Root `suggestions/` PHP URLs are compatibility adapters.
- First-party pages load the canonical bundled third-party assets from `assets/vendor/`; feature-local vendor copies are not required.
- Browser/OCR results under `tests/gps-photo-results/` and k6 output under `stress-tests/results/` are generated locally and ignored except for their `.gitkeep` markers.
