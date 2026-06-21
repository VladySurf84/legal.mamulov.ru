<?php

return [
    'tinkoff' => [
        'base_url' => env('TINKOFF_BUSINESS_BASE_URL', 'https://business.tinkoff.ru/openapi'),
        'sync_days' => (int) env('TINKOFF_BUSINESS_SYNC_DAYS', 5),
        'sync_proxy_url' => env('TINKOFF_SYNC_PROXY_URL'),
        'sync_proxy_token' => env('TINKOFF_SYNC_PROXY_TOKEN'),
    ],
];
