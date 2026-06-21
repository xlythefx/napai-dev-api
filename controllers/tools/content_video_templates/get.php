<?php
/**
 * Get one video template.
 * GET ?id=1
 * -> { template: {id, name, description, user_email, template_json, created_at, updated_at} }
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
if (!$conn) { vtpl_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { vtpl_jsonResponse(['error' => 'id required'], 400); exit; }

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, user_email, name, description, template_json, created_at, updated_at
     FROM content_video_templates WHERE id = ?'
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) { vtpl_jsonResponse(['error' => 'Template not found'], 404); exit; }

$row['id']            = (int) $row['id'];
$row['template_json'] = json_decode($row['template_json'], true);

vtpl_jsonResponse(['template' => $row]);
