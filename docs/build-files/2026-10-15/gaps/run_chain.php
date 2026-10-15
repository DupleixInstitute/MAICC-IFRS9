<?php
// Fresh EIR revenue chain on maiic_ifrs9_gaps, 2024-07 to 2026-09.
// argv[1] = 'head' to load the committed EirRevenueService (before), else the working tree (after).
$root = 'C:/xampp/htdocs/MAICC-IFRS9';
putenv('DB_DATABASE=maiic_ifrs9_gaps'); $_ENV['DB_DATABASE'] = 'maiic_ifrs9_gaps'; $_SERVER['DB_DATABASE'] = 'maiic_ifrs9_gaps';
require $root . '/vendor/autoload.php';
if (($argv[1] ?? '') === 'head') {
    require __DIR__ . '/head/EirRevenueService.php';
}
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_gaps') { fwrite(STDERR, "wrong database\n"); exit(1); }
DB::table('eir_amortisation')->delete();
$svc = app(App\Services\Eir\EirRevenueService::class);
$ids = DB::table('contract_eir')->whereNotNull('locked_at')->orderBy('id')->pluck('contract_id')->all();
$blocked = [];
$p = new DateTimeImmutable('2024-07-01');
while ($p->format('Y-m') <= '2026-09') {
    foreach ($ids as $id) {
        $r = $svc->run((string) $id, $p->format('Y-m'));
        if ($r['status'] === 'BLOCKED') $blocked[] = [$p->format('Y-m'), $id, $r['error']];
    }
    $p = $p->modify('+1 month');
}
$label = $argv[1] ?? 'tree';
$f = fopen(__DIR__ . "/blocked_{$label}.tsv", 'w');
foreach ($blocked as $b) fwrite($f, implode("\t", $b) . "\n");
fclose($f);
DB::statement("DROP TABLE IF EXISTS zz_amort_{$label}");
DB::statement("CREATE TABLE zz_amort_{$label} AS SELECT * FROM eir_amortisation");
echo count($blocked) . " blocked\n";
