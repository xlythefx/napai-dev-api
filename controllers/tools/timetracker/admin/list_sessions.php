<?php
/**
 * List time sessions for admin panel.
 * GET ?requester_email=&developer_id=&from=YYYY-MM-DD&to=YYYY-MM-DD
 *  OR POST { requester_email, developer_id?, from?, to? }
 * -> { sessions: [...] }
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

$input = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : $_GET;

require_admin($conn, $input['requester_email'] ?? '');

$developerId = isset($input['developer_id']) && $input['developer_id'] !== ''
    ? (int) $input['developer_id'] : null;
$from = isset($input['from']) && $input['from'] !== '' ? $input['from'] : null;
$to = isset($input['to']) && $input['to'] !== '' ? $input['to'] : null;

$where = [];
$params = [];
$types = '';
if ($developerId) {
    $where[] = 's.developer_id = ?';
    $types .= 'i';
    $params[] = $developerId;
}
if ($from) {
    $where[] = 's.started_at >= ?';
    $types .= 's';
    $params[] = $from . ' 00:00:00';
}
if ($to) {
    $where[] = 's.started_at <= ?';
    $types .= 's';
    $params[] = $to . ' 23:59:59';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT s.id, s.developer_id, d.email AS developer_email, d.name AS developer_name,
           d.hourly_rate, d.currency,
           s.task, s.started_at, s.ended_at, s.active_seconds, s.idle_seconds,
           s.is_valid, s.invalidated_reason,
           (SELECT COUNT(*) FROM time_screenshots ts WHERE ts.session_id = s.id) AS screenshots_count
    FROM time_sessions s
    JOIN developers d ON d.id = s.developer_id
    {$whereSql}
    ORDER BY s.started_at DESC
    LIMIT 500
";

$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$sessions = [];
while ($row = mysqli_fetch_assoc($res)) {
    $rate = (float) $row['hourly_rate'];
    $isValid = (int) $row['is_valid'] === 1;
    $hours = ((int) $row['active_seconds']) / 3600.0;
    $earnings = $isValid ? round($hours * $rate, 2) : 0.0;
    $sessions[] = [
        'id' => (int) $row['id'],
        'developer_id' => (int) $row['developer_id'],
        'developer_email' => $row['developer_email'],
        'developer_name' => $row['developer_name'],
        'task' => $row['task'],
        'started_at' => $row['started_at'],
        'ended_at' => $row['ended_at'],
        'active_seconds' => (int) $row['active_seconds'],
        'idle_seconds' => (int) $row['idle_seconds'],
        'screenshots_count' => (int) $row['screenshots_count'],
        'hourly_rate' => $rate,
        'currency' => $row['currency'],
        'earnings' => $earnings,
        'is_valid' => $isValid,
        'invalidated_reason' => $row['invalidated_reason'],
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['sessions' => $sessions]);
