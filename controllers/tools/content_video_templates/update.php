<?php
/**
 * Update a shared video template (any user — the library is global).
 * POST { id, name?, description?, template_json? }
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { vtpl_jsonResponse(['error' => 'Method not allowed'], 405); exit; }
if (!$conn) { vtpl_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id    = (int) ($input['id'] ?? 0);
if ($id <= 0) { vtpl_jsonResponse(['error' => 'id required'], 400); exit; }

$updates = [];
$params  = [];
$types   = '';

if (isset($input['name']) && trim($input['name']) !== '') {
    $updates[] = 'name = ?';
    $params[]  = trim($input['name']);
    $types    .= 's';
}
if (array_key_exists('description', $input)) {
    $updates[] = 'description = ?';
    $params[]  = $input['description'] !== null ? trim($input['description']) : null;
    $types    .= 's';
}
if (isset($input['template_json'])) {
    if (!vtpl_validTemplateJson($input['template_json'])) {
        vtpl_jsonResponse(['error' => 'Invalid template_json (code + propsSchema required)'], 400);
        exit;
    }
    $updates[] = 'template_json = ?';
    $params[]  = json_encode($input['template_json']);
    $types    .= 's';
}

if (empty($updates)) { vtpl_jsonResponse(['error' => 'Nothing to update'], 400); exit; }

$params[] = $id;
$types   .= 'i';
$stmt = mysqli_prepare($conn, 'UPDATE content_video_templates SET ' . implode(', ', $updates) . ' WHERE id = ?');
mysqli_stmt_bind_param($stmt, $types, ...$params);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok) { vtpl_jsonResponse(['error' => 'Update failed'], 500); exit; }

vtpl_jsonResponse(['success' => true]);
