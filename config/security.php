<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTPS / TLS
    |--------------------------------------------------------------------------
    |
    | These flags are OFF by default so local XAMPP development over http keeps
    | working. Turn them on once a certificate is installed on the server (see
    | docs/SSL_SETUP.md). When force_https is on, all generated URLs use https
    | and the app treats the request as secure (useful behind a TLS-terminating
    | proxy / load balancer).
    |
    */

    'force_https' => env('FORCE_HTTPS', false),

    /*
    | HTTP Strict Transport Security. Only sent on requests that are already
    | secure, so enabling it can never lock you out of an http-only box. Keep
    | it off until https is confirmed working end-to-end.
    */
    'hsts' => [
        'enabled' => env('HSTS_ENABLED', false),
        'max_age' => env('HSTS_MAX_AGE', 31536000), // 1 year
        'include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => env('HSTS_PRELOAD', false),
    ],

    /*
    | Baseline response security headers (safe to leave on everywhere).
    */
    'headers' => [
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'SAMEORIGIN',
        'referrer_policy' => 'strict-origin-when-cross-origin',
    ],
];
