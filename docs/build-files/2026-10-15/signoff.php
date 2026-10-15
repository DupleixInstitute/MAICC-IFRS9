<?php
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_fliall') exit(1);
$u = App\Models\User::firstOrCreate(['email' => 'second.admin@localhost.com'], ['name' => 'Second Admin', 'password' => Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(12)))]);
if (method_exists($u, 'assignRole') && ! $u->hasRole('admin')) $u->assignRole('admin');
echo "second user id {$u->id}, roles " . implode(',', $u->getRoleNames()->all()) . "\n";
$close = app(App\Services\Fli\FliCloseService::class);
foreach (['2025-12', '2026-09'] as $p) {
    $id = (int) DB::table('fli_run_snapshots')->where('reporting_period', $p)->where('status', 'RECORDED')->max('id');
    try { $close->signOff($id, 1, 'x'); echo "{$p}: recorder signed off (unexpected)\n"; } catch (RuntimeException $e) { echo "{$p} record {$id}: recorder refused: " . $e->getMessage() . "\n"; }
    try { $close->signOff($id, $u->id); echo "{$p}: signed without commentary\n"; } catch (RuntimeException $e) { echo "{$p}: no commentary refused: " . substr($e->getMessage(), 0, 140) . "\n"; }
    $close->signOff($id, $u->id, 'Verification sign-off on maiic_ifrs9_fliall: the loans left undiscounted are named on the record with their reason.');
    $s = DB::table('fli_run_snapshots')->where('id', $id)->first();
    echo "{$p} record {$id}: {$s->status} by user {$s->signed_off_by} (created by {$s->created_by}); discounted {$s->booked_ecl_discounted} vs {$s->weighted_ecl_discounted}\n";
}
