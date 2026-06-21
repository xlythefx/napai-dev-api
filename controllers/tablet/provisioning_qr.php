<?php
/**
 * GET ?requester_email=&key_id=&wifi_ssid=&wifi_pass=&wifi_type=&universal=0|1
 *
 * Returns the provisioning JSON payload (the React side renders the QR
 * client-side via qrcode.react).
 *
 * Two modes:
 *   - per-key (default): pass key_id. Bundle includes `license_serial` so the
 *     buyer's tablet auto-fills the activation field. Used for retail.
 *   - universal=1: skip key_id. Bundle has NO serial. ONE QR can provision
 *     any number of tablets in a bulk batch — the buyer types whichever
 *     serial from the CSV after provisioning. Used for resellers.
 *
 * Response: { json, key|null, active_apk, mode: 'per_key'|'universal' }
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) tablet_json_response(500, ['error' => 'Database connection failed']);

require_admin($conn, tablet_requester_email());

$universal = !empty($_GET['universal']) && $_GET['universal'] !== '0';
$keyId     = (int) ($_GET['key_id'] ?? 0);
$wifiSsid  = trim((string) ($_GET['wifi_ssid'] ?? ''));
$wifiPass  = (string) ($_GET['wifi_pass'] ?? '');
$wifiType  = $_GET['wifi_type'] ?? 'WPA';
if (!in_array($wifiType, ['NONE', 'WPA', 'WEP'], true)) $wifiType = 'WPA';

$key = null;
if (!$universal) {
    if ($keyId <= 0) tablet_json_response(400, ['error' => 'key_id required (or pass universal=1)']);
    $stmt = mysqli_prepare($conn, 'SELECT id, serial, android_id, revoked FROM license_keys WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $keyId);
    mysqli_stmt_execute($stmt);
    $key = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$key) tablet_json_response(404, ['error' => 'Key not found']);
}

$ar = mysqli_query($conn, 'SELECT id, version, sha256_b64, size_bytes FROM apk_versions WHERE is_active = 1 LIMIT 1');
$active = mysqli_fetch_assoc($ar);
if (!$active) {
    tablet_json_response(409, [
        'error' => 'No active APK published',
        'key' => $key,
        'active_apk' => null,
    ]);
}

$payload = [
    'android.app.extra.PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME' => TABLET_PROVISIONING_ADMIN_COMPONENT,
    'android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_DOWNLOAD_LOCATION' => TABLET_APK_PUBLIC_URL,
    // SIGNATURE_CHECKSUM = SHA-256 of the APK signing certificate (constant
    // per keystore). REQUIRED for Android 14/15 QR provisioning — the older
    // PACKAGE_CHECKSUM (file hash) is deprecated and ignored on newer Android.
    // We send both so older devices (Android <14) can still use the file hash.
    'android.app.extra.PROVISIONING_DEVICE_ADMIN_SIGNATURE_CHECKSUM' => TABLET_APK_SIGNING_CERT_SHA256_B64,
    'android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_CHECKSUM' => $active['sha256_b64'],
    'android.app.extra.PROVISIONING_LEAVE_ALL_SYSTEM_APPS_ENABLED' => true,
    'android.app.extra.PROVISIONING_SKIP_ENCRYPTION' => false,
];
if (!$universal) {
    // Per-key QR pre-fills the license serial in the activation page.
    $payload['android.app.extra.PROVISIONING_ADMIN_EXTRAS_BUNDLE'] = [
        'license_serial' => $key['serial'],
    ];
}
if ($wifiSsid !== '') {
    $payload['android.app.extra.PROVISIONING_WIFI_SSID'] = $wifiSsid;
    $payload['android.app.extra.PROVISIONING_WIFI_SECURITY_TYPE'] = $wifiType;
    if ($wifiPass !== '') $payload['android.app.extra.PROVISIONING_WIFI_PASSWORD'] = $wifiPass;
}

echo json_encode([
    'json' => $payload,
    'mode' => $universal ? 'universal' : 'per_key',
    'key' => $key ? [
        'id' => (int) $key['id'],
        'serial' => $key['serial'],
        'android_id' => $key['android_id'],
        'revoked' => (int) $key['revoked'],
    ] : null,
    'active_apk' => [
        'version' => $active['version'],
        'sha256_b64' => $active['sha256_b64'],
        'size_bytes' => (int) $active['size_bytes'],
    ],
]);
