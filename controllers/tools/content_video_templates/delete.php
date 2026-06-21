<?php
/**
 * Delete a shared video template (any user — the library is global).
 * POST/DELETE { id }
 * -> { success, deleted }
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
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
    vtpl_jsonResponse(['error' => 'Method not allowed'], 405);
    exit;
}
if (!$conn) { vtpl_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id    = (int) ($input['id'] ?? 0);
if ($id <= 0) { vtpl_jsonResponse(['error' => 'id required'], 400); exit; }

$stmt = mysqli_prepare($conn, 'DELETE FROM content_video_templates WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$deleted = mysqli_affected_rows($conn) > 0;
mysqli_stmt_close($stmt);

vtpl_jsonResponse(['success' => true, 'deleted' => $deleted]);
