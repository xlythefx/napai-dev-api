<?php
/**
 * Live rainfall accumulations from PAGASA's Pasig-Marikina-Tullahan FFWS
 * telemetered rain gauges. Same CORS problem as river_levels.php.
 *
 * Rain that has already fallen over the Marikina watershed is the earliest
 * honest warning for the Cainta–Taytay lowlands, since it arrives downstream
 * with a lag of hours. Forecast rain is a guess; this is a measurement.
 *
 * GET -> { ok, fetched_at, stale, data: { gauges: [...], observed_at } }
 */

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/utils.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

const FFWS_RAIN_URL = 'https://pasig-marikina-tullahanffws.pagasa.dost.gov.ph/rainfall/table_list.do';

/**
 * Gauges worth surfacing. "watershed" gauges sit upstream in the Marikina
 * catchment — they lead; "local" gauges are in the corridor itself.
 */
const NOTABLE_GAUGES = [
    '11105101' => ['key' => 'taytay',       'label' => 'Taytay',                'zone' => 'local'],
    '11105301' => ['key' => 'antipolo',     'label' => 'Antipolo',              'zone' => 'watershed'],
    '11102101' => ['key' => 'sitio_wawa',   'label' => 'Sitio Wawa (Montalban)','zone' => 'watershed'],
    '11103103' => ['key' => 'boso_boso',    'label' => 'Boso Boso',             'zone' => 'watershed'],
    '11103106' => ['key' => 'nangka',       'label' => 'Nangka',                'zone' => 'local'],
    '11103107' => ['key' => 'marikina',     'label' => 'Marikina (Youth Camp)', 'zone' => 'local'],
    '11102103' => ['key' => 'macabud',      'label' => 'Macabud',               'zone' => 'watershed'],
    '11103104' => ['key' => 'mt_aries',     'label' => 'Mt. Aries',             'zone' => 'watershed'],
    '11102102' => ['key' => 'mt_oro',       'label' => 'Mt. Oro',               'zone' => 'watershed'],
    '11103105' => ['key' => 'bagong_nayon', 'label' => 'Bagong Nayon',          'zone' => 'watershed'],
    '11203101' => ['key' => 'science_gdn',  'label' => 'Science Garden (QC)',   'zone' => 'local'],
];

// The endpoint returns all-null accumulations unless given a Manila-local stamp.
$stamp = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('YmdHi');

$result = weather_cached('ffws_rain', 180, function () use ($stamp) {
    $raw = weather_fetch(FFWS_RAIN_URL, 'ymdhm=' . $stamp, 15, WEATHER_PAGASA_CA);
    if ($raw === null) return null;

    $rows = json_decode($raw, true);
    if (!is_array($rows) || !$rows) return null;

    $gauges = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !isset($row['obscd'])) continue;
        $code = (string) $row['obscd'];
        $meta = NOTABLE_GAUGES[$code] ?? null;
        if ($meta === null) continue;

        [$rf30, ] = weather_num($row['rf30m'] ?? null);
        [$rf1,  ] = weather_num($row['rf01h'] ?? null);
        [$rf3,  ] = weather_num($row['rf03h'] ?? null);
        [$rf6,  ] = weather_num($row['rf06h'] ?? null);
        [$rf12, ] = weather_num($row['rf12h'] ?? null);
        [$rf24, ] = weather_num($row['rf24h'] ?? null);

        $gauges[] = [
            'code'  => $code,
            'key'   => $meta['key'],
            'label' => $meta['label'],
            'zone'  => $meta['zone'],
            'mm_30m' => $rf30,
            'mm_1h'  => $rf1,
            'mm_3h'  => $rf3,
            'mm_6h'  => $rf6,
            'mm_12h' => $rf12,
            'mm_24h' => $rf24,
            'offline' => $rf1 === null && $rf24 === null,
        ];
    }

    if (!$gauges) return null;

    return ['gauges' => $gauges, 'observed_at' => $stamp];
});

if ($result === null) {
    weather_fail('Could not reach the PAGASA rain gauge network.');
}

weather_ok($result, ['source' => 'PAGASA Pasig-Marikina-Tullahan FFWS']);
