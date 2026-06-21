<?php
/**
 * Update a slideshow (owner only). Autosave target.
 * POST { user_email, id, name?, slideshow_json? }
 * -> { success }
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

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$userEmail = trim($input['user_email'] ?? '');
$id        = (int) ($input['id'] ?? 0);

if ($userEmail === '' || $id <= 0) {
    slideshow_jsonResponse(['error' => 'user_email and id required'], 400);
    exit;
}

// Verify ownership.
$stmt = mysqli_prepare($conn, 'SELECT id FROM content_slideshows WHERE id = ? AND user_email = ?');
mysqli_stmt_bind_param($stmt, 'is', $id, $userEmail);
mysqli_stmt_execute($stmt);
$owns = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$owns) { slideshow_jsonResponse(['error' => 'Slideshow not found'], 404); exit; }

$updates = [];
$params  = [];
$types   = '';

if (isset($input['name']) && trim($input['name']) !== '') {
    $updates[] = 'name = ?';
    $params[]  = trim($input['name']);
    $types    .= 's';
}
if (isset($input['slideshow_json']) && is_array($input['slideshow_json'])) {
    $updates[] = 'slideshow_json = ?';
    $params[]  = json_encode($input['slideshow_json']);
    $types    .= 's';
}

if (empty($updates)) { slideshow_jsonResponse(['error' => 'Nothing to update'], 400); exit; }

$params[] = $id;
$types   .= 'i';
$stmt = mysqli_prepare($conn, 'UPDATE content_slideshows SET ' . implode(', ', $updates) . ' WHERE id = ?');
mysqli_stmt_bind_param($stmt, $types, ...$params);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok) { slideshow_jsonResponse(['error' => 'Update failed'], 500); exit; }

slideshow_jsonResponse(['success' => true]);
