<?php

if (!defined('SCAIMS_REPORT_IMAGE_MAX_BYTES')) {
    define('SCAIMS_REPORT_IMAGE_MAX_BYTES', 6 * 1024 * 1024);
}

if (!function_exists('scaims_report_image_allowed_mimes')) {
    function scaims_report_image_allowed_mimes(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
    }
}

if (!function_exists('scaims_report_image_base_dir')) {
    function scaims_report_image_base_dir(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reports';
    }
}

if (!function_exists('scaims_report_image_folder')) {
    function scaims_report_image_folder(string $reportType): string
    {
        return $reportType === 'damage' ? 'damage' : ($reportType === 'pending' ? 'pending' : 'harvest');
    }
}

if (!function_exists('scaims_ensure_report_image_columns')) {
    function scaims_ensure_report_image_columns(mysqli $conn): void
    {
        $columns = [
            'crop_reports' => 'image_path',
            'damage_reports' => 'image_path',
        ];

        foreach ($columns as $table => $column) {
            $tableEscaped = $conn->real_escape_string($table);
            $columnEscaped = $conn->real_escape_string($column);
            $result = $conn->query("SHOW COLUMNS FROM `$tableEscaped` LIKE '$columnEscaped'");

            if ($result && $result->num_rows > 0) {
                $result->free();
                continue;
            }

            if ($result instanceof mysqli_result) {
                $result->free();
            }

            if (!$conn->query("ALTER TABLE `$tableEscaped` ADD COLUMN `$columnEscaped` VARCHAR(255) NULL DEFAULT NULL AFTER `longitude`")) {
                throw new RuntimeException('Report image database fields are unavailable.');
            }
        }
    }
}

if (!function_exists('scaims_normalize_report_image_path')) {
    function scaims_normalize_report_image_path(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        if (strpos($path, '..') !== false || strpos($path, 'uploads/reports/') !== 0) {
            return null;
        }

        return $path;
    }
}

if (!function_exists('scaims_report_image_absolute_path')) {
    function scaims_report_image_absolute_path(?string $path): ?string
    {
        $normalizedPath = scaims_normalize_report_image_path($path);
        if ($normalizedPath === null) {
            return null;
        }

        $relative = substr($normalizedPath, strlen('uploads/reports/'));
        $absolute = scaims_report_image_base_dir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return $absolute;
    }
}

if (!function_exists('scaims_report_image_exists')) {
    function scaims_report_image_exists(?string $path): bool
    {
        $absolute = scaims_report_image_absolute_path($path);
        return $absolute !== null && is_file($absolute);
    }
}

if (!function_exists('scaims_report_image_url')) {
    function scaims_report_image_url(?string $path, string $prefix = ''): ?string
    {
        $normalizedPath = scaims_normalize_report_image_path($path);
        if ($normalizedPath === null || !scaims_report_image_exists($normalizedPath)) {
            return null;
        }

        return $prefix . $normalizedPath;
    }
}

if (!function_exists('scaims_report_image_upload_error_message')) {
    function scaims_report_image_upload_error_message(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'The selected image is too large. Please choose an image up to 6 MB.';
            case UPLOAD_ERR_PARTIAL:
                return 'The image upload was interrupted. Please try again.';
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return 'The image could not be saved on the server. Please try again.';
            default:
                return 'The image upload failed. Please try again.';
        }
    }
}

if (!function_exists('scaims_create_report_image_directory')) {
    function scaims_create_report_image_directory(string $reportType): string
    {
        $folder = scaims_report_image_folder($reportType);
        $directory = scaims_report_image_base_dir() . DIRECTORY_SEPARATOR . $folder;

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the image upload directory.');
        }

        return $directory;
    }
}

if (!function_exists('scaims_detect_report_image_mime')) {
    function scaims_detect_report_image_mime(string $tmpPath): ?string
    {
        if (!is_file($tmpPath)) {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        if (!is_string($mime) || $mime === '') {
            return null;
        }

        return $mime;
    }
}

if (!function_exists('scaims_store_uploaded_report_image')) {
    function scaims_store_uploaded_report_image(array $file, string $reportType): array
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'path' => null, 'has_upload' => false];
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'path' => null, 'has_upload' => true, 'message' => scaims_report_image_upload_error_message($errorCode)];
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return ['ok' => false, 'path' => null, 'has_upload' => true, 'message' => 'The uploaded image was not received correctly. Please try again.'];
        }

        if ($size <= 0) {
            return ['ok' => false, 'path' => null, 'has_upload' => true, 'message' => 'The selected image is empty. Please choose another image.'];
        }

        if ($size > SCAIMS_REPORT_IMAGE_MAX_BYTES) {
            return ['ok' => false, 'path' => null, 'has_upload' => true, 'message' => 'The selected image is too large. Please choose an image up to 6 MB.'];
        }

        $mime = scaims_detect_report_image_mime($tmpPath);
        $allowedMimes = scaims_report_image_allowed_mimes();
        if ($mime === null || !array_key_exists($mime, $allowedMimes)) {
            return ['ok' => false, 'path' => null, 'has_upload' => true, 'message' => 'Unsupported image type. Please upload a JPG, PNG, or WEBP image.'];
        }

        $imageSize = @getimagesize($tmpPath);
        if ($imageSize === false) {
            return ['ok' => false, 'path' => null, 'has_upload' => true, 'message' => 'The selected file is not a valid image.'];
        }

        try {
            $directory = scaims_create_report_image_directory($reportType);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'path' => null, 'has_upload' => true, 'message' => 'The image upload folder is unavailable. Please try again later.'];
        }

        $folder = scaims_report_image_folder($reportType);
        $filename = $folder . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(12)) . '.' . $allowedMimes[$mime];
        $targetPath = $directory . DIRECTORY_SEPARATOR . $filename;
        $relativePath = 'uploads/reports/' . $folder . '/' . $filename;

        // Preserve original resolution and visible GPS Camera stamps. No metadata extraction.
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            return ['ok' => false, 'path' => null, 'has_upload' => true, 'message' => 'The image could not be saved. Please try again.'];
        }

        return ['ok' => true, 'path' => $relativePath, 'has_upload' => true];
    }
}

