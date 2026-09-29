<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/report_image_storage.php';

function phase8_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$marker = 'phase8a-' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.png';
$root = dirname(__DIR__);
$pendingAbsolute = $root . '/uploads/reports/pending/' . $marker;
$harvestAbsolute = $root . '/uploads/reports/harvest/' . $marker;
$outsideDirectory = __DIR__ . '/phase1-results';
$outsideAbsolute = $outsideDirectory . '/' . $marker;

foreach ([dirname($pendingAbsolute), dirname($harvestAbsolute), $outsideDirectory] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create Phase 8A test directory.');
    }
}

try {
    file_put_contents($pendingAbsolute, 'pending');
    file_put_contents($harvestAbsolute, 'harvest');
    file_put_contents($outsideAbsolute, 'outside');

    phase8_assert(scaims_normalize_report_image_path('uploads/reports/pending/' . $marker) !== null, 'Managed pending path was rejected.');
    phase8_assert(scaims_normalize_report_image_path('uploads/reports/unknown/' . $marker) === null, 'Unknown report folder was accepted.');
    phase8_assert(scaims_normalize_report_image_path('uploads/reports/pending/nested/' . $marker) === null, 'Nested report path was accepted.');

    scaims_delete_pending_report_image('uploads/reports/harvest/' . $marker);
    phase8_assert(is_file($harvestAbsolute), 'Pending cleanup deleted a finalized Harvest image.');

    scaims_delete_report_image('uploads/reports/pending/../../../tests/phase1-results/' . $marker);
    phase8_assert(is_file($outsideAbsolute), 'Traversal cleanup deleted a file outside managed report storage.');

    scaims_delete_pending_report_image('uploads/reports/pending/' . $marker);
    phase8_assert(!file_exists($pendingAbsolute), 'Owned pending image was not deleted.');

    echo "PASS: report image path normalization and pending-only deletion boundaries.\n";
} finally {
    foreach ([$pendingAbsolute, $harvestAbsolute, $outsideAbsolute] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
