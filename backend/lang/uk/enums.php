<?php

return [
    'user_role' => [
        'admin' => 'Адміністратор',
        'driver' => 'Водій',
    ],
    'location_source' => [
        'geocoder' => 'Геокодер',
        'manual' => 'Вручну',
    ],
    'order_status' => [
        'new' => 'Нове',
        'planned' => 'В маршруті',
        'delivered' => 'Доставлено',
        'cancelled' => 'Скасовано',
    ],
    'route_status' => [
        'draft' => 'Чернетка',
        'assigned' => 'Призначено',
        'in_progress' => 'В дорозі',
        'completed' => 'Завершено',
    ],
    'route_stop_status' => [
        'pending' => 'Очікує',
        'completed' => 'Виконано',
        'skipped' => 'Пропущено',
    ],
    'route_stop_order_status' => [
        'pending' => 'Очікує',
        'delivered' => 'Доставлено',
        'not_delivered' => 'Не доставлено',
    ],
];
