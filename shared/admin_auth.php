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

if (!defined('SCAIMS_PASSWORD_MAX_AGE_DAYS')) {
    define('SCAIMS_PASSWORD_MAX_AGE_DAYS', 90);
}

if (!function_exists('scaims_require_admin')) {
    /**
     * Gate an admin page or endpoint behind a signed-in admin session.
     *
     * Pages get redirected to the admin login; XHR and export endpoints
     * get a 401 instead, so the calling script sees a clear failure
     * rather than a login page parsed as data. Pass $allowExpiredPassword
     * true only from the change-password page/endpoint itself, so an
     * admin with an expired password can still reach the one page that
     * lets them fix it.
     */
    function scaims_require_admin(string $loginPath = '../../adminLogin/adminLogin.html', bool $allowExpiredPassword = false): void
    {
        scaims_session_start();

        $isXhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

        if (empty($_SESSION['AdminID'])) {
            scaims_deny_admin_access($loginPath, $isXhr, 'Not signed in.');
        }

        // Re-check on every request (not just at login) so a deactivated,
        // offboarded, or demoted admin's existing session changes immediately.
        $account = scaims_admin_account((string) $_SESSION['AdminID']);
        if ($account === null || (int) $account['is_active'] !== 1) {
            scaims_session_destroy();
            scaims_deny_admin_access($loginPath, $isXhr, 'This account has been deactivated.');
        }

        $currentRole = scaims_normalize_admin_role($account['AdminRole'] ?? 'admin');
        $_SESSION['AdminRole'] = $currentRole;
        $_SESSION['IsSuperAdmin'] = $currentRole === 'super_admin';

        if (!$allowExpiredPassword && scaims_admin_password_expired((string) $_SESSION['AdminID'])) {
            $changePasswordPath = preg_replace('#adminLogin/adminLogin\.html$#', 'adminLogin/changePassword.html', $loginPath);
            scaims_deny_admin_access($changePasswordPath ?: $loginPath, $isXhr, 'Your password has expired and must be changed.');
        }
    }
}

if (!function_exists('scaims_deny_admin_access')) {
    function scaims_deny_admin_access(string $loginPath, bool $isXhr, string $message): void
    {
        if ($isXhr) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
            exit();
        }

        header('Location: ' . $loginPath);
        exit();
    }
}

if (!function_exists('scaims_admin_account')) {
    // One lookup per request, shared by the active, role, and expiry checks.
    function scaims_admin_account(string $adminId): ?array
    {
        static $cache = [];
        if (!array_key_exists($adminId, $cache)) {
            $conn = scaims_db_mysqli();
            $stmt = $conn->prepare('SELECT is_active, AdminRole, PasswordChangedAt FROM admin WHERE AdminID = ?');
            $stmt->bind_param('s', $adminId);
            $stmt->execute();
            $cache[$adminId] = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            $conn->close();
        }

        return $cache[$adminId];
    }
}

if (!function_exists('scaims_admin_password_expired')) {
    function scaims_admin_password_expired(string $adminId): bool
    {
        $row = scaims_admin_account($adminId);

        if (empty($row) || empty($row['PasswordChangedAt'])) {
            return true;
        }

        $changedAt = strtotime($row['PasswordChangedAt']);
        $maxAgeSeconds = SCAIMS_PASSWORD_MAX_AGE_DAYS * 86400;

        return $changedAt === false || (time() - $changedAt) > $maxAgeSeconds;
    }
}

if (!function_exists('scaims_require_super_admin')) {
    function scaims_require_super_admin(string $fallbackPath = '../home/home.php'): void
    {
        scaims_require_admin('../../adminLogin/adminLogin.html');

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

