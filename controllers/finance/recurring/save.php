<?php
/**
 * Create or update a recurring monthly income/expense item.
 * Create: POST { requester_email, type, label, amount, category?, currency?, fx_to_base?,
 *                 day_of_month?, active?, notes? }
 * Update: POST { requester_email, id, ...fields }
 * -> { success, item }
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

// day_of_month: optional 1-31, else null
$normalizeDay = function ($v) {
    if ($v === null || $v === '') return null;
    $d = (int) $v;
    return ($d >= 1 && $d <= 31) ? $d : null;
};

if ($id > 0) {
    // ---- Update existing ----
    $stmt = mysqli_prepare($conn, 'SELECT * FROM finance_recurring WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) { http_response_code(404); echo json_encode(['error' => 'Item not found']); exit; }

    $type     = in_array($input['type'] ?? '', ['income','expense'], true) ? $input['type'] : $row['type'];
    $label    = isset($input['label'])      ? trim($input['label'])                          : $row['label'];
    $category = isset($input['category'])   ? trim($input['category'])                       : $row['category'];
    $amount   = isset($input['amount'])     ? (float) $input['amount']                       : (float) $row['amount'];
    $currency = isset($input['currency'])   ? strtoupper(substr(trim($input['currency']),0,8)) : $row['currency'];
    $fx       = isset($input['fx_to_base']) ? (float) $input['fx_to_base']                   : (float) $row['fx_to_base'];
    $day      = array_key_exists('day_of_month', $input) ? $normalizeDay($input['day_of_month']) : ($row['day_of_month'] !== null ? (int) $row['day_of_month'] : null);
    $active   = isset($input['active'])     ? (int) (bool) $input['active']                  : (int) $row['active'];
    $notes    = array_key_exists('notes', $input) ? (trim((string)($input['notes'] ?? '')) ?: null) : $row['notes'];

    if ($label === '' || $amount < 0) { http_response_code(400); echo json_encode(['error' => 'label and non-negative amount required']); exit; }
    if ($fx <= 0) $fx = 1;

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE finance_recurring
         SET type=?, label=?, category=?, amount=?, currency=?, fx_to_base=?, day_of_month=?, active=?, notes=?
         WHERE id=?'
    );
    mysqli_stmt_bind_param($stmt, 'sssdsdiisi', $type, $label, $category, $amount, $currency, $fx, $day, $active, $notes, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Update failed: ' . mysqli_error($conn)]); exit; }
    $newId = $id;
} else {
    // ---- Create new ----
    $type     = $input['type'] ?? '';
    $label    = trim($input['label'] ?? '');
    $category = trim($input['category'] ?? 'subscription') ?: 'subscription';
    $amount   = isset($input['amount']) ? (float) $input['amount'] : -1;
    $currency = strtoupper(substr(trim($input['currency'] ?? 'PHP'), 0, 8));
    $fx       = isset($input['fx_to_base']) ? (float) $input['fx_to_base'] : 1.0;
    $day      = $normalizeDay($input['day_of_month'] ?? null);
    $active   = isset($input['active']) ? (int) (bool) $input['active'] : 1;
    $notes    = trim($input['notes'] ?? '') ?: null;

    if (!in_array($type, ['income','expense'], true) || $label === '' || $amount < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'type (income/expense), label and non-negative amount required']);
        exit;
    }
    if ($fx <= 0) $fx = 1;

    $adminId = (int) $admin['id'];
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO finance_recurring
            (type, label, category, amount, currency, fx_to_base, day_of_month, active, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    mysqli_stmt_bind_param($stmt, 'sssdsdiisi', $type, $label, $category, $amount, $currency, $fx, $day, $active, $notes, $adminId);
    $ok    = mysqli_stmt_execute($stmt);
    $newId = (int) mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Insert failed: ' . mysqli_error($conn)]); exit; }
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM finance_recurring WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $newId);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$row['id']           = (int)   $row['id'];
$row['amount']       = (float) $row['amount'];
$row['fx_to_base']   = (float) $row['fx_to_base'];
$row['day_of_month'] = $row['day_of_month'] !== null ? (int) $row['day_of_month'] : null;
$row['active']       = (int)   $row['active'];

echo json_encode(['success' => true, 'item' => $row]);
