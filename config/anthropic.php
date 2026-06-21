<?php
/**
 * Anthropic (Claude) API config. Set the key via:
 * 1. Environment variable ANTHROPIC_API_KEY (php.ini, Apache SetEnv)
 * 2. Or create config/anthropic_key.php that returns your key string
 *
 * Used by the production Slideshow Studio AI endpoint
 * (controllers/tools/content_slideshows/ai_edit.php). In local dev the
 * frontend uses the Claude CLI bridge instead, so no key is needed there.
 */
$anthropic_key = getenv('ANTHROPIC_API_KEY');
if (($anthropic_key === false || $anthropic_key === '') && file_exists(__DIR__ . '/anthropic_key.php')) {
    $anthropic_key = include __DIR__ . '/anthropic_key.php';
}
if (!is_string($anthropic_key)) {
    $anthropic_key = '';
}
