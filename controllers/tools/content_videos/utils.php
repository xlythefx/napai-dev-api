<?php

function video_jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data);
}

/**
 * Lightweight gallery preview for the Remotion model: aspect from the project
 * dimensions, a gradient swatch (from the first scene's bg props if present),
 * and a best-effort first line of text from common prop keys.
 */
function video_buildPreview($vid): ?array {
    if (!is_array($vid)) return null;
    $scenes = $vid['scenes'] ?? [];
    if (!is_array($scenes) || count($scenes) === 0) return null;

    $props = is_array($scenes[0]['props'] ?? null) ? $scenes[0]['props'] : [];

    $w = (int) ($vid['width'] ?? 1080);
    $h = (int) ($vid['height'] ?? 1920);
    $aspect = $w === $h ? '1:1' : ($w > $h ? '16:9' : '9:16');

    $firstText = '';
    foreach (['title', 'caption', 'name', 'heading', 'headline', 'text'] as $k) {
        if (!empty($props[$k]) && is_string($props[$k])) {
            $firstText = mb_substr($props[$k], 0, 40);
            break;
        }
    }

    $from = (isset($props['bgFrom']) && is_string($props['bgFrom'])) ? $props['bgFrom'] : '#6366f1';
    $to   = (isset($props['bgTo']) && is_string($props['bgTo'])) ? $props['bgTo'] : '#ec4899';

    return [
        'aspectRatio' => $aspect,
        'background'  => [
            'type'          => 'gradient',
            'color'         => '#0f172a',
            'gradientFrom'  => $from,
            'gradientTo'    => $to,
            'gradientAngle' => 135,
        ],
        'firstText'   => $firstText,
    ];
}
