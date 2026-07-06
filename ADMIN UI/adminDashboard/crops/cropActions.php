<?php

require_once dirname(__DIR__, 3) . '/shared/admin_auth.php';
require_once dirname(__DIR__, 3) . '/shared/crop_catalog.php';

header('Content-Type: application/json');

scaims_require_super_admin('../home/home.php');

function respond(bool $success, array $payload = []): void
{
    echo json_encode(['success' => $success] + $payload);
    exit;
}

function clean_crop_name(string $value): string
{
    return preg_replace('/\s+/', ' ', trim($value));
}

try {
    $conn = scaims_db_mysqli();
    scaims_ensure_crop_catalog_table($conn);

    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';

    if ($action === 'list') {
        respond(true, ['rows' => scaims_get_crop_catalog_rows($conn, true)]);
    }

    $allowedCommodities = ['grains', 'plantation', 'root', 'vegetables', 'fruits'];

    if ($action === 'create' || $action === 'update') {
        $cropName = clean_crop_name($_POST['crop_name'] ?? '');
        $commodity = scaims_normalize_crop_commodity($_POST['commodity'] ?? '');
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 100;
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;

        if ($cropName === '' || strlen($cropName) > 120) {
            respond(false, ['message' => 'Enter a crop name up to 120 characters.']);
        }

        if (!in_array($commodity, $allowedCommodities, true)) {
            respond(false, ['message' => 'Choose a valid commodity.']);
        }

        if ($action === 'create') {
            $lookup = $conn->prepare('SELECT id FROM crop_catalog WHERE commodity = ? AND LOWER(crop_name) = LOWER(?) LIMIT 1');
            $lookup->bind_param('ss', $commodity, $cropName);
            $lookup->execute();
            $existing = $lookup->get_result()->fetch_assoc();
            $lookup->close();

            if ($existing) {
                $id = (int) $existing['id'];
                $stmt = $conn->prepare('UPDATE crop_catalog SET crop_name = ?, is_active = 1, sort_order = ? WHERE id = ?');
                $stmt->bind_param('sii', $cropName, $sortOrder, $id);
                $stmt->execute();
                $stmt->close();
                respond(true, ['message' => 'Crop already existed, so it was restored and updated.']);
            }

            $stmt = $conn->prepare('INSERT INTO crop_catalog (commodity, crop_name, is_active, sort_order) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssii', $commodity, $cropName, $isActive, $sortOrder);
            $stmt->execute();
            $stmt->close();
            respond(true, ['message' => 'Crop added.']);
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            respond(false, ['message' => 'Missing crop ID.']);
        }

        $lookup = $conn->prepare('SELECT id FROM crop_catalog WHERE commodity = ? AND LOWER(crop_name) = LOWER(?) AND id <> ? LIMIT 1');
        $lookup->bind_param('ssi', $commodity, $cropName, $id);
        $lookup->execute();
        $duplicate = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if ($duplicate) {
            respond(false, ['message' => 'Another crop in this commodity already uses that name.']);
        }

        $stmt = $conn->prepare('UPDATE crop_catalog SET commodity = ?, crop_name = ?, is_active = ?, sort_order = ? WHERE id = ?');
        $stmt->bind_param('ssiii', $commodity, $cropName, $isActive, $sortOrder, $id);
        $stmt->execute();
        $stmt->close();
        respond(true, ['message' => 'Crop updated.']);
    }

    if ($action === 'delete' || $action === 'restore') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            respond(false, ['message' => 'Missing crop ID.']);
        }

        $isActive = $action === 'restore' ? 1 : 0;
        $stmt = $conn->prepare('UPDATE crop_catalog SET is_active = ? WHERE id = ?');
        $stmt->bind_param('ii', $isActive, $id);
        $stmt->execute();
        $stmt->close();

        respond(true, ['message' => $action === 'restore' ? 'Crop restored.' : 'Crop deactivated.']);
    }

    respond(false, ['message' => 'Unsupported action.']);
} catch (Throwable $error) {
    http_response_code(500);
    respond(false, ['message' => $error->getMessage()]);
}

