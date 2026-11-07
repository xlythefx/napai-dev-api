<?php
/**
 * These are JSON APIs: PHP warnings/notices/deprecations must never be
 * printed into the response body (it corrupts JSON — "Unexpected token '<'").
 * Keep full error logging, just stop displaying errors inline.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Environment auto-detect: anything that isn't nap-ai.com is treated as
 * local dev. Override with `define('FORCE_DEV', true)` before requiring.
 */
if (!defined('IS_DEV')) {
    if (defined('FORCE_DEV')) {
        define('IS_DEV', (bool) FORCE_DEV);
    } else {
        $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
        define('IS_DEV', strpos($host, 'nap-ai.com') === false);
    }
}

if (IS_DEV) {
    // ---- Local WAMP ----
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'napai_db';
} else {
    // ---- Hostinger shared hosting (nap-ai.com) ----
    // Credentials are kept OUT of git (this repo is public). Provide them via
    // environment variables DB_HOST / DB_USER / DB_PASS / DB_NAME, or via
    // config/db_credentials.php (gitignored; copy db_credentials.example.php
    // and fill in the values from hPanel → Databases).
    $db_creds = [];
    if (file_exists(__DIR__ . '/db_credentials.php')) {
        $loaded = include __DIR__ . '/db_credentials.php';
        if (is_array($loaded)) {
            $db_creds = $loaded;
        }
    }
    $db_host = getenv('DB_HOST') ?: ($db_creds['host'] ?? '127.0.0.1');
    $db_user = getenv('DB_USER') ?: ($db_creds['user'] ?? '');
    $db_pass = getenv('DB_PASS') ?: ($db_creds['pass'] ?? '');
    $db_name = getenv('DB_NAME') ?: ($db_creds['name'] ?? '');
    unset($db_creds, $loaded);

    if ($db_user === '' || $db_name === '') {
        error_log('napai-api: production DB credentials missing — create config/db_credentials.php');
    }
}

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    // Don't use die() as it outputs HTML and breaks JSON responses
    // Set $conn to false so calling code can handle the error
    $conn = false;
    // Store error for debugging if needed
    $conn_error = mysqli_connect_error();
} else {
    mysqli_set_charset($conn, 'utf8mb4');
}
