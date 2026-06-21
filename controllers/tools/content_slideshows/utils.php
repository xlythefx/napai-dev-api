<?php

function slideshow_jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data);
}

/**
 * Build a lightweight gallery preview from a full slideshow payload,
 * deliberately omitting heavy image data URLs.
 * Shape: { aspectRatio, background{...}, firstText, slideCount, durationMs }
 */
function slideshow_buildPreview($deck): ?array {
    if (!is_array($deck)) return null;
    $settings = is_array($deck['settings'] ?? null) ? $deck['settings'] : [];
    $slides   = is_array($deck['slides'] ?? null) ? $deck['slides'] : [];
    if (count($slides) === 0) return null;

    $first = $slides[0];
    $bg    = $first['background'] ?? [];

    $firstText = '';
    foreach (($first['elements'] ?? []) as $el) {
        if (($el['type'] ?? '') === 'text' && !empty($el['text'])) {
            $firstText = mb_substr((string) $el['text'], 0, 40);
            break;
        }
    }

    $durationMs = 0;
    foreach ($slides as $s) {
        $durationMs += (int) ($s['durationMs'] ?? 0) + (int) ($s['transitionMs'] ?? 0);
    }

    return [
        'aspectRatio' => $settings['aspectRatio'] ?? '16:9',
        'background'  => [
            'type'          => $bg['type'] ?? 'gradient',
            'color'         => $bg['color'] ?? '#0f172a',
            'gradientFrom'  => $bg['gradientFrom'] ?? '#1e1b4b',
            'gradientTo'    => $bg['gradientTo'] ?? '#312e81',
            'gradientAngle' => $bg['gradientAngle'] ?? 145,
        ],
        'firstText'  => $firstText,
        'slideCount' => count($slides),
        'durationMs' => $durationMs,
    ];
}

/**
 * Build the Claude prompt for an animated-deck edit. Port of buildDeckEditPrompt
 * in server/claudeBridge.mjs so the production endpoint matches the dev bridge.
 */
function slideshow_buildDeckEditPrompt(string $instruction, string $scope, string $targetSlideId, array $deck, array $history): string {
    $enter = 'none, fadeIn, slideInLeft, slideInRight, slideInUp, slideInDown, scaleIn, popIn, blurIn, rotateIn, typewriter';
    $exit = 'none, fadeOut, slideOutLeft, slideOutRight, slideOutUp, slideOutDown, scaleOut, blurOut';
    $emphasis = 'none, pulse, float, shake, wiggle, glow, kenBurns';
    $transitions = 'none, fade, slideLeft, slideUp, zoom, wipeLeft, dissolve';
    $easings = 'linear, easeIn, easeOut, easeInOut, anticipate, backOut';

    $settings = $deck['settings'] ?? [];
    $w = $settings['width'] ?? 1920;
    $h = $settings['height'] ?? 1080;

    $convoLines = [];
    foreach (array_slice($history, -6) as $m) {
        $role = strtoupper((string) ($m['role'] ?? 'user'));
        $convoLines[] = $role . ': ' . ($m['content'] ?? '');
    }
    $convo = implode("\n", $convoLines);

    $scopeRule = $scope === 'deck'
        ? 'You MAY modify any or all slides. Return the full array of slides you changed.'
        : 'Modify ONLY the slide whose id is "' . $targetSlideId . '". Return exactly that one slide.';

    $schema = '{"summary": "one short sentence on what you changed", "slides": [{"id": string, "name"?: string, "durationMs"?: number, "transitionIn"?: string, "transitionMs"?: number, "background"?: {"type","color","gradientFrom","gradientTo","gradientAngle","opacity"}, "elements": [{"id","type","x","y","width","height","rotation","opacity","zIndex","text"?,"fontSize"?,"color"?,"fontFamily"?,"fontWeight"?,"align"?,"lineHeight"?,"letterSpacing"?,"fit"?,"borderRadius"?,"shape"?,"fill"?,"stroke"?,"strokeWidth"?,"animation":{"enter","enterDurationMs","enterDelayMs","enterEasing","emphasis","emphasisDurationMs","exit","exitDurationMs"}}]}]}';

    $lines = [
        'You are a senior motion designer editing an animated marketing slideshow.',
        'Slides render as DOM + framer-motion (HTML/CSS animation), then export to video.',
        'Edit per the user instruction: text, fonts, colors, sizes, positions, backgrounds, per-element animation, and slide timing/transitions.',
        "Coordinate system: origin is top-left, units are pixels. The deck is {$w} x {$h}. Keep elements inside these bounds.",
        'Rules:',
        '- Preserve each element\'s existing "id" when modifying it; only assign a new id for a genuinely new text or shape element.',
        '- Element "type" is one of: text, image, shape. For images you may ONLY reposition/resize/animate by id (never invent "src" or new images).',
        '- Do NOT use emojis or pictographic characters.',
        '- Colors are hex strings. fontFamily must be one of: Montserrat, Inter, Roboto, Open Sans, Lato, Poppins, Playfair Display, Oswald.',
        '- text align is one of left|center|right.',
        "- animation.enter is one of: {$enter}.",
        "- animation.exit is one of: {$exit}.",
        "- animation.emphasis is one of: {$emphasis}.",
        "- animation.enterEasing is one of: {$easings}.",
        "- slide transitionIn is one of: {$transitions}. Durations are in milliseconds.",
        "- Scope: {$scopeRule}",
        '- Keep good contrast, readable sizes, and a balanced layout. Stagger element enter delays for a polished feel.',
        '',
        'Current deck JSON (image data omitted):',
        json_encode($deck),
        '',
        $convo !== '' ? "Conversation so far:\n{$convo}\n" : '',
        "User instruction: {$instruction}",
        '',
        'Return ONLY valid JSON (no markdown, no code fences) of this exact shape:',
        $schema,
    ];
    return implode("\n", $lines);
}

/** Extract a JSON object from model text (handles stray prose/fences). Returns array or null. */
function slideshow_extractJson(string $text): ?array {
    $cleaned = trim($text);
    $cleaned = preg_replace('/^\s*```(?:json)?\s*/i', '', $cleaned);
    $cleaned = preg_replace('/\s*```\s*$/i', '', $cleaned);
    $decoded = json_decode($cleaned, true);
    if (is_array($decoded)) return $decoded;
    $start = strpos($cleaned, '{');
    $end   = strrpos($cleaned, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $decoded = json_decode(substr($cleaned, $start, $end - $start + 1), true);
        if (is_array($decoded)) return $decoded;
    }
    return null;
}
