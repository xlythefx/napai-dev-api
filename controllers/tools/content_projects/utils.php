<?php

function project_jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data);
}

/**
 * Build a lightweight gallery preview from a full project payload,
 * deliberately omitting heavy image data URLs.
 */
function project_buildPreview($proj): ?array {
    if (!is_array($proj)) return null;
    $canvases = $proj['canvases'] ?? [];
    if (!is_array($canvases) || count($canvases) === 0) return null;
    $first = $canvases[0];
    $bg = $first['background'] ?? [];
    $firstText = '';
    if (!empty($first['textElements'][0]['text'])) {
        $firstText = mb_substr((string) $first['textElements'][0]['text'], 0, 40);
    }
    return [
        'aspectRatio' => $first['aspectRatio'] ?? '1:1',
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
