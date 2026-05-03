<?php
/**
 * Create a project. Admin-only.
 * POST { requester_email, name, description?, color? } -> { success, project }
 */

require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../lib/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
if (!$conn) { http_response_code(500); echo json_encode(['error' => 'DB error']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
require_admin($conn, $input['requester_email'] ?? '');

$name = trim($input['name'] ?? '');
if ($name === '') { http_response_code(400); echo json_encode(['error' => 'name required']); exit; }

$description = isset($input['description']) ? trim($input['description']) : null;
$color = isset($input['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $input['color'])
    ? $input['color'] : '#5b8def';

$stmt = mysqli_prepare($conn, 'INSERT INTO projects (name, description, color) VALUES (?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'sss', $name, $description, $color);
if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500); echo json_encode(['error' => mysqli_error($conn)]); exit;
}
$id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'project' => ['id' => $id, 'name' => $name, 'description' => $description, 'color' => $color]]);
