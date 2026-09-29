<?php

require_once "includes/functions.php";
require_once "classes/Student.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$studentName = trim($_POST["student_name"] ?? "");

$grades = [
    "Programming" => (float) ($_POST["programming"] ?? 0),
    "Database" => (float) ($_POST["database"] ?? 0),
    "Web Development" => (float) ($_POST["web"] ?? 0),
    "Networking" => (float) ($_POST["networking"] ?? 0)
];

$average = calculateAverage($grades);
$remark = getRemark($average);
$status = getStatus($average);

$student = new Student(
    $studentName,
    $grades,
    $average,
    $remark,
    $status
);

require "views/result.php";
