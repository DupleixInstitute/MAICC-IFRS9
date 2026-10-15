<?php
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$h = new App\Services\Ebanker\InstalmentChartHistory(new App\Services\Ebanker\LandingZoneReader());
$rows = Illuminate\Support\Facades\DB::table('loan_books')->where('loan_portfolio_id', '<>', 8)->where('reporting_period', '>=', '2024-07')->get(['external_identity_id', 'reporting_period']);
$out = [];
foreach ($rows as $r) {
    $me = Carbon\CarbonImmutable::parse($r->reporting_period . '-01')->endOfMonth()->toDateString();
    $c = $h->at((string) $r->external_identity_id, $me);
    if ($c && $c['collapsed']) { $out[$r->external_identity_id][] = $r->reporting_period . ($c['unreadable'] ? 'U' : ''); }
}
foreach ($out as $a => $ps) { echo $a, ' ', count($ps), ' months ', $ps[0], '..', end($ps), "\n"; }
