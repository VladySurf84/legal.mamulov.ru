<?php

return [
    'pgsql' => [
        'host' => env('LEGACY_PG_HOST', 'mamulov.ru'),
        'port' => env('LEGACY_PG_PORT', 5432),
        'database' => env('LEGACY_PG_DATABASE', 'mp'),
        'username' => env('LEGACY_PG_USERNAME', 'mp_user'),
        'password' => env('LEGACY_PG_PASSWORD'),
        'sslmode' => env('LEGACY_PG_SSLMODE', 'prefer'),
    ],
];
