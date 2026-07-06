<?php
require_once dirname(__DIR__, 4) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    error_log($e->getMessage());
    die($e->getMessage());
}
?>
