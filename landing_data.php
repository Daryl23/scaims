<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conn.php';

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function scalarInt(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_row();
    return isset($row[0]) ? (int) $row[0] : 0;
}

function scalarFloat(mysqli $conn, string $sql, int $precision = 2): float
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0.0;
    }

    $row = $result->fetch_row();
    $value = isset($row[0]) ? (float) $row[0] : 0.0;
    return round($value, $precision);
}

function createExcerpt(string $content, int $length = 160): string
{
    $plainText = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES, 'UTF-8')));
    if ($plainText === '') {
        return 'No announcement details available yet.';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($plainText) <= $length) {
            return $plainText;
        }

        return rtrim(mb_substr($plainText, 0, $length - 1)) . '…';
    }

    if (strlen($plainText) <= $length) {
        return $plainText;
    }

    return rtrim(substr($plainText, 0, $length - 1)) . '...';
}

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    respond([
        'success' => false,
        'error' => 'Database connection failed.',
    ], 500);
}

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = rtrim($scriptDir, '/.');

$stats = [
    'registeredFarmers' => scalarInt($conn, "SELECT COUNT(*) FROM farmer"),
    'hectaresMonitored' => scalarFloat($conn, "SELECT COALESCE(SUM(total_area), 0) FROM parcel_details"),
    'cropTypesTracked' => scalarInt($conn, "
        SELECT COUNT(*) FROM (
            SELECT DISTINCT CONVERT(crop_type USING utf8mb4) COLLATE utf8mb4_general_ci AS crop_type
            FROM crop_details
            WHERE crop_type IS NOT NULL AND crop_type <> ''
            UNION
            SELECT DISTINCT CONVERT(crop_type USING utf8mb4) COLLATE utf8mb4_general_ci AS crop_type
            FROM crop_reports
            WHERE crop_type IS NOT NULL AND crop_type <> ''
        ) tracked_crops
    "),
    'totalParcels' => scalarInt($conn, "SELECT COUNT(*) FROM parcel_details"),
    'cropReports' => scalarInt($conn, "SELECT COUNT(*) FROM crop_reports"),
    'damageReports' => scalarInt($conn, "SELECT COUNT(*) FROM damage_reports"),
    'suggestionsReceived' => scalarInt($conn, "SELECT COUNT(*) FROM suggestions"),
    'publishedPosts' => scalarInt($conn, "SELECT COUNT(*) FROM posts"),
];

$latestPost = null;
$latestPostQuery = $conn->query("SELECT id, content, image_paths, created_at FROM posts ORDER BY created_at DESC LIMIT 1");

if ($latestPostQuery && $latestPostQuery->num_rows > 0) {
    $row = $latestPostQuery->fetch_assoc();
    $imageUrl = null;

    if (!empty($row['image_paths'])) {
        $decodedPaths = json_decode($row['image_paths'], true);
        if (is_array($decodedPaths) && !empty($decodedPaths[0])) {
            $imageUrl = ($basePath !== '' ? $basePath : '') . '/' . ltrim(str_replace('\\', '/', $decodedPaths[0]), '/');
        }
    }

    $latestPost = [
        'id' => (int) $row['id'],
        'content' => html_entity_decode($row['content'], ENT_QUOTES, 'UTF-8'),
        'excerpt' => createExcerpt($row['content']),
        'createdAt' => $row['created_at'],
        'formattedDate' => date('F j, Y', strtotime($row['created_at'])),
        'image' => $imageUrl,
    ];
}

$conn->close();

respond([
    'success' => true,
    'stats' => $stats,
    'latestPost' => $latestPost,
]);

