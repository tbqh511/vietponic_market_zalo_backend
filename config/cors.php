<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => explode(',', env('ALLOWED_ZALO_ORIGINS', 'https://h5.zdn.vn')),
    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
