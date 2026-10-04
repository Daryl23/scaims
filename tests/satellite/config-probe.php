<?php
require dirname(__DIR__, 2) . '/shared/satellite_config.php';

echo json_encode([
    'configured' => scaims_satellite_service_configured(),
    'provider' => SCAIMS_SATELLITE_PROVIDER,
]);
