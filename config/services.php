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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'crypto' => [
        'provider'       => env('CRYPTO_PROVIDER', 'stub'),
        'webhook_secret' => env('WEBHOOK_SECRET', ''),
        'nowpayments'    => [
            'api_key' => env('NOWPAYMENTS_API_KEY'),
            'sandbox' => env('NOWPAYMENTS_SANDBOX', true),
        ],
    ],

    'goldapi' => [
        'key' => env('GOLD_API_KEY'),
    ],

    'newsapi' => [
        'key' => env('NEWS_API_KEY'),
    ],

];
