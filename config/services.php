<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'timeout' => (int) env('TELEGRAM_TIMEOUT', 10),
    ],

    'mamulov_gateway' => [
        'base_url' => env('MAMULOV_GATEWAY_API_BASE_URL', 'https://mamulov.com'),
        'telegram_api_token' => env('MAMULOV_GATEWAY_TELEGRAM_API_TOKEN'),
        'timeout' => (int) env('MAMULOV_GATEWAY_TIMEOUT', 10),
    ],

    'hh' => [
        'client_id' => env('HH_CLIENT_ID'),
        'client_secret' => env('HH_CLIENT_SECRET'),
        'redirect_uri' => env('HH_REDIRECT_URI', env('APP_URL').'/hh/oauth/callback'),
        'base_url' => env('HH_API_BASE_URL', 'https://api.hh.ru'),
        'auth_url' => env('HH_AUTH_URL', 'https://hh.ru/oauth/authorize'),
        'token_url' => env('HH_TOKEN_URL', 'https://api.hh.ru/token'),
        'user_agent' => env('HH_USER_AGENT', 'legal.mamulov.ru resume sync (admin@mamulov.ru)'),
        'timeout' => (int) env('HH_TIMEOUT', 60),
        'download_resumes' => filter_var(env('HH_DOWNLOAD_RESUMES', true), FILTER_VALIDATE_BOOL),
        'browser_capture_token' => env('HH_BROWSER_CAPTURE_TOKEN'),
    ],

];
