<?php

require_once __DIR__ . '/../conn.php';

if (!function_exists('scaims_crop_catalog_defaults')) {
    function scaims_crop_catalog_defaults(): array
    {
        return [
            'grains' => ['Rice', 'Corn'],
            'plantation' => ['Coconut', 'Coffee', 'Sugarcane', 'Black Pepper', 'Cacao'],
            'root' => ['Sweet Potato', 'Cassava', 'Ube/Yam', 'Ginger', 'Gabi'],
            'vegetables' => [
                'Ampalaya', 'Bell Pepper', 'Bush Sitao', 'Chayote', 'Cowpea', 'Cucumber',
                'Eggplant', 'Hot Pepper', 'Lady Finger (okra)', 'Lettuce', 'Mung Bean',
                'Mustard', 'Pechay', 'Pole Sitao', 'Radish', 'Snap Beans', 'Soybeans',
                'Squash', 'Tomato', 'Upo', 'Patola', 'Sigarilyas'
            ],
            'fruits' => [
                'Avocado', 'Banana', 'Calamansi', 'Citrus', 'Pomelo', 'Papaya',
                'Dragon Fruit', 'Durian', 'Guyabano', 'Jackfruit', 'Lanzones', 'Mango',
                'Mango Pajo', 'Mangosteen', 'Pineapple', 'Rambutan', 'Santol', 'Tamarind',
                'Pili'
            ],
        ];
    }
}

if (!function_exists('scaims_crop_commodity_labels')) {
    function scaims_crop_commodity_labels(): array
    {
        return [
            'grains' => 'Grains',
            'plantation' => 'Plantation Crops',
            'root' => 'Root Crops',
            'root_crops' => 'Root Crops',
            'vegetables' => 'Vegetables',
            'fruits' => 'Fruits',
        ];
    }
}

if (!function_exists('scaims_normalize_crop_commodity')) {
    function scaims_normalize_crop_commodity(?string $commodity): string
    {
        $key = strtolower(trim((string) $commodity));
        $key = str_replace(['-', ' '], '_', $key);

        if ($key === 'root_crops' || $key === 'root_crop') {
            return 'root';
        }

        return $key;
    }
}

if (!function_exists('scaims_crop_filter_slug')) {
    function scaims_crop_filter_slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string) $value, '-');
    }
}

if (!function_exists('scaims_expand_crop_filter_names')) {
    function scaims_expand_crop_filter_names(array $cropNames): array
    {
        $expanded = [];
        foreach ($cropNames as $cropName) {
            $name = trim((string) $cropName);
            if ($name === '') {
                continue;
            }

            $expanded[] = $name;
            $slug = scaims_crop_filter_slug($name);

            if ($slug === 'lady-finger-okra' || $slug === 'ladyfinger-okra') {
                $expanded[] = 'Lady Finger';
                $expanded[] = 'Lady Finger (Okra)';
                $expanded[] = 'Ladyfinger (Okra)';
                $expanded[] = 'Ladyfinger';
                $expanded[] = 'Okra';
            }

            if ($slug === 'ube-yam') {
                $expanded[] = 'Ube-Yam';
                $expanded[] = 'Ube Yam';
            }
        }

        return array_values(array_unique($expanded));
    }
}

if (!function_exists('scaims_crop_match_condition')) {
    function scaims_crop_match_condition(string $column): string
    {
        $slugColumn = "LOWER(TRIM(BOTH '-' FROM REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($column, '/', '-'), ' ', '-'), '(', ''), ')', ''), '--', '-')))";
        return "(LOWER($column) = LOWER(?) OR $slugColumn = ?)";
    }
}

if (!function_exists('scaims_ensure_crop_catalog_table')) {
    function scaims_ensure_crop_catalog_table(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS crop_catalog (
                id INT AUTO_INCREMENT PRIMARY KEY,
                commodity VARCHAR(32) NOT NULL,
                crop_name VARCHAR(120) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_crop_catalog_commodity_name (commodity, crop_name),
                KEY idx_crop_catalog_active (is_active),
                KEY idx_crop_catalog_commodity_sort (commodity, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $result = $conn->query('SELECT COUNT(*) AS total FROM crop_catalog');
        $row = $result ? $result->fetch_assoc() : ['total' => 0];

        if ((int) ($row['total'] ?? 0) > 0) {
            return;
        }

        $stmt = $conn->prepare(
            'INSERT INTO crop_catalog (commodity, crop_name, is_active, sort_order) VALUES (?, ?, 1, ?)'
        );

        foreach (scaims_crop_catalog_defaults() as $commodity => $crops) {
            foreach ($crops as $index => $cropName) {
                $order = ($index + 1) * 10;
                $stmt->bind_param('ssi', $commodity, $cropName, $order);
                $stmt->execute();
            }
        }

        $stmt->close();
    }
}

if (!function_exists('scaims_get_crop_catalog_rows')) {
    function scaims_get_crop_catalog_rows(mysqli $conn, bool $includeInactive = false): array
    {
        scaims_ensure_crop_catalog_table($conn);

        $sql = 'SELECT id, commodity, crop_name, is_active, sort_order FROM crop_catalog';
        if (!$includeInactive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= " ORDER BY FIELD(commodity, 'grains', 'plantation', 'root', 'vegetables', 'fruits'), sort_order, crop_name";

        $result = $conn->query($sql);
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'id' => (int) $row['id'],
                'commodity' => $row['commodity'],
                'crop_name' => $row['crop_name'],
                'is_active' => (int) $row['is_active'] === 1,
                'sort_order' => (int) $row['sort_order'],
            ];
        }

        return $rows;
    }
}

if (!function_exists('scaims_get_crop_catalog_groups')) {
    function scaims_get_crop_catalog_groups(mysqli $conn, bool $includeInactive = false): array
    {
        $groups = [
            'grains' => [],
            'plantation' => [],
            'root' => [],
            'vegetables' => [],
            'fruits' => [],
        ];

        foreach (scaims_get_crop_catalog_rows($conn, $includeInactive) as $row) {
            if (!$includeInactive && !$row['is_active']) {
                continue;
            }

            $commodity = scaims_normalize_crop_commodity($row['commodity']);
            if (!array_key_exists($commodity, $groups)) {
                $groups[$commodity] = [];
            }
            $groups[$commodity][] = $row['crop_name'];
        }

        return $groups;
    }
}

if (!function_exists('scaims_get_active_crop_names')) {
    function scaims_get_active_crop_names(mysqli $conn): array
    {
        $names = [];
        foreach (scaims_get_crop_catalog_rows($conn, false) as $row) {
            $names[] = $row['crop_name'];
        }
        return scaims_expand_crop_filter_names($names);
    }
}

if (!function_exists('scaims_crop_not_in_condition')) {
    function scaims_crop_not_in_condition(string $column, array $cropNames): string
    {
        if (empty($cropNames)) {
            return '1=1';
        }

        $placeholders = implode(',', array_fill(0, count($cropNames), '?'));
        return "LOWER($column) NOT IN ($placeholders)";
    }
}
