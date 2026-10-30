<?php

// Карта в адмін-панелі (MapLibre GL JS)
return [
    'style_url' => env('MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/liberty'),

    // Початковий вигляд, поки точку не вибрано: уся Україна. [lng, lat], як у MapLibre
    'default_center' => [31.17, 48.38],
    'default_zoom' => 5,
    'point_zoom' => 16,

    'attribution' => '© OpenStreetMap contributors',
];
