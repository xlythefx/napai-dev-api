<?php
/**
 * List developers currently in an active (unended) tracking session.
 * GET/POST { requester_email } -> { active: [{ developer_id, session_id, started_at, task }] }
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

// One row per developer — keep the most recent open session if multiple exist.
$res = mysqli_query(
    $conn,
    "SELECT s.developer_id, MAX(s.id) AS session_id, MAX(s.started_at) AS started_at,
            SUBSTRING_INDEX(GROUP_CONCAT(s.task ORDER BY s.started_at DESC SEPARATOR '||'), '||', 1) AS task
     FROM time_sessions s
     WHERE s.ended_at IS NULL
     GROUP BY s.developer_id"
);

$active = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $active[] = [
            'developer_id' => (int) $row['developer_id'],
            'session_id'   => (int) $row['session_id'],
            'started_at'   => $row['started_at'],
            'task'         => $row['task'],
        ];
    }
}

echo json_encode(['active' => $active]);
