<?php
/**
 * Public endpoint hit from the marketing landing page wizard (step 2 → 3).
 * POST { email, name? } → { ok:true, serial, qr_json } or { ok:false, error }.
 *
 * Creates the trial key and returns the QR payload so the React wizard can
 * render the QR client-side. Emailing the rendered PNG is done in a follow-up
 * call to trial_email_qr.php (we don't email a serial-only message here so
 * the buyer's inbox always has the scannable QR).
 *
 * Throttled by buyer email (TABLET_TRIAL_MAX_PER_EMAIL).
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tablet_json_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
if (!$conn) tablet_json_response(500, ['ok' => false, 'error' => 'db_error']);
tablet_guard_maintenance($conn);

$body = tablet_json_input();
$email = strtolower(trim((string) ($body['email'] ?? '')));
$name  = trim((string) ($body['name'] ?? ''));
$otp   = preg_replace('/\D/', '', (string) ($body['otp'] ?? ''));

$ip        = tablet_client_ip();
$emailHash = hash('sha256', $email);

/**
 * Append a row to trial_requests for auditing + throttling. Best-effort —
 * if the table is missing (migration 030 hasn't run yet) we silently skip
 * so the endpoint stays available.
 */
$logAttempt = function (string $result) use ($conn, $ip, $emailHash): void {
    $stmt = @mysqli_prepare($conn, 'INSERT INTO trial_requests (ip, email_hash, result) VALUES (?, ?, ?)');
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, 'sss', $ip, $emailHash, $result);
    @mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
};

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $logAttempt('invalid_email');
    tablet_json_response(400, ['ok' => false, 'error' => 'Invalid email']);
}

// Per-IP throttle: total attempts in last hour AND successfully-issued trials
// in last 24 hours. Both windows must be under their caps. Checked BEFORE the
// per-email throttle so a bot spamming distinct emails from one IP gets cut
// off without polluting license_keys.
$cap1h  = (int) TABLET_TRIAL_MAX_PER_IP_1H;
$cap24h = (int) TABLET_TRIAL_MAX_PER_IP_24H;

$rl = mysqli_prepare(
    $conn,
    "SELECT
       SUM(CASE WHEN created_at > NOW() - INTERVAL 1 HOUR THEN 1 ELSE 0 END) AS hits_1h,
       SUM(CASE WHEN created_at > NOW() - INTERVAL 24 HOUR AND result = 'ok' THEN 1 ELSE 0 END) AS ok_24h
     FROM trial_requests WHERE ip = ?"
);
if ($rl) {
    mysqli_stmt_bind_param($rl, 's', $ip);
    mysqli_stmt_execute($rl);
    $rlRow  = mysqli_fetch_assoc(mysqli_stmt_get_result($rl)) ?: [];
    $hits1h = (int) ($rlRow['hits_1h'] ?? 0);
    $ok24h  = (int) ($rlRow['ok_24h']  ?? 0);
    mysqli_stmt_close($rl);

    if ($hits1h >= $cap1h) {
        $logAttempt('rate_limit_ip_1h');
        header('Retry-After: 3600');
        tablet_json_response(429, ['ok' => false, 'error' => 'Too many requests. Please try again in an hour.']);
    }
    if ($ok24h >= $cap24h) {
        $logAttempt('rate_limit_ip_24h');
        header('Retry-After: 86400');
        tablet_json_response(429, ['ok' => false, 'error' => 'Daily trial limit reached from your network. Try again tomorrow.']);
    }
}

// OTP verification — required since migration 032. If no code was supplied,
// tell the client to call trial_otp_send.php first. If the code is wrong,
// burn an attempt and refuse; after 5 attempts the row is locked.
if ($otp === '' || strlen($otp) !== 6) {
    $logAttempt('otp_missing');
    tablet_json_response(400, ['ok' => false, 'error' => 'otp_required', 'message' => 'Enter the 6-digit code from your email.']);
}
$otpSel = mysqli_prepare(
    $conn,
    'SELECT id, code_hash, attempts, consumed, expires_at FROM trial_otp
     WHERE email = ? ORDER BY id DESC LIMIT 1'
);
mysqli_stmt_bind_param($otpSel, 's', $email);
mysqli_stmt_execute($otpSel);
$otpRow = mysqli_fetch_assoc(mysqli_stmt_get_result($otpSel));
mysqli_stmt_close($otpSel);

