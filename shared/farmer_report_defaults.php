<?php

if (!function_exists('scaims_empty_farmer_report_defaults')) {
    function scaims_empty_farmer_report_defaults(): array
    {
        return [
            'location' => '',
            'crop_commodity' => '',
            'crop_type' => '',
            'crop_variety' => '',
            'date_planted' => '',
            'harvest_date' => '',
            'estimate_harvest' => '',
            'crop_status' => '',
            'number_of_trees' => '',
            'farm_gate_price' => '',
            'date_occurrence' => '',
            'damage_cause' => '',
            'percent_damage' => '',
            'estimated_loss' => '',
            'latitude' => '',
            'longitude' => '',
        ];
    }
}

if (!function_exists('scaims_ensure_farmer_report_defaults_table')) {
    function scaims_ensure_farmer_report_defaults_table(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS farmer_report_defaults (
                id INT AUTO_INCREMENT PRIMARY KEY,
                FarmerID INT NOT NULL,
                report_type ENUM('crop', 'damage') NOT NULL,
                location VARCHAR(255) DEFAULT NULL,
                crop_commodity VARCHAR(255) DEFAULT NULL,
                crop_type VARCHAR(255) DEFAULT NULL,
                crop_variety VARCHAR(255) DEFAULT NULL,
                date_planted DATE DEFAULT NULL,
                harvest_date DATE DEFAULT NULL,
                estimate_harvest DECIMAL(12,2) DEFAULT NULL,
                crop_status VARCHAR(50) DEFAULT NULL,
                number_of_trees VARCHAR(255) DEFAULT NULL,
                farm_gate_price DECIMAL(10,2) DEFAULT NULL,
                date_occurrence DATE DEFAULT NULL,
                damage_cause VARCHAR(255) DEFAULT NULL,
                percent_damage DECIMAL(5,2) DEFAULT NULL,
                estimated_loss DECIMAL(12,2) DEFAULT NULL,
                latitude DECIMAL(10,7) DEFAULT NULL,
                longitude DECIMAL(10,7) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_farmer_report_defaults (FarmerID, report_type),
                KEY idx_farmer_report_defaults_farmer (FarmerID)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
}

if (!function_exists('scaims_normalize_farmer_report_default_row')) {
    function scaims_normalize_farmer_report_default_row(array $row): array
    {
        $defaults = scaims_empty_farmer_report_defaults();
        foreach ($defaults as $key => $value) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $defaults[$key] = (string) $row[$key];
            }
        }
        return $defaults;
    }
}

if (!function_exists('scaims_merge_farmer_report_defaults')) {
    function scaims_merge_farmer_report_defaults(array $base, array $saved): array
    {
        $merged = $base;
        foreach ($saved as $key => $value) {
            if ($value !== null && $value !== '') {
                $merged[$key] = (string) $value;
            }
        }
        return $merged;
    }
}

if (!function_exists('scaims_get_farmer_report_defaults')) {
    function scaims_get_farmer_report_defaults(mysqli $conn, int $farmerId): array
    {
        scaims_ensure_farmer_report_defaults_table($conn);

        $result = [
            'crop' => scaims_empty_farmer_report_defaults(),
            'damage' => scaims_empty_farmer_report_defaults(),
        ];

        $stmt = $conn->prepare('SELECT * FROM farmer_report_defaults WHERE FarmerID = ?');
        if (!$stmt) {
            return $result;
        }

        $stmt->bind_param('i', $farmerId);
        $stmt->execute();
        $rows = $stmt->get_result();
        while ($row = $rows->fetch_assoc()) {
            $reportType = $row['report_type'] ?? '';
            if (isset($result[$reportType])) {
                $result[$reportType] = scaims_normalize_farmer_report_default_row($row);
            }
        }
        $stmt->close();

        return $result;
    }
}

