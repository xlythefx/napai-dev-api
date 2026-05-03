<?php
/**
 * Developer login - verify credentials against developers table
 * POST body: { "email": "...", "password": "..." }
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    http_response_code(405);
    exit;
}

if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    http_response_code(500);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    echo json_encode(['error' => 'Email and password are required']);
    http_response_code(400);
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, email, name, avatar, password_hash, role, time_tracking_enabled FROM developers WHERE email = ?');
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row || !password_verify($password, $row['password_hash'] ?? '')) {
    echo json_encode(['error' => 'Invalid email or password']);
    http_response_code(401);
    exit;
}

// Bootstrap: if no admin exists, make this user admin
$adminCheck = mysqli_query($conn, "SELECT id FROM developers WHERE role = 'admin' LIMIT 1");
if (mysqli_num_rows($adminCheck) === 0) {
    mysqli_query($conn, "UPDATE developers SET role = 'admin' WHERE id = " . (int) $row['id']);
    $row['role'] = 'admin';
}

echo json_encode([
    'success' => true,
    'developer' => [
        'id' => (int) $row['id'],
        'email' => $row['email'],
        'name' => $row['name'],
        'avatar' => $row['avatar'],
        'role' => $row['role'] ?? 'developer',
        'time_tracking_enabled' => (int) ($row['time_tracking_enabled'] ?? 1) === 1,
    ],
]);
