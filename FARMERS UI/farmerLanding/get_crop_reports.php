<?php
/**
 * GeoJSON endpoint for the public Leaflet Crop Map.
 *
 * The public Crop Map is intentionally scoped to the supported SCAIMS
 * agricultural area: Lipa City, Batangas and Mataas na Kahoy, Batangas.
 *
 * Existing schema inspected:
 * - crop_reports.location, latitude, longitude
 * - farmer.Barangay
 * - damage_reports joined by FarmerID + crop type
 *
 * No PostGIS tables, geometry columns, GeoJSON, shapefiles, or barangay/LGU
 * boundary datasets are present in this repository/database. The endpoint
 * therefore enforces the scope using the existing location fields and rejects
 * saved coordinates outside the supported map extent.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__, 2) . '/conn.php';

define('PRICE_HIGH', 80);
define('PRICE_MEDIUM', 30);

$SCOPE_BOUNDS = [
    // Bounds used for initial map fitting. These are display/navigation bounds,
    // not administrative boundary polygons.
    'initial' => [[13.8400, 121.0350], [14.0200, 121.2450]],
    'max' => [[13.8000, 120.9900], [14.0600, 121.2900]],
    // Backend coordinate sanity envelope. Saved report coordinates outside this
    // envelope are not sent to the public Crop Map.
    'coordinate' => [[13.8400, 121.0350], [14.0200, 121.2550]],
];

$LGUS = [
    'lipa_city' => [
        'key' => 'lipa_city',
        'value' => 'lgu:lipa_city',
        'label' => 'Lipa City, Batangas',
        'shortLabel' => 'Lipa City',
        'center' => [13.9411, 121.1631],
        'bounds' => [[13.8500, 121.0850], [14.0100, 121.2450]],
    ],
    'mataas_na_kahoy' => [
        'key' => 'mataas_na_kahoy',
        'value' => 'lgu:mataas_na_kahoy',
        'label' => 'Mataas na Kahoy, Batangas',
        'shortLabel' => 'Mataas na Kahoy',
        'center' => [13.9600, 121.1100],
        'bounds' => [[13.9250, 121.0450], [14.0100, 121.1500]],
    ],
];

/*
 * Lipa barangay values are taken from the existing SCAIMS forms/filter lists.
 * Centers are only present where the previous Crop Map already had a coordinate
 * lookup; missing centers require saved report coordinates before they can be
 * rendered accurately.
 */
