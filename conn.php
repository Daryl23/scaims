<?php

// Update these values in one place when moving between XAMPP and InfinityFree.
if (!defined('SCAIMS_DB_HOST')) {
    define('SCAIMS_DB_HOST', 'localhost');
    define('SCAIMS_DB_NAME', 'apgorrs');
    define('SCAIMS_DB_USER', 'root');
    define('SCAIMS_DB_PASS', '');
    define('SCAIMS_DB_PORT', 3306);
}

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
        $conn = new mysqli(
            SCAIMS_DB_HOST,
            SCAIMS_DB_USER,
            SCAIMS_DB_PASS,
            SCAIMS_DB_NAME,
            SCAIMS_DB_PORT
        );

        if ($conn->connect_error) {
            throw new RuntimeException('Connection failed: ' . $conn->connect_error);
        }

        $conn->set_charset('utf8mb4');

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
    }
}
