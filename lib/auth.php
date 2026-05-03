<?php
/**
 * Auth helper: require_admin($conn, $email)
 * Returns developer row if role='admin', else responds 403 and exits.
 */

function require_admin($conn, $email) {
    $email = trim((string) $email);
    if ($email === '') {
        http_response_code(401);
        echo json_encode(['error' => 'requester_email required']);
        exit;
    }
    $stmt = mysqli_prepare($conn, 'SELECT id, email, role FROM developers WHERE email = ?');
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$row || ($row['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    return $row;
}

function require_developer($conn, $email) {
    $email = trim((string) $email);
    if ($email === '') {
        http_response_code(401);
        echo json_encode(['error' => 'developer_email required']);
        exit;
    }
    $stmt = mysqli_prepare($conn, 'SELECT id, email, role, hourly_rate, currency FROM developers WHERE email = ?');
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Developer not found']);
        exit;
    }
    return $row;
}
