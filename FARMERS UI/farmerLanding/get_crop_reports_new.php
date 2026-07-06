<?php
/**
 * get_crop_reports.php
 * GeoJSON endpoint for the Leaflet crop map.
 *
 * Tables used:
 *   crop_reports   â€” primary data
 *   farmer         â€” farmer name  (JOIN on FarmerID)
 *   damage_reports â€” damage info  (LEFT JOIN on FarmerID + crop_type)
 *
 * GET filters:
 *   crop_type       string   exact match
 *   location        string   exact match
 *   commodity       string   exact match (crop_commodity)
 *   crop_status     string   exact match (growth stage)
 *   price_min       float    farm_gate_price >=
 *   price_max       float    farm_gate_price <=
 *   yield_min       float    estimate_harvest >=
 *   yield_max       float    estimate_harvest <=
 *   damage_min      float    max percent_damage >= (0â€“100)
 *   has_damage      string   'yes' | 'no'  â€” has any damage report
 *   value_category  string   'high' | 'medium' | 'low'
 *
 * Value thresholds (farm_gate_price per kg, adjust as needed):
 *   High   > 80
 *   Medium  30â€“80
 *   Low    < 30
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

/* â”€â”€ DB credentials â”€â”€ */
require_once dirname(__DIR__, 2) . '/conn.php';

define('LOCATION_MODE', 'auto');

/* Value category thresholds */
define('PRICE_HIGH',   80);
define('PRICE_MEDIUM', 30);

/* Barangay coordinate lookup (used when location is text) */
$BARANGAY_COORDS = [
    'Adya'              => [13.9180, 121.1450],
    'Anilao'            => [13.9210, 121.1380],
    'Balintawak'        => [13.9290, 121.1520],
    'Banaybanay'        => [13.9350, 121.1480],
    'Bolbok'            => [13.9450, 121.1500],
    'Bulacnin'          => [13.9520, 121.1560],
    'Cumba'             => [13.9600, 121.1650],
    'Dagatan'           => [13.9500, 121.1580],
    'Duhatan'           => [13.9380, 121.1420],
    'Halang'            => [13.9350, 121.1550],
    'Inosloban'         => [13.9200, 121.1600],
    'Kayumanggi'        => [13.9270, 121.1490],
    'Latag'             => [13.9320, 121.1610],
    'Lodlod'            => [13.9480, 121.1700],
    'Lumbang'           => [13.9560, 121.1720],
    'Mabini'            => [13.9230, 121.1350],
    'Malagonlong'       => [13.9310, 121.1670],
    'Malitlit'          => [13.9390, 121.1590],
    'Marawoy'           => [13.9421, 121.1640],
    'Mataas na Lupa'    => [13.9460, 121.1460],
    'Munting Pulo'      => [13.9340, 121.1530],
    'Pagolingin Bata'   => [13.9270, 121.1560],
    'Pagolingin East'   => [13.9260, 121.1580],
    'Pagolingin West'   => [13.9250, 121.1540],
    'Pangao'            => [13.9540, 121.1480],
    'Pinagkawitan'      => [13.9380, 121.1700],
    'Pinagtongulan'     => [13.9420, 121.1720],
    'Plaridel'          => [13.9490, 121.1530],
    'Poblacion'         => [13.9411, 121.1631],
    'Pusil'             => [13.9580, 121.1690],
    'Quezon'            => [13.9510, 121.1610],
    'Rizal'             => [13.9530, 121.1590],
    'Sabang'            => [13.9370, 121.1440],
    'Sampaguita'        => [13.9300, 121.1510],
    'San Carlos'        => [13.9300, 121.1750],
    'San Jose'          => [13.9440, 121.1680],
    'San Lucas'         => [13.9280, 121.1400],
    'San Salvador'      => [13.9360, 121.1460],
    'Santo NiÃ±o'        => [13.9480, 121.1550],
    'Sico'              => [13.9220, 121.1320],
    'Talisay'           => [13.9570, 121.1630],
    'Tambo'             => [13.9550, 121.1800],
    'Tangob'            => [13.9250, 121.1470],
    'Tanguay'           => [13.9330, 121.1580],
    'Tibig'             => [13.9190, 121.1560],
    'Tipacan'           => [13.9240, 121.1430],
    'default'           => [13.9411, 121.1631],
];

