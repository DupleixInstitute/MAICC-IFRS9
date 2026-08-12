<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login CAPTCHA (self-hosted, offline)
    |--------------------------------------------------------------------------
    |
    | A lightweight, dependency-free image CAPTCHA rendered on this server with
    | PHP GD. No third-party service, no API keys and no user data leave the
    | box - a good fit for an internal platform. Verification is wired into
    | Fortify (see App\Providers\FortifyServiceProvider).
    |
    | Disable it for automated tests or local convenience with CAPTCHA_ENABLED=false.
    |
    */

    'enabled' => env('CAPTCHA_ENABLED', true),

    // Number of characters in the challenge.
    'length' => env('CAPTCHA_LENGTH', 5),

    // Character set. Ambiguous glyphs (0/O, 1/I/L) are intentionally excluded
    // so the challenge stays human-readable.
    'characters' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',

    // Session key the generated code is stored under.
    'session_key' => 'login_captcha',

    // Local-only bypass code for the manual:screenshots headless bot.
    // Ignored unless APP_ENV=local and the value is non-empty.
    'manual_shot_code' => env('MANUAL_SHOT_CAPTCHA', ''),

    // How long (seconds) a generated code stays valid before it is rejected.
    'ttl' => env('CAPTCHA_TTL', 300),
];
