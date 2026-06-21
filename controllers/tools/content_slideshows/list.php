<?php
/**
 * List a user's slideshows (metadata + light preview, no full JSON / image data).
 * GET ?user_email=...
 * -> { slideshows: [{id, name, slideCount, durationMs, created_at, updated_at, preview}] }
 */
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/utils.php';

ob_end_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) { slideshow_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$userEmail = trim($_GET['user_email'] ?? '');
if ($userEmail === '') { slideshow_jsonResponse(['error' => 'user_email required'], 400); exit; }

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, name, slideshow_json, created_at, updated_at
     FROM content_slideshows WHERE user_email = ? ORDER BY updated_at DESC'
);
mysqli_stmt_bind_param($stmt, 's', $userEmail);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$slideshows = [];
while ($row = mysqli_fetch_assoc($res)) {
    $deck    = json_decode($row['slideshow_json'], true);
    $slides  = is_array($deck['slides'] ?? null) ? $deck['slides'] : [];
    $preview = slideshow_buildPreview($deck);
    $slideshows[] = [
        'id'         => (int) $row['id'],
        'name'       => $row['name'],
        'slideCount' => count($slides),
        'durationMs' => $preview['durationMs'] ?? 0,
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'preview'    => $preview,
    ];
}
mysqli_stmt_close($stmt);

slideshow_jsonResponse(['slideshows' => $slideshows]);
