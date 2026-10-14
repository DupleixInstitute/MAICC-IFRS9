<?php

// Forward-looking module settings read by the code (config:cache safe).
return [
    'sidecar' => [
        // the statistics sidecar for the correlation and regression engines; null when not installed
        'url' => env('FLI_SIDECAR_URL'),
    ],
];
