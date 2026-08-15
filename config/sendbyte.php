<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your SendByte API key. Use an sk_test_ key while developing (no domain
    | verification required) and an sk_live_ key once your sending domain is
    | verified. Generate keys from https://app.sendbyte.africa.
    |
    */

    'api_key' => env('SENDBYTE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    */

    'base_url' => env('SENDBYTE_BASE_URL', 'https://api.sendbyte.africa/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('SENDBYTE_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | SendByte signs every webhook payload with HMAC-SHA256 using a signing
    | secret shown in your dashboard when you create the endpoint. Confirm the
    | exact header name(s) SendByte sends against your dashboard/API reference
    | when you set this up — 'signature_header' and 'timestamp_header' below
    | are configurable so you can match them without touching package code.
    |
    */

    'webhook' => [

        // Secret used to verify the HMAC signature on incoming webhooks.
        'signing_secret' => env('SENDBYTE_WEBHOOK_SECRET'),

        // Header carrying the HMAC signature.
        'signature_header' => env('SENDBYTE_WEBHOOK_SIGNATURE_HEADER', 'X-Sendbyte-Signature'),

        // Optional header carrying a unix timestamp, used for replay protection.
        // Set to null to skip timestamp validation.
        'timestamp_header' => env('SENDBYTE_WEBHOOK_TIMESTAMP_HEADER', 'X-Sendbyte-Timestamp'),

        // How many seconds of clock drift to tolerate when timestamp_header is set.
        'tolerance' => (int) env('SENDBYTE_WEBHOOK_TOLERANCE', 300),

        // Auto-registered route that receives webhook POSTs.
        'route' => [
            'enabled' => env('SENDBYTE_WEBHOOK_ROUTE_ENABLED', true),
            'path' => env('SENDBYTE_WEBHOOK_PATH', 'webhooks/sendbyte'),
            'middleware' => ['api'],
        ],
    ],

];
