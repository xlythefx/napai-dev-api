<?php
/**
 * Get a single template (global or owned) with full canvas JSON.
 * GET ?id=1&user_email=...
 * -> { template: {id, name, description, aspect_ratio, is_global, canvas_json, created_at, updated_at} }
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
if (!$conn) { template_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$id        = (int) ($_GET['id'] ?? 0);
$userEmail = trim($_GET['user_email'] ?? '');
if ($id <= 0) { template_jsonResponse(['error' => 'id required'], 400); exit; }

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, name, description, aspect_ratio, is_global, canvas_json, created_at, updated_at
     FROM content_templates WHERE id = ? AND (is_global = 1 OR user_email = ?)'
);
mysqli_stmt_bind_param($stmt, 'is', $id, $userEmail);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) { template_jsonResponse(['error' => 'Template not found'], 404); exit; }

$row['id']          = (int) $row['id'];
$row['is_global']   = (int) $row['is_global'];
$row['canvas_json'] = json_decode($row['canvas_json'], true);

template_jsonResponse(['template' => $row]);
