<?php
require_once __DIR__ . '/report-repository.php';
require_once __DIR__ . '/report-validation.php';
require_once dirname(__DIR__) . '/report_image_storage.php';
require_once dirname(__DIR__) . '/gps_photo.php';
require_once dirname(__DIR__) . '/farmer_report_defaults.php';

function scaims_reporting_result(bool $ok, string $message, int $status = 200, array $extra = []): array
{
    return array_merge(['ok' => $ok, 'message' => $message, 'status' => $status], $extra);
}

function scaims_reporting_pending_photo(array $request, array $files): array
{
    $pending = scaims_pending_gps_photo($request['pending-report-image-token'] ?? null);
    foreach (['report-image', 'admin-report-image', 'crop-report-image', 'damage-report-image'] as $field) {
        if (!empty($files[$field]['tmp_name'])) {
            $pending = null;
            break;
        }
    }
    return $pending
        ? scaims_reporting_result(true, '', 200, ['pending' => $pending, 'photo' => $pending['photo']])
        : scaims_reporting_result(false, 'Please upload a GPS Camera photo and wait for GPS information to be detected.', 400);
}

function scaims_reporting_create(mysqli $conn, array $request, array $files, array $context): array
{
    $reportType = (string) ($request['report-type'] ?? '');
    $ownerId = (int) ($context['owner_id'] ?? 0);
    $actor = (string) ($context['actor'] ?? '');
    $policy = (string) ($context['validation_policy'] ?? 'farmer_legacy');
    $saveDefaults = !empty($context['save_defaults']);

    $pendingResult = scaims_reporting_pending_photo($request, $files);
    if (!$pendingResult['ok']) {
        return $pendingResult;
    }
    if (!scaims_reporting_is_type($reportType)) {
        return scaims_reporting_result(false, 'Please choose a report type.', 400);
    }

    $pending = $pendingResult['pending'];
    $photo = $pendingResult['photo'];
    $location = mb_substr($photo['location'], 0, 255);
    $latitude = $photo['latitude'];
    $longitude = $photo['longitude'];

    if (($latitude === null) xor ($longitude === null)) {
        return scaims_reporting_result(false, 'Please provide both latitude and longitude, or leave both empty.', 400);
    }
    if ($latitude === false || $longitude === false) {
        return scaims_reporting_result(false, 'Coordinates are invalid. Latitude must be between -90 and 90, and longitude must be between -180 and 180.', 400);
    }

    if ($reportType === 'crop') {
        $cropCommodity = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'crop-commodity') : (string) ($request['crop-commodity'] ?? '');
        $cropType = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'crop-type') : (string) ($request['crop-type'] ?? '');
        if ($cropType === 'Other') {
            $cropType = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'other-crop-type') : (string) ($request['other-crop-type'] ?? '');
        }
        $cropVariety = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'crop-variety') : (string) ($request['crop-variety'] ?? '');
        $datePlanted = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'date-planted') : (string) ($request['date-planted'] ?? '');
        $harvestDate = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'harvest-date') : ($request['harvest-date'] ?? null);
        $estimateHarvest = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'estimate-harvest') : ($request['estimate-harvest'] ?? null);
        $cropStatus = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'crop-status') : (string) ($request['crop-status'] ?? '');
        $farmGatePrice = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'farm-gate-price') : (string) ($request['farm-gate-price'] ?? '');
        $numberOfTrees = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'number-of-trees') : ($request['number-of-trees'] ?? null);

        if ($policy === 'admin_strict') {
            $error = scaims_reporting_first_error([
                scaims_reporting_required_error($location, 'Location'),
                scaims_reporting_required_error($cropCommodity, 'Crop commodity'),
                scaims_reporting_required_error($cropType, 'Crop type'),
                scaims_reporting_required_error($cropVariety, 'Crop variety'),
                scaims_reporting_date_error((string) $datePlanted, 'date planted'),
                scaims_reporting_date_error((string) $harvestDate, 'harvest date'),
                scaims_reporting_positive_error((string) $estimateHarvest, 'estimated harvest'),
                scaims_reporting_required_error($cropStatus, 'Crop status'),
                scaims_reporting_positive_error($farmGatePrice, 'farm gate price'),
            ]);
            if ($error !== null) return scaims_reporting_result(false, $error, 400);
            if (strtotime($datePlanted) > strtotime(date('Y-m-d'))) return scaims_reporting_result(false, 'Date planted cannot be in the future.', 400);
            if (strtotime((string) $harvestDate) <= strtotime($datePlanted)) return scaims_reporting_result(false, 'Harvest date must be after the date planted.', 400);
        } elseif ($harvestDate === null || $estimateHarvest === null) {
            return scaims_reporting_result(false, 'Harvest date and estimated harvest cannot be null.', 400);
        }

        $imageResult = scaims_finalize_pending_report_image($pending['token'], $reportType);
        if (!$imageResult['ok'] || empty($imageResult['path'])) {
            return scaims_reporting_result(false, $imageResult['message'] ?? 'Please upload a GPS Camera photo.', 400);
        }
        $imagePath = $imageResult['path'];
        $stmt = $conn->prepare("INSERT INTO crop_reports (
            location, crop_commodity, crop_type, crop_variety, date_planted, harvest_date,
            estimate_harvest, FarmerID, crop_status, farm_gate_price, number_of_trees,
            latitude, longitude, image_path, reportDate, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, NOW(), ?)");
        if (!$stmt) {
            scaims_delete_report_image($imagePath);
            return scaims_reporting_result(false, 'Error: ' . $conn->error, 500);
        }
        $stmt->bind_param('sssssssisssssss', $location, $cropCommodity, $cropType, $cropVariety,
            $datePlanted, $harvestDate, $estimateHarvest, $ownerId, $cropStatus, $farmGatePrice,
            $numberOfTrees, $latitude, $longitude, $imagePath, $actor);

        if (!scaims_execute_photo_report($conn, $stmt, $reportType, $photo)) {
            $error = $stmt->error;
            $stmt->close();
            scaims_delete_report_image($imagePath);
            return scaims_reporting_result(false, 'Error: ' . $error, 500);
        }
        $stmt->close();

        if ($saveDefaults) {
            $saved = scaims_save_farmer_report_defaults($conn, $ownerId, 'crop', [
                'crop_commodity' => $cropCommodity, 'crop_type' => $cropType,
                'crop_variety' => $cropVariety, 'date_planted' => $datePlanted,
                'harvest_date' => $harvestDate, 'estimate_harvest' => $estimateHarvest,
                'crop_status' => $cropStatus, 'number_of_trees' => $numberOfTrees,
                'farm_gate_price' => $farmGatePrice,
            ]);
            return scaims_reporting_result(true, $saved
                ? 'New crop report submitted successfully✅'
                : 'New crop report submitted successfully, but reusable report data could not be saved.');
        }
        return scaims_reporting_result(true, 'Crop report submitted successfully.');
    }

    $cropCommodity = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'damage-crop-commodity') : (string) ($request['damage-crop-commodity'] ?? '');
    $cropType = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'damage-crop-type') : (string) ($request['damage-crop-type'] ?? '');
    if ($cropType === 'Other') {
        $cropType = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'other-damage-crop-type') : (string) ($request['other-damage-crop-type'] ?? '');
    }
    $cropVariety = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'damage-crop-variety') : (string) ($request['damage-crop-variety'] ?? '');
    $dateOccurrence = $photo['date'];
    $damageCause = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'damage-cause') : (string) ($request['damage-cause'] ?? '');
    $percentDamage = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'damage-percentage') : (string) ($request['damage-percentage'] ?? '');
    $estimatedLoss = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'estimated-loss') : (string) ($request['estimated-loss'] ?? '');
    $farmGatePrice = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'damage-farm-gate-price') : (string) ($request['damage-farm-gate-price'] ?? '');
    $numberOfTrees = $policy === 'admin_strict' ? scaims_reporting_clean($request, 'damage-number-of-trees') : ($request['damage-number-of-trees'] ?? null);

    if ($policy === 'admin_strict') {
        $error = scaims_reporting_first_error([
            scaims_reporting_required_error($location, 'Location'),
            scaims_reporting_required_error($cropCommodity, 'Crop commodity'),
            scaims_reporting_required_error($cropType, 'Crop type'),
            scaims_reporting_required_error($cropVariety, 'Crop variety'),
            scaims_reporting_date_error($dateOccurrence, 'date of occurrence'),
            scaims_reporting_required_error($damageCause, 'Damage cause'),
            scaims_reporting_percentage_error($percentDamage),
            scaims_reporting_positive_error($estimatedLoss, 'estimated loss'),
            scaims_reporting_positive_error($farmGatePrice, 'farm gate price'),
        ]);
        if ($error !== null) return scaims_reporting_result(false, $error, 400);
        if (strtotime($dateOccurrence) > strtotime(date('Y-m-d'))) return scaims_reporting_result(false, 'Date of occurrence cannot be in the future.', 400);
    }

    $imageResult = scaims_finalize_pending_report_image($pending['token'], $reportType);
    if (!$imageResult['ok'] || empty($imageResult['path'])) {
        return scaims_reporting_result(false, $imageResult['message'] ?? 'Please upload a GPS Camera photo.', 400);
    }
    $imagePath = $imageResult['path'];
    $stmt = $conn->prepare("INSERT INTO damage_reports (
        dmgLocation, dmgCropCommodity, dmgCropType, dmgCropVariety, date_occurrence,
        damage_cause, percent_damage, estimated_loss, FarmerID, dmgFarmGatePrice,
        damage_number_of_trees, latitude, longitude, image_path, reportDate, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, NOW(), ?)");
    if (!$stmt) {
        scaims_delete_report_image($imagePath);
        return scaims_reporting_result(false, 'Error: ' . $conn->error, 500);
    }
    $stmt->bind_param('ssssssssissssss', $location, $cropCommodity, $cropType, $cropVariety,
        $dateOccurrence, $damageCause, $percentDamage, $estimatedLoss, $ownerId,
        $farmGatePrice, $numberOfTrees, $latitude, $longitude, $imagePath, $actor);

    if (!scaims_execute_photo_report($conn, $stmt, $reportType, $photo)) {
        $error = $stmt->error;
        $stmt->close();
        scaims_delete_report_image($imagePath);
        return scaims_reporting_result(false, $policy === 'farmer_legacy' ? 'Error: Execute failed: ' . $error : 'Error: ' . $error, 500);
    }
    if ($policy === 'farmer_legacy' && $stmt->affected_rows <= 0) {
        $stmt->close();
        scaims_delete_report_image($imagePath);
        return scaims_reporting_result(false, 'Error: No rows were inserted.', 500);
    }
    $stmt->close();

    if ($saveDefaults) {
        $saved = scaims_save_farmer_report_defaults($conn, $ownerId, 'damage', [
            'crop_commodity' => $cropCommodity, 'crop_type' => $cropType,
            'crop_variety' => $cropVariety, 'damage_cause' => $damageCause,
            'percent_damage' => $percentDamage, 'estimated_loss' => $estimatedLoss,
            'farm_gate_price' => $farmGatePrice, 'number_of_trees' => $numberOfTrees,
        ]);
        return scaims_reporting_result(true, $saved
            ? 'Damage report submitted successfully✅'
            : 'Damage report submitted successfully, but reusable report data could not be saved.');
    }
    return scaims_reporting_result(true, 'Damage report submitted successfully.');
}

