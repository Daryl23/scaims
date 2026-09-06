<?php
/**
 * Ends the farmer session properly.
 *
 * The sidebar used to link straight back to the login page, which left the
 * PHP session alive — the browser Back button walked straight into the
 * dashboard, and on a shared phone the previous farmer's account was still
 * open. This clears the session data, drops the session cookie and then
 * sends the farmer to the login screen.
 */

session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: ../../farmerLogin/farmerlogin.html');
exit();
