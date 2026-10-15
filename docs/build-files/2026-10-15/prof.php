<?php
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('memory_limit', '4096M');
use Illuminate\Support\Facades\DB;
echo DB::connection()->getDatabaseName(), "\n";
$n = 0; $tq = 0.0;
DB::listen(function ($q) use (&$n, &$tq) { $n++; $tq += $q->time; });
$s = app(App\Services\Eir\RestructureCandidateService::class);
foreach (['chart', 'fromChartHistory', 'fromCopies', 'fromOpeningBalance', 'fromModificationLog'] as $m) {
    $t = microtime(true); $n0 = $n; $q0 = $tq;
    $r = $s->$m();
    printf("%s: %.1fs, %d rows, %d queries, %.1fs in SQL\n", $m, microtime(true) - $t, count($r), $n - $n0, ($tq - $q0) / 1000);
}
