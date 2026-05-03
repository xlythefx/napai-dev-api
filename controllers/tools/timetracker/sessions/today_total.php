<?php
/**
 * Total active/idle seconds for the developer's sessions started today (Asia/Manila).
 * GET ?developer_email=...
 *  -> { success, active_seconds, idle_seconds, timezone, day_start }
 */

require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../lib/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$email = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (json_decode(file_get_contents('php://input'), true)['developer_email'] ?? '')
    : ($_GET['developer_email'] ?? '');

$dev = require_developer($conn, $email);

$tz = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $tz);
$dayStart = (clone $now)->setTime(0, 0, 0);
$dayStartStr = $dayStart->format('Y-m-d H:i:s');

// Only count COMPLETED sessions here. The currently-running session's
// active/idle seconds are tracked locally by the desktop client and added
// to this total on the client side — counting them in both places leads
// to double-counting as heartbeats arrive.
$stmt = mysqli_prepare(
    $conn,
    'SELECT COALESCE(SUM(active_seconds), 0) AS active_total,
            COALESCE(SUM(idle_seconds),   0) AS idle_total
       FROM time_sessions
      WHERE developer_id = ?
        AND started_at >= ?
        AND ended_at IS NOT NULL'
);
$devId = (int) $dev['id'];
mysqli_stmt_bind_param($stmt, 'is', $devId, $dayStartStr);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'active_seconds' => (int) ($row['active_total'] ?? 0),
    'idle_seconds'   => (int) ($row['idle_total']   ?? 0),
    'timezone'       => 'Asia/Manila',
    'timezone_label' => 'PHT',
    'day_start'      => $dayStartStr,
]);
