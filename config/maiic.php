<?php

// Settings the MAIIC code reads outside the framework's own config (config:cache safe).
return [
    // re-seed the help articles over edited ones (HelpContentSeeder, HelpAdminContentSeeder)
    'help_seed_refresh' => env('HELP_SEED_REFRESH', false),
    // the browser the manual screenshot command drives; null finds one
    'browser_path' => env('MAIIC_BROWSER_PATH'),
    'manual_shot' => [
        'email' => env('MANUAL_SHOT_EMAIL', 'admin@localhost.com'),
        'password' => env('MANUAL_SHOT_PASSWORD'),
    ],
];
