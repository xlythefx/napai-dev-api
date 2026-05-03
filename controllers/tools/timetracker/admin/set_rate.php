<?php
/**
 * Set hourly rate (and currency) for a developer. Admin-only.
 * POST { requester_email, developer_id, hourly_rate, currency? } -> { success }
 */

require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../lib/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
require_admin($conn, $input['requester_email'] ?? '');

$developerId = (int) ($input['developer_id'] ?? 0);
$rate = isset($input['hourly_rate']) ? (float) $input['hourly_rate'] : -1;
$currency = isset($input['currency']) && trim($input['currency']) !== ''
    ? strtoupper(substr(trim($input['currency']), 0, 8)) : null;

if ($developerId <= 0 || $rate < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'developer_id and non-negative hourly_rate required']);
    exit;
}

if ($currency) {
    $stmt = mysqli_prepare(
        $conn,
        'UPDATE developers SET hourly_rate = ?, currency = ? WHERE id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'dsi', $rate, $currency, $developerId);
} else {
    $stmt = mysqli_prepare(
        $conn,
        'UPDATE developers SET hourly_rate = ? WHERE id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'di', $rate, $developerId);
}
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed']);
    exit;
}
echo json_encode(['success' => true]);
