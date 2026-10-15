<?php
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'maiic_ifrs9_fliall') exit(1);
$mega = app(App\Services\MegaFarm\MegaFarmEclService::class);
$p = $argv[1];
if ($mega->loans($p)->exists()) { $mega->run($p, 1, App\Services\Ebanker\LoanBookBuildService::BOOTSTRAP_LABEL); echo "{$p} mega-farm: run\n"; } else echo "{$p} no mega-farm loans\n";
