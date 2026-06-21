<?php
/**
 * Public ADB setup-script download endpoint.
 *
 * GET ?step=adb|install|uninstall&platform=win|unix
 *
 * Streams one of the static .bat or .sh files in setup_scripts/. These are
 * the same scripts shown on the public /products/quantab/download page and
 * are intentionally generic (no per-license-key templating).
 */
require_once __DIR__ . '/../../config/cors.php';

$step = isset($_GET['step']) ? (string) $_GET['step'] : '';
$platform = isset($_GET['platform']) ? (string) $_GET['platform'] : '';

$map = [
    'adb'       => ['win' => 'adb_setup.bat',     'unix' => 'adb_setup.sh'],
    'install'   => ['win' => 'install_kiosk.bat', 'unix' => 'install_kiosk.sh'],
    'uninstall' => ['win' => 'uninstall.bat',     'unix' => 'uninstall.sh'],
];

if (!isset($map[$step][$platform])) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Bad request. Expected step=adb|install|uninstall and platform=win|unix.\n";
    exit;
}

$filename = $map[$step][$platform];
$path = __DIR__ . '/setup_scripts/' . $filename;

if (!is_file($path)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Script file is missing on server: {$filename}\n";
    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: public, max-age=300');
readfile($path);
exit;
