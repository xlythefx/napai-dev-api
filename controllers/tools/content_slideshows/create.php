<?php
/**
 * Create a slideshow.
 * POST { user_email, name, slideshow_json: { settings, slides: [...] } }
 * -> { success, id }
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { slideshow_jsonResponse(['error' => 'Method not allowed'], 405); exit; }
if (!$conn) { slideshow_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$userEmail     = trim($input['user_email'] ?? '');
$name          = trim($input['name'] ?? '');
if ($name === '') $name = 'Untitled slideshow';
$slideshowJson = $input['slideshow_json'] ?? null;

if ($userEmail === '' || !is_array($slideshowJson)) {
    slideshow_jsonResponse(['error' => 'user_email and slideshow_json required'], 400);
    exit;
}

$jsonStr = json_encode($slideshowJson);

$stmt = mysqli_prepare($conn, 'INSERT INTO content_slideshows (user_email, name, slideshow_json) VALUES (?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'sss', $userEmail, $name, $jsonStr);
$ok = mysqli_stmt_execute($stmt);
$id = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if (!$ok) { slideshow_jsonResponse(['error' => 'Insert failed'], 500); exit; }

slideshow_jsonResponse(['success' => true, 'id' => $id]);
