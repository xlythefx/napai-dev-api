<?php
/**
 * Delete a finance transaction.
 * POST { requester_email, id } -> { success }
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
if (!$conn) { http_response_code(500); echo json_encode(['error' => 'DB fail']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
require_admin($conn, $input['requester_email'] ?? '');

$id = (int) ($input['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }

$stmt = mysqli_prepare($conn, 'SELECT id FROM finance_transactions WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$exists = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$exists) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }

$stmt = mysqli_prepare($conn, 'DELETE FROM finance_transactions WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Delete failed']); exit; }

echo json_encode(['success' => true]);