$LIPA_BARANGAYS = [
    'adya' => ['value' => 'adya', 'label' => 'Adya', 'center' => [13.9180, 121.1450]],
    'anilao' => ['value' => 'anilao', 'label' => 'Anilao', 'center' => [13.9210, 121.1380]],
    'anilaolabac' => ['value' => 'anilao-labac', 'label' => 'Anilao-Labac'],
    'antipolodelnorte' => ['value' => 'antipolodelnorte', 'label' => 'Antipolo del Norte'],
    'antipolodelsur' => ['value' => 'antipolodelsur', 'label' => 'Antipolo del Sur'],
    'bagongpook' => ['value' => 'bagongpook', 'label' => 'Bagong Pook'],
    'balintawak' => ['value' => 'balintawak', 'label' => 'Balintawak', 'center' => [13.9290, 121.1520]],
    'banaybanay' => ['value' => 'banaybanay', 'label' => 'Banaybanay', 'center' => [13.9350, 121.1480]],
    'bolbok' => ['value' => 'bolbok', 'label' => 'Bolbok', 'center' => [13.9450, 121.1500]],
    'bugtongnapulo' => ['value' => 'bugtongnapulo', 'label' => 'Bugtong na Pulo'],
    'bulacnin' => ['value' => 'bulacnin', 'label' => 'Bulacnin', 'center' => [13.9520, 121.1560]],
    'bulaklakan' => ['value' => 'bulaklakan', 'label' => 'Bulaklakan'],
    'calamias' => ['value' => 'calamias', 'label' => 'Calamias'],
    'cumba' => ['value' => 'cumba', 'label' => 'Cumba', 'center' => [13.9600, 121.1650]],
    'dagatan' => ['value' => 'dagatan', 'label' => 'Dagatan', 'center' => [13.9500, 121.1580]],
    'duhatan' => ['value' => 'duhatan', 'label' => 'Duhatan', 'center' => [13.9380, 121.1420]],
    'halang' => ['value' => 'halang', 'label' => 'Halang', 'center' => [13.9350, 121.1550]],
    'inosluban' => ['value' => 'inosluban', 'label' => 'Inosluban', 'center' => [13.9200, 121.1600]],
    'kayumanggi' => ['value' => 'kayumanggi', 'label' => 'Kayumanggi', 'center' => [13.9270, 121.1490]],
    'latag' => ['value' => 'latag', 'label' => 'Latag', 'center' => [13.9320, 121.1610]],
    'lodlod' => ['value' => 'lodlod', 'label' => 'Lodlod', 'center' => [13.9480, 121.1700]],
    'lumbang' => ['value' => 'lumbang', 'label' => 'Lumbang', 'center' => [13.9560, 121.1720]],
    'mabini' => ['value' => 'mabini', 'label' => 'Mabini', 'center' => [13.9230, 121.1350]],
    'malagonlong' => ['value' => 'malagonlong', 'label' => 'Malagonlong', 'center' => [13.9310, 121.1670]],
    'malitlit' => ['value' => 'malitlit', 'label' => 'Malitlit', 'center' => [13.9390, 121.1590]],
    'marawoy' => ['value' => 'marawoy', 'label' => 'Marawoy', 'center' => [13.9421, 121.1640]],
    'mataasnalupa' => ['value' => 'mataasnalupa', 'label' => 'Mataas na Lupa', 'center' => [13.9460, 121.1460]],
    'muntingpulo' => ['value' => 'muntingpulo', 'label' => 'Munting Pulo', 'center' => [13.9340, 121.1530]],
    'pagolinginbata' => ['value' => 'pagolinginbata', 'label' => 'Pagolingin Bata', 'center' => [13.9270, 121.1560]],
    'pagolingineast' => ['value' => 'pagolingineast', 'label' => 'Pagolingin East', 'center' => [13.9260, 121.1580]],
    'pagolinginwest' => ['value' => 'pagolinginwest', 'label' => 'Pagolingin West', 'center' => [13.9250, 121.1540]],
    'pangao' => ['value' => 'pangao', 'label' => 'Pangao', 'center' => [13.9540, 121.1480]],
    'pinagkawitan' => ['value' => 'pinagkawitan', 'label' => 'Pinagkawitan', 'center' => [13.9380, 121.1700]],
    'pinagtongulan' => ['value' => 'pinagtongulan', 'label' => 'Pinagtongulan', 'center' => [13.9420, 121.1720]],
    'plaridel' => ['value' => 'plaridel', 'label' => 'Plaridel', 'center' => [13.9490, 121.1530]],
    'poblacionbarangay1' => ['value' => 'poblacionbarangay1', 'label' => 'Poblacion Barangay 1'],
    'poblacionbarangay2' => ['value' => 'poblacionbarangay2', 'label' => 'Poblacion Barangay 2'],
    'poblacionbarangay3' => ['value' => 'poblacionbarangay3', 'label' => 'Poblacion Barangay 3'],
    'poblacionbarangay4' => ['value' => 'poblacionbarangay4', 'label' => 'Poblacion Barangay 4'],
    'poblacionbarangay5' => ['value' => 'poblacionbarangay5', 'label' => 'Poblacion Barangay 5'],
    'poblacionbarangay6' => ['value' => 'poblacionbarangay6', 'label' => 'Poblacion Barangay 6'],
    'poblacionbarangay7' => ['value' => 'poblacionbarangay7', 'label' => 'Poblacion Barangay 7'],
    'poblacionbarangay8' => ['value' => 'poblacionbarangay8', 'label' => 'Poblacion Barangay 8'],
    'poblacionbarangay9' => ['value' => 'poblacionbarangay9', 'label' => 'Poblacion Barangay 9'],
    'poblacionbarangay9a' => ['value' => 'poblacionbarangay9a', 'label' => 'Poblacion Barangay 9-A'],
    'poblacionbarangay10' => ['value' => 'poblacionbarangay10', 'label' => 'Poblacion Barangay 10'],
    'poblacionbarangay11' => ['value' => 'poblacionbarangay11', 'label' => 'Poblacion Barangay 11'],
    'poblacionbarangay12' => ['value' => 'poblacionbarangay12', 'label' => 'Poblacion Barangay 12'],
    'pusil' => ['value' => 'pusil', 'label' => 'Pusil', 'center' => [13.9580, 121.1690]],
    'quezon' => ['value' => 'quezon', 'label' => 'Quezon', 'center' => [13.9510, 121.1610]],
    'rizal' => ['value' => 'rizal', 'label' => 'Rizal', 'center' => [13.9530, 121.1590]],
    'sabang' => ['value' => 'sabang', 'label' => 'Sabang', 'center' => [13.9370, 121.1440]],
    'sampaguita' => ['value' => 'sampaguita', 'label' => 'Sampaguita', 'center' => [13.9300, 121.1510]],
    'sanbenito' => ['value' => 'sanbenito', 'label' => 'San Benito'],
    'sancarlos' => ['value' => 'sancarlos', 'label' => 'San Carlos', 'center' => [13.9300, 121.1750]],
    'sancelestino' => ['value' => 'sancelestino', 'label' => 'San Celestino'],
    'sanfrancisco' => ['value' => 'sanfrancisco', 'label' => 'San Francisco'],
    'sanguillermo' => ['value' => 'sanguillermo', 'label' => 'San Guillermo'],
    'sanjose' => ['value' => 'sanjose', 'label' => 'San Jose', 'center' => [13.9440, 121.1680]],
    'sanlucas' => ['value' => 'sanlucas', 'label' => 'San Lucas', 'center' => [13.9280, 121.1400]],
    'sansalvador' => ['value' => 'sansalvador', 'label' => 'San Salvador', 'center' => [13.9360, 121.1460]],
    'sansebastian' => ['value' => 'sansebastian', 'label' => 'San Sebastian'],
    'santonino' => ['value' => 'santonino', 'label' => 'Santo Nino', 'center' => [13.9480, 121.1550]],
    'santoribio' => ['value' => 'santoribio', 'label' => 'Santo Toribio'],
    'sapac' => ['value' => 'sapac', 'label' => 'Sapac'],
    'sico' => ['value' => 'sico', 'label' => 'Sico', 'center' => [13.9220, 121.1320]],
    'talisay' => ['value' => 'talisay', 'label' => 'Talisay', 'center' => [13.9570, 121.1630]],
    'tambo' => ['value' => 'tambo', 'label' => 'Tambo', 'center' => [13.9550, 121.1800]],
    'tangob' => ['value' => 'tangob', 'label' => 'Tangob', 'center' => [13.9250, 121.1470]],
    'tanguay' => ['value' => 'tanguay', 'label' => 'Tanguay', 'center' => [13.9330, 121.1580]],
    'tibig' => ['value' => 'tibig', 'label' => 'Tibig', 'center' => [13.9190, 121.1560]],
    'tipacan' => ['value' => 'tipacan', 'label' => 'Tipacan', 'center' => [13.9240, 121.1430]],
];