if (!function_exists('scaims_stage_report_image')) {
    function scaims_stage_report_image(string $fieldName): array
    {
        if (!isset($_FILES[$fieldName])) {
            return ['ok' => false, 'message' => 'Please choose an image first.'];
        }

        $result = scaims_store_uploaded_report_image($_FILES[$fieldName], 'pending');
        if (!$result['ok']) {
            return $result;
        }

        if (empty($result['path'])) {
            return ['ok' => false, 'message' => 'Please choose an image first.'];
        }

        $token = bin2hex(random_bytes(24));
        $_SESSION['pending_report_images'][$token] = [
            'path' => $result['path'],
            'created_at' => time(),
        ];

        return ['ok' => true, 'token' => $token, 'path' => $result['path']];
    }
}

if (!function_exists('scaims_get_pending_report_image')) {
    function scaims_get_pending_report_image(?string $token): ?array
    {
        $token = trim((string) $token);
        if ($token === '' || empty($_SESSION['pending_report_images'][$token])) {
            return null;
        }

        $pending = $_SESSION['pending_report_images'][$token];
        if ((time() - (int) ($pending['created_at'] ?? 0)) > 3600) {
            unset($_SESSION['pending_report_images'][$token]);
            return null;
        }

        $path = scaims_normalize_report_image_path($pending['path'] ?? null);
        if ($path === null || strpos($path, 'uploads/reports/pending/') !== 0 || !scaims_report_image_exists($path)) {
            unset($_SESSION['pending_report_images'][$token]);
            return null;
        }

        return ['path' => $path, 'token' => $token];
    }
}

if (!function_exists('scaims_delete_report_image')) {
    function scaims_delete_report_image(?string $path): void
    {
        $absolute = scaims_report_image_absolute_path($path);
        if ($absolute !== null && is_file($absolute)) {
            @unlink($absolute);
        }
    }
}

if (!function_exists('scaims_finalize_pending_report_image')) {
    function scaims_finalize_pending_report_image(?string $token, string $reportType): array
    {
        $pending = scaims_get_pending_report_image($token);
        if ($pending === null) {
            return ['ok' => false, 'path' => null, 'message' => 'The selected image is no longer available. Please choose it again.'];
        }

        $source = scaims_report_image_absolute_path($pending['path']);
        if ($source === null || !is_file($source)) {
            return ['ok' => false, 'path' => null, 'message' => 'The selected image could not be found. Please choose it again.'];
        }

        try {
            $directory = scaims_create_report_image_directory($reportType);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'path' => null, 'message' => 'The image upload folder is unavailable. Please try again later.'];
        }

        $folder = scaims_report_image_folder($reportType);
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $filename = $folder . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(12)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
        $target = $directory . DIRECTORY_SEPARATOR . $filename;
        $relativePath = 'uploads/reports/' . $folder . '/' . $filename;

        if (!rename($source, $target)) {
            return ['ok' => false, 'path' => null, 'message' => 'The selected image could not be saved with the report. Please try again.'];
        }

        unset($_SESSION['pending_report_images'][$pending['token']]);

        return ['ok' => true, 'path' => $relativePath];
    }
}

if (!function_exists('scaims_collect_report_image')) {
    function scaims_collect_report_image(string $fieldName, string $reportType, ?string $pendingToken = null): array
    {
        if (isset($_FILES[$fieldName]) && (int) ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $result = scaims_store_uploaded_report_image($_FILES[$fieldName], $reportType);
            if (!$result['ok']) {
                return $result;
            }

            if (!empty($pendingToken)) {
                $pending = scaims_get_pending_report_image($pendingToken);
                if ($pending !== null) {
                    scaims_delete_report_image($pending['path']);
                    unset($_SESSION['pending_report_images'][$pending['token']]);
                }
            }

            return ['ok' => true, 'path' => $result['path'] ?? null];
        }

        if (!empty($pendingToken)) {
            return scaims_finalize_pending_report_image($pendingToken, $reportType);
        }

        return ['ok' => true, 'path' => null];
    }
}
