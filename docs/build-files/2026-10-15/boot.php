<?php
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'C:/xampp/htdocs/MAICC-IFRS9/vendor/autoload.php';
$app = require 'C:/xampp/htdocs/MAICC-IFRS9/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require $argv[1];
