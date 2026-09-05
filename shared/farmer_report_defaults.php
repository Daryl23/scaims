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