/* â”€â”€ Connect â”€â”€ */
try {
    $pdo = scaims_db_pdo();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   BUILD QUERY + FILTERS
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
$conditions = ['1=1'];
$params     = [];

/* â”€â”€ Exact match filters â”€â”€ */
$exactFilters = [
    'crop_type'      => 'cr.crop_type',
    'location'       => 'cr.location',
    'commodity'      => 'cr.crop_commodity',
    'crop_status'    => 'cr.crop_status',
];
foreach ($exactFilters as $param => $col) {
    if (!empty($_GET[$param])) {
        $conditions[] = "$col = :$param";
        $params[":$param"] = $_GET[$param];
    }
}

/* â”€â”€ Price range â”€â”€ */
if (isset($_GET['price_min']) && $_GET['price_min'] !== '') {
    $conditions[] = 'cr.farm_gate_price >= :price_min';
    $params[':price_min'] = (float)$_GET['price_min'];
}
if (isset($_GET['price_max']) && $_GET['price_max'] !== '') {
    $conditions[] = 'cr.farm_gate_price <= :price_max';
    $params[':price_max'] = (float)$_GET['price_max'];
}

/* â”€â”€ Yield (estimate_harvest) range â”€â”€ */
if (isset($_GET['yield_min']) && $_GET['yield_min'] !== '') {
    $conditions[] = 'cr.estimate_harvest >= :yield_min';
    $params[':yield_min'] = (float)$_GET['yield_min'];
}
if (isset($_GET['yield_max']) && $_GET['yield_max'] !== '') {
    $conditions[] = 'cr.estimate_harvest <= :yield_max';
    $params[':yield_max'] = (float)$_GET['yield_max'];
}

/* â”€â”€ Value category (derived from farm_gate_price) â”€â”€ */
if (!empty($_GET['value_category'])) {
    switch ($_GET['value_category']) {
        case 'high':
            $conditions[] = 'cr.farm_gate_price > ' . PRICE_HIGH;   break;
        case 'medium':
            $conditions[] = 'cr.farm_gate_price BETWEEN ' . PRICE_MEDIUM . ' AND ' . PRICE_HIGH; break;
        case 'low':
            $conditions[] = 'cr.farm_gate_price < ' . PRICE_MEDIUM;  break;
    }
}

/* â”€â”€ Damage filters (applied via HAVING after JOIN) â”€â”€ */
$havingClauses = [];
if (isset($_GET['damage_min']) && $_GET['damage_min'] !== '') {
    $havingClauses[] = 'max_damage >= ' . (float)$_GET['damage_min'];
}
if (!empty($_GET['has_damage'])) {
    if ($_GET['has_damage'] === 'yes') $havingClauses[] = 'has_damage_report = 1';
    if ($_GET['has_damage'] === 'no')  $havingClauses[] = 'has_damage_report = 0';
}

$where  = implode(' AND ', $conditions);
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
        CONCAT(COALESCE(f.FirstName,''), ' ', COALESCE(f.Surname,'')) AS farmer_name,
        f.Barangay                        AS farmer_barangay,
        COALESCE(MAX(dr.percent_damage), 0)            AS max_damage,
        IF(MAX(dr.id) IS NOT NULL, 1, 0)               AS has_damage_report,
        COALESCE(SUM(dr.estimated_loss), 0)            AS total_loss
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
        cr.number_of_trees, farmer_name, farmer_barangay
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

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   META / FILTER OPTIONS
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
$meta = [];
$queries = [
    'cropTypes'   => "SELECT DISTINCT crop_type      FROM crop_reports WHERE crop_type IS NOT NULL      AND crop_type != ''      ORDER BY crop_type",
    'locations'   => "SELECT DISTINCT location        FROM crop_reports WHERE location IS NOT NULL       AND location != ''       ORDER BY location",
    'commodities' => "SELECT DISTINCT crop_commodity  FROM crop_reports WHERE crop_commodity IS NOT NULL AND crop_commodity != '' ORDER BY crop_commodity",
    'cropStatuses'=> "SELECT DISTINCT crop_status     FROM crop_reports WHERE crop_status IS NOT NULL    AND crop_status != ''    ORDER BY crop_status",
];
foreach ($queries as $key => $q) {
    try { $meta[$key] = $pdo->query($q)->fetchAll(PDO::FETCH_COLUMN); }
    catch (PDOException $e) { $meta[$key] = []; }
}

/* Price range */
try {
    $r = $pdo->query("SELECT MIN(farm_gate_price) as mn, MAX(farm_gate_price) as mx FROM crop_reports")->fetch(PDO::FETCH_ASSOC);
    $meta['priceRange'] = ['min' => (float)$r['mn'], 'max' => (float)$r['mx']];
} catch (PDOException $e) { $meta['priceRange'] = ['min' => 0, 'max' => 500]; }

/* Yield range */
try {
    $r = $pdo->query("SELECT MIN(estimate_harvest) as mn, MAX(estimate_harvest) as mx FROM crop_reports")->fetch(PDO::FETCH_ASSOC);
    $meta['yieldRange'] = ['min' => (float)$r['mn'], 'max' => (float)$r['mx']];
} catch (PDOException $e) { $meta['yieldRange'] = ['min' => 0, 'max' => 10000]; }

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   COORD RESOLVER
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
function resolveCoords(string $location, array $lookup): ?array {
    /* Try lat,lng */
    if (LOCATION_MODE === 'latlng' || LOCATION_MODE === 'auto') {
        $parts = array_map('trim', explode(',', $location));
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]))
            return [(float)$parts[0], (float)$parts[1]];
        if (LOCATION_MODE === 'latlng') return null;
    }
    /* Barangay lookup */
    $key = trim($location);
    $base = $lookup[$key] ?? $lookup['default'];
    $j = 0.003;
    return [$base[0] + (mt_rand(-100,100)/100)*$j, $base[1] + (mt_rand(-100,100)/100)*$j];
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   BUILD GeoJSON
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
function valueCategory(float $price): string {
    if ($price > PRICE_HIGH)   return 'high';
    if ($price >= PRICE_MEDIUM) return 'medium';
    return 'low';
}

