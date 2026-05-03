<?php
/**
 * Fal AI config. Set FAL_KEY via:
 * 1. Environment variable FAL_KEY (e.g. in php.ini, Apache SetEnv)
 * 2. Or create config/fal_key.php that returns your key
 */
$fal_key = getenv('FAL_KEY');
if (($fal_key === false || $fal_key === '') && file_exists(__DIR__ . '/fal_key.php')) {
    $fal_key = include __DIR__ . '/fal_key.php';
}
if (!is_string($fal_key)) {
    $fal_key = '';
}
