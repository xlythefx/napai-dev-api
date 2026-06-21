<?php

function template_jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data);
}

/**
 * Lightweight gallery preview from a template's CanvasItem JSON
 * (no heavy image data URLs).
 */
function template_buildPreview($cj): ?array {
    if (!is_array($cj)) return null;
    $bg = $cj['background'] ?? [];
    $firstText = '';
    if (!empty($cj['textElements'][0]['text'])) {
        $firstText = mb_substr((string) $cj['textElements'][0]['text'], 0, 40);
    }
    return [
        'aspectRatio' => $cj['aspectRatio'] ?? '1:1',
        'background'  => [
            'type'          => $bg['type'] ?? 'solid',
            'color'         => $bg['color'] ?? '#0f172a',
            'gradientFrom'  => $bg['gradientFrom'] ?? '#6366f1',
            'gradientTo'    => $bg['gradientTo'] ?? '#ec4899',
            'gradientAngle' => $bg['gradientAngle'] ?? 135,
        ],
        'firstText'   => $firstText,
    ];
}
