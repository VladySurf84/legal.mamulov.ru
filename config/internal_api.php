<?php

return [
    'signature_sync_base_url' => env('SIGNATURE_SYNC_API_BASE_URL', env('APP_URL')),
    'signature_sync_token' => env('SIGNATURE_SYNC_API_TOKEN'),
    'signature_sync_timeout' => (int) env('SIGNATURE_SYNC_API_TIMEOUT', 120),
];
