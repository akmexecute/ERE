<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'maps' => [
        'satellite_url' => env('MAP_SATELLITE_URL', 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'),
        'satellite_attribution' => env('MAP_SATELLITE_ATTRIBUTION', '&copy; Esri, Maxar, Earthstar Geographics'),
    ],

    'fayda' => [
        'enabled' => env('FAYDA_API_ENABLED', false),
        'endpoint' => env('FAYDA_API_ENDPOINT'),
        'client_id' => env('FAYDA_CLIENT_ID'),
        'api_key' => env('FAYDA_API_KEY'),
        'timeout' => (int) env('FAYDA_TIMEOUT_SECONDS', 10),
    ],

    'telebirr' => [
        'enabled' => env('TELEBIRR_ENABLED', false),
        'app_id' => env('TELEBIRR_APP_ID', 'MOCK_TB_APP_001'),
        'app_key' => env('TELEBIRR_APP_KEY', 'telebirr_mock_signing_key_secret_2026'),
        'public_key' => env('TELEBIRR_PUBLIC_KEY'),
        'short_code' => env('TELEBIRR_SHORT_CODE'),
        'notify_url' => env('TELEBIRR_NOTIFY_URL'),
        'endpoint' => env('TELEBIRR_ENDPOINT', 'https://app.ethiomobilemoney.et:2121/ammapi/payment/service-openup/toTradeWebPay'),
    ],

    'cbe_birr' => [
        'enabled' => env('CBE_BIRR_ENABLED', false),
        'merchant_id' => env('CBE_BIRR_MERCHANT_ID'),
        'app_secret' => env('CBE_BIRR_APP_SECRET'),
        'endpoint' => env('CBE_BIRR_ENDPOINT'),
    ],

    'chapa' => [
        'enabled' => env('CHAPA_ENABLED', false),
        'secret_key' => env('CHAPA_SECRET_KEY'),
        'public_key' => env('CHAPA_PUBLIC_KEY'),
        'webhook_secret' => env('CHAPA_WEBHOOK_SECRET'),
        'endpoint' => env('CHAPA_ENDPOINT', 'https://api.chapa.co/v1'),
    ],

];
