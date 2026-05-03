<?php
/**
 * Update a project. Admin-only.
 * PATCH { requester_email, id, name?, description?, color?, active? } -> { success }
 */

require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../lib/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'PATCH' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}
if (!$conn) { http_response_code(500); echo json_encode(['error' => 'DB error']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
require_admin($conn, $input['requester_email'] ?? '');

$id = (int) ($input['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }

$sets = []; $types = ''; $params = [];

if (isset($input['name'])) {
    $n = trim($input['name']);
    if ($n === '') { http_response_code(400); echo json_encode(['error' => 'name cannot be empty']); exit; }
    $sets[] = 'name = ?'; $types .= 's'; $params[] = $n;
}
if (isset($input['description'])) { $sets[] = 'description = ?'; $types .= 's'; $params[] = trim($input['description']) ?: null; }
if (isset($input['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $input['color'])) {
    $sets[] = 'color = ?'; $types .= 's'; $params[] = $input['color'];
}
if (isset($input['active'])) { $sets[] = 'active = ?'; $types .= 'i'; $params[] = $input['active'] ? 1 : 0; }

if (!$sets) { http_response_code(400); echo json_encode(['error' => 'Nothing to update']); exit; }

$params[] = $id; $types .= 'i';
$stmt = mysqli_prepare($conn, 'UPDATE projects SET ' . implode(', ', $sets) . ' WHERE id = ?');
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
echo json_encode(['success' => true]);
