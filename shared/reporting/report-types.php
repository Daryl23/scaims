<?php

function scaims_reporting_type_config(string $reportType): ?array
{
    $types = [
        'crop' => [
            'table' => 'crop_reports',
            'label' => 'Harvest',
            'image_destination' => 'harvest',
        ],
        'damage' => [
            'table' => 'damage_reports',
            'label' => 'Damage',
            'image_destination' => 'damage',
        ],
    ];

    return $types[$reportType] ?? null;
}

function scaims_reporting_is_type(string $reportType): bool
{
    return scaims_reporting_type_config($reportType) !== null;
}
