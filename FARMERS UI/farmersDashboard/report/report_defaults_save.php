<?php
require_once dirname(__DIR__) . '/shared/farmer-auth.php';
header('Content-Type: application/json');
scaims_require_farmer_json('Please log in before saving report data.');

require_once dirname(__DIR__, 3) . '/conn.php';
require_once dirname(__DIR__, 3) . '/shared/farmer_report_defaults.php';
require_once dirname(__DIR__, 3) . '/shared/reporting/report-repository.php';

function respond_report_defaults(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit();
}

function default_value(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function optional_date_value(string $key, string $label): ?string
{
    $value = default_value($key);
    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        respond_report_defaults(false, 'Please enter a valid date for ' . $label . '.', [], 400);
    }

    return $value;
}

function optional_positive_number(string $key, string $label): ?string
{
    $value = default_value($key);
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value) || (float) $value <= 0) {
        respond_report_defaults(false, 'Please enter a valid positive number for ' . $label . '.', [], 400);
    }

    return $value;
}

function optional_percentage(string $key): ?string
{
    $value = default_value($key);
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
        respond_report_defaults(false, 'Please enter a valid damage percentage between 0 and 100.', [], 400);
    }

    return $value;
}

function optional_coordinate(string $key, float $min, float $max)
{
    $value = default_value($key);
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return false;
    }

    $number = (float) $value;
    if ($number < $min || $number > $max) {
        return false;
    }

    return number_format($number, 7, '.', '');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_report_defaults(false, 'Invalid request method.', [], 405);
}

$reportType = default_value('report-type');
if (!in_array($reportType, ['crop', 'damage'], true)) {
    respond_report_defaults(false, 'Please choose a report type before saving.', [], 400);
}

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_farmer_report_defaults_table($conn);
} catch (RuntimeException $e) {
    respond_report_defaults(false, 'The report database is unavailable right now. Please try again later.', [], 500);
}

$owner = scaims_reporting_find_farmer_by_username($conn, (string) ($_SESSION['UName'] ?? ''));
if (!$owner) {
    $conn->close();
    respond_report_defaults(false, 'Unable to find your farmer profile. Please sign in again.', [], 403);
}
$farmerId = (int) $owner['FarmerID'];
$latitude = optional_coordinate($reportType === 'crop' ? 'crop-latitude' : 'damage-latitude', -90, 90);
$longitude = optional_coordinate($reportType === 'crop' ? 'crop-longitude' : 'damage-longitude', -180, 180);

if (($latitude === null) xor ($longitude === null)) {
    $conn->close();
    respond_report_defaults(false, 'Please provide both latitude and longitude, or leave both empty.', [], 400);
}

if ($latitude === false || $longitude === false) {
    $conn->close();
    respond_report_defaults(false, 'Coordinates are invalid. Latitude must be between -90 and 90, and longitude must be between -180 and 180.', [], 400);
}

if ($reportType === 'crop') {
    $values = [
        'location' => default_value('crop-location'),
        'crop_commodity' => default_value('crop-commodity'),
        'crop_type' => default_value('crop-type') === 'Other' ? default_value('other-crop-type') : default_value('crop-type'),
        'crop_variety' => default_value('crop-variety'),
        'date_planted' => optional_date_value('date-planted', 'date planted'),
        'harvest_date' => optional_date_value('harvest-date', 'harvest date'),
        'estimate_harvest' => optional_positive_number('estimate-harvest', 'estimated harvest'),
        'crop_status' => default_value('crop-status'),
        'number_of_trees' => default_value('number-of-trees'),
        'farm_gate_price' => optional_positive_number('farm-gate-price', 'farm gate price'),
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];
} else {
    $values = [
        'location' => default_value('damage-location'),
        'crop_commodity' => default_value('damage-crop-commodity'),
        'crop_type' => default_value('damage-crop-type') === 'Other' ? default_value('other-damage-crop-type') : default_value('damage-crop-type'),
        'crop_variety' => default_value('damage-crop-variety'),
        'date_occurrence' => optional_date_value('date-occurrence', 'date of occurrence'),
        'damage_cause' => default_value('damage-cause'),
        'percent_damage' => optional_percentage('damage-percentage'),
        'estimated_loss' => optional_positive_number('estimated-loss', 'estimated loss'),
        'farm_gate_price' => optional_positive_number('damage-farm-gate-price', 'farm gate price'),
        'number_of_trees' => default_value('damage-number-of-trees'),
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];
}

if (!scaims_save_farmer_report_defaults($conn, $farmerId, $reportType, $values)) {
    $conn->close();
    respond_report_defaults(false, 'Unable to save report data right now. Please try again.', [], 500);
}

$savedDefaults = scaims_get_farmer_report_defaults($conn, $farmerId);
$conn->close();

respond_report_defaults(true, 'Report data saved.', ['defaults' => $savedDefaults[$reportType]]);
