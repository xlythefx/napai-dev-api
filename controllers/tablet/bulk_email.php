<?php
/**
 * POST { requester_email, to, serials[], qr_png, note? }
 *
 * Emails a reseller two attachments:
 *   - quantab-keys-{stamp}-bulk-{N}.csv  (built server-side from DB rows)
 *   - quantab-bulk-qr.png                (the universal provisioning QR captured by React)
 *
 * Updates each license_keys row with bulk_emailed_at + bulk_emailed_to so the
 * dashboard can show a 📧 indicator next to keys that have been emailed.
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';
require_once __DIR__ . '/../../lib/mailer.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tablet_json_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
if (!$conn) tablet_json_response(500, ['ok' => false, 'error' => 'db_error']);

$actorEmail = tablet_requester_email();
require_admin($conn, $actorEmail);
tablet_guard_email_enabled($conn);

$body = tablet_json_input();
$to       = trim((string) ($body['to'] ?? ''));
$serials  = is_array($body['serials'] ?? null) ? $body['serials'] : [];
$pngData  = (string) ($body['qr_png'] ?? '');
$note     = trim((string) ($body['note'] ?? ''));

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) tablet_json_response(400, ['ok' => false, 'error' => 'Invalid email']);
if (count($serials) === 0)  tablet_json_response(400, ['ok' => false, 'error' => 'No serials provided']);
if (count($serials) > 500)  tablet_json_response(400, ['ok' => false, 'error' => 'Too many serials in one batch (max 500)']);
if ($pngData === '')        tablet_json_response(400, ['ok' => false, 'error' => 'QR image missing']);

// Decode QR PNG.
$pos = strpos($pngData, 'base64,');
$b64 = $pos !== false ? substr($pngData, $pos + 7) : $pngData;
$qrBytes = base64_decode($b64);
if (!$qrBytes) tablet_json_response(400, ['ok' => false, 'error' => 'Could not decode QR image']);

// Pull canonical rows from DB.
$serials = array_values(array_unique(array_map(fn($s) => strtoupper(trim((string) $s)), $serials)));
$placeholders = implode(',', array_fill(0, count($serials), '?'));
$types = str_repeat('s', count($serials));
$sql = "SELECT serial, type, sell_type, note, created_at FROM license_keys WHERE serial IN ($placeholders)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$serials);
mysqli_stmt_execute($stmt);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

if (count($rows) === 0) tablet_json_response(404, ['ok' => false, 'error' => 'No matching keys found']);

// Build CSV server-side (don't trust client). RFC 4180 quote-escape note.
$escape = fn(string $s) => '"' . str_replace('"', '""', $s) . '"';
$lines = ['serial,type,sell_type,note,created_at'];
foreach ($rows as $r) {
    $lines[] = implode(',', [
        $r['serial'],
        $r['type'],
        $r['sell_type'],
        $escape($r['note'] ?? ''),
        $r['created_at'],
    ]);
}
$csvBody = implode("\r\n", $lines) . "\r\n";
$stamp = date('Y-m-d-Hi');
$csvName = sprintf('quantab-keys-%s-bulk-%d.csv', $stamp, count($rows));

// Build email body.
$count = count($rows);
$safeNote = $note !== '' ? htmlspecialchars($note, ENT_QUOTES) : '(no label)';
$apkUrl = htmlspecialchars(TABLET_APK_PUBLIC_URL, ENT_QUOTES);
$qrCid = 'qr-' . bin2hex(random_bytes(6)) . '@quantab';
$html =
    '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:560px;line-height:1.6;color:#1f2937;">' .
    "<h2 style=\"color:#16a34a;margin-bottom:8px;\">Your QuanTab bulk batch — $count keys</h2>" .
    "<p>Batch label: <strong>$safeNote</strong></p>" .

    // Large inline universal QR
    '<div style="text-align:center;margin:24px 0 28px;">' .
    '<div style="display:inline-block;background:#ffffff;padding:20px;border:2px solid #16a34a;border-radius:16px;box-shadow:0 4px 14px rgba(22,163,74,0.12);">' .
    '<img src="cid:' . $qrCid . '" alt="QuanTab universal provisioning QR" width="320" height="320" style="display:block;width:320px;height:320px;image-rendering:pixelated;" />' .
    '</div>' .
    '<p style="color:#6b7280;font-size:12px;margin-top:10px;">Universal QR — works for all ' . $count . ' keys in this batch ↑</p>' .
    '</div>' .

    '<p>Attached:</p>' .
    '<ol style="padding-left:20px;">' .
    "<li><strong>$csvName</strong> — your $count license serials. One per tablet you provision.</li>" .
    '<li><strong>quantab-bulk-qr.png</strong> — the same QR shown above, in case you want to print it.</li>' .
    '</ol>' .
    '<h3 style="margin-top:24px;">Per-tablet workflow</h3>' .
    '<ol style="padding-left:20px;">' .
    '<li>Factory-reset the tablet (Settings → System → Reset).</li>' .
    '<li>On the welcome screen, tap 6 times. The QR scanner opens.</li>' .
    '<li>Scan the universal QR shown above. Wait 2–5 minutes for QuanTab to install.</li>' .
    '<li>QuanTab opens with an empty serial field. Type the next unused serial from the CSV.</li>' .
    '<li>Tap Activate. Set a 6-digit admin PIN. Done.</li>' .
    '</ol>' .
    '<hr style="margin:28px 0;border:none;border-top:1px solid #e5e7eb;">' .
    "<p style=\"color:#6b7280;font-size:13px;\">Each serial is bound to the first tablet that activates it. If a tablet breaks or you upgrade, the operator can self-unbind from QuanTab Admin → License → Unbind. Need help? Reply to this email.</p>" .
    '</div>';

$subject = "QuanTab bulk batch — $count keys";

// Two-level multipart so the universal QR can be inline (cid:) AND attached:
//   multipart/mixed (outer)
//   ├── multipart/related (inner) — HTML + inline QR image
//   └── text/csv attachment
$outer = '=_b_' . bin2hex(random_bytes(8));
$inner = '=_r_' . bin2hex(random_bytes(8));
$fromAddr = TABLET_MAIL_FROM;
$fromName = TABLET_MAIL_FROM_NAME;
$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'From: ' . sprintf('%s <%s>', addslashes($fromName), $fromAddr);
$headers[] = 'Reply-To: ' . $fromAddr;
$headers[] = 'Date: ' . date(DATE_RFC2822);
$headers[] = 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . parse_url($fromAddr, PHP_URL_HOST) . '>';
$headers[] = 'Content-Type: multipart/mixed; boundary="' . $outer . '"';

$msgBody  = "--$outer\r\n";
$msgBody .= 'Content-Type: multipart/related; boundary="' . $inner . "\"\r\n\r\n";

// HTML body
$msgBody .= "--$inner\r\n";
$msgBody .= "Content-Type: text/html; charset=UTF-8\r\n";
$msgBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$msgBody .= preg_replace('/\r?\n/', "\r\n", $html) . "\r\n\r\n";

// Inline QR image (referenced via cid:)
$msgBody .= "--$inner\r\n";
$msgBody .= "Content-Type: image/png; name=\"quantab-bulk-qr.png\"\r\n";
$msgBody .= "Content-Transfer-Encoding: base64\r\n";
$msgBody .= 'Content-ID: <' . $qrCid . ">\r\n";
$msgBody .= "Content-Disposition: inline; filename=\"quantab-bulk-qr.png\"\r\n\r\n";
$msgBody .= chunk_split(base64_encode($qrBytes)) . "\r\n";

$msgBody .= "--$inner--\r\n\r\n";

// CSV attachment
$msgBody .= "--$outer\r\n";
$msgBody .= "Content-Type: text/csv; name=\"$csvName\"\r\n";
$msgBody .= "Content-Transfer-Encoding: base64\r\n";
$msgBody .= "Content-Disposition: attachment; filename=\"$csvName\"\r\n\r\n";
$msgBody .= chunk_split(base64_encode($csvBody)) . "\r\n";

$msgBody .= "--$outer--\r\n";

$res = _tablet_send_raw($to, $subject, $msgBody, implode("\r\n", $headers));
tablet_log_email($conn, $to, $subject, null, !empty($res['ok']), $res['error'] ?? null);

if (!empty($res['ok'])) {
    // Mark each key as emailed.
    $updateSql = "UPDATE license_keys SET bulk_emailed_at = NOW(), bulk_emailed_to = ? WHERE serial IN ($placeholders)";
    $up = mysqli_prepare($conn, $updateSql);
    $params = array_merge([$to], $serials);
    mysqli_stmt_bind_param($up, 's' . $types, ...$params);
    mysqli_stmt_execute($up);
    mysqli_stmt_close($up);
}

tablet_audit($conn, $actorEmail, 'keys.bulk_email', null, [
    'recipient'   => $to,
    'serial_count' => count($rows),
    'serials'     => array_column($rows, 'serial'),
    'note'        => $note,
    'sent_ok'     => !empty($res['ok']),
]);

echo json_encode([
    'ok' => !empty($res['ok']),
    'error' => $res['error'] ?? null,
    'sent_count' => count($rows),
    'recipient' => $to,
]);

/**
 * Lightweight wrapper around the SMTP/mail() routing logic in lib/mailer.php.
 * tablet_send_email() in that file builds its own multipart for one attachment;
 * here we already have the full MIME body so we just need the transport.
 */
function _tablet_send_raw(string $to, string $subject, string $body, string $headerString): array {
    if (defined('TABLET_SMTP_HOST') && TABLET_SMTP_HOST !== '') {
        return _tablet_smtp_send($to, $subject, $body, $headerString);
    }
    $ok = @mail($to, $subject, $body, $headerString);
    return ['ok' => $ok, 'error' => $ok ? null : (error_get_last()['message'] ?? 'mail() failed')];
}
