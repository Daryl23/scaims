<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__) . '/conn.php';

$mode = $argv[1] ?? '--status';
if (!in_array($mode, ['--status', '--apply'], true)) {
    fwrite(STDERR, "Usage: php database/migrate.php [--status|--apply]\n");
    exit(2);
}

function scaims_migration_statements(string $sql): array
{
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    return array_values(array_filter(array_map('trim', explode(';', $sql)), static fn ($statement) => $statement !== ''));
}

$conn = scaims_db_mysqli();
$ledgerResult = $conn->query("SHOW TABLES LIKE 'schema_migrations'");
$ledgerExists = $ledgerResult && $ledgerResult->num_rows > 0;
if ($mode === '--apply' && !$ledgerExists) {
    $conn->query(
        "CREATE TABLE schema_migrations (
            migration_id VARCHAR(191) NOT NULL PRIMARY KEY,
            checksum CHAR(64) NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $ledgerExists = true;
}

$applied = [];
if ($ledgerExists) {
    $result = $conn->query('SELECT migration_id, checksum, applied_at FROM schema_migrations ORDER BY migration_id');
    while ($row = $result->fetch_assoc()) {
        $applied[$row['migration_id']] = $row;
    }
}

$migrations = require __DIR__ . '/migrations.php';
$failed = false;

foreach ($migrations as $migration) {
    $path = __DIR__ . '/migrations/' . $migration['file'];
    if (!is_file($path)) {
        throw new RuntimeException('Migration file is missing: ' . $migration['file']);
    }

    $sql = (string) file_get_contents($path);
    $checksum = hash('sha256', $sql);
    $record = $applied[$migration['id']] ?? null;

    if ($record) {
        if (!hash_equals($record['checksum'], $checksum)) {
            fwrite(STDERR, '[CHANGED] ' . $migration['id'] . " checksum differs from the applied migration.\n");
            $failed = true;
        } else {
            echo '[APPLIED] ' . $migration['id'] . ' at ' . $record['applied_at'] . "\n";
        }
        continue;
    }

    if (empty($migration['automatic'])) {
        echo '[MANUAL] ' . $migration['id'] . ' - ' . ($migration['reason'] ?? 'manual migration') . "\n";
        continue;
    }

    if ($mode === '--status') {
        echo '[PENDING] ' . $migration['id'] . "\n";
        continue;
    }

    echo '[APPLYING] ' . $migration['id'] . "\n";
    foreach (scaims_migration_statements($sql) as $statement) {
        if (!$conn->query($statement)) {
            throw new RuntimeException('Migration failed: ' . $migration['id'] . '. No credentials or SQL were printed.');
        }
    }

    $stmt = $conn->prepare('INSERT INTO schema_migrations (migration_id, checksum) VALUES (?, ?)');
    $stmt->bind_param('ss', $migration['id'], $checksum);
    $stmt->execute();
    $stmt->close();
    echo '[APPLIED] ' . $migration['id'] . "\n";
}

$conn->close();
exit($failed ? 1 : 0);
