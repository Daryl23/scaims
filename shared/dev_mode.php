<?php

/**
 * Local development helpers.
 *
 * scaims_dev_bypass_enabled() is deliberately hard to switch on by accident.
 * BOTH of these must be true:
 *
 *   1. the request comes from the loopback interface (localhost), and
 *   2. a file named ".dev-bypass" exists in the project root.
 *
 * The marker file is git-ignored, so it never reaches a server. Delete it and
 * every bypass in the codebase turns itself off — there is no other switch.
 *
 * Used only to skip the Google reCAPTCHA check while testing on XAMPP, where
 * the widget cannot be completed by an automated smoke test.
 */

if (!function_exists('scaims_dev_bypass_enabled')) {
    function scaims_dev_bypass_enabled(): bool
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $isLoopback = in_array($remote, ['127.0.0.1', '::1', 'localhost'], true);

        if (!$isLoopback) {
            return false;
        }

        return is_file(__DIR__ . '/../.dev-bypass');
    }
}
