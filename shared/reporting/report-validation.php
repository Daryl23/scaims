<?php

function scaims_reporting_clean(array $request, string $key): string
{
    return trim((string) ($request[$key] ?? ''));
}

function scaims_reporting_optional(array $request, string $key): ?string
{
    $value = scaims_reporting_clean($request, $key);
    return $value === '' ? null : $value;
}

function scaims_reporting_normalize_coordinate(?string $value, float $min, float $max)
{
    if ($value === null) return null;
    if (!is_numeric($value)) return false;
    $number = (float) $value;
    if ($number < $min || $number > $max) return false;
    return number_format($number, 7, '.', '');
}

function scaims_reporting_required_error(string $value, string $label): ?string
{
    return $value === '' ? $label . ' is required.' : null;
}

function scaims_reporting_date_error(string $value, string $label): ?string
{
    $required = scaims_reporting_required_error($value, $label);
    if ($required !== null) return $required;
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return (!$date || $date->format('Y-m-d') !== $value)
        ? 'Please enter a valid date for ' . $label . '.'
        : null;
}

function scaims_reporting_positive_error(string $value, string $label): ?string
{
    return ($value === '' || !is_numeric($value) || (float) $value <= 0)
        ? 'Please enter a valid positive number for ' . $label . '.'
        : null;
}

function scaims_reporting_percentage_error(string $value): ?string
{
    return ($value === '' || !is_numeric($value) || (float) $value < 0 || (float) $value > 100)
        ? 'Please enter a valid damage percentage between 0 and 100.'
        : null;
}

function scaims_reporting_first_error(array $errors): ?string
{
    foreach ($errors as $error) {
        if ($error !== null) return $error;
    }
    return null;
}
