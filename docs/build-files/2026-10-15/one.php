<?php
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_fliall') exit(1);
DB::beginTransaction();
$q = fn () => DB::table('loan_books')->where('reporting_period', '2025-12')->where('contract_id', '104430000071');
$l = $q()->first();
$pd = (float) $l->pd_post_fli;
foreach (['set' => $l->fli_by_scenario, 'overlay' => json_encode(['Overlay' => ['weight' => 100.0, 'pd' => $pd]])] as $k => $by) {
    $q()->update(['fli_by_scenario' => $by]);
    (new App\Services\Ecl\TimePhasedEclService())->run(collect([$q()->first()]), '2025-12', 'collection_lgd', null, 'pd_post_fli');
    $r = $q()->first();
    printf("%s: discounted %.2f by %s\n", $k, $r->ecl_value_discounted, $r->ecl_by_scenario);
}
DB::rollBack();
