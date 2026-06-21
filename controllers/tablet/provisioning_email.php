<?php
/**
 * POST { requester_email, email, serial, png (data URL or base64) }
 * Sends the QR PNG to the buyer's email. Decoded server-side and attached.
 * Logs every send to email_log.
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';
require_once __DIR__ . '/../../lib/mailer.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tablet_json_response(405, ['error' => 'Method not allowed']);
if (!$conn) tablet_json_response(500, ['error' => 'Database connection failed']);

$actorEmail = tablet_requester_email();
require_admin($conn, $actorEmail);
tablet_guard_email_enabled($conn);

$body = tablet_json_input();
$email   = trim((string) ($body['email'] ?? ''));
$serial  = trim((string) ($body['serial'] ?? ''));
$pngData = (string) ($body['png'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) tablet_json_response(400, ['ok' => false, 'error' => 'Invalid email address']);
if ($serial === '')   tablet_json_response(400, ['ok' => false, 'error' => 'Serial required']);
if ($pngData === '')  tablet_json_response(400, ['ok' => false, 'error' => 'QR image missing']);

$pos = strpos($pngData, 'base64,');
$b64 = $pos !== false ? substr($pngData, $pos + 7) : $pngData;
$bytes = base64_decode($b64);
if (!$bytes) tablet_json_response(400, ['ok' => false, 'error' => 'Could not decode QR image']);

$subject = "QuanTab provisioning QR — $serial";
$safeSerial = htmlspecialchars($serial, ENT_QUOTES);
$qrCid = 'qr-' . bin2hex(random_bytes(6)) . '@quantab';
$html =
    '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:560px;line-height:1.6;color:#1f2937;">' .
    '<h2 style="color:#16a34a;margin-bottom:8px;">Your QuanTab is ready to provision</h2>' .
    '<p style="margin-top:0;">Your license key is:</p>' .
    '<p style="font-family:monospace;font-size:20px;font-weight:600;background:#f3f4f6;padding:14px 18px;border-radius:10px;text-align:center;letter-spacing:1px;">' . $safeSerial . '</p>' .

    // Large inline QR
    '<div style="text-align:center;margin:28px 0;">' .
    '<div style="display:inline-block;background:#ffffff;padding:20px;border:2px solid #16a34a;border-radius:16px;box-shadow:0 4px 14px rgba(22,163,74,0.12);">' .
    '<img src="cid:' . $qrCid . '" alt="QuanTab provisioning QR" width="320" height="320" style="display:block;width:320px;height:320px;image-rendering:pixelated;" />' .
    '</div>' .
    '<p style="color:#6b7280;font-size:12px;margin-top:10px;">Scan this with the tablet during Android setup ↑</p>' .
    '</div>' .

    '<h3 style="margin-top:24px;">What to do</h3>' .
    '<ol style="padding-left:20px;">' .
    '<li>Factory-reset your Android tablet (Settings → System → Reset).</li>' .
    '<li>On the very first <strong>Welcome</strong> screen, tap the screen <strong>6 times</strong> in the same spot. The QR scanner opens.</li>' .
    '<li>Scan the QR shown above.</li>' .
    '<li>Wait 2–5 minutes — Android downloads QuanTab and configures itself automatically.</li>' .
    '<li>QuanTab opens with your license key pre-filled. Tap Activate.</li>' .
    '</ol>' .
    '<p style="color:#6b7280;font-size:13px;">A high-resolution copy is also attached if you need to print it. If you reset the tablet later or want to move the license to a different tablet, contact us and we\'ll release the binding.</p>' .
    '</div>';

$res = tablet_send_email($email, $subject, $html, [
    'name'   => 'quantab-' . $serial . '.png',
    'mime'   => 'image/png',
    'bytes'  => $bytes,
    'inline' => true,
    'cid'    => $qrCid,
]);
tablet_log_email($conn, $email, $subject, $serial, !empty($res['ok']), $res['error'] ?? null);

// On success, mark the key as emailed and tag the buyer so the dashboard's
// ✉ indicator shows up next to retail keys (same column scheme used by
// bulk_email.php so the UI doesn't need a separate concept).
if (!empty($res['ok'])) {
    $up = mysqli_prepare(
        $conn,
        'UPDATE license_keys SET buyer_email = ?, bulk_emailed_at = NOW(), bulk_emailed_to = ? WHERE serial = ?'
    );
    mysqli_stmt_bind_param($up, 'sss', $email, $email, $serial);
    mysqli_stmt_execute($up);
    mysqli_stmt_close($up);
}

tablet_audit($conn, $actorEmail, 'keys.provisioning_email', $serial, [
    'recipient' => $email,
    'sent_ok'   => !empty($res['ok']),
]);

echo json_encode($res);