$LOCATION_ALIASES = [
    'marauoy' => 'marawoy',
    'inosloban' => 'inosluban',
    'sanbenito' => 'sanbenito',
    'santoino' => 'santonino',
];

$LGU_LOCATION_KEYS = [
    'lipa' => 'lipa_city',
    'lipacity' => 'lipa_city',
    'lipacitybatangas' => 'lipa_city',
    'mataasnakahoy' => 'mataas_na_kahoy',
    'mataasnakahoybatangas' => 'mataas_na_kahoy',
];

function scaims_crop_map_normalize_location($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    $value = str_replace(["\xC3\xB1", "\xC3\x91"], 'n', $value);
    $value = strtolower($value);

    return preg_replace('/[^a-z0-9]+/', '', $value) ?: '';
}

function scaims_crop_map_canonical_location_key($value, array $aliases): string
{
    $key = scaims_crop_map_normalize_location($value);
    return $aliases[$key] ?? $key;
}

function scaims_crop_map_bounds_around_center(?array $center, float $pad = 0.008): ?array
{
    if (!$center || count($center) !== 2) {
        return null;
    }

    return [
        [$center[0] - $pad, $center[1] - $pad],
        [$center[0] + $pad, $center[1] + $pad],
    ];
}

function scaims_crop_map_is_valid_coordinate($lat, $lng): bool
{
    if ($lat === null || $lng === null || $lat === '' || $lng === '') {
        return false;
    }

    if (!is_numeric($lat) || !is_numeric($lng)) {
        return false;
    }

    $lat = (float) $lat;
    $lng = (float) $lng;

    return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
}

