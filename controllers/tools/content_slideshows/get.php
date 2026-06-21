<?php
/**
 * Get one slideshow (owner only) with full settings + slides.
 * GET ?id=1&user_email=...
 * -> { slideshow: {id, name, settings, slides, created_at, updated_at} }
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

$id        = (int) ($_GET['id'] ?? 0);
$userEmail = trim($_GET['user_email'] ?? '');
if ($id <= 0 || $userEmail === '') {
    slideshow_jsonResponse(['error' => 'id and user_email required'], 400);
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, name, slideshow_json, created_at, updated_at
     FROM content_slideshows WHERE id = ? AND user_email = ?'
);
mysqli_stmt_bind_param($stmt, 'is', $id, $userEmail);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) { slideshow_jsonResponse(['error' => 'Slideshow not found'], 404); exit; }

$deck     = json_decode($row['slideshow_json'], true);
$settings = is_array($deck['settings'] ?? null) ? $deck['settings'] : null;
$slides   = is_array($deck['slides'] ?? null) ? $deck['slides'] : [];

slideshow_jsonResponse([
    'slideshow' => [
        'id'         => (int) $row['id'],
        'name'       => $row['name'],
        'settings'   => $settings,
        'slides'     => $slides,
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ],
]);
