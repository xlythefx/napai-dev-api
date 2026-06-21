<?php
/**
 * List content templates (global starters + the user's own).
 * GET ?user_email=...&aspect_ratio=1:1
 * -> { templates: [{id, name, description, aspect_ratio, is_global, created_at, updated_at}] }
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

$userEmail = trim($_GET['user_email'] ?? '');
$ratio     = trim($_GET['aspect_ratio'] ?? '');

$sql    = 'SELECT id, name, description, aspect_ratio, is_global, canvas_json, created_at, updated_at
           FROM content_templates WHERE (is_global = 1 OR user_email = ?)';
$params = [$userEmail];
$types  = 's';
if ($ratio !== '') {
    $sql     .= ' AND aspect_ratio = ?';
    $params[] = $ratio;
    $types   .= 's';
}
$sql .= ' ORDER BY is_global DESC, updated_at DESC';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$templates = [];
while ($row = mysqli_fetch_assoc($res)) {
    $row['id']        = (int) $row['id'];
    $row['is_global'] = (int) $row['is_global'];
    $row['preview']   = template_buildPreview(json_decode($row['canvas_json'], true));
    unset($row['canvas_json']); // keep the list light
    $templates[]      = $row;
}
mysqli_stmt_close($stmt);

template_jsonResponse(['templates' => $templates]);