function scaims_crop_map_point_in_bounds(float $lat, float $lng, array $bounds): bool
{
    return $lat >= $bounds[0][0]
        && $lat <= $bounds[1][0]
        && $lng >= $bounds[0][1]
        && $lng <= $bounds[1][1];
}

function scaims_crop_map_location_info($location, $farmerBarangay, array $barangays, array $aliases, array $lguKeys, array $lgus): ?array
{
    foreach ([$location, $farmerBarangay] as $candidate) {
        $rawKey = scaims_crop_map_normalize_location($candidate);
        if ($rawKey === '') {
            continue;
        }

        $key = $aliases[$rawKey] ?? $rawKey;
        if (isset($barangays[$key])) {
            $barangay = $barangays[$key];
            $center = $barangay['center'] ?? null;

            return [
                'type' => 'barangay',
                'key' => $key,
                'value' => 'barangay:' . $key,
                'label' => $barangay['label'],
                'source_value' => $barangay['value'],
                'lgu_key' => 'lipa_city',
                'lgu_label' => $lgus['lipa_city']['label'],
                'center' => $center,
                'bounds' => scaims_crop_map_bounds_around_center($center),
            ];
        }

        if (isset($lguKeys[$rawKey])) {
            $lgu = $lgus[$lguKeys[$rawKey]];

            return [
                'type' => 'lgu',
                'key' => $lgu['key'],
                'value' => $lgu['value'],
                'label' => $lgu['shortLabel'],
                'source_value' => $candidate,
                'lgu_key' => $lgu['key'],
                'lgu_label' => $lgu['label'],
                'center' => $lgu['center'],
                'bounds' => $lgu['bounds'],
            ];
        }
    }

    return null;
}

function scaims_crop_map_offset_center(array $center, int $id): array
{
    $angle = deg2rad(($id * 137) % 360);
    $radius = 0.00025 * (($id % 6) + 1);

    return [
        $center[0] + cos($angle) * $radius,
        $center[1] + sin($angle) * $radius,
    ];
}

function scaims_crop_map_resolve_coords(array $row, array $locationInfo, array $coordinateBounds): ?array
{
    $hasAnySavedCoordinate = ($row['latitude'] ?? null) !== null
        || ($row['longitude'] ?? null) !== null;

    if ($hasAnySavedCoordinate) {
        if (!scaims_crop_map_is_valid_coordinate($row['latitude'] ?? null, $row['longitude'] ?? null)) {
            return null;
        }

        $lat = (float) $row['latitude'];
        $lng = (float) $row['longitude'];

        if (!scaims_crop_map_point_in_bounds($lat, $lng, $coordinateBounds)) {
            return null;
        }

        return [$lat, $lng, 'saved'];
    }

    if (!empty($locationInfo['center'])) {
        [$lat, $lng] = scaims_crop_map_offset_center($locationInfo['center'], (int) $row['id']);
        return [$lat, $lng, 'location_center'];
    }

    return null;
}

function scaims_crop_map_value_category(float $price): string
{
    if ($price > PRICE_HIGH) {
        return 'high';
    }
    if ($price >= PRICE_MEDIUM) {
        return 'medium';
    }
    return 'low';
}

function scaims_crop_map_add_params(array &$params, array $values, string $prefix): array
{
    $placeholders = [];
    foreach (array_values($values) as $index => $value) {
        $placeholder = ':' . $prefix . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $value;
    }

    return $placeholders;
}

