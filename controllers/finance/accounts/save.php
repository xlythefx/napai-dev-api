<?php
/**
 * Create or update a finance account.
 * Create: POST { requester_email, name, balance?, currency?, fx_to_base?, color?, sort_order? }
 * Update: POST { requester_email, id, ...fields }
 * -> { success, account }
 */

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../lib/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
if (!$conn) { http_response_code(500); echo json_encode(['error' => 'DB fail']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$admin = require_admin($conn, $input['requester_email'] ?? '');

$id = (int) ($input['id'] ?? 0);

if ($id > 0) {
    // ---- Update existing ----
    $stmt = mysqli_prepare($conn, 'SELECT * FROM finance_accounts WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) { http_response_code(404); echo json_encode(['error' => 'Account not found']); exit; }

    $name      = isset($input['name'])       ? trim($input['name'])                         : $row['name'];
    $balance   = isset($input['balance'])    ? (float) $input['balance']                    : (float) $row['balance'];
    $currency  = isset($input['currency'])   ? strtoupper(substr(trim($input['currency']),0,8)) : $row['currency'];
    $fx        = isset($input['fx_to_base']) ? (float) $input['fx_to_base']                 : (float) $row['fx_to_base'];
    $color     = array_key_exists('color', $input) ? (trim((string)($input['color'] ?? '')) ?: null) : $row['color'];
    $sortOrder = isset($input['sort_order']) ? (int) $input['sort_order']                   : (int) $row['sort_order'];

    if ($name === '') { http_response_code(400); echo json_encode(['error' => 'name required']); exit; }
    if ($fx <= 0) $fx = 1;

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE finance_accounts
         SET name=?, balance=?, currency=?, fx_to_base=?, color=?, sort_order=?
         WHERE id=?'
    );
    mysqli_stmt_bind_param($stmt, 'sdsdsii', $name, $balance, $currency, $fx, $color, $sortOrder, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Update failed: ' . mysqli_error($conn)]); exit; }
    $newId = $id;
} else {
    // ---- Create new ----
    $name      = trim($input['name'] ?? '');
    $balance   = isset($input['balance']) ? (float) $input['balance'] : 0.0;
    $currency  = strtoupper(substr(trim($input['currency'] ?? 'PHP'), 0, 8));
    $fx        = isset($input['fx_to_base']) ? (float) $input['fx_to_base'] : 1.0;
    $color     = trim($input['color'] ?? '') ?: null;
    $sortOrder = (int) ($input['sort_order'] ?? 0);

    if ($name === '') { http_response_code(400); echo json_encode(['error' => 'name required']); exit; }
    if ($fx <= 0) $fx = 1;

    $adminId = (int) $admin['id'];
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO finance_accounts (name, balance, currency, fx_to_base, color, sort_order, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    mysqli_stmt_bind_param($stmt, 'sdsdsii', $name, $balance, $currency, $fx, $color, $sortOrder, $adminId);
    $ok    = mysqli_stmt_execute($stmt);
    $newId = (int) mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Insert failed: ' . mysqli_error($conn)]); exit; }
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM finance_accounts WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $newId);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$row['id']         = (int)   $row['id'];
$row['balance']    = (float) $row['balance'];
$row['fx_to_base'] = (float) $row['fx_to_base'];
$row['sort_order'] = (int)   $row['sort_order'];

echo json_encode(['success' => true, 'account' => $row]);
