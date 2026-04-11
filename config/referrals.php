<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Share URL template
    |--------------------------------------------------------------------------
    | %s is replaced with the user's referral_code.
    */
    'share_url_template' => rtrim((string) env('FRONTEND_URL', 'https://nexu-web.vercel.app'), '/')
        . '/register?ref=%s',

    /*
    |--------------------------------------------------------------------------
    | Elite tier thresholds (in USD points)
    |--------------------------------------------------------------------------
    | Each key is the EliteTier enum value; 'min' is inclusive.
    | Platinum has no upper bound.
    */
    'tiers' => [
        'bronze'   => ['min' => 0,      'max' => 999],
        'silver'   => ['min' => 1000,   'max' => 4999],
        'gold'     => ['min' => 5000,   'max' => 24999],
        'platinum' => ['min' => 25000,  'max' => null],
    ],
];
