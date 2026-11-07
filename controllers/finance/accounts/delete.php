<?php
/**
 * Delete a finance (bank) account, recording a final audit-trail entry.
 * POST { requester_email, id, note? } -> { success }
 */

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/finance_account_log.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
if (!$conn) { http_response_code(500); echo json_encode(['error' => 'DB fail']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$admin = require_admin($conn, $input['requester_email'] ?? '');

$id     = (int) ($input['id'] ?? 0);
$reason = isset($input['note']) ? trim((string) $input['note']) : '';
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }

$stmt = mysqli_prepare($conn, 'SELECT * FROM finance_accounts WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$row) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }

// Record the deletion BEFORE removing the row (FK will null account_id afterward,
// but the account_name snapshot keeps the entry meaningful).
fin_account_log($conn, [
    'account_id'       => $id,
    'account_name'     => $row['name'],
    'action'           => 'delete',
    'changes'          => null,
    'balance_before'   => (float) $row['balance'],
    'balance_after'    => 0,
    'currency'         => $row['currency'],
    'note'             => $reason,
    'changed_by'       => (int) $admin['id'],
    'changed_by_email' => $admin['email'] ?? null,
]);

$stmt = mysqli_prepare($conn, 'DELETE FROM finance_accounts WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Delete failed']); exit; }

echo json_encode(['success' => true]);
