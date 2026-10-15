<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows = json_decode(file_get_contents($argv[1]), true);
file_put_contents($argv[2], json_encode(app(App\Services\Eir\FeeWorkbookTieOut::class)->check($rows), JSON_PRETTY_PRINT));
