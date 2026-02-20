<?php
/**
 * config.php - Private Credentials
 * Keep this file secure and do not share it.
 */

return [
    'google_maps_api_key' => 'AIzaSyDydCCrnu21a-d-1RMP41c64twP0PsaV0Q',
    'mqtt_ws_url'         => 'ws://203.154.4.209:9001',
    'mqtt_user'           => 'admin',
    'mqtt_pass'           => '@Kbs2024!#',
    'topics' => [
        'sikhio' => 'kbs/firepump/sikhio',
        'korn1'  => 'kbs/firepump/korn1',
        'korn2'  => 'kbs/firepump/korn2',
        'kpp'    => 'kbs/firepump/kpp',
    ],
    'api' => [
        'points' => '/api/iot/crud_gis_points.php',
        'latest' => '/api/iot/iot_station_status_latest.php',
    ]
];