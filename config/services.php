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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'zalo' => [
        'app_id' => env('ZALO_APP_ID'),
        'app_secret' => env('ZALO_APP_SECRET'),
        'api_base_url' => env('ZALO_API_BASE_URL', 'https://openapi.zalo.me'),
        'mini_app_id' => env('ZALO_MINI_APP_ID'),

        // Zalo OA / ZNS push notification
        'oa_id'                  => env('ZALO_OA_ID'),
        'oa_secret_key'          => env('ZALO_OA_SECRET_KEY'),
        // Cờ mặc định khi chưa có row Setting 'zalo_notify_enabled'. Admin toggle ghi đè runtime.
        'notify_enabled'         => env('ZALO_NOTIFY_ENABLED', false),
        'zns_template_paid'      => env('ZALO_ZNS_TEMPLATE_PAID'),
        'zns_template_status'    => env('ZALO_ZNS_TEMPLATE_STATUS'),
        'zns_template_cancelled' => env('ZALO_ZNS_TEMPLATE_CANCELLED'),
        'zns_template_shipping'  => env('ZALO_ZNS_TEMPLATE_SHIPPING'),
    ],

];
