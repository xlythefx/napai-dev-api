<?php
/**
 * API entry - info and routing hints
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
echo json_encode([
    'name' => 'Napai API',
    'endpoints' => [
        'developers' => '/napai-api/controllers/developers/index.php',
        'developers_login' => '/napai-api/controllers/developers/login.php',
        'developers_signup' => '/napai-api/controllers/developers/signup.php',
    ],
]);
