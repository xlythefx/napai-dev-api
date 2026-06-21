<?php
/**
 * Create a shared video template.
 * POST { user_email, name, description?, template_json: { code, propsSchema, previewProps } }
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { vtpl_jsonResponse(['error' => 'Method not allowed'], 405); exit; }
if (!$conn) { vtpl_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$userEmail = trim($input['user_email'] ?? '');
$name      = trim($input['name'] ?? '');
if ($name === '') $name = 'Untitled template';
$desc      = isset($input['description']) ? trim($input['description']) : null;
$tj        = $input['template_json'] ?? null;

if ($userEmail === '' || !vtpl_validTemplateJson($tj)) {
    vtpl_jsonResponse(['error' => 'user_email and a valid template_json (code + propsSchema) required'], 400);
    exit;
}

$tjStr = json_encode($tj);

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO content_video_templates (user_email, name, description, template_json)
     VALUES (?, ?, ?, ?)'
);
mysqli_stmt_bind_param($stmt, 'ssss', $userEmail, $name, $desc, $tjStr);
$ok = mysqli_stmt_execute($stmt);
$id = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if (!$ok) { vtpl_jsonResponse(['error' => 'Insert failed'], 500); exit; }

vtpl_jsonResponse(['success' => true, 'id' => $id]);