if (!$otpRow) {
    $logAttempt('otp_missing');
    tablet_json_response(400, ['ok' => false, 'error' => 'otp_not_sent', 'message' => 'Request a code first.']);
}
if ((int) $otpRow['consumed'] === 1) {
    $logAttempt('otp_consumed');
    tablet_json_response(400, ['ok' => false, 'error' => 'otp_expired', 'message' => 'That code is no longer valid. Request a new one.']);
}
if (strtotime($otpRow['expires_at']) < time()) {
    $logAttempt('otp_expired');
    tablet_json_response(400, ['ok' => false, 'error' => 'otp_expired', 'message' => 'That code has expired. Request a new one.']);
}
if ((int) $otpRow['attempts'] >= 5) {
    $logAttempt('otp_locked');
    tablet_json_response(429, ['ok' => false, 'error' => 'otp_locked', 'message' => 'Too many wrong attempts. Request a new code.']);
}

if (!hash_equals((string) $otpRow['code_hash'], hash('sha256', $otp))) {
    // Burn an attempt. Mark consumed if this was the 5th.
    $bump = mysqli_prepare(
        $conn,
        'UPDATE trial_otp SET attempts = attempts + 1,
            consumed = CASE WHEN attempts + 1 >= 5 THEN 1 ELSE consumed END
         WHERE id = ?'
    );
    mysqli_stmt_bind_param($bump, 'i', $otpRow['id']);
    mysqli_stmt_execute($bump);
    mysqli_stmt_close($bump);
    $logAttempt('otp_wrong');
    tablet_json_response(400, ['ok' => false, 'error' => 'otp_wrong', 'message' => 'Wrong code.']);
}

// Code matched — mark consumed so it can't be replayed.
$consume = mysqli_prepare($conn, 'UPDATE trial_otp SET consumed = 1 WHERE id = ?');
mysqli_stmt_bind_param($consume, 'i', $otpRow['id']);
mysqli_stmt_execute($consume);
mysqli_stmt_close($consume);

// Per-email throttle: how many trials has this email already received?
$stmt = mysqli_prepare($conn, 'SELECT COUNT(*) c FROM license_keys WHERE buyer_email = ? AND kind = "trial"');
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$cnt = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
mysqli_stmt_close($stmt);
if ($cnt >= TABLET_TRIAL_MAX_PER_EMAIL) {
    $logAttempt('rate_limit_email');
    tablet_json_response(429, ['ok' => false, 'error' => 'You already requested a trial. Buy a license to continue.']);
}

$ar = mysqli_query($conn, 'SELECT version, sha256_b64, size_bytes FROM apk_versions WHERE is_active = 1 LIMIT 1');
$active = mysqli_fetch_assoc($ar);
$serial = null;
$ins = mysqli_prepare(
    $conn,
    'INSERT INTO license_keys (serial, kind, note, buyer_email, buyer_ip) VALUES (?, "trial", ?, ?, ?)'
);
$noteText = $name !== '' ? 'Trial requested by ' . $name : 'Trial';
for ($try = 0; $try < 5; $try++) {
    $candidate = tablet_generate_serial();
    mysqli_stmt_bind_param($ins, 'ssss', $candidate, $noteText, $email, $ip);
    if (@mysqli_stmt_execute($ins)) { $serial = $candidate; break; }
}
mysqli_stmt_close($ins);
if (!$serial) {
    $logAttempt('alloc_failed');
    tablet_json_response(500, ['ok' => false, 'error' => 'Could not allocate trial key']);
}
$logAttempt('ok');

$qrPayload = null;
if ($active) {
    $qrPayload = [
        'android.app.extra.PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME' => TABLET_PROVISIONING_ADMIN_COMPONENT,
        'android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_DOWNLOAD_LOCATION' => TABLET_APK_PUBLIC_URL,
        'android.app.extra.PROVISIONING_DEVICE_ADMIN_SIGNATURE_CHECKSUM' => $active['sha256_b64'],
        'android.app.extra.PROVISIONING_LEAVE_ALL_SYSTEM_APPS_ENABLED' => true,
        'android.app.extra.PROVISIONING_SKIP_ENCRYPTION' => false,
        'android.app.extra.PROVISIONING_ADMIN_EXTRAS_BUNDLE' => ['license_serial' => $serial],
    ];
}

echo json_encode([
    'ok' => true,
    'serial' => $serial,
    'name' => $name,
    'email' => $email,
    'trial_days' => TABLET_TRIAL_DAYS,
    'qr_json' => $qrPayload,
    'apk_url' => TABLET_APK_PUBLIC_URL,
    'has_active_apk' => $active !== null,
]);
