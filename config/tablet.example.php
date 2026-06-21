<?php
/**
 * Template for config/tablet.php — copy this file to config/tablet.php and
 * fill in the real values. The real file is gitignored on purpose: it holds
 * the HMAC signing secret and the Hostinger SMTP password.
 *
 * Bootstrap:
 *   cp config/tablet.example.php config/tablet.php
 *   # then edit config/tablet.php and replace every TODO below
 *
 * Generating the signing secret (do this ONCE per environment; rotating it
 * invalidates every cached offline token until devices re-activate):
 *   openssl rand -base64 32 | tr -d '\n='
 *
 * The exact same string MUST be supplied to the Flutter app at build time:
 *   flutter build apk --dart-define=LICENSE_SIGNING_SECRET="<paste here>"
 * (see lib/core/constants.dart for how the app reads it.)
 *
 * Rotation procedure (single-key, until dual-key versioning lands):
 *   1. Flip /admin/system-settings → Maintenance mode ON (blocks new activations).
 *   2. Generate a new secret with the openssl command above.
 *   3. Update config/tablet.php on the server.
 *   4. Re-build + ship a new APK using --dart-define with the new secret.
 *   5. Every existing tablet will fail its next heartbeat token-check and
 *      prompt for re-activation. Have buyers re-enter their serial.
 *   6. Flip Maintenance mode OFF.
 */

// HMAC-SHA256 secret used to sign offline activation tokens. MUST match
// Flutter app's K.licenseSigningSecret (lib/core/constants.dart).
const TABLET_LICENSE_SIGNING_SECRET = 'TODO-generate-with-openssl-rand-base64-32';

const TABLET_LICENSE_PRICE = 499.00;
const TABLET_LICENSE_CURRENCY = '₱';

// Trial settings.
const TABLET_TRIAL_DAYS = 3;
const TABLET_TRIAL_MAX_PER_EMAIL = 1;
// Per-IP rate limits added in migration 030. Refused requests count toward
// the 1h window; only successfully-issued trials count toward the 24h window.
const TABLET_TRIAL_MAX_PER_IP_24H = 3;
const TABLET_TRIAL_MAX_PER_IP_1H  = 10;

// Where uploaded APK binaries live on the server.
const TABLET_APK_STORAGE_DIR = __DIR__ . '/../uploads/tablet-apk';

// Public URL of the active APK.
const TABLET_APK_PUBLIC_URL = 'https://nap-ai.com/api/apk/vendotab';

// Device-owner provisioning constants.
const TABLET_PROVISIONING_ADMIN_COMPONENT = 'com.xlythe.vendotab/.DeviceAdminReceiver';
const TABLET_APK_SIGNING_CERT_SHA256_B64  = 'TODO-fill-from-apksigner-verify';

// ---- Email (SMTP) ----
// Hostinger Mail. SSL on 465 is the most reliable; 587 + 'tls' also works.
const TABLET_SMTP_HOST   = 'smtp.hostinger.com';
const TABLET_SMTP_PORT   = 465;
const TABLET_SMTP_USER   = 'TODO-mailbox@nap-ai.com';
const TABLET_SMTP_PASS   = 'TODO-mailbox-password';
const TABLET_SMTP_SECURE = 'ssl';
const TABLET_MAIL_FROM   = 'TODO-mailbox@nap-ai.com';
const TABLET_MAIL_FROM_NAME = 'QuanTab by Nap.AI';
