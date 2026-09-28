<?php

require_once __DIR__ . '/config/database.php';

$scaimsHasCompleteConstantConfig = defined('SCAIMS_DB_HOST')
    && defined('SCAIMS_DB_NAME')
    && defined('SCAIMS_DB_USER')
    && defined('SCAIMS_DB_PASS')
    && defined('SCAIMS_DB_PORT');
$scaimsDatabaseConfig = $scaimsHasCompleteConstantConfig ? [] : scaims_database_config();

// Constants remain the public compatibility boundary used by the application
// and test routers. A pre-defined constant always wins over environment input.
if (!defined('SCAIMS_DB_HOST')) define('SCAIMS_DB_HOST', $scaimsDatabaseConfig['host']);
if (!defined('SCAIMS_DB_NAME')) define('SCAIMS_DB_NAME', $scaimsDatabaseConfig['name']);
if (!defined('SCAIMS_DB_USER')) define('SCAIMS_DB_USER', $scaimsDatabaseConfig['user']);
if (!defined('SCAIMS_DB_PASS')) define('SCAIMS_DB_PASS', $scaimsDatabaseConfig['password']);
if (!defined('SCAIMS_DB_PORT')) define('SCAIMS_DB_PORT', $scaimsDatabaseConfig['port']);
unset($scaimsDatabaseConfig, $scaimsHasCompleteConstantConfig);

$servername = SCAIMS_DB_HOST;
$username = SCAIMS_DB_USER;
$password = SCAIMS_DB_PASS;
$dbname = SCAIMS_DB_NAME;

$host = SCAIMS_DB_HOST;
$db = SCAIMS_DB_NAME;
$user = SCAIMS_DB_USER;
$pass = SCAIMS_DB_PASS;
$port = SCAIMS_DB_PORT;

if (!function_exists('scaims_db_mysqli')) {
    function scaims_db_mysqli(): mysqli
    {
        try {
            $conn = @new mysqli(
                SCAIMS_DB_HOST,
                SCAIMS_DB_USER,
                SCAIMS_DB_PASS,
                SCAIMS_DB_NAME,
                SCAIMS_DB_PORT
            );
        } catch (mysqli_sql_exception $error) {
            error_log('SCAIMS MySQLi connection failed (error ' . $error->getCode() . ').');
            throw new RuntimeException('Database connection failed.');
        }

        if ($conn->connect_error) {
            error_log('SCAIMS MySQLi connection failed (error ' . $conn->connect_errno . ').');
            throw new RuntimeException('Database connection failed.');
        }

        if (!$conn->set_charset('utf8mb4')) {
            $conn->close();
            throw new RuntimeException('Database character set initialization failed.');
        }

        return $conn;
    }
}

if (!function_exists('scaims_db_pdo')) {
    function scaims_db_pdo(array $options = []): PDO
    {
        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            return new PDO(
                sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    SCAIMS_DB_HOST,
                    SCAIMS_DB_PORT,
                    SCAIMS_DB_NAME
                ),
                SCAIMS_DB_USER,
                SCAIMS_DB_PASS,
                $options + $defaults
            );
        } catch (PDOException $error) {
            error_log('SCAIMS PDO connection failed (driver code ' . $error->getCode() . ').');
            throw new PDOException('Database connection failed.');
        }
    }
}
