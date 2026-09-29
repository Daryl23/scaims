<?php

function calculateAverage($grades)
{
    $total = 0;

    foreach ($grades as $grade) {
        $total += $grade;
    }

    return $total / count($grades);
}

function getRemark($average)
{
    if ($average >= 90) {
        return "Excellent";
    }

    if ($average >= 85) {
        return "Very Good";
    }

    if ($average >= 80) {
        return "Good";
    }

    if ($average >= 75) {
        return "Passed";
    }

    return "Failed";
}

function getStatus($average)
{
    if ($average >= 75) {
        return "PASSED";
    }

    return "FAILED";
}
