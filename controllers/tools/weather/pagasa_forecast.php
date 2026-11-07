<?php
/**
 * Official PAGASA data for Rizal, parsed out of the NCR-PRSD regional page.
 *
 * PAGASA has no free public JSON API — tenday.pagasa.dost.gov.ph requires an
 * approved token and sends no CORS headers, and panahon.gov.ph's internal API
 * is down. The public site, however, is CORS-open and server-rendered: it
 * embeds a `let regional = {...}` blob with the provincial outlook, and prints
 * the live Heavy Rainfall Warnings, which name Cainta / Taytay / Angono /
 * Binangonan individually. Parsing happens here rather than in the browser so
 * the front end gets clean JSON and one cached hit serves every visitor.
 *
 * GET -> { ok, fetched_at, stale, data: {...}|null }
 * data is null (with parse_failed set) if PAGASA changes their markup — the
 * page then degrades to Open-Meteo instead of erroring.
 */

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/utils.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

const PAGASA_URL   = 'https://www.pagasa.dost.gov.ph/regional-forecast/ncrprsd';
const PSGC_RIZAL   = '045800000';
const TRACKED_TOWNS = ['Cainta', 'Taytay', 'Angono', 'Binangonan'];

/** Pull the `let regional = {...}` object literal out of the page. */
function pagasa_regional_blob(string $html): ?array
{
    $at = strpos($html, 'let regional');
    if ($at === false) return null;
    $start = strpos($html, '{', $at);
    if ($start === false) return null;

    $depth = 0;
    $len = strlen($html);
    for ($i = $start; $i < $len; $i++) {
        if ($html[$i] === '{') $depth++;
        elseif ($html[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                $decoded = json_decode(substr($html, $start, $i - $start + 1), true);
                return is_array($decoded) ? $decoded : null;
            }
        }
    }
    return null;
}

