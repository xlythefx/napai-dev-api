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
    // Replace these with the real values from hPanel → Databases.
    $db_host = '127.0.0.1';
    $db_user = 'u583540151_napai';
    $db_pass = '';
    $db_name = 'u583540151_napai';
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
