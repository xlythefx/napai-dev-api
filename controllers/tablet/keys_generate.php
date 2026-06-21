<?php
/**
 * POST { requester_email, count, note?, type?, sell_type?, custom_price? }
 *   type         : 'plus' | 'pro' | 'max'    (default 'pro')
 *   sell_type    : 'bulk' | 'retail'         (default 'retail')
 *   count        : 1..500
 *   custom_price : decimal pesos, only honored when sell_type='bulk'.
 *                  NULL = fall back to default price for the tier.
 *
 * Response: {
 *   generated: [{ serial, type, sell_type, note, custom_price }],
 *   count, type, sell_type, custom_price, generated_at
 * }
 *
 * The full per-row metadata is returned so the React side can build a CSV
 * client-side without a follow-up GET.
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tablet_json_response(405, ['error' => 'Method not allowed']);
if (!$conn) tablet_json_response(500, ['error' => 'Database connection failed']);

$actorEmail = tablet_requester_email();
require_admin($conn, $actorEmail);

$body = tablet_json_input();
$count    = max(1, min(500, (int) ($body['count'] ?? 1)));
$note     = trim((string) ($body['note'] ?? ''));
$type     = strtolower(trim((string) ($body['type'] ?? 'pro')));
$sellType = strtolower(trim((string) ($body['sell_type'] ?? 'retail')));

if (!in_array($type, ['plus', 'pro', 'max'], true))     $type = 'pro';
if (!in_array($sellType, ['bulk', 'retail'], true))     $sellType = 'retail';

// Custom price: only meaningful for bulk batches with 2+ keys. Retail always
// uses the tier's default price (no per-key discounts).
$customPrice = null;
if ($sellType === 'bulk' && $count >= 2 && isset($body['custom_price']) && $body['custom_price'] !== '' && $body['custom_price'] !== null) {
    $cp = (float) $body['custom_price'];
    if ($cp > 0 && $cp < 100000) $customPrice = round($cp, 2);
}

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO license_keys (serial, note, type, sell_type, custom_price) VALUES (?, ?, ?, ?, ?)'
);
$generated = [];
for ($i = 0; $i < $count; $i++) {
    for ($try = 0; $try < 5; $try++) {
        $serial = tablet_generate_serial();
        mysqli_stmt_bind_param($stmt, 'ssssd', $serial, $note, $type, $sellType, $customPrice);
        if (@mysqli_stmt_execute($stmt)) {
            $generated[] = [
                'serial'       => $serial,
                'type'         => $type,
                'sell_type'    => $sellType,
                'note'         => $note,
                'custom_price' => $customPrice,
            ];
            break;
        }
    }
}
mysqli_stmt_close($stmt);

tablet_audit($conn, $actorEmail, 'keys.generate', null, [
    'count_requested' => $count,
    'count_generated' => count($generated),
    'type'            => $type,
    'sell_type'       => $sellType,
    'custom_price'    => $customPrice,
    'note'            => $note,
    'serials'         => array_column($generated, 'serial'),
]);

echo json_encode([
    'generated'    => $generated,
    'count'        => count($generated),
    'type'         => $type,
    'sell_type'    => $sellType,
    'custom_price' => $customPrice,
    'generated_at' => date('Y-m-d H:i:s'),
]);
