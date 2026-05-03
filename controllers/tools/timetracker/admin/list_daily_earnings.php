<?php
/**
 * List per-developer per-day earnings, with payment status.
 * GET ?requester_email=&developer_id=&from=YYYY-MM-DD&to=YYYY-MM-DD
 *  OR POST { requester_email, developer_id?, from?, to? }
 * -> { days: [...] }
 *
 * Each row: { developer_id, developer_email, developer_name, date,
 *             active_seconds, session_count, invalid_count, hourly_rate, currency,
 *             earnings, paid: bool, payment: {...}|null }
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
    SELECT s.developer_id,
           d.email AS developer_email,
           d.name  AS developer_name,
           d.hourly_rate,
           d.currency,
           DATE(s.started_at) AS day,
           SUM(CASE WHEN s.is_valid = 1 THEN s.active_seconds ELSE 0 END) AS active_seconds,
           COUNT(s.id) AS session_count,
           SUM(CASE WHEN s.is_valid = 0 THEN 1 ELSE 0 END) AS invalid_count,
           p.id AS payment_id,
           p.amount AS payment_amount,
           p.currency AS payment_currency,
           p.proof_path,
           p.note,
           p.paid_at
    FROM time_sessions s
    JOIN developers d ON d.id = s.developer_id
    LEFT JOIN developer_payments p
      ON p.developer_id = s.developer_id AND p.payment_date = DATE(s.started_at)
    {$whereSql}
    GROUP BY s.developer_id, day
    ORDER BY day DESC, developer_email ASC
    LIMIT 1000
";

$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$days = [];
while ($row = mysqli_fetch_assoc($res)) {
    $rate = (float) $row['hourly_rate'];
    $hours = ((int) $row['active_seconds']) / 3600.0;
    $earnings = round($hours * $rate, 2);
    $payment = null;
    if (!empty($row['payment_id'])) {
        $payment = [
            'id' => (int) $row['payment_id'],
            'amount' => (float) $row['payment_amount'],
            'currency' => $row['payment_currency'],
            'proof_path' => $row['proof_path'],
            'note' => $row['note'],
            'paid_at' => $row['paid_at'],
        ];
    }
    $days[] = [
        'developer_id' => (int) $row['developer_id'],
        'developer_email' => $row['developer_email'],
        'developer_name' => $row['developer_name'],
        'date' => $row['day'],
        'active_seconds' => (int) $row['active_seconds'],
        'session_count' => (int) $row['session_count'],
        'invalid_count' => (int) $row['invalid_count'],
        'hourly_rate' => $rate,
        'currency' => $row['currency'],
        'earnings' => $earnings,
        'paid' => $payment !== null,
        'payment' => $payment,
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['days' => $days]);
