<?php

return [
    'base_url' => env('EDO_LIGHT_BASE_URL', 'https://edo-gismt.crpt.ru'),
    'true_api_base_url' => env('TRUE_API_BASE_URL', 'https://markirovka.crpt.ru/api/v3/true-api'),
    'cryptcp_path' => env('CRYPTCP_PATH', '/opt/cprocsp/bin/amd64/cryptcp'),
    'cryptcp_work_dir' => env('CRYPTCP_WORK_DIR', storage_path('app/cryptopro')),
];
