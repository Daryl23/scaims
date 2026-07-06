<?php
ini_set('display_errors', '0');
error_reporting(0);
session_start();
$_SESSION['UName'] = 'manual-preview-farmer';
$_SESSION['UserID'] = 'manual-preview-farmer';
$_SESSION['FirstName'] = 'Juan';
$_SESSION['Surname'] = 'Dela Cruz';
include __DIR__ . '/report.php';

$mode = $_GET['mode'] ?? '';
if ($mode === 'crop' || $mode === 'damage') {
    $safeMode = json_encode($mode);
    echo "<script>
        setTimeout(function () {
            var type = document.getElementById('report-type');
            if (type) {
                type.value = $safeMode;
                if (typeof toggleCropReport === 'function') {
                    toggleCropReport();
                }
            }
        }, 300);
    </script>";
}
