<?php
require __DIR__ . '/boot.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
const LABEL = 'Demo, not a MAIIC approval';
$maker = User::findOrFail(1);
$checker = User::where('email', 'demo.approver@maiic.local')->first();
if (! $checker) {
    $checker = new User();
    $checker->forceFill(['name' => 'Demo Approver (not MAIIC)', 'email' => 'demo.approver@maiic.local', 'password' => \Illuminate\Support\Facades\Hash::make(Str::password(32)), 'active' => 1])->save();
    echo "created user {$checker->id}\n";
}
if (! $checker->hasPermissionTo('eir.govern')) { $checker->givePermissionTo('eir.govern'); }
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
$maker->refresh(); $checker->refresh();
// the controller's gate (FliAdjustmentsController: permission:eir.govern on propose/approve)
if (! $maker->can('eir.govern') || ! $checker->can('eir.govern')) { fwrite(STDERR, "eir.govern missing: maker " . (int) $maker->can('eir.govern') . " checker " . (int) $checker->can('eir.govern') . "\n"); exit(1); }
$route = app(\App\Services\Fli\FliRouteService::class);
foreach (['2026-01','2026-02','2026-03','2026-04','2026-05','2026-06','2026-07','2026-08'] as $p) {
    $ym = str_replace('-', '', $p);
    // the system's selection rule for the route (Bootstrap.php before d062a53): the best applied fit on a book-level proxy, by R2 then n
    $best = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')->where('f.reporting_period', $ym)->where('f.verdict', 'applied')->where('f.sign_ok', 1)
        ->whereIn('r.proxy_code', ['NPL_RATIO', 'STAGE3_SHARE', 'DEFAULT_RATE_12M'])->orderByDesc('f.r_squared')->orderByDesc('f.n_obs')->first(['f.id', 'r.statistic_code', 'r.proxy_code', 'r.lag_months', 'f.r_squared']);
    if (! $best) { echo "$p: NO applied book-level fit, nothing approved\n"; continue; }
    $route->proposeFit((int) $best->id, $maker->id, LABEL);
    $route->approveFit((int) $best->id, $checker->id, LABEL);
    $a = $route->approvedFit($p);
    echo "$p: fit {$a->id} {$a->statistic_code} -> {$a->proxy_code} lag {$a->lag_months} R2 " . round($a->r_squared, 4) . " proposed by {$maker->name}, approved by {$checker->name}\n";
}