function scaims_crop_map_location_filter_keys(string $requestedLocation, array $lipaBarangays, array $aliases, array $lguLocationKeys): array
{
    if (str_starts_with($requestedLocation, 'lgu:')) {
        $lguKey = substr($requestedLocation, 4);
        if ($lguKey === 'lipa_city') {
            return array_unique(array_merge(array_keys($lipaBarangays), array_keys($aliases)));
        }
        if ($lguKey === 'mataas_na_kahoy') {
            return array_keys(array_filter($lguLocationKeys, fn ($value) => $value === 'mataas_na_kahoy'));
        }
    }

    if (str_starts_with($requestedLocation, 'barangay:')) {
        return [substr($requestedLocation, 9)];
    }

    $key = scaims_crop_map_canonical_location_key($requestedLocation, $aliases);
    return $key !== '' ? [$key] : [];
}

function scaims_crop_map_feature_values(array $features, string $property): array
{
    $values = [];
    foreach ($features as $feature) {
        $value = $feature['properties'][$property] ?? '';
        if ($value !== null && trim((string) $value) !== '') {
            $values[(string) $value] = (string) $value;
        }
    }

    natcasesort($values);
    return array_values($values);
}

function scaims_crop_map_numeric_range(array $features, string $property): array
{
    $values = [];
    foreach ($features as $feature) {
        $value = $feature['properties'][$property] ?? null;
        if ($value !== null && is_numeric($value)) {
            $values[] = (float) $value;
        }
    }

    if (!$values) {
        return ['min' => 0, 'max' => 0];
    }

    return ['min' => min($values), 'max' => max($values)];
}

function scaims_crop_map_sql_location_expression(): string
{
    return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(NULLIF(cr.location, ''), f.Barangay, ''), '-', ''), ' ', ''), '_', ''), '.', ''), ',', ''))";
}

function scaims_crop_map_scope_keys(array $lipaBarangays, array $aliases, array $lguLocationKeys): array
{
    $keys = array_unique(array_merge(
        array_keys($lipaBarangays),
        array_keys($aliases),
        array_keys($lguLocationKeys)
    ));

    sort($keys);
    return $keys;
}

