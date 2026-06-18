<?php

return [
    'auth' => [
        'user' => env('ADMIN_AUTH_USER', env('ADMIN_BASIC_AUTH_USER')),
        'password' => env('ADMIN_AUTH_PASSWORD', env('ADMIN_BASIC_AUTH_PASSWORD')),
    ],

    'basic_auth' => [
        'enabled' => (bool) env('ADMIN_BASIC_AUTH_ENABLED', false),
        'user' => env('ADMIN_BASIC_AUTH_USER'),
        'password' => env('ADMIN_BASIC_AUTH_PASSWORD'),
    ],
];
