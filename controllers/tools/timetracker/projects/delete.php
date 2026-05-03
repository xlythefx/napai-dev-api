<?php
/**
 * Soft-delete (deactivate) a project. Admin-only.
 * POST { requester_email, id } -> { success }
 */

require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../lib/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}
if (!$conn) { http_response_code(500); echo json_encode(['error' => 'DB error']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
require_admin($conn, $input['requester_email'] ?? '');

$id = (int) ($input['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }

$stmt = mysqli_prepare($conn, 'UPDATE projects SET active = 0 WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
echo json_encode(['success' => true]);
