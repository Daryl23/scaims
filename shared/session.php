<?php

require_once __DIR__ . '/app_version.php';

if (!function_exists('scaims_session_start')) {
    function scaims_session_start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

            // HttpOnly keeps session cookies out of reach of injected scripts;
            // SameSite=Lax stops other sites from riding a signed-in session
            // on cross-site POSTs; strict mode rejects attacker-chosen IDs.
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
            scaims_session_enforce_version();
        }
    }
}

if (!function_exists('scaims_session_enforce_version')) {
    // A session left over from an older release is wiped once and given a new
    // ID, so returning users sign in fresh instead of carrying old state.
    function scaims_session_enforce_version(): void
    {
        if (($_SESSION['_scaims_version'] ?? null) === SCAIMS_APP_VERSION) {
            return;
        }

        $hadOldSession = !empty($_SESSION);
        $_SESSION = [];
        if ($hadOldSession) {
            session_regenerate_id(true);
        }
        $_SESSION['_scaims_version'] = SCAIMS_APP_VERSION;
    }
}

if (!function_exists('scaims_session_destroy')) {
    function scaims_session_destroy(): void
    {
        scaims_session_start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }
}
