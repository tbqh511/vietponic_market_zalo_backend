<?php

/*
 | JWT config
 | NOTE: In production override these values via your .env file. The analyzer in
 | this environment flags env(...) as missing, so this file contains safe
 | defaults. Replace 'secret' and other values in your .env as needed.
*/

return [
    // Secret used to sign tokens. Replace with env('JWT_SECRET') in production.
    'secret' => 'change-me',

    // Optional asymmetric keys (public/private)
    'keys' => [
        'public' => null,
        'private' => null,
    ],

    // Token time-to-live (seconds). Default 3600 (1 hour). Set to null if you want no expiry.
    'ttl' => 3600,
    'refresh_ttl' => 20160,

    // Algorithm to sign tokens
    'algo' => 'HS256',

    // Required claims
    'required_claims' => ['iss', 'iat', 'nbf', 'sub', 'jti'],

    // Blacklist (allows invalidating tokens)
    'blacklist_enabled' => false,

    'providers' => [
        'user' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        'storage' => [
            'driver' => 'database',
            'table' => 'jwt_blacklist',
        ],
    ],
];
