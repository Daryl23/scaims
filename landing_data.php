<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conn.php';

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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
    'registeredFarmers' => 0,
    'hectaresMonitored' => 0.0,
    'cropTypesTracked' => 0,
    'totalParcels' => 0,
    'cropReports' => 0,
    'damageReports' => 0,
    'suggestionsReceived' => 0,
    'publishedPosts' => 0,
];

$statsQuery = $conn->query("SELECT
    (SELECT COUNT(*) FROM farmer) AS registered_farmers,
    (SELECT COALESCE(SUM(total_area), 0) FROM parcel_details) AS hectares_monitored,
    (SELECT COUNT(*) FROM (
        SELECT DISTINCT CONVERT(crop_type USING utf8mb4) COLLATE utf8mb4_general_ci AS crop_type
        FROM crop_details
        WHERE crop_type IS NOT NULL AND crop_type <> ''
        UNION
        SELECT DISTINCT CONVERT(crop_type USING utf8mb4) COLLATE utf8mb4_general_ci AS crop_type
        FROM crop_reports
        WHERE crop_type IS NOT NULL AND crop_type <> ''
    ) tracked_crops) AS crop_types_tracked,
    (SELECT COUNT(*) FROM parcel_details) AS total_parcels,
    (SELECT COUNT(*) FROM crop_reports) AS crop_reports,
    (SELECT COUNT(*) FROM damage_reports) AS damage_reports,
    (SELECT COUNT(*) FROM suggestions) AS suggestions_received,
    (SELECT COUNT(*) FROM posts) AS published_posts");

if ($statsQuery) {
    $row = $statsQuery->fetch_assoc();
    $stats = [
        'registeredFarmers' => (int) $row['registered_farmers'],
        'hectaresMonitored' => round((float) $row['hectares_monitored'], 2),
        'cropTypesTracked' => (int) $row['crop_types_tracked'],
        'totalParcels' => (int) $row['total_parcels'],
        'cropReports' => (int) $row['crop_reports'],
        'damageReports' => (int) $row['damage_reports'],
        'suggestionsReceived' => (int) $row['suggestions_received'],
        'publishedPosts' => (int) $row['published_posts'],
    ];
}

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