function scaims_crop_map_location_hierarchy(array $lgus, array $locationDescriptors): array
{
    $hierarchy = [];

    foreach ($lgus as $lguKey => $lgu) {
        $locations = array_values($locationDescriptors[$lguKey] ?? []);
        usort($locations, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        $hierarchy[] = [
            'type' => 'lgu',
            'key' => $lgu['key'],
            'value' => $lgu['value'],
            'label' => $lgu['label'],
            'shortLabel' => $lgu['shortLabel'],
            'center' => $lgu['center'],
            'bounds' => $lgu['bounds'],
            'locations' => $locations,
        ];
    }

    return $hierarchy;
}

try {
    $pdo = scaims_db_pdo();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

$conditions = [];
$params = [];
$locationExpression = scaims_crop_map_sql_location_expression();
$scopeKeys = scaims_crop_map_scope_keys($LIPA_BARANGAYS, $LOCATION_ALIASES, $LGU_LOCATION_KEYS);
$scopePlaceholders = scaims_crop_map_add_params($params, $scopeKeys, 'scope_location_');

$conditions[] = $locationExpression . ' IN (' . implode(',', $scopePlaceholders) . ')';
$conditions[] = '(cr.latitude IS NULL OR cr.longitude IS NULL OR (cr.latitude BETWEEN :scope_min_lat AND :scope_max_lat AND cr.longitude BETWEEN :scope_min_lng AND :scope_max_lng))';
$params[':scope_min_lat'] = $SCOPE_BOUNDS['coordinate'][0][0];
$params[':scope_max_lat'] = $SCOPE_BOUNDS['coordinate'][1][0];
$params[':scope_min_lng'] = $SCOPE_BOUNDS['coordinate'][0][1];
$params[':scope_max_lng'] = $SCOPE_BOUNDS['coordinate'][1][1];

$exactFilters = [
    'crop_type' => 'cr.crop_type',
    'commodity' => 'cr.crop_commodity',
    'crop_status' => 'cr.crop_status',
];

foreach ($exactFilters as $param => $column) {
    if (isset($_GET[$param]) && $_GET[$param] !== '') {
        $conditions[] = "$column = :$param";
        $params[":$param"] = $_GET[$param];
    }
}

if (isset($_GET['location']) && $_GET['location'] !== '') {
    $locationKeys = scaims_crop_map_location_filter_keys(
        (string) $_GET['location'],
        $LIPA_BARANGAYS,
        $LOCATION_ALIASES,
        $LGU_LOCATION_KEYS
    );

    if ($locationKeys) {
        $locationPlaceholders = scaims_crop_map_add_params($params, $locationKeys, 'filter_location_');
        $conditions[] = $locationExpression . ' IN (' . implode(',', $locationPlaceholders) . ')';
    } else {
        $conditions[] = '1=0';
    }
}

if (isset($_GET['price_min']) && $_GET['price_min'] !== '') {
    $conditions[] = 'cr.farm_gate_price >= :price_min';
    $params[':price_min'] = (float) $_GET['price_min'];
}
if (isset($_GET['price_max']) && $_GET['price_max'] !== '') {
    $conditions[] = 'cr.farm_gate_price <= :price_max';
    $params[':price_max'] = (float) $_GET['price_max'];
}

if (isset($_GET['yield_min']) && $_GET['yield_min'] !== '') {
    $conditions[] = 'cr.estimate_harvest >= :yield_min';
    $params[':yield_min'] = (float) $_GET['yield_min'];
}
if (isset($_GET['yield_max']) && $_GET['yield_max'] !== '') {
    $conditions[] = 'cr.estimate_harvest <= :yield_max';
    $params[':yield_max'] = (float) $_GET['yield_max'];
}

if (!empty($_GET['value_category'])) {
    switch ($_GET['value_category']) {
        case 'high':
            $conditions[] = 'cr.farm_gate_price > ' . PRICE_HIGH;
            break;
        case 'medium':
            $conditions[] = 'cr.farm_gate_price BETWEEN ' . PRICE_MEDIUM . ' AND ' . PRICE_HIGH;
            break;
        case 'low':
            $conditions[] = 'cr.farm_gate_price < ' . PRICE_MEDIUM;
            break;
    }
}

$havingClauses = [];
if (isset($_GET['damage_min']) && $_GET['damage_min'] !== '') {
    $havingClauses[] = 'max_damage >= :damage_min';
    $params[':damage_min'] = (float) $_GET['damage_min'];
}
if (!empty($_GET['has_damage'])) {
    if ($_GET['has_damage'] === 'yes') {
        $havingClauses[] = 'has_damage_report = 1';
    }
    if ($_GET['has_damage'] === 'no') {
        $havingClauses[] = 'has_damage_report = 0';
    }
}

$where = implode(' AND ', $conditions);
$having = $havingClauses ? 'HAVING ' . implode(' AND ', $havingClauses) : '';

$sql = "
    SELECT
        cr.id,
        cr.location,
        cr.crop_commodity,
        cr.crop_type,
        cr.crop_variety,
        cr.date_planted,
        cr.harvest_date,
        cr.estimate_harvest,
        cr.FarmerID,
        cr.reportDate,
        cr.created_by,
        cr.farm_gate_price,
        cr.crop_status,
        cr.number_of_trees,
        cr.latitude,
        cr.longitude,
        CONCAT(COALESCE(f.FirstName, ''), ' ', COALESCE(f.Surname, '')) AS farmer_name,
        f.Barangay AS farmer_barangay,
        COALESCE(MAX(dr.percent_damage), 0) AS max_damage,
        IF(MAX(dr.id) IS NOT NULL, 1, 0) AS has_damage_report,
        COALESCE(SUM(dr.estimated_loss), 0) AS total_loss
    FROM crop_reports cr
    LEFT JOIN farmer f
        ON f.FarmerID = cr.FarmerID
    LEFT JOIN damage_reports dr
        ON dr.FarmerID = cr.FarmerID
        AND LOWER(dr.dmgCropType) = LOWER(cr.crop_type)
    WHERE $where
    GROUP BY
        cr.id, cr.location, cr.crop_commodity, cr.crop_type, cr.crop_variety,
        cr.date_planted, cr.harvest_date, cr.estimate_harvest, cr.FarmerID,
        cr.reportDate, cr.created_by, cr.farm_gate_price, cr.crop_status,
        cr.number_of_trees, cr.latitude, cr.longitude, farmer_name, farmer_barangay
    $having
    ORDER BY cr.reportDate DESC, cr.id DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
    exit;
}

$features = [];
$locationDescriptors = [];

foreach ($rows as $row) {
    $locationInfo = scaims_crop_map_location_info(
        $row['location'] ?? '',
        $row['farmer_barangay'] ?? '',
        $LIPA_BARANGAYS,
        $LOCATION_ALIASES,
        $LGU_LOCATION_KEYS,
        $LGUS
    );

    if (!$locationInfo) {
        continue;
    }

    $coords = scaims_crop_map_resolve_coords($row, $locationInfo, $SCOPE_BOUNDS['coordinate']);
    if (!$coords) {
        continue;
    }

    [$lat, $lng, $coordinateSource] = $coords;
    $price = (float) $row['farm_gate_price'];

    if ($locationInfo['type'] === 'barangay') {
        $locationDescriptors[$locationInfo['lgu_key']][$locationInfo['key']] = [
            'type' => 'barangay',
            'key' => $locationInfo['key'],
            'value' => $locationInfo['value'],
            'label' => $locationInfo['label'],
            'center' => $locationInfo['center'],
            'bounds' => $locationInfo['bounds'],
        ];
    }

    $features[] = [
        'type' => 'Feature',
        'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
        'properties' => [
            'id' => (int) $row['id'],
            'location' => $row['location'],
            'location_key' => $locationInfo['key'],
            'location_label' => $locationInfo['label'],
            'location_filter_value' => $locationInfo['value'],
            'lgu_key' => $locationInfo['lgu_key'],
            'lgu_label' => $locationInfo['lgu_label'],
            'coordinate_source' => $coordinateSource,
            'crop_commodity' => $row['crop_commodity'],
            'crop_type' => $row['crop_type'],
            'crop_variety' => $row['crop_variety'],
            'date_planted' => $row['date_planted'],
            'harvest_date' => $row['harvest_date'],
            'estimate_harvest' => (float) $row['estimate_harvest'],
            'farmer_id' => $row['FarmerID'],
            'farmer_name' => trim($row['farmer_name']) ?: ('Farmer #' . $row['FarmerID']),
            'farmer_barangay' => $row['farmer_barangay'],
            'report_date' => $row['reportDate'],
            'farm_gate_price' => $price,
            'crop_status' => $row['crop_status'],
            'number_of_trees' => $row['number_of_trees'],
            'max_damage' => round((float) $row['max_damage'], 2),
            'has_damage' => (bool) $row['has_damage_report'],
            'total_loss' => (float) $row['total_loss'],
            'value_category' => scaims_crop_map_value_category($price),
        ],
    ];
}

$meta = [
    'total' => count($features),
    'cropTypes' => scaims_crop_map_feature_values($features, 'crop_type'),
    'locations' => scaims_crop_map_feature_values($features, 'location_label'),
    'commodities' => scaims_crop_map_feature_values($features, 'crop_commodity'),
    'cropStatuses' => scaims_crop_map_feature_values($features, 'crop_status'),
    'priceRange' => scaims_crop_map_numeric_range($features, 'farm_gate_price'),
    'yieldRange' => scaims_crop_map_numeric_range($features, 'estimate_harvest'),
    'locationHierarchy' => scaims_crop_map_location_hierarchy($LGUS, $locationDescriptors),
    'scope' => [
        'supportedAreaLabel' => 'Lipa City + Mataas na Kahoy, Batangas',
        'supportedLgus' => array_values($LGUS),
        'initialBounds' => $SCOPE_BOUNDS['initial'],
        'maxBounds' => $SCOPE_BOUNDS['max'],
        'coordinateBounds' => $SCOPE_BOUNDS['coordinate'],
        'center' => [13.9300, 121.1400],
        'desktopMinZoom' => 12,
        'mobileMinZoom' => 11,
        'maxZoom' => 19,
        'boundaryGeometryAvailable' => false,
        'barangayBoundaryGeometryAvailable' => false,
        'boundaryNote' => 'No validated LGU/barangay boundary geometry is present in the repository or database.',
    ],
];

echo json_encode([
    'type' => 'FeatureCollection',
    'features' => $features,
    'meta' => $meta,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
