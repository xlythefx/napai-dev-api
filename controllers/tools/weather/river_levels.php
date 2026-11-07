<?php
/**
 * Live river / dam water levels from PAGASA's Pasig-Marikina-Tullahan Flood
 * Forecasting and Warning System.
 *
 * That host sends no CORS headers, so the browser cannot call it directly —
 * this proxies it and re-serves with CORS plus a short cache.
 *
 * GET -> { ok, fetched_at, stale, data: { stations: [...] } }
 */

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/utils.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

const FFWS_WATER_URL = 'https://pasig-marikina-tullahanffws.pagasa.dost.gov.ph/water/table_list.do';

/**
 * Stations that say something about the Cainta / Taytay / Angono / Binangonan
 * corridor, tagged by which water system they belong to.
 *
 * `basin` matters: Cainta and Taytay flood from the Marikina system, while
 * Angono and Binangonan are Laguna de Bay lakeshore towns whose water arrives
 * from the lake staying high — a different mechanism on a much slower clock.
 * Scoring a lakeshore town off a Marikina headwater gauge points at the wrong
 * river entirely, so the front end filters on this field.
 *
 * `relevance` drives ordering and prominence; everything else on the network is
 * still returned, just unflagged.
 */
const CORRIDOR_STATIONS = [
    // --- Marikina system: Cainta, Taytay ---
    '11104201' => ['key' => 'sto_nino',  'label' => 'Sto. Niño (Marikina River)',   'basin' => 'marikina', 'relevance' => 'primary',
                   'note'  => 'The reference gauge for Marikina–Cainta flooding.'],
    '11103202' => ['key' => 'nangka',    'label' => 'Nangka (Marikina River)',      'basin' => 'marikina', 'relevance' => 'primary',
                   'note'  => 'Upstream of Sto. Niño — rises first.'],
    '11102202' => ['key' => 'montalban', 'label' => 'Montalban (Upper Marikina)',   'basin' => 'marikina', 'relevance' => 'upstream',
                   'note'  => 'Headwaters — a rise here reaches the lowlands in hours.'],
    '11102201' => ['key' => 'rodriguez', 'label' => 'Rodriguez (Upper Marikina)',   'basin' => 'marikina', 'relevance' => 'upstream',
                   'note'  => 'Headwaters.'],
    '11103201' => ['key' => 'san_mateo', 'label' => 'San Mateo (Marikina River)',   'basin' => 'marikina', 'relevance' => 'upstream',
                   'note'  => 'Between the headwaters and Marikina proper.'],

    // --- Laguna de Bay system: Angono, Binangonan ---
    // PAGASA publishes NO alert/alarm/critical for Angono, so it is reported as
    // a level and a trend rather than as a warning state. Inventing a threshold
    // for a safety readout would be worse than admitting there isn't one.
    '11105201' => ['key' => 'angono',    'label' => 'Angono (Laguna de Bay shore)', 'basin' => 'laguna',   'relevance' => 'primary',
                   'note'  => 'Lakeshore level. PAGASA publishes no alert thresholds for this gauge, so it is read as a trend.'],
    '11202201' => ['key' => 'napindan2', 'label' => 'Napindan-2 (Laguna outflow)',  'basin' => 'laguna',   'relevance' => 'primary',
                   'note'  => 'Napindan Channel, where Laguna de Bay drains to the Pasig. Has published thresholds.'],
    '11201201' => ['key' => 'napindan1', 'label' => 'Napindan-1 (Laguna outflow)',  'basin' => 'laguna',   'relevance' => 'primary',
                   'note'  => 'The other side of the Napindan hydraulic control structure.'],

    // --- Shared downstream context ---
    '11303201' => ['key' => 'rosario',   'label' => 'Rosario / Ugong (Pasig River)','basin' => 'pasig',    'relevance' => 'downstream',
                   'note'  => 'Downstream of the Manggahan Floodway junction.'],
];

$result = weather_cached('ffws_water', 180, function () {
    $raw = weather_fetch(FFWS_WATER_URL, '', 15, WEATHER_PAGASA_CA);
    if ($raw === null) return null;

    $rows = json_decode($raw, true);
    if (!is_array($rows) || !$rows) return null;

    $stations = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !isset($row['obscd'])) continue;
        $code = (string) $row['obscd'];
        $meta = CORRIDOR_STATIONS[$code] ?? null;

        [$wl,   $s0] = weather_num($row['wl']    ?? null);
        [$wl30, $s1] = weather_num($row['wl30m'] ?? null);
        [$wl1h, $s2] = weather_num($row['wl1h']  ?? null);
        [$wl2h, $s3] = weather_num($row['wl2h']  ?? null);
        [$alert,    ] = weather_num($row['alertwl']    ?? null);
        [$alarm,    ] = weather_num($row['alarmwl']    ?? null);
        [$critical, ] = weather_num($row['criticalwl'] ?? null);

        // These gauges intermittently report a flat 0.00 when telemetry drops.
        // On a river whose alert level is metres above zero that is a dropout,
        // not a reading — publishing it would show a phantom crash-and-surge.
        $isDropout = function (?float $v) use ($alert): bool {
            return $v !== null && $v === 0.0 && $alert !== null && $alert >= 1.0;
        };
        $dropped = false;
        if ($isDropout($wl))   { $wl   = null; $dropped = true; }
        if ($isDropout($wl30)) { $wl30 = null; $dropped = true; }
        if ($isDropout($wl1h)) { $wl1h = null; $dropped = true; }
        if ($isDropout($wl2h)) { $wl2h = null; $dropped = true; }

        // Positive means rising. Compared against the 1-hour-ago reading so a
        // single flat 30-minute step doesn't read as "steady" during a surge.
        // Anything beyond 3 m/hr is a sensor artefact rather than a flood — the
        // Marikina rose ~1 m/hr at the peak of Ondoy.
        $trend = null;
        if ($wl !== null && $wl1h !== null) {
            $delta = round($wl - $wl1h, 2);
            if (abs($delta) <= 3.0) $trend = $delta;
        }

        $stations[] = [
            'code'          => $code,
            'name'          => $row['obsnm'] ?? $code,
            'key'           => $meta['key'] ?? null,
            'label'         => $meta['label'] ?? ($row['obsnm'] ?? $code),
            'basin'         => $meta['basin'] ?? null,
            'relevance'     => $meta['relevance'] ?? null,
            'note'          => $meta['note'] ?? null,
            'level'         => $wl,
            'level_30m'     => $wl30,
            'level_1h'      => $wl1h,
            'level_2h'      => $wl2h,
            'trend_1h'      => $trend,
            'alert'         => $alert,
            'alarm'         => $alarm,
            'critical'      => $critical,
            'stale_reading' => $s0 || $s1 || $s2 || $s3 || $dropped,
        ];
    }

    if (!$stations) return null;

    // Corridor stations first, in the order declared above.
    $order = array_flip(array_keys(CORRIDOR_STATIONS));
    usort($stations, function ($a, $b) use ($order) {
        $ra = $order[$a['code']] ?? 999;
        $rb = $order[$b['code']] ?? 999;
        return $ra === $rb ? strcmp($a['name'], $b['name']) : $ra - $rb;
    });

    return ['stations' => $stations];
});

if ($result === null) {
    weather_fail('Could not reach the PAGASA flood forecasting system.');
}

weather_ok($result, ['source' => 'PAGASA Pasig-Marikina-Tullahan FFWS']);
