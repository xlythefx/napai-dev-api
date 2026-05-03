<?php
/**
 * Developer signup - inserts into developers table
 * POST body: { "email": "...", "password": "...", "name": "..." }
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
$name = trim($input['name'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode(['error' => 'Email and password are required']);
    http_response_code(400);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['error' => 'Password must be at least 6 characters']);
    http_response_code(400);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conn, 'INSERT INTO developers (email, password_hash, name, role) VALUES (?, ?, ?, ?)');
$role = 'developer';
mysqli_stmt_bind_param($stmt, 'ssss', $email, $passwordHash, $name, $role);

if (mysqli_stmt_execute($stmt)) {
    $id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    echo json_encode([
        'success' => true,
        'developer' => [
            'id' => (int) $id,
            'email' => $email,
            'name' => $name,
            'role' => $role,
        ],
    ]);
} else {
    $err = mysqli_error($conn);
    mysqli_stmt_close($stmt);
    if (strpos($err, 'Duplicate') !== false || strpos($err, 'uq_email') !== false) {
        echo json_encode(['error' => 'Email already registered']);
        http_response_code(409);
    } else {
        echo json_encode(['error' => $err]);
        http_response_code(500);
    }
}