$features = [];
foreach ($rows as $row) {
    $loc = $row['location'] ?? '';
    if (!$loc) $loc = $row['farmer_barangay'] ?? '';

    $coords = resolveCoords($loc, $BARANGAY_COORDS);
    if (!$coords) continue;

    [$lat, $lng] = $coords;
    $price = (float)$row['farm_gate_price'];

    $features[] = [
        'type'     => 'Feature',
        'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
        'properties' => [
            'id'               => (int)$row['id'],
            'location'         => $row['location'],
            'crop_commodity'   => $row['crop_commodity'],
            'crop_type'        => $row['crop_type'],
            'crop_variety'     => $row['crop_variety'],
            'date_planted'     => $row['date_planted'],
            'harvest_date'     => $row['harvest_date'],
            'estimate_harvest' => (float)$row['estimate_harvest'],
            'farmer_id'        => $row['FarmerID'],
            'farmer_name'      => trim($row['farmer_name']) ?: ('Farmer #' . $row['FarmerID']),
            'farmer_barangay'  => $row['farmer_barangay'],
            'report_date'      => $row['reportDate'],
            'farm_gate_price'  => $price,
            'crop_status'      => $row['crop_status'],
            'number_of_trees'  => $row['number_of_trees'],
            'max_damage'       => round((float)$row['max_damage'], 2),
            'has_damage'       => (bool)$row['has_damage_report'],
            'total_loss'       => (float)$row['total_loss'],
            'value_category'   => valueCategory($price),
        ]
    ];
}

echo json_encode([
    'type'     => 'FeatureCollection',
    'features' => $features,
    'meta'     => array_merge($meta, ['total' => count($features)]),
], JSON_PRETTY_PRINT);
