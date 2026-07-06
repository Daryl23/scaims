<?php
ini_set('display_errors', '0');
error_reporting(0);
session_start();
$_SESSION['UName'] = 'manual-preview-farmer';
$_SESSION['UserID'] = 'manual-preview-farmer';
$_SESSION['FirstName'] = 'Juan';
$_SESSION['Surname'] = 'Dela Cruz';
include __DIR__ . '/farmParcel.php';