if (!function_exists('scaims_save_farmer_report_defaults')) {
    function scaims_save_farmer_report_defaults(mysqli $conn, int $farmerId, string $reportType, array $values): bool
    {
        scaims_ensure_farmer_report_defaults_table($conn);

        $defaults = scaims_empty_farmer_report_defaults();
        $values = array_merge($defaults, array_intersect_key($values, $defaults));

        foreach ($values as $key => $value) {
            $values[$key] = $value === '' ? null : $value;
        }

        $stmt = $conn->prepare(
            "INSERT INTO farmer_report_defaults (
                FarmerID,
                report_type,
                location,
                crop_commodity,
                crop_type,
                crop_variety,
                date_planted,
                harvest_date,
                estimate_harvest,
                crop_status,
                number_of_trees,
                farm_gate_price,
                date_occurrence,
                damage_cause,
                percent_damage,
                estimated_loss,
                latitude,
                longitude
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                location = VALUES(location),
                crop_commodity = VALUES(crop_commodity),
                crop_type = VALUES(crop_type),
                crop_variety = VALUES(crop_variety),
                date_planted = VALUES(date_planted),
                harvest_date = VALUES(harvest_date),
                estimate_harvest = VALUES(estimate_harvest),
                crop_status = VALUES(crop_status),
                number_of_trees = VALUES(number_of_trees),
                farm_gate_price = VALUES(farm_gate_price),
                date_occurrence = VALUES(date_occurrence),
                damage_cause = VALUES(damage_cause),
                percent_damage = VALUES(percent_damage),
                estimated_loss = VALUES(estimated_loss),
                latitude = VALUES(latitude),
                longitude = VALUES(longitude)"
        );

        if (!$stmt) {
            return false;
        }

        $location = $values['location'];
        $cropCommodity = $values['crop_commodity'];
        $cropType = $values['crop_type'];
        $cropVariety = $values['crop_variety'];
        $datePlanted = $values['date_planted'];
        $harvestDate = $values['harvest_date'];
        $estimateHarvest = $values['estimate_harvest'];
        $cropStatus = $values['crop_status'];
        $numberOfTrees = $values['number_of_trees'];
        $farmGatePrice = $values['farm_gate_price'];
        $dateOccurrence = $values['date_occurrence'];
        $damageCause = $values['damage_cause'];
        $percentDamage = $values['percent_damage'];
        $estimatedLoss = $values['estimated_loss'];
        $latitude = $values['latitude'];
        $longitude = $values['longitude'];

        $stmt->bind_param(
            'isssssssssssssssss',
            $farmerId,
            $reportType,
            $location,
            $cropCommodity,
            $cropType,
            $cropVariety,
            $datePlanted,
            $harvestDate,
            $estimateHarvest,
            $cropStatus,
            $numberOfTrees,
            $farmGatePrice,
            $dateOccurrence,
            $damageCause,
            $percentDamage,
            $estimatedLoss,
            $latitude,
            $longitude
        );

        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('scaims_get_farmer_base_report_prefill')) {
    function scaims_get_farmer_base_report_prefill(mysqli $conn, int $farmerId): array
    {
        $prefill = scaims_empty_farmer_report_defaults();
        $farmerUserName = '';

        $stmt = $conn->prepare(
            'SELECT f.Barangay, u.UName
             FROM farmer f
             LEFT JOIN user u ON f.UserID = u.UserID
             WHERE f.FarmerID = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return $prefill;
        }

        $stmt->bind_param('i', $farmerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return $prefill;
        }

        $prefill['location'] = trim((string) ($row['Barangay'] ?? ''));
        $farmerUserName = trim((string) ($row['UName'] ?? ''));

        if ($farmerUserName === '') {
            return $prefill;
        }

        $cropRows = scaims_get_farmer_registered_crop_choices($conn, $farmerId);
        $distinctCropRows = [];
        $distinctLocations = [];

        foreach ($cropRows as $cropRow) {
            $cropSignature = implode('|', [
                $cropRow['crop_commodity'],
                $cropRow['crop_type'],
                $cropRow['crop_variety'],
                $cropRow['number_of_trees'],
                $cropRow['crop_status'],
            ]);
            $distinctCropRows[$cropSignature] = $cropRow;

            if ($cropRow['location'] !== '') {
                $distinctLocations[strtolower($cropRow['location'])] = $cropRow['location'];
            }
        }

        if ($prefill['location'] === '' && count($distinctLocations) === 1) {
            $prefill['location'] = array_values($distinctLocations)[0];
        }

        if (count($distinctCropRows) === 1) {
            $crop = array_values($distinctCropRows)[0];
            $prefill = array_merge($prefill, [
                'location' => $crop['location'] !== '' ? $crop['location'] : $prefill['location'],
                'crop_commodity' => $crop['crop_commodity'],
                'crop_type' => $crop['crop_type'],
                'crop_variety' => $crop['crop_variety'],
                'number_of_trees' => $crop['number_of_trees'],
                'crop_status' => $crop['crop_status'],
            ]);
        }

        return $prefill;
    }
}

if (!function_exists('scaims_get_farmer_registered_crop_choices')) {
    function scaims_get_farmer_registered_crop_choices(mysqli $conn, int $farmerId): array
    {
        $stmt = $conn->prepare(
            'SELECT
                cd.id,
                cd.crop_commodity,
                cd.crop_type,
                cd.crop_variety,
                cd.num_trees,
                cd.tree_status,
                pd.location AS parcel_location
             FROM crop_details cd
             LEFT JOIN parcel_details pd ON cd.parcel_detail_id = pd.id
             LEFT JOIN user u ON CONVERT(cd.created_by USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.UName USING utf8mb4) COLLATE utf8mb4_general_ci
             LEFT JOIN farmer f ON f.UserID = u.UserID
             WHERE f.FarmerID = ?
             ORDER BY pd.location, cd.crop_type, cd.crop_variety'
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $farmerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'id' => (int) $row['id'],
                'location' => trim((string) ($row['parcel_location'] ?? '')),
                'crop_commodity' => function_exists('scaims_normalize_crop_commodity')
                    ? scaims_normalize_crop_commodity($row['crop_commodity'] ?? '')
                    : trim((string) ($row['crop_commodity'] ?? '')),
                'crop_type' => trim((string) ($row['crop_type'] ?? '')),
                'crop_variety' => trim((string) ($row['crop_variety'] ?? '')),
                'number_of_trees' => trim((string) ($row['num_trees'] ?? '')),
                'crop_status' => trim((string) ($row['tree_status'] ?? '')),
            ];
        }

        $stmt->close();

        return $rows;
    }
}
