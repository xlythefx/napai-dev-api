<?php
/**
 * Shared helpers for the weather controllers.
 *
 * These endpoints proxy PAGASA sources that the browser cannot reach directly:
 * the Pasig-Marikina-Tullahan FFWS sends no CORS headers at all, and the main
 * PAGASA site serves HTML rather than JSON. Everything here is read-only,
 * public, and touches no database.
 */

/**
 * Trust anchors for *.pagasa.dost.gov.ph.
 *
 * The flood-forecasting host serves ONLY its leaf certificate and omits the
 * GlobalSign intermediate that signs it, so the chain cannot be built from a
 * normal CA store. Browsers hide this by fetching the missing intermediate over
 * AIA; curl does not, and fails with "unable to get local issuer certificate".
 * This file supplies the missing intermediate plus its root, so verification
 * still happens in full — it is not a bypass, and it is passed only to the
 * requests aimed at that host.
 */
define('WEATHER_PAGASA_CA', __DIR__ . DIRECTORY_SEPARATOR . 'pagasa-ca.pem');

/** Where cached upstream responses live. Temp is always writable; the API has no cache dir. */
function weather_cache_dir(): string
{
    $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'napai_weather_cache';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    return $dir;
}

/**
 * GET or POST an upstream URL.
 *
 * Returns null on any failure rather than throwing — every caller has a stale
 * cache entry to fall back on, and a stale reading beats an empty panel.
 *
 * @param string|null $post     URL-encoded body. Non-null (even "") forces a POST.
 * @param string|null $caBundle Verify against this CA file instead of the system
 *                              store — see WEATHER_PAGASA_CA.
 */
function weather_fetch(string $url, ?string $post = null, int $timeout = 12, ?string $caBundle = null): ?string
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6,
        // PAGASA rejects some default agents with a 403.
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                                . '(KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept: */*'],
    ];
    if ($caBundle !== null && is_file($caBundle)) {
        $opts[CURLOPT_CAINFO] = $caBundle;
    } elseif (defined('CURLSSLOPT_NATIVE_CA')) {
        // WAMP ships without a curl.cainfo bundle, so verification fails locally
        // against these HTTPS hosts. Falling back to the OS certificate store keeps
        // verification ON; on the Linux host, where a bundle is configured, this is
        // simply an extra source and changes nothing.
        $opts[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
    }
    if ($post !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $post;
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded', 'Accept: */*'];
    }
    curl_setopt_array($ch, $opts);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code !== 200 || $body === '') return null;
    return $body;
}

/**
 * Serve $fn's result from a file cache, falling back to the stale entry when
 * the upstream is unreachable. $fn returns null to signal failure.
 *
 * @return array{data:mixed,fetched_at:string,stale:bool}|null
 */
function weather_cached(string $key, int $ttl, callable $fn): ?array
{
    $file = weather_cache_dir() . DIRECTORY_SEPARATOR . preg_replace('/[^a-z0-9_]/i', '_', $key) . '.json';
    $cached = null;

    if (is_file($file)) {
        $cached = json_decode((string) @file_get_contents($file), true);
        if (is_array($cached) && (time() - ($cached['ts'] ?? 0)) < $ttl) {
            return [
                'data'       => $cached['data'],
                'fetched_at' => gmdate('c', $cached['ts']),
                'stale'      => false,
            ];
        }
    }

    $fresh = $fn();
    if ($fresh !== null) {
        @file_put_contents($file, json_encode(['ts' => time(), 'data' => $fresh]), LOCK_EX);
        return ['data' => $fresh, 'fetched_at' => gmdate('c'), 'stale' => false];
    }

    // Upstream is down — hand back whatever we last saw, clearly marked.
    if (is_array($cached) && array_key_exists('data', $cached)) {
        return [
            'data'       => $cached['data'],
            'fetched_at' => gmdate('c', $cached['ts'] ?? time()),
            'stale'      => true,
        ];
    }

    return null;
}

/** Emit the standard envelope and stop. */
function weather_ok(array $result, array $extra = []): void
{
    echo json_encode(array_merge([
        'ok'         => true,
        'fetched_at' => $result['fetched_at'],
        'stale'      => $result['stale'],
        'data'       => $result['data'],
    ], $extra));
    exit;
}

function weather_fail(string $message, int $status = 502): void
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

/**
 * PAGASA marks a reading it could not refresh by appending "(*)" to the value.
 *
 * @return array{0: float|null, 1: bool} [value, wasStale]
 */
function weather_num(?string $raw): array
{
    if ($raw === null || $raw === '') return [null, false];
    $stale = strpos($raw, '(*)') !== false;
    $clean = trim(str_replace('(*)', '', $raw));
    if ($clean === '' || !is_numeric($clean)) return [null, $stale];
    return [(float) $clean, $stale];
}
