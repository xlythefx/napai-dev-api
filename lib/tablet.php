<?php
/**
 * QuanTab tablet-saas helpers — JSON I/O, signing, serial generation,
 * activations log writer. Self-contained; safe to require from any
 * tablet controller.
 */
require_once __DIR__ . '/../config/tablet.php';

function tablet_json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

function tablet_json_response(int $code, array $body): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}

function tablet_sign_token(string $serial, string $androidId): string {
    $payload = "$serial|$androidId";
    $raw = hash_hmac('sha256', $payload, TABLET_LICENSE_SIGNING_SECRET, true);
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function tablet_generate_serial(): string {
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $parts = [];
    for ($g = 0; $g < 4; $g++) {
        $s = '';
        for ($i = 0; $i < 4; $i++) {
            $s .= $alpha[random_int(0, strlen($alpha) - 1)];
        }
        $parts[] = $s;
    }
    return implode('-', $parts);
}

function tablet_client_ip(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
}

function tablet_log_activation(mysqli $conn, string $serial, string $androidId, string $result): void {
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO activations_log (serial, android_id, ip, result) VALUES (?, ?, ?, ?)'
    );
    $ip = tablet_client_ip();
    mysqli_stmt_bind_param($stmt, 'ssss', $serial, $androidId, $ip, $result);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Returns the requester email from query string or POST body. All admin
 * tablet endpoints expect this and pass it to require_admin($conn, $email).
 */
/**
 * Append a row to tablet_admin_audit. Call from every admin-mutating
 * controller, AFTER the action has succeeded, so the log reflects real
 * state changes rather than failed attempts. Best-effort: errors here
 * never block the controller's response.
 *
 * @param string|null  $targetSerial  Affected license serial when applicable.
 * @param array|null   $payload       Endpoint-specific context (counts,
 *                                    filters, fields changed). Serialized to
 *                                    JSON; keep it small.
 */
function tablet_audit(mysqli $conn, string $actorEmail, string $action, ?string $targetSerial = null, ?array $payload = null): void {
    $stmt = @mysqli_prepare(
        $conn,
        'INSERT INTO tablet_admin_audit (actor_email, action, target_serial, ip, payload_json) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) return;
    $ip   = tablet_client_ip();
    $json = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES);
    mysqli_stmt_bind_param($stmt, 'sssss', $actorEmail, $action, $targetSerial, $ip, $json);
    @mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Read a single setting from tablet_settings. Returns $default if the row
 * doesn't exist OR if the table itself is missing (e.g. migration not yet run).
 * Result is cached per-request so repeated checks don't hammer the DB.
 */
function tablet_get_setting(mysqli $conn, string $key, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = @mysqli_query($conn, "SELECT `key`, `value` FROM tablet_settings");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $cache[$row['key']] = $row['value'];
            }
        }
    }
    return $cache[$key] ?? $default;
}

function tablet_email_enabled(mysqli $conn): bool {
    return tablet_get_setting($conn, 'email_keys_enabled', '1') === '1';
}

function tablet_is_maintenance(mysqli $conn): bool {
    return tablet_get_setting($conn, 'is_maintenance', '0') === '1';
}

/**
 * Short-circuit a customer-facing tablet endpoint with 503 if the operator has
 * flipped the maintenance toggle. Call near the top, AFTER db.php + lib/tablet.php
 * are loaded but BEFORE you start doing real work. Admin endpoints should NOT
 * call this — operators still need to manage the system during maintenance.
 */
function tablet_guard_maintenance(mysqli $conn): void {
    if (tablet_is_maintenance($conn)) {
        tablet_json_response(503, [
            'ok' => false,
            'error' => 'maintenance',
            'message' => 'QuanTab is in maintenance mode. Please try again shortly.',
        ]);
    }
}

/**
 * Short-circuit an email-sending controller with 503 if the operator has
 * disabled outbound key emails. Returns a distinct error code so the admin UI
 * can show a useful message instead of a generic SMTP failure.
 */
function tablet_guard_email_enabled(mysqli $conn): void {
    if (!tablet_email_enabled($conn)) {
        tablet_json_response(503, [
            'ok' => false,
            'error' => 'email_disabled',
            'message' => 'License-key emailing is disabled in System Settings.',
        ]);
    }
}

function tablet_requester_email(): string {
    if (isset($_GET['requester_email']) && $_GET['requester_email'] !== '') {
        return trim((string) $_GET['requester_email']);
    }
    if (isset($_POST['requester_email']) && $_POST['requester_email'] !== '') {
        return trim((string) $_POST['requester_email']);
    }
    $body = tablet_json_input();
    return trim((string) ($body['requester_email'] ?? ''));
}
