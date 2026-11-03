<?php

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/session.php';

if (!function_exists('scaims_ensure_admin_role_column')) {
    function scaims_ensure_admin_role_column(mysqli $conn): void
    {
        $dbName = SCAIMS_DB_NAME;
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'admin'
               AND COLUMN_NAME = 'AdminRole'"
        );
        $stmt->bind_param('s', $dbName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : ['total' => 0];
        $stmt->close();

        if ((int) ($row['total'] ?? 0) === 0) {
            throw new RuntimeException('Database schema is not up to date. Run the SCAIMS migrations.');
        }
    }
}

if (!function_exists('scaims_normalize_admin_role')) {
    function scaims_normalize_admin_role(?string $role): string
    {
        $role = strtolower(trim((string) $role));
        if (in_array($role, ['super_admin', 'super-admin', 'superadmin'], true)) {
            return 'super_admin';
        }
        if (in_array($role, ['barangay', 'barangay_admin', 'barangay-admin'], true)) {
            return 'barangay';
        }
        return 'admin';
    }
}

if (!function_exists('scaims_is_super_admin')) {
    function scaims_is_super_admin(): bool
    {
        scaims_session_start();

        return scaims_normalize_admin_role($_SESSION['AdminRole'] ?? '') === 'super_admin'
            || !empty($_SESSION['IsSuperAdmin']);
    }
}

if (!function_exists('scaims_require_admin')) {
    /**
     * Gate an admin page or endpoint behind a signed-in admin session.
     *
     * Pages get redirected to the admin login; XHR and export endpoints
     * get a 401 instead, so the calling script sees a clear failure
     * rather than a login page parsed as data.
     */
    function scaims_require_admin(string $loginPath = '../../adminLogin/adminLogin.html'): void
    {
        scaims_session_start();

        if (!empty($_SESSION['AdminID'])) {
            return;
        }

        $isXhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

        if ($isXhr) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not signed in.']);
            exit();
        }

        header('Location: ' . $loginPath);
        exit();
    }
}

if (!function_exists('scaims_require_super_admin')) {
    function scaims_require_super_admin(string $fallbackPath = '../home/home.php'): void
    {
        scaims_session_start();

        if (empty($_SESSION['AdminID'])) {
            header('Location: ../../adminLogin/adminLogin.html');
            exit();
        }

        if (!scaims_is_super_admin()) {
            header('Location: ' . $fallbackPath);
            exit();
        }
    }
}

if (!function_exists('scaims_admin_role_label')) {
    function scaims_admin_role_label(): string
    {
        scaims_session_start();
        $role = scaims_normalize_admin_role($_SESSION['AdminRole'] ?? '');
        if ($role === 'super_admin') {
            return 'Super Administrator';
        }
        if ($role === 'barangay') {
            return 'Barangay Administrator';
        }
        return 'Administrator';
    }
}

if (!function_exists('scaims_can_validate_farmers')) {
    /**
     * Only the agency-level superadmin can mark a farmer as validated.
     * Barangay (and plain admin) accounts may still search for and
     * register farmers, just not validate them.
     */
    function scaims_can_validate_farmers(): bool
    {
        return scaims_is_super_admin();
    }
}

