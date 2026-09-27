<?php
// CLI-only, additive and idempotent. Never backfills historical photo information.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/conn.php';
require dirname(__DIR__) . '/shared/gps_photo.php';
$conn = scaims_db_mysqli();
function report_snapshot(mysqli $conn): array {
    $snapshot = [];
    foreach (['crop_reports', 'damage_reports'] as $table) {
        $hash = hash_init('sha256'); $count = 0;
        $rows = $conn->query("SELECT * FROM `$table` ORDER BY id");
        while ($row = $rows->fetch_assoc()) {
            foreach (array_keys(scaims_photo_columns()) as $column) unset($row[$column]);
            hash_update($hash, json_encode($row)); $count++;
        }
        $snapshot[$table] = ['count' => $count, 'hash' => hash_final($hash)];
    }
    return $snapshot;
}
$before = report_snapshot($conn);
scaims_ensure_gps_photo_columns($conn);
$after = report_snapshot($conn);
if ($before !== $after) throw new RuntimeException('Existing report data changed during migration; inspect concurrent report activity.');
foreach ($after as $table => $state) {
    $result = $conn->query("SELECT COUNT(*) AS n FROM `$table` WHERE photo_ocr_version IS NOT NULL")->fetch_assoc();
    echo "$table: seven nullable GPS photo columns ready; {$state['count']} existing reports unchanged; {$result['n']} OCR reports.\n";
}