function scaims_reporting_update_owned(mysqli $conn, array $request, array $files, int $farmerId, string $imageUrlPrefix = '../../../'): array
{
    $reportType = scaims_reporting_clean($request, 'report-type');
    $reportId = isset($request['report-id']) ? (int) $request['report-id'] : 0;
    if (!scaims_reporting_is_type($reportType) || $reportId <= 0) {
        return scaims_reporting_result(false, 'Invalid report selection.', 400);
    }

    try {
        $existing = scaims_reporting_fetch_owned($conn, $reportType, $reportId, $farmerId);
    } catch (RuntimeException $e) {
        return scaims_reporting_result(false, $e->getMessage(), 500);
    }
    if (!$existing) {
        return scaims_reporting_result(false, 'Report not found or you do not have permission to edit it.', 404);
    }

    $oldImagePath = $existing['image_path'] ?? null;
    $uploadedImagePath = null;
    $newImagePath = $oldImagePath;
    $removeImage = scaims_reporting_clean($request, 'remove-report-image') === '1';
    $photo = scaims_photo_from_row($existing);
    $replacement = null;

    if ($photo !== null) {
        if ($removeImage || !empty($files['report-image']['tmp_name'])) {
            return scaims_reporting_result(false, 'Replace this GPS photo with another successfully read GPS Camera photo.', 400);
        }
        $replacementToken = scaims_reporting_clean($request, 'edit-pending-report-image-token');
        if ($replacementToken !== '') {
            $replacement = scaims_pending_gps_photo($replacementToken);
            if (!$replacement) return scaims_reporting_result(false, 'GPS extraction is missing or expired. Choose the photo again.', 400);
            $photo = $replacement['photo'];
        }
    }

    $latitude = $photo ? $photo['latitude'] : scaims_reporting_normalize_coordinate(scaims_reporting_optional($request, 'latitude'), -90, 90);
    $longitude = $photo ? $photo['longitude'] : scaims_reporting_normalize_coordinate(scaims_reporting_optional($request, 'longitude'), -180, 180);
    if (($latitude === null) xor ($longitude === null)) return scaims_reporting_result(false, 'Please provide both latitude and longitude, or leave both empty.', 400);
    if ($latitude === false || $longitude === false) return scaims_reporting_result(false, 'Coordinates are invalid. Latitude must be between -90 and 90, and longitude must be between -180 and 180.', 400);

    $location = $photo ? mb_substr($photo['location'], 0, 255) : scaims_reporting_clean($request, 'location');
    $commodity = scaims_reporting_clean($request, 'crop-commodity');
    $cropType = scaims_reporting_clean($request, 'crop-type');
    $cropVariety = scaims_reporting_clean($request, 'crop-variety');
    $farmGatePrice = scaims_reporting_clean($request, 'farm-gate-price');
    $numberOfTrees = scaims_reporting_optional($request, 'number-of-trees');

    if ($reportType === 'crop') {
        $datePlanted = scaims_reporting_clean($request, 'date-planted');
        $harvestDate = scaims_reporting_clean($request, 'harvest-date');
        $estimateHarvest = scaims_reporting_clean($request, 'estimate-harvest');
        $cropStatus = scaims_reporting_clean($request, 'crop-status');
        $error = scaims_reporting_first_error([
            scaims_reporting_required_error($location, 'Location'),
            scaims_reporting_required_error($commodity, 'Crop commodity'),
            scaims_reporting_required_error($cropType, 'Crop type'),
            scaims_reporting_required_error($cropVariety, 'Crop variety'),
            scaims_reporting_date_error($datePlanted, 'date planted'),
            scaims_reporting_date_error($harvestDate, 'harvest date'),
            scaims_reporting_positive_error($estimateHarvest, 'estimated harvest'),
            scaims_reporting_required_error($cropStatus, 'Crop status'),
            scaims_reporting_positive_error($farmGatePrice, 'farm gate price'),
        ]);
        if ($error !== null) return scaims_reporting_result(false, $error, 400);
        if (strtotime($datePlanted) > strtotime(date('Y-m-d'))) return scaims_reporting_result(false, 'Date planted cannot be in the future.', 400);
        if (strtotime($harvestDate) <= strtotime($datePlanted)) return scaims_reporting_result(false, 'Harvest date must be after the date planted.', 400);
        $defaults = [
            'location' => $location, 'crop_commodity' => $commodity, 'crop_type' => $cropType,
            'crop_variety' => $cropVariety, 'date_planted' => $datePlanted,
            'harvest_date' => $harvestDate, 'estimate_harvest' => $estimateHarvest,
            'crop_status' => $cropStatus, 'number_of_trees' => $numberOfTrees,
            'farm_gate_price' => $farmGatePrice, 'latitude' => $latitude, 'longitude' => $longitude,
        ];
    } else {
        $dateOccurrence = $photo ? $photo['date'] : scaims_reporting_clean($request, 'date-occurrence');
        $damageCause = scaims_reporting_clean($request, 'damage-cause');
        $percentDamage = scaims_reporting_clean($request, 'damage-percentage');
        $estimatedLoss = scaims_reporting_clean($request, 'estimated-loss');
        $error = scaims_reporting_first_error([
            scaims_reporting_required_error($location, 'Location'),
            scaims_reporting_required_error($commodity, 'Crop commodity'),
            scaims_reporting_required_error($cropType, 'Crop type'),
            scaims_reporting_required_error($cropVariety, 'Crop variety'),
            scaims_reporting_date_error($dateOccurrence, 'date of occurrence'),
            scaims_reporting_required_error($damageCause, 'Damage cause'),
            scaims_reporting_percentage_error($percentDamage),
            scaims_reporting_positive_error($estimatedLoss, 'estimated loss'),
            scaims_reporting_positive_error($farmGatePrice, 'farm gate price'),
        ]);
        if ($error !== null) return scaims_reporting_result(false, $error, 400);
        if (strtotime($dateOccurrence) > strtotime(date('Y-m-d'))) return scaims_reporting_result(false, 'Date of occurrence cannot be in the future.', 400);
        $defaults = [
            'location' => $location, 'crop_commodity' => $commodity, 'crop_type' => $cropType,
            'crop_variety' => $cropVariety, 'date_occurrence' => $dateOccurrence,
            'damage_cause' => $damageCause, 'percent_damage' => $percentDamage,
            'estimated_loss' => $estimatedLoss, 'farm_gate_price' => $farmGatePrice,
            'number_of_trees' => $numberOfTrees, 'latitude' => $latitude, 'longitude' => $longitude,
        ];
    }

    $imageResult = $photo
        ? ($replacement ? scaims_finalize_pending_report_image($replacement['token'], $reportType) : ['ok' => true, 'path' => null])
        : scaims_collect_report_image('report-image', $reportType);
    if (!$imageResult['ok']) return scaims_reporting_result(false, $imageResult['message'] ?? 'The report image could not be uploaded. Please try again.', 400);
    $uploadedImagePath = $imageResult['path'] ?? null;
    if ($uploadedImagePath !== null) $newImagePath = $uploadedImagePath;
    elseif ($removeImage) $newImagePath = null;

    if ($reportType === 'crop') {
        $stmt = $conn->prepare("UPDATE crop_reports SET location=?, crop_commodity=?, crop_type=?, crop_variety=?,
            date_planted=?, harvest_date=?, estimate_harvest=?, crop_status=?, farm_gate_price=?,
            number_of_trees=?, latitude=?, longitude=?, image_path=? WHERE id=? AND FarmerID=?");
        if ($stmt) $stmt->bind_param('sssssssssssssii', $location, $commodity, $cropType, $cropVariety,
            $datePlanted, $harvestDate, $estimateHarvest, $cropStatus, $farmGatePrice,
            $numberOfTrees, $latitude, $longitude, $newImagePath, $reportId, $farmerId);
    } else {
        $stmt = $conn->prepare("UPDATE damage_reports SET dmgLocation=?, dmgCropCommodity=?, dmgCropType=?,
            dmgCropVariety=?, date_occurrence=?, damage_cause=?, percent_damage=?, estimated_loss=?,
            dmgFarmGatePrice=?, damage_number_of_trees=?, latitude=?, longitude=?, image_path=?
            WHERE id=? AND FarmerID=?");
        if ($stmt) $stmt->bind_param('sssssssssssssii', $location, $commodity, $cropType, $cropVariety,
            $dateOccurrence, $damageCause, $percentDamage, $estimatedLoss, $farmGatePrice,
            $numberOfTrees, $latitude, $longitude, $newImagePath, $reportId, $farmerId);
    }
    if (!$stmt) {
        scaims_delete_report_image($uploadedImagePath);
        return scaims_reporting_result(false, 'Unable to save report changes.', 500);
    }
    if (!scaims_execute_photo_report($conn, $stmt, $reportType, $photo, $reportId)) {
        $error = $stmt->error;
        $stmt->close();
        scaims_delete_report_image($uploadedImagePath);
        return scaims_reporting_result(false, 'Unable to save report changes: ' . $error, 500);
    }
    $stmt->close();

    if ($uploadedImagePath !== null && $oldImagePath !== null && $oldImagePath !== $uploadedImagePath) scaims_delete_report_image($oldImagePath);
    elseif ($removeImage && $oldImagePath !== null) scaims_delete_report_image($oldImagePath);

    try {
        $updated = scaims_reporting_fetch_presented($conn, $reportType, $reportId, $farmerId, $imageUrlPrefix);
    } catch (RuntimeException $e) {
        return scaims_reporting_result(false, $e->getMessage(), 500);
    }
    if (!$updated) return scaims_reporting_result(false, 'Report not found.', 404);
    if ($photo) foreach (['location', 'latitude', 'longitude', 'date_occurrence'] as $key) unset($defaults[$key]);
    $saved = scaims_save_farmer_report_defaults($conn, $farmerId, $reportType, $defaults);
    return scaims_reporting_result(true, $saved
        ? 'Report changes saved.'
        : 'Report changes saved, but reusable report data could not be saved.', 200, ['report' => $updated]);
}
