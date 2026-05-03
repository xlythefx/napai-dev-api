<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'napai_db';

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
