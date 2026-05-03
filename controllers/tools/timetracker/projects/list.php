<?php
/**
 * List active projects.
 * GET (no auth required — all logged-in developers can see projects)
 * -> { projects: [{id, name, description, color}] }
 */

require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) { http_response_code(500); echo json_encode(['error' => 'DB error']); exit; }

$res = mysqli_query($conn, "SELECT id, name, description, color FROM projects WHERE active = 1 ORDER BY name ASC");
$projects = [];
while ($row = mysqli_fetch_assoc($res)) {
    $projects[] = [
        'id'          => (int) $row['id'],
        'name'        => $row['name'],
        'description' => $row['description'],
        'color'       => $row['color'],
    ];
}
echo json_encode(['projects' => $projects]);
