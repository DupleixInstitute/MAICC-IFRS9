<?php
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_demo') { exit(1); }
$sets = app(App\Services\Scenario\ScenarioSetService::class);
foreach (range(1, 8) as $id) {
    $s = $sets->sensitivity($id);
    $set = DB::table('governed_scenario_sets')->where('id', $id)->first();
    $booked = (float) DB::table('loan_books')->where('reporting_period', $set->reporting_period)->sum('ecl_value');
    printf("%s set %d weighted %.2f booked %.2f diff %.2f (%.2e)\n", $set->reporting_period, $id, $s['weighted_ecl'], $booked, $booked - $s['weighted_ecl'], ($booked - $s['weighted_ecl']) / $booked);
}
