<?php
/**
 * List all developers (admin)
 * GET - returns all developers
 */
ob_start();

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Method not allowed']);
    http_response_code(405);
    exit;
}

if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    http_response_code(500);
    exit;
}

$res = mysqli_query($conn, 'SELECT id, email, name, avatar, role, hourly_rate, currency, time_tracking_enabled, created_at, updated_at FROM developers ORDER BY created_at DESC');
$developers = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $developers[] = [
            'id' => (int) $row['id'],
            'email' => $row['email'] ?? '',
            'name' => $row['name'] ?? null,
            'avatar' => $row['avatar'] ?? null,
            'role' => $row['role'] ?? 'developer',
            'hourly_rate' => (float) ($row['hourly_rate'] ?? 0),
            'currency' => $row['currency'] ?? 'PHP',
            'time_tracking_enabled' => (int) ($row['time_tracking_enabled'] ?? 1) === 1,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}

echo json_encode(['developers' => $developers]);
