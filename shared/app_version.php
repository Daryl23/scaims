<?php

// Bump on every release that changes session shape, stored browser data, or
// cached assets. Keep in sync with SCAIMS_APP_VERSION in shared/app-version.js
// (tests/app-version-sync.php fails the build if they differ).
if (!defined('SCAIMS_APP_VERSION')) {
    define('SCAIMS_APP_VERSION', '7.0.0');
}
