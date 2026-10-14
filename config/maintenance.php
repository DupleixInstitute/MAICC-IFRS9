<?php

// The Maintenance console runs artisan jobs in a child process; null uses the PHP binary running the app.
return [
    'php_binary' => env('MAINTENANCE_PHP_BINARY'),
];
