<?php
require_once __DIR__ . '/report_image_storage.php';

function scaims_photo_columns(): array
{
    return [
        'photo_capture_date' => 'DATE NULL',
        // VARCHAR preserves whether the stamp actually included seconds.
        'photo_capture_time' => 'VARCHAR(8) NULL',
        'photo_location' => 'TEXT NULL',
        'photo_latitude' => 'DECIMAL(12,9) NULL',
        'photo_longitude' => 'DECIMAL(12,9) NULL',
        'photo_timezone' => 'VARCHAR(16) NULL',
        'photo_ocr_version' => 'VARCHAR(32) NULL',
    ];
}

function scaims_ensure_gps_photo_columns(mysqli $conn): void
{
    foreach (['crop_reports', 'damage_reports'] as $table) {
        $existing = [];
        $result = $conn->query("SHOW COLUMNS FROM `$table`");
        while ($row = $result->fetch_assoc()) $existing[] = $row['Field'];
        foreach (scaims_photo_columns() as $column => $definition) {
            if (!in_array($column, $existing, true)) {
                $conn->query("ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `$column` $definition");
            }
        }
    }
}

function scaims_valid_photo_data($photo): bool
{
    if (!is_array($photo)) return false;
    foreach (['date', 'time', 'location', 'latitude', 'longitude', 'version'] as $key) {
        if (!isset($photo[$key]) || !is_string($photo[$key]) || $photo[$key] === '') return false;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $photo['date']);
    if (!$date || $date->format('Y-m-d') !== $photo['date']) return false;
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $photo['time'])) return false;
    if (!preg_match('/^-?\d{1,3}(?:\.\d{1,9})?$/D', $photo['latitude']) || !preg_match('/^-?\d{1,3}(?:\.\d{1,9})?$/D', $photo['longitude'])) return false;
    if ((float) $photo['latitude'] < -90 || (float) $photo['latitude'] > 90 || (float) $photo['longitude'] < -180 || (float) $photo['longitude'] > 180) return false;
    return strlen($photo['location']) >= 5 && strlen($photo['location']) <= 8000 && $photo['version'] === 'tesseract7-mdY-v1';
}

function scaims_log_gps_ocr_failure(string $category, float $startedAt, ?int $exitCode = null): void
{
    $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
    $exitLabel = $exitCode === null ? 'unknown' : (string) $exitCode;
    error_log("SCAIMS GPS OCR failure category={$category} elapsed_ms={$elapsedMs} exit_code={$exitLabel}");
}

