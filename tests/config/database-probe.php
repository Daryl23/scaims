<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

if (($constantDb = getenv('SCAIMS_PROBE_CONSTANT_DB')) !== false && $constantDb !== '') {
    define('SCAIMS_DB_HOST', getenv('SCAIMS_PROBE_CONSTANT_HOST') ?: '127.0.0.1');
    define('SCAIMS_DB_PORT', (int) (getenv('SCAIMS_PROBE_CONSTANT_PORT') ?: 3306));
    define('SCAIMS_DB_NAME', $constantDb);
    define('SCAIMS_DB_USER', getenv('SCAIMS_PROBE_CONSTANT_USER') ?: 'root');
    define('SCAIMS_DB_PASS', getenv('SCAIMS_PROBE_CONSTANT_PASSWORD') ?: '');
}

try {
    require dirname(__DIR__, 2) . '/conn.php';
    $mode = $argv[1] ?? 'config';

    if ($mode === 'config') {
        echo json_encode([
            'host' => SCAIMS_DB_HOST,
            'port' => SCAIMS_DB_PORT,
            'name' => SCAIMS_DB_NAME,
            'user' => SCAIMS_DB_USER,
            'password_set' => SCAIMS_DB_PASS !== '',
        ]);
        exit;
    }

    if ($mode === 'mysqli') {
        $conn = scaims_db_mysqli();
        $database = $conn->query('SELECT DATABASE() AS database_name')->fetch_assoc()['database_name'];
        echo json_encode(['database' => $database, 'charset' => $conn->character_set_name()]);
        $conn->close();
        exit;
    }

    if ($mode === 'pdo') {
        $pdo = scaims_db_pdo();
        echo json_encode([
            'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
            'charset' => $pdo->query('SELECT @@character_set_connection')->fetchColumn(),
        ]);
        exit;
    }

    throw new RuntimeException('Unknown probe mode.');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(3);
}
