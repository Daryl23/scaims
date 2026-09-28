<?php

require_once dirname(__DIR__, 3) . '/shared/session.php';

function scaims_farmer_session_start(): void
{
    scaims_session_start();
}

function scaims_farmer_is_authenticated(): bool
{
    scaims_farmer_session_start();
    return isset($_SESSION['UName']);
}

function scaims_require_farmer_page(string $loginPath): void
{
    if (!scaims_farmer_is_authenticated()) {
        header('Location: ' . $loginPath);
        exit;
    }
}

function scaims_require_farmer_json(string $message): void
{
    if (!scaims_farmer_is_authenticated()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

function scaims_require_farmer_text(string $message = 'Unauthorized access'): void
{
    if (!scaims_farmer_is_authenticated()) {
        die($message);
    }
}
