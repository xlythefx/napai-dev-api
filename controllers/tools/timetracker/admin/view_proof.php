<?php
/**
 * Stream a payment proof file. Admin-only.
 * GET ?payment_id=<id>&requester_email=<admin_email>
 */

require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../lib/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
if (!$conn) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');
require_admin($conn, $_GET['requester_email'] ?? '');

$id = (int) ($_GET['payment_id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'payment_id required']);
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT proof_path FROM developer_payments WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);
if (!$row || empty($row['proof_path'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

$rel = ltrim($row['proof_path'], '/');
$abs = realpath(__DIR__ . '/../../../../' . $rel);
$base = realpath(__DIR__ . '/../../../../uploads/timetracker/payments');
if (!$abs || !$base || strpos($abs, $base) !== 0 || !is_file($abs)) {
    http_response_code(404);
    echo json_encode(['error' => 'File missing']);
    exit;
}

$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$mime = [
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
][$ext] ?? 'application/octet-stream';

header_remove('Content-Type');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Cache-Control: private, max-age=300');
readfile($abs);
