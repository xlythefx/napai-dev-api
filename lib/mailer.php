<?php
/**
 * Minimal vanilla mailer for QuanTab. Uses raw-socket SMTP when
 * TABLET_SMTP_HOST is set, otherwise falls back to PHP's mail().
 * Supports one binary attachment (used to deliver QR PNGs).
 */
require_once __DIR__ . '/../config/tablet.php';

function _tablet_crlf(string $s): string {
    return preg_replace('/\r?\n/', "\r\n", $s);
}

/**
 * @param array{name?:string,mime:string,bytes:string,inline?:bool,cid?:string}|null $attachment
 *   Set inline=true and cid='something' to embed the image inline (referenced from
 *   the HTML body via <img src="cid:something">). It will still appear as a
 *   downloadable attachment in most clients.
 * @return array{ok:bool,error:?string}
 */
function tablet_send_email(string $to, string $subject, string $htmlBody, ?array $attachment = null): array {
    $boundary = '=_b_' . bin2hex(random_bytes(8));
    $fromAddr = TABLET_MAIL_FROM ?: 'noreply@localhost';
    $fromName = TABLET_MAIL_FROM_NAME ?: 'Mailer';
    $fromHeader = sprintf('%s <%s>', addslashes($fromName), $fromAddr);

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: ' . $fromHeader;
    $headers[] = 'Reply-To: ' . $fromAddr;
    $headers[] = 'Date: ' . date(DATE_RFC2822);
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . parse_url($fromAddr, PHP_URL_HOST) . '>';

    if ($attachment) {
        $isInline = !empty($attachment['inline']);
        // multipart/related lets the HTML reference the image via cid:. Most
        // clients still surface it as an attachment for download.
        $multipartType = $isInline ? 'multipart/related' : 'multipart/mixed';
        $disposition = $isInline ? 'inline' : 'attachment';
        $name = $attachment['name'] ?? 'attachment';
        $cid = $attachment['cid'] ?? null;

        $headers[] = 'Content-Type: ' . $multipartType . '; boundary="' . $boundary . '"';
        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= _tablet_crlf($htmlBody) . "\r\n\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: " . $attachment['mime'] . '; name="' . $name . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        if ($cid) {
            $body .= 'Content-ID: <' . $cid . ">\r\n";
        }
        $body .= "Content-Disposition: $disposition; filename=\"" . $name . "\"\r\n\r\n";
        $body .= chunk_split(base64_encode($attachment['bytes'])) . "\r\n";
        $body .= "--$boundary--\r\n";
    } else {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $body = _tablet_crlf($htmlBody);
    }

    $headerString = implode("\r\n", $headers);

    if (defined('TABLET_SMTP_HOST') && TABLET_SMTP_HOST !== '') {
        return _tablet_smtp_send($to, $subject, $body, $headerString);
    }
    $ok = @mail($to, $subject, $body, $headerString);
    return ['ok' => $ok, 'error' => $ok ? null : (error_get_last()['message'] ?? 'mail() failed')];
}

function _tablet_smtp_send(string $to, string $subject, string $body, string $headerString): array {
    $host = TABLET_SMTP_HOST;
    $port = (int) (TABLET_SMTP_PORT ?: 587);
    $user = TABLET_SMTP_USER;
    $pass = TABLET_SMTP_PASS;
    $secure = strtolower((string) TABLET_SMTP_SECURE);

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$fp) return ['ok' => false, 'error' => "Connect: $errstr ($errno)"];

    $expect = function ($code) use ($fp) {
        $line = '';
        while (!feof($fp)) {
            $chunk = fgets($fp, 515);
            if ($chunk === false) break;
            $line .= $chunk;
            if (isset($chunk[3]) && $chunk[3] === ' ') break;
        }
        return [substr($line, 0, 3) === (string) $code, $line];
    };
    $send = function ($s) use ($fp) { fwrite($fp, $s . "\r\n"); };

    [$ok, $line] = $expect(220); if (!$ok) return ['ok' => false, 'error' => "Greet: $line"];
    $send('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    [$ok, $line] = $expect(250); if (!$ok) return ['ok' => false, 'error' => "EHLO: $line"];

    if ($secure === 'tls') {
        $send('STARTTLS');
        [$ok, $line] = $expect(220); if (!$ok) return ['ok' => false, 'error' => "STARTTLS: $line"];
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return ['ok' => false, 'error' => 'TLS handshake failed'];
        }
        $send('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        [$ok, $line] = $expect(250); if (!$ok) return ['ok' => false, 'error' => "EHLO2: $line"];
    }

    if ($user !== '') {
        $send('AUTH LOGIN');
        [$ok, $line] = $expect(334); if (!$ok) return ['ok' => false, 'error' => "AUTH: $line"];
        $send(base64_encode($user));
        [$ok, $line] = $expect(334); if (!$ok) return ['ok' => false, 'error' => "USER: $line"];
        $send(base64_encode($pass));
        [$ok, $line] = $expect(235); if (!$ok) return ['ok' => false, 'error' => "PASS: $line"];
    }

    $send('MAIL FROM:<' . TABLET_MAIL_FROM . '>');
    [$ok, $line] = $expect(250); if (!$ok) return ['ok' => false, 'error' => "MAIL FROM: $line"];
    $send('RCPT TO:<' . $to . '>');
    [$ok, $line] = $expect(250); if (!$ok) return ['ok' => false, 'error' => "RCPT TO: $line"];
    $send('DATA');
    [$ok, $line] = $expect(354); if (!$ok) return ['ok' => false, 'error' => "DATA: $line"];

    $message = "To: $to\r\nSubject: $subject\r\n$headerString\r\n\r\n$body\r\n.";
    fwrite($fp, $message . "\r\n");
    [$ok, $line] = $expect(250); if (!$ok) return ['ok' => false, 'error' => "BODY: $line"];

    $send('QUIT');
    fclose($fp);
    return ['ok' => true, 'error' => null];
}

function tablet_log_email(mysqli $conn, string $to, string $subject, ?string $serial, bool $ok, ?string $err): void {
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO email_log (recipient, subject, related_serial, ok, error) VALUES (?, ?, ?, ?, ?)'
    );
    $okI = $ok ? 1 : 0;
    mysqli_stmt_bind_param($stmt, 'sssis', $to, $subject, $serial, $okI, $err);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