/** Strip the page to plain, non-empty text lines. */
function pagasa_text_lines(string $html): array
{
    $t = preg_replace('#<script\b.*?</script>#is', ' ', $html);
    $t = preg_replace('#<style\b.*?</style>#is', ' ', $t);
    $t = preg_replace('#<[^>]+>#', "\n", $t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace("\xc2\xa0", ' ', $t);

    $out = [];
    foreach (explode("\n", $t) as $line) {
        $line = trim(preg_replace('/\s+/u', ' ', $line));
        if ($line !== '') $out[] = $line;
    }
    return $out;
}

/**
 * Work out what an advisory sentence says about Rizal.
 *
 * PAGASA writes areas as `Province(Town, Town)` or as a bare `Province`.
 * The catch: Nueva Ecija has a municipality called Rizal, so a plain search for
 * "Rizal" produces false positives. Each `Province(...)` span is therefore
 * attributed to the capitalised name immediately before it and blanked out
 * before the bare-province check runs.
 *
 * @return array{province_wide:bool,towns:string[]}
 */
function pagasa_rizal_scope(string $text): array
{
    $towns = [];
    $masked = $text;

    if (preg_match_all('/\(([^)]*)\)/u', $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $parenStart = $match[0][1];
            $parenEnd   = $parenStart + strlen($match[0][0]);
            $inner      = $match[1][0];

            // The province owning this list is the run of capitalised words just before it.
            $before = substr($text, 0, $parenStart);
            if (!preg_match('/([A-ZÑ][\p{L}.\-\']*(?: [A-ZÑ][\p{L}.\-\']*)*)\s*$/u', $before, $nameMatch)) {
                continue;
            }
            $province  = $nameMatch[1];
            $nameStart = $parenStart - strlen($nameMatch[0]);

            if (strcasecmp($province, 'Rizal') === 0) {
                foreach (explode(',', $inner) as $town) {
                    $town = trim($town);
                    if ($town !== '') $towns[] = $town;
                }
            }

            // Blank the whole `Province(...)` span so its contents can't be
            // mistaken for a bare province mention later.
            $masked = substr_replace($masked, str_repeat(' ', $parenEnd - $nameStart), $nameStart, $parenEnd - $nameStart);
        }
    }

    return [
        'province_wide' => (bool) preg_match('/\bRizal\b/u', $masked),
        'towns'         => array_values(array_unique($towns)),
    ];
}

/** Split the page text into Heavy Rainfall Warning blocks, newest first. */
function pagasa_rainfall_warnings(array $lines): array
{
    $starts = [];
    foreach ($lines as $i => $line) {
        if (preg_match('/^Heavy Rainfall Warning No\.\s*(\d+)/i', $line)) $starts[] = $i;
    }
    if (!$starts) return [];

    $warnings = [];
    foreach ($starts as $n => $start) {
        $end = $starts[$n + 1] ?? count($lines);
        // A block ends at the sign-off; anything after belongs to another section.
        $block = [];
        for ($i = $start; $i < $end; $i++) {
            $block[] = $lines[$i];
            if (stripos($lines[$i], 'For more information and queries') === 0) break;
        }

        $levels = [];
        $advisory = [];
        $hazard = null;
        $system = null;
        $issued = null;
        $next = null;

        foreach ($block as $line) {
            if (preg_match('/^Weather System:\s*(.+)$/i', $line, $m))            { $system = trim($m[1]); continue; }
            if (preg_match('/^Issued at:\s*(.+)$/i', $line, $m))                 { $issued = trim($m[1]); continue; }
            if (preg_match('/^ASSOCIATED HAZARD:\s*(.+)$/i', $line, $m))         { $hazard = trim($m[1]); continue; }
            if (preg_match('/^(RED|ORANGE|YELLOW) WARNING LEVEL:\s*(.+)$/i', $line, $m)) {
                $levels[] = ['level' => strtolower($m[1]), 'areas' => trim($m[2])];
                continue;
            }
            if (preg_match('/next warning to be issued at\s*([^.]+)/i', $line, $m)) { $next = trim($m[1]); }
            // The block's own title also begins with "Heavy" — skip it.
            if (preg_match('/^Heavy Rainfall Warning No\./i', $line)) continue;
            if (preg_match('/^(Meanwhile|Light to moderate|Moderate to heavy|Heavy)/i', $line)) {
                $advisory[] = $line;
            }
        }

        preg_match('/^Heavy Rainfall Warning No\.\s*(\d+)/i', $block[0], $noMatch);

        $warnings[] = [
            'number'         => isset($noMatch[1]) ? (int) $noMatch[1] : null,
            'title'          => $block[0],
            'weather_system' => $system,
            'issued_at'      => $issued,
            'hazard'         => $hazard,
            'levels'         => $levels,
            'advisory_lines' => $advisory,
            'next_warning'   => $next,
            'text'           => implode("\n", $block),
        ];
    }

    return $warnings;
}

$result = weather_cached('pagasa_ncrprsd', 900, function () {
    $html = weather_fetch(PAGASA_URL, null, 20);
    if ($html === null) return null;

    $blob  = pagasa_regional_blob($html);
    $lines = pagasa_text_lines($html);

    $province = null;
    $outlook  = [];
    $issuedProvincial = null;

    if ($blob && isset($blob['provincial']['data'])) {
        $issuedProvincial = $blob['provincial']['issued_date'] ?? null;

        foreach ($blob['provincial']['data'] as $psgc => $entry) {
            if (ltrim((string) $psgc, '0') !== ltrim(PSGC_RIZAL, '0')) continue;

            $province = [
                'name'      => $entry['name'] ?? 'Rizal',
                'psgc'      => (string) $psgc,
                'latitude'  => isset($entry['latitude']) ? (float) $entry['latitude'] : null,
                'longitude' => isset($entry['longitude']) ? (float) $entry['longitude'] : null,
            ];

            // The outlook rows carry no dates; they run forward from the issue date.
            $base = null;
            if ($issuedProvincial) {
                try { $base = new DateTime($issuedProvincial, new DateTimeZone('Asia/Manila')); }
                catch (Exception $e) { $base = null; }
            }

            foreach (($entry['outlook'] ?? []) as $i => $day) {
                $date = null;
                if ($base) {
                    $d = clone $base;
                    $d->modify("+$i day");
                    $date = $d->format('Y-m-d');
                }
                $outlook[] = [
                    'date'                  => $date,
                    'day_description'       => $day['day_description'] ?? null,
                    'day_high'              => isset($day['day_high']) ? (float) $day['day_high'] : null,
                    'day_low'               => isset($day['day_low']) ? (float) $day['day_low'] : null,
                    'day_wind_speed'        => $day['day_wind_speed'] ?? null,
                    'day_wind_direction'    => $day['day_wind_direction'] ?? null,
                    'night_description'     => $day['night_description'] ?? null,
                    'night_high'            => isset($day['night_high']) ? (float) $day['night_high'] : null,
                    'night_low'             => isset($day['night_low']) ? (float) $day['night_low'] : null,
                    'night_wind_speed'      => $day['night_wind_speed'] ?? null,
                    'night_wind_direction'  => $day['night_wind_direction'] ?? null,
                ];
            }
            break;
        }
    }

    $warnings = pagasa_rainfall_warnings($lines);
    $current  = $warnings[0] ?? null;

    // Per-town status from the newest warning: a named warning level beats a
    // general advisory mention, which beats nothing.
    $townStatus = [];
    foreach (TRACKED_TOWNS as $town) {
        $townStatus[$town] = ['status' => 'none', 'source' => null];
    }

    if ($current) {
        foreach (array_reverse($current['levels']) as $lvl) {   // yellow -> orange -> red
            $scope = pagasa_rizal_scope($lvl['areas']);
            foreach (TRACKED_TOWNS as $town) {
                if ($scope['province_wide'] || in_array($town, $scope['towns'], true)) {
                    $townStatus[$town] = ['status' => $lvl['level'], 'source' => 'warning'];
                }
            }
        }
        foreach ($current['advisory_lines'] as $line) {
            $scope = pagasa_rizal_scope($line);
            foreach (TRACKED_TOWNS as $town) {
                if ($townStatus[$town]['status'] !== 'none') continue;
                if ($scope['province_wide'] || in_array($town, $scope['towns'], true)) {
                    $townStatus[$town] = ['status' => 'advisory', 'source' => 'advisory'];
                }
            }
        }
    }

    $thunderstorm = null;
    foreach ($lines as $line) {
        if (stripos($line, 'Thunderstorm Advisory') !== false) { $thunderstorm = $line; break; }
    }

    $system  = $current['weather_system'] ?? null;
    $habagat = $system !== null && (bool) preg_match('/southwest monsoon|habagat/i', $system);

    if (!$province && !$current) return null;   // markup changed beyond recognition

    return [
        'province'          => $province,
        'outlook'           => $outlook,
        'outlook_issued_at' => $issuedProvincial,
        'warning'           => $current,
        'warning_history'   => array_slice($warnings, 1, 3),
        'town_status'       => $townStatus,
        'weather_system'    => $system,
        'habagat'           => $habagat,
        'thunderstorm'      => $thunderstorm,
    ];
});

if ($result === null) {
    // Never a hard failure: the front end hides the PAGASA panels and carries on.
    echo json_encode([
        'ok'           => true,
        'fetched_at'   => gmdate('c'),
        'stale'        => false,
        'data'         => null,
        'parse_failed' => true,
        'error'        => 'PAGASA page unreachable or its markup changed.',
    ]);
    exit;
}

weather_ok($result, ['source' => 'PAGASA NCR-PRSD Regional Forecast']);
