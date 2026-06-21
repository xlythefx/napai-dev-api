<?php
/**
 * Public follow-up endpoint hit by the trial wizard step 3.
 * POST { email, serial, png (data URL or base64), name? }
 *
 * Anti-abuse: only emails if (email, serial) actually exists in license_keys
 * with kind='trial'. Prevents the endpoint from being used as an open mail relay.
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

$body = tablet_json_input();
$email   = strtolower(trim((string) ($body['email'] ?? '')));
$serial  = strtoupper(trim((string) ($body['serial'] ?? '')));
$name    = trim((string) ($body['name'] ?? ''));
$pngData = (string) ($body['png'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) tablet_json_response(400, ['ok' => false, 'error' => 'Invalid email']);
if ($serial === '')  tablet_json_response(400, ['ok' => false, 'error' => 'Serial required']);
if ($pngData === '') tablet_json_response(400, ['ok' => false, 'error' => 'QR image missing']);

// Verify the serial belongs to this buyer.
$stmt = mysqli_prepare($conn, 'SELECT id FROM license_keys WHERE serial = ? AND buyer_email = ? AND kind = "trial" LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ss', $serial, $email);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$row) tablet_json_response(403, ['ok' => false, 'error' => 'Serial does not match this email']);

$pos = strpos($pngData, 'base64,');
$b64 = $pos !== false ? substr($pngData, $pos + 7) : $pngData;
$bytes = base64_decode($b64);
if (!$bytes) tablet_json_response(400, ['ok' => false, 'error' => 'Could not decode QR image']);

$days = TABLET_TRIAL_DAYS;
$safeSerial = htmlspecialchars($serial, ENT_QUOTES);
$safeName   = htmlspecialchars($name !== '' ? $name : 'there', ENT_QUOTES);
$apkUrl     = htmlspecialchars(TABLET_APK_PUBLIC_URL, ENT_QUOTES);
$qrCid      = 'qr-' . bin2hex(random_bytes(6)) . '@quantab';

$subject = "Your $days-day QuanTab trial — key $serial";
$html =
    '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:560px;line-height:1.6;color:#1f2937;">' .
    "<h2 style=\"color:#16a34a;margin-bottom:8px;\">Hi $safeName, your QuanTab trial is ready</h2>" .
    "<p style=\"margin-top:0;\">You have <strong>$days days</strong> of free access from the moment you activate the tablet. Your trial key is:</p>" .
    "<p style=\"font-family:monospace;font-size:20px;font-weight:600;background:#f3f4f6;padding:14px 18px;border-radius:10px;text-align:center;letter-spacing:1px;\">$safeSerial</p>" .

    // Large inline QR — referenced via cid: so it renders big and clear.
    '<div style="text-align:center;margin:28px 0;">' .
    '<div style="display:inline-block;background:#ffffff;padding:20px;border:2px solid #16a34a;border-radius:16px;box-shadow:0 4px 14px rgba(22,163,74,0.12);">' .
    '<img src="cid:' . $qrCid . '" alt="QuanTab provisioning QR" width="320" height="320" style="display:block;width:320px;height:320px;image-rendering:pixelated;" />' .
    '</div>' .
    '<p style="color:#6b7280;font-size:12px;margin-top:10px;">Scan this with the tablet during Android setup ↑</p>' .
    '</div>' .

    '<h3 style="margin-top:24px;">How to install (recommended)</h3>' .
    '<ol style="padding-left:20px;">' .
    '<li>Factory-reset the Android tablet (Settings → System → Reset).</li>' .
    "<li>On the very first <strong>Welcome</strong> screen, tap the screen <strong>6 times</strong> in the same spot. The QR scanner opens.</li>" .
    '<li>Scan the QR code shown above (it\'s also attached as <code>quantab-' . $safeSerial . '.png</code> if you need to print it).</li>' .
    '<li>Wait 2–5 minutes — Android downloads QuanTab and locks the tablet to it automatically.</li>' .
    "<li>The trial activates. Your $days-day countdown begins.</li>" .
    '</ol>' .
    "<h3 style=\"margin-top:24px;\">Side-loading (limited features)</h3>" .
    "<p>If you can't factory-reset right now, download the APK at <a href=\"$apkUrl\">$apkUrl</a> and install it normally. Enter the trial key when prompted. Note: kiosk lockdown features only activate with the QR provisioning above.</p>" .
    '<hr style="margin:28px 0;border:none;border-top:1px solid #e5e7eb;">' .
    "<p style=\"color:#6b7280;font-size:13px;\">One trial per device. After $days days the tablet locks until you buy a license (₱499 lifetime). Just reply to this email when you're ready.</p>" .
    '</div>';

$res = tablet_send_email($email, $subject, $html, [
    'name'   => 'quantab-' . $serial . '.png',
    'mime'   => 'image/png',
    'bytes'  => $bytes,
    'inline' => true,
    'cid'    => $qrCid,
]);
tablet_log_email($conn, $email, $subject, $serial, !empty($res['ok']), $res['error'] ?? null);

echo json_encode($res);