function scaims_extract_gps_photo(string $path): array
{
    $startedAt = microtime(true);
    $absolute = scaims_report_image_absolute_path($path);
    if (!$absolute || !is_file($absolute) || !function_exists('proc_open')) {
        scaims_log_gps_ocr_failure('unavailable', $startedAt);
        return ['ok' => false, 'reason' => 'ocr_unavailable'];
    }

    $node = getenv('SCAIMS_NODE_BINARY') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/nodejs/node.exe' : '/usr/bin/node');
    $process = @proc_open([$node, __DIR__ . '/gps-ocr/extract.cjs', $absolute], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__ . '/gps-ocr', null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        scaims_log_gps_ocr_failure('process_start', $startedAt);
        return ['ok' => false, 'reason' => 'ocr_unavailable'];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $output = '';
    $timedOut = false;
    $outputLimitExceeded = false;
    $exitCode = null;
    do {
        $output .= stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]); // Do not expose internal process paths/errors to the browser.
        $status = proc_get_status($process);
        if (!$status['running']) {
            if ((int) ($status['exitcode'] ?? -1) >= 0) {
                $exitCode = (int) $status['exitcode'];
            }
            break;
        }
        if (microtime(true) - $startedAt > 58) {
            $timedOut = true;
            proc_terminate($process);
            break;
        }
        if (strlen($output) > 16000) {
            $outputLimitExceeded = true;
            proc_terminate($process);
            break;
        }
        usleep(50000);
    } while (true);

    if ($timedOut || $outputLimitExceeded) {
        $terminationDeadline = microtime(true) + 1.0;
        do {
            usleep(50000);
            $status = proc_get_status($process);
            if (!$status['running']) {
                if ((int) ($status['exitcode'] ?? -1) >= 0) {
                    $exitCode = (int) $status['exitcode'];
                }
                break;
            }
        } while (microtime(true) < $terminationDeadline);
        if ($status['running'] ?? false) {
            proc_terminate($process, 9);
        }
    }

    $output .= stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closedExitCode = proc_close($process);
    if ($exitCode === null && $closedExitCode >= 0) {
        $exitCode = $closedExitCode;
    }

    if ($timedOut) {
        scaims_log_gps_ocr_failure('timeout', $startedAt, $exitCode);
        return ['ok' => false, 'reason' => 'timeout'];
    }
    if ($outputLimitExceeded) {
        scaims_log_gps_ocr_failure('output_limit', $startedAt, $exitCode);
        return ['ok' => false, 'reason' => 'unreadable'];
    }

    $result = json_decode($output, true);
    if ($exitCode !== null && $exitCode !== 0 && (!is_array($result) || !empty($result['ok']))) {
        scaims_log_gps_ocr_failure('process_exit', $startedAt, $exitCode);
        return ['ok' => false, 'reason' => 'unreadable'];
    }
    if (!is_array($result) || empty($result['ok']) || !scaims_valid_photo_data($result['photo'] ?? null)) {
        $reason = in_array($result['reason'] ?? '', ['ocr_unavailable', 'timeout'], true) ? $result['reason'] : 'unreadable';
        $category = !is_array($result) ? 'malformed_output' : ($result['reason'] ?? 'invalid_result');
        scaims_log_gps_ocr_failure((string) $category, $startedAt, $exitCode);
        return ['ok' => false, 'reason' => $reason];
    }
    return $result;
}

function scaims_pending_gps_photo(?string $token): ?array
{
    $pending = scaims_get_pending_report_image($token);
    $entry = $pending ? ($_SESSION['pending_report_images'][$pending['token']] ?? []) : [];
    $photo = $entry['gps_photo'] ?? null;
    if (!$pending || !scaims_valid_photo_data($photo)) return null;
    $hash = hash_file('sha256', scaims_report_image_absolute_path($pending['path']));
    if (!hash_equals($entry['sha256'] ?? '', $hash)) return null;
    return ['photo' => $photo, 'path' => $pending['path'], 'token' => $pending['token']];
}

function scaims_photo_from_row(array $row): ?array
{
    if (empty($row['photo_ocr_version'])) return null;
    return [
        'date' => $row['photo_capture_date'], 'time' => $row['photo_capture_time'],
        'location' => $row['photo_location'], 'latitude' => $row['photo_latitude'],
        'longitude' => $row['photo_longitude'], 'timezone' => $row['photo_timezone'],
        'version' => $row['photo_ocr_version'],
    ];
}

function scaims_write_photo_data(mysqli $conn, string $type, int $id, array $photo): void
{
    if (!scaims_valid_photo_data($photo)) throw new RuntimeException('Invalid extracted photo information.');
    $table = $type === 'damage' ? 'damage_reports' : 'crop_reports';
    $stmt = $conn->prepare("UPDATE `$table` SET photo_capture_date=?, photo_capture_time=?, photo_location=?, photo_latitude=?, photo_longitude=?, photo_timezone=?, photo_ocr_version=? WHERE id=?");
    $timezone = $photo['timezone'] ?? null;
    $stmt->bind_param('sssssssi', $photo['date'], $photo['time'], $photo['location'], $photo['latitude'], $photo['longitude'], $timezone, $photo['version'], $id);
    $stmt->execute(); $stmt->close();
}

function scaims_execute_photo_report(mysqli $conn, mysqli_stmt $stmt, string $type, ?array $photo, ?int $id = null): bool
{
    if ($photo === null) return $stmt->execute(); // Historical reports keep their existing semantics.
    $conn->begin_transaction();
    try {
        if (!$stmt->execute()) { $conn->rollback(); return false; }
        $recordId = $id ?? (int) $conn->insert_id;
        scaims_write_photo_data($conn, $type, $recordId, $photo);
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('GPS report save failed: ' . $e->getMessage());
        return false;
    }
}
