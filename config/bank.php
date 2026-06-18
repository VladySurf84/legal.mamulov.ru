<?php

return [
    'tinkoff' => [
        'base_url' => env('TINKOFF_BUSINESS_BASE_URL', 'https://business.tinkoff.ru/openapi'),
        'sync_days' => (int) env('TINKOFF_BUSINESS_SYNC_DAYS', 5),
        'tokens' => json_decode((string) env('TINKOFF_BUSINESS_TOKENS', '{}'), true) ?: [],
    ],
];
