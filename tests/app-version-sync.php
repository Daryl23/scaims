<?php
declare(strict_types=1);

// The server and browser halves of the version wipe must agree, otherwise
// every page load would look like an upgrade and wipe data again.
require_once dirname(__DIR__) . '/shared/app_version.php';

$js = file_get_contents(dirname(__DIR__) . '/shared/app-version.js');
if (!preg_match("/var SCAIMS_APP_VERSION = '([^']+)'/", $js, $match)) {
    fwrite(STDERR, "FAIL: version constant not found in shared/app-version.js\n");
    exit(1);
}

if ($match[1] !== SCAIMS_APP_VERSION) {
    fwrite(STDERR, "FAIL: shared/app-version.js has {$match[1]} but shared/app_version.php has " . SCAIMS_APP_VERSION . "\n");
    exit(1);
}

echo 'OK: app version ' . SCAIMS_APP_VERSION . " matches in PHP and JS\n";
