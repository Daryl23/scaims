<?php
ini_set('display_errors', '0');
error_reporting(0);
session_start();
$_SESSION['AdminID'] = 'manual-preview-admin';
$_SESSION['AdminName'] = 'APGORRS Admin';
include __DIR__ . '/damageReports.php';
