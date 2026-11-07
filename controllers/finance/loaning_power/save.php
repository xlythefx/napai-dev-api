<?php
/**
 * Create or update a finance loaning-power source (borrowing capacity).
 * Create: POST { requester_email, name, limit_amount, used_amount?, icon?,
 *                 currency?, fx_to_base?, color?, notes?, sort_order? }
 * Update: POST { requester_email, id, ...fields }
 * -> { success, loan }
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
    $stmt = mysqli_prepare($conn, 'SELECT * FROM finance_loaning_power WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) { http_response_code(404); echo json_encode(['error' => 'Loaning source not found']); exit; }

    $name     = isset($input['name'])         ? trim((string) $input['name'])                       : $row['name'];
    $icon     = isset($input['icon'])         ? substr(trim((string) $input['icon']), 0, 32)        : $row['icon'];
    $limit    = isset($input['limit_amount']) ? (float) $input['limit_amount']                       : (float) $row['limit_amount'];
    $used     = isset($input['used_amount'])  ? (float) $input['used_amount']                        : (float) $row['used_amount'];
    $currency = isset($input['currency'])     ? strtoupper(substr(trim((string) $input['currency']),0,8)) : $row['currency'];
    $fx       = isset($input['fx_to_base'])   ? (float) $input['fx_to_base']                         : (float) $row['fx_to_base'];
    $color    = array_key_exists('color', $input) ? (trim((string)($input['color'] ?? '')) ?: null) : $row['color'];
    $notes    = array_key_exists('notes', $input) ? (trim((string)($input['notes'] ?? '')) ?: null) : $row['notes'];
    $sortOrder= isset($input['sort_order'])   ? (int) $input['sort_order']                           : (int) $row['sort_order'];

    if ($name === '' || $limit <= 0) { http_response_code(400); echo json_encode(['error' => 'name and a credit limit > 0 are required']); exit; }
    if ($icon === '') $icon = 'credit-card';
    if ($fx <= 0) $fx = 1;
    if ($used < 0) $used = 0;

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE finance_loaning_power
         SET name=?, icon=?, limit_amount=?, used_amount=?, currency=?, fx_to_base=?, color=?, notes=?, sort_order=?
         WHERE id=?'
    );
    mysqli_stmt_bind_param($stmt, 'ssddsdssii', $name, $icon, $limit, $used, $currency, $fx, $color, $notes, $sortOrder, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Update failed: ' . mysqli_error($conn)]); exit; }
    $newId = $id;
} else {
    // ---- Create new ----
    $name     = trim((string) ($input['name'] ?? ''));
    $icon     = substr(trim((string) ($input['icon'] ?? 'credit-card')), 0, 32) ?: 'credit-card';
    $limit    = isset($input['limit_amount']) ? (float) $input['limit_amount'] : 0.0;
    $used     = isset($input['used_amount'])  ? (float) $input['used_amount']  : 0.0;
    $currency = strtoupper(substr(trim((string) ($input['currency'] ?? 'PHP')), 0, 8));
    $fx       = isset($input['fx_to_base']) ? (float) $input['fx_to_base'] : 1.0;
    $color    = trim((string) ($input['color'] ?? '')) ?: null;
    $notes    = trim((string) ($input['notes'] ?? '')) ?: null;
    $sortOrder= (int) ($input['sort_order'] ?? 0);

    if ($name === '' || $limit <= 0) { http_response_code(400); echo json_encode(['error' => 'name and a credit limit > 0 are required']); exit; }
    if ($fx <= 0) $fx = 1;
    if ($used < 0) $used = 0;

    $adminId = (int) $admin['id'];
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO finance_loaning_power
            (name, icon, limit_amount, used_amount, currency, fx_to_base, color, notes, sort_order, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    mysqli_stmt_bind_param($stmt, 'ssddsdssii', $name, $icon, $limit, $used, $currency, $fx, $color, $notes, $sortOrder, $adminId);
    $ok    = mysqli_stmt_execute($stmt);
    $newId = (int) mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Insert failed: ' . mysqli_error($conn)]); exit; }
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM finance_loaning_power WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $newId);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$row['id']           = (int)   $row['id'];
$row['limit_amount'] = (float) $row['limit_amount'];
$row['used_amount']  = (float) $row['used_amount'];
$row['fx_to_base']   = (float) $row['fx_to_base'];
$row['sort_order']   = (int)   $row['sort_order'];

echo json_encode(['success' => true, 'loan' => $row]);
