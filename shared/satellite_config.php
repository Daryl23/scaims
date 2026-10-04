<?php

if (!function_exists('scaims_satellite_service_configured')) {
    /**
     * Report credential availability without exposing either credential.
     */
    function scaims_satellite_service_configured(): bool
    {
        $clientId = trim((string) (getenv('COPERNICUS_CLIENT_ID') ?: ''));
        $clientSecret = trim((string) (getenv('COPERNICUS_CLIENT_SECRET') ?: ''));

        return $clientId !== '' && $clientSecret !== '';
    }
}

if (!defined('SCAIMS_SATELLITE_PROVIDER')) {
    define('SCAIMS_SATELLITE_PROVIDER', 'Copernicus Data Space Ecosystem / Sentinel-2 L2A');
}
