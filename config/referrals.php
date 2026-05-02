<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Share URL template
    |--------------------------------------------------------------------------
    | %s is replaced with the user's referral_code.
    */
    'share_url_template' => rtrim((string) env('FRONTEND_URL', 'https://nexu.capital'), '/')
        . '/register?ref=%s',
];
