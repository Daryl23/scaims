<?php

/**
 * Compatibility include for legacy pages that expect a `$conn` MySQLi value.
 * New code should include conn.php and call scaims_db_mysqli() explicitly.
 */

require_once dirname(__DIR__) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $error) {
    error_log('SCAIMS legacy database wrapper could not connect.');
    die($error->getMessage());
}

