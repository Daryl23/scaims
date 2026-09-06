<?php

require_once __DIR__ . '/../conn.php';

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
            $conn->query("ALTER TABLE admin ADD COLUMN AdminRole VARCHAR(32) NOT NULL DEFAULT 'admin' AFTER AdminPass");
        }
    }
}

if (!function_exists('scaims_normalize_admin_role')) {
    function scaims_normalize_admin_role(?string $role): string
    {
        $role = strtolower(trim((string) $role));
        return in_array($role, ['super_admin', 'super-admin', 'superadmin'], true) ? 'super_admin' : 'admin';
    }
}

if (!function_exists('scaims_is_super_admin')) {
    function scaims_is_super_admin(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

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
        return scaims_is_super_admin() ? 'Super Administrator' : 'Administrator';
    }
}

