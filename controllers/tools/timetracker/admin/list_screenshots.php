<?php
/**
 * List screenshots for a session (or developer + date).
 * GET ?requester_email=&session_id=  (preferred)
 *  OR ?requester_email=&developer_id=&date=YYYY-MM-DD
 * -> { screenshots: [...] }
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

$sessionId = isset($input['session_id']) && $input['session_id'] !== ''
    ? (int) $input['session_id'] : null;
$developerId = isset($input['developer_id']) && $input['developer_id'] !== ''
    ? (int) $input['developer_id'] : null;
$date = isset($input['date']) && $input['date'] !== '' ? $input['date'] : null;
$from = isset($input['from']) && $input['from'] !== '' ? $input['from'] : null;
$to = isset($input['to']) && $input['to'] !== '' ? $input['to'] : null;

if (!$sessionId && !($developerId && $date) && !($from && $to)) {
    http_response_code(400);
    echo json_encode(['error' => 'session_id, (developer_id + date), or (from + to) required']);
    exit;
}

if ($sessionId) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT s.id, s.session_id, s.developer_id, s.taken_at, s.image_path, s.activity_level,
                d.email AS developer_email, d.name AS developer_name
         FROM time_screenshots s
         JOIN developers d ON d.id = s.developer_id
         WHERE s.session_id = ? ORDER BY s.taken_at ASC LIMIT 2000'
    );
    mysqli_stmt_bind_param($stmt, 'i', $sessionId);
} elseif ($developerId && $date) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT s.id, s.session_id, s.developer_id, s.taken_at, s.image_path, s.activity_level,
                d.email AS developer_email, d.name AS developer_name
         FROM time_screenshots s
         JOIN developers d ON d.id = s.developer_id
         WHERE s.developer_id = ? AND s.taken_at >= ? AND s.taken_at <= ?
         ORDER BY s.taken_at ASC LIMIT 2000'
    );
    $fromTs = $date . ' 00:00:00';
    $toTs = $date . ' 23:59:59';
    mysqli_stmt_bind_param($stmt, 'iss', $developerId, $fromTs, $toTs);
} else {
    // Date range, optional developer
    $sql = 'SELECT s.id, s.session_id, s.developer_id, s.taken_at, s.image_path, s.activity_level,
                   d.email AS developer_email, d.name AS developer_name
            FROM time_screenshots s
            JOIN developers d ON d.id = s.developer_id
            WHERE s.taken_at >= ? AND s.taken_at <= ?';
    $fromTs = $from . ' 00:00:00';
    $toTs = $to . ' 23:59:59';
    if ($developerId) {
        $sql .= ' AND s.developer_id = ?';
    }
    $sql .= ' ORDER BY s.taken_at DESC LIMIT 2000';
    $stmt = mysqli_prepare($conn, $sql);
    if ($developerId) {
        mysqli_stmt_bind_param($stmt, 'ssi', $fromTs, $toTs, $developerId);
    } else {
        mysqli_stmt_bind_param($stmt, 'ss', $fromTs, $toTs);
    }
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$screenshots = [];
while ($row = mysqli_fetch_assoc($res)) {
    $screenshots[] = [
        'id' => (int) $row['id'],
        'session_id' => (int) $row['session_id'],
        'developer_id' => (int) $row['developer_id'],
        'developer_email' => $row['developer_email'],
        'developer_name' => $row['developer_name'],
        'taken_at' => $row['taken_at'],
        'image_path' => $row['image_path'],
        'activity_level' => $row['activity_level'] !== null ? (int) $row['activity_level'] : null,
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['screenshots' => $screenshots]);
