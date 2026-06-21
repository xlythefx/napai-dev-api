<?php
/**
 * Public endpoint hit by the trial wizard step 1.
 * POST { email } → { ok:true, expires_in } or { ok:false, error }.
 *
 * Mails a 6-digit code to prove the requester owns the inbox. The trial-key
 * mint endpoint (request_trial.php) will refuse unless the user submits the
 * code back. Codes live 15 minutes and at most one active per email at a
 * time — re-requesting invalidates earlier codes for that email.
 *
 * Re-uses the same per-IP throttle table as request_trial.php so a bot can't
 * burn a Hostinger daily quota by spamming this endpoint.
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/tablet.php';
require_once __DIR__ . '/../../lib/mailer.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tablet_json_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
if (!$conn) tablet_json_response(500, ['ok' => false, 'error' => 'db_error']);
tablet_guard_maintenance($conn);
tablet_guard_email_enabled($conn);

$body  = tablet_json_input();
$email = strtolower(trim((string) ($body['email'] ?? '')));
$ip    = tablet_client_ip();

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    tablet_json_response(400, ['ok' => false, 'error' => 'Invalid email']);
}

// Same per-IP cap as request_trial.php (1h window). We don't insert here — that
// happens once the user actually submits the code at request_trial.php — but
// we DO refuse if the IP is already over the 1h attempt budget.
$cap1h = (int) TABLET_TRIAL_MAX_PER_IP_1H;
$rl = @mysqli_prepare(
    $conn,
    "SELECT COUNT(*) c FROM trial_requests WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR"
);
if ($rl) {
    mysqli_stmt_bind_param($rl, 's', $ip);
    mysqli_stmt_execute($rl);
    $hits = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($rl))['c'] ?? 0);
    mysqli_stmt_close($rl);
    if ($hits >= $cap1h) {
        header('Retry-After: 3600');
        tablet_json_response(429, ['ok' => false, 'error' => 'Too many requests. Please try again in an hour.']);
    }
}

// Anti-resend abuse: cap to 3 OTPs per email in last 15 minutes.
$resend = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) c FROM trial_otp WHERE email = ? AND created_at > NOW() - INTERVAL 15 MINUTE'
);
mysqli_stmt_bind_param($resend, 's', $email);
mysqli_stmt_execute($resend);
$recent = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($resend))['c'] ?? 0);
mysqli_stmt_close($resend);
if ($recent >= 3) {
    tablet_json_response(429, ['ok' => false, 'error' => 'Too many code requests for this email. Check your spam folder.']);
}

// Invalidate any prior unconsumed code for this email so only the freshest one
// can be verified.
$invalidate = mysqli_prepare($conn, 'UPDATE trial_otp SET consumed = 1 WHERE email = ? AND consumed = 0');
mysqli_stmt_bind_param($invalidate, 's', $email);
mysqli_stmt_execute($invalidate);
mysqli_stmt_close($invalidate);

// Generate code + insert hashed row.
$code     = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$codeHash = hash('sha256', $code);
$ins = mysqli_prepare(
    $conn,
    'INSERT INTO trial_otp (email, code_hash, ip, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))'
);
mysqli_stmt_bind_param($ins, 'sss', $email, $codeHash, $ip);
if (!mysqli_stmt_execute($ins)) {
    tablet_json_response(500, ['ok' => false, 'error' => 'Could not issue code']);
}
mysqli_stmt_close($ins);

// Send the email. Plain HTML, single block, no QR yet.
$safeCode = htmlspecialchars($code, ENT_QUOTES);
$html =
    '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:480px;line-height:1.6;color:#1f2937;">' .
    '<h2 style="color:#16a34a;margin-bottom:8px;">Your QuanTab trial code</h2>' .
    '<p>Enter this 6-digit code in the trial wizard to receive your activation QR:</p>' .
    '<div style="margin:20px 0;padding:18px 24px;background:#f3f4f6;border-radius:12px;text-align:center;">' .
    "<span style=\"font-size:32px;font-weight:700;letter-spacing:8px;font-family:monospace;color:#111;\">$safeCode</span>" .
    '</div>' .
    '<p style="color:#6b7280;font-size:13px;">The code expires in 15 minutes. If you didn\'t request this, you can safely ignore the email.</p>' .
    '</div>';

$res = tablet_send_email($email, 'Your QuanTab trial code: ' . $code, $html);
tablet_log_email($conn, $email, 'QuanTab trial OTP', null, !empty($res['ok']), $res['error'] ?? null);

if (empty($res['ok'])) {
    tablet_json_response(502, ['ok' => false, 'error' => 'Could not send email: ' . ($res['error'] ?? 'unknown')]);
}

echo json_encode([
    'ok' => true,
    'expires_in_seconds' => 15 * 60,
]);
