<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('memory_limit', '-1');
$r = App\Services\Reports\Ifrs9NoteService::build('2025-12', '2026-09');
foreach ($r['checks'] as $c) if ($c['level'] !== 'pass') echo $c['level'], ' ', $c['key'], ': ', substr($c['detail'], 0, 200), "\n";
