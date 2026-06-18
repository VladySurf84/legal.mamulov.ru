<?php

return [
    'auth' => [
        'user' => env('ADMIN_AUTH_USER', env('ADMIN_BASIC_AUTH_USER')),
        'password' => env('ADMIN_AUTH_PASSWORD', env('ADMIN_BASIC_AUTH_PASSWORD')),
        'google_allowed_emails' => array_values(array_filter(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            explode(',', (string) env('ADMIN_GOOGLE_ALLOWED_EMAILS', ''))
        ))),
    ],

    'basic_auth' => [
        'enabled' => (bool) env('ADMIN_BASIC_AUTH_ENABLED', false),
        'user' => env('ADMIN_BASIC_AUTH_USER'),
        'password' => env('ADMIN_BASIC_AUTH_PASSWORD'),
    ],
];
