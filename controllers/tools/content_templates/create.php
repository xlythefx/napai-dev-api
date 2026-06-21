<?php
/**
 * Create a user-owned template.
 * POST { user_email, name, canvas_json (object), aspect_ratio?, description? }
 * -> { success, template: {id, name, aspect_ratio, description, is_global} }
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { template_jsonResponse(['error' => 'Method not allowed'], 405); exit; }
if (!$conn) { template_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$userEmail = trim($input['user_email'] ?? '');
$name      = trim($input['name'] ?? '');
$ratio     = trim($input['aspect_ratio'] ?? '1:1');
$desc      = isset($input['description']) ? trim($input['description']) : null;
$canvas    = $input['canvas_json'] ?? null;

if ($userEmail === '' || $name === '' || !is_array($canvas)) {
    template_jsonResponse(['error' => 'user_email, name and canvas_json required'], 400);
    exit;
}

$canvasStr = json_encode($canvas);

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO content_templates (user_email, is_global, name, description, aspect_ratio, canvas_json)
     VALUES (?, 0, ?, ?, ?, ?)'
);
mysqli_stmt_bind_param($stmt, 'sssss', $userEmail, $name, $desc, $ratio, $canvasStr);
$ok = mysqli_stmt_execute($stmt);
$id = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if (!$ok) { template_jsonResponse(['error' => 'Insert failed'], 500); exit; }

template_jsonResponse([
    'success'  => true,
    'template' => [
        'id'           => $id,
        'name'         => $name,
        'aspect_ratio' => $ratio,
        'description'  => $desc,
        'is_global'    => 0,
    ],
]);
