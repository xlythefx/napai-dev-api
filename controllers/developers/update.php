<?php
/**
 * Update developer (admin)
 * PATCH body: { "id": 1, "name"?, "role"?, "email"?, "password"? }
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
$id = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid developer id']);
    exit;
}

$updates = [];
$types   = '';
$params  = [];

if (isset($input['name'])) {
    $updates[] = 'name = ?';
    $types .= 's';
    $params[] = trim($input['name']);
}

if (isset($input['role'])) {
    $role = trim($input['role']);
    if (!in_array($role, ['developer', 'admin'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid role']);
        exit;
    }
    $updates[] = 'role = ?';
    $types .= 's';
    $params[] = $role;
}

if (isset($input['email'])) {
    $email = trim($input['email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email']);
        exit;
    }
    // Check uniqueness (excluding self)
    $chk = mysqli_prepare($conn, 'SELECT id FROM developers WHERE email = ? AND id != ?');
    mysqli_stmt_bind_param($chk, 'si', $email, $id);
    mysqli_stmt_execute($chk);
    $chkRes = mysqli_stmt_get_result($chk);
    if (mysqli_fetch_assoc($chkRes)) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already in use by another developer']);
        exit;
    }
    mysqli_stmt_close($chk);
    $updates[] = 'email = ?';
    $types .= 's';
    $params[] = $email;
}

if (array_key_exists('time_tracking_enabled', $input)) {
    $val = $input['time_tracking_enabled'] ? 1 : 0;
    $updates[] = 'time_tracking_enabled = ?';
    $types .= 'i';
    $params[] = $val;
}

if (isset($input['password'])) {
    $password = $input['password'];
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $updates[] = 'password_hash = ?';
    $types .= 's';
    $params[] = $hash;
}

if (empty($updates)) {
    http_response_code(400);
    echo json_encode(['error' => 'No fields to update']);
    exit;
}

$params[] = $id;
$types   .= 'i';
$sql = 'UPDATE developers SET ' . implode(', ', $updates) . ' WHERE id = ?';
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
} else {
    mysqli_stmt_close($stmt);
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . mysqli_error($conn)]);
}
