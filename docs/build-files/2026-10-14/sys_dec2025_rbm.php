<?php
use Illuminate\Support\Facades\DB;
use App\Services\Rbm\RbmReturnService;
use App\Services\Ebanker\LandingZoneReader;

$period = '2025-12';
$rows = DB::table('loan_books')->where('reporting_period', $period)
    ->get(['contract_id', 'product_group', 'product_code', 'tenor', 'overdue_days', 'overdue_principal_date', 'carrying_amount', 'ecl_value', 'ifrs9stage_post_qualitative', 'arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360']);
echo "rows at {$period}: " . $rows->count() . "\n";
$main = $rows->filter(fn ($r) => in_array($r->product_code, array_keys(LandingZoneReader::LOAN_GLS)));
$mega = $rows->filter(fn ($r) => str_contains(strtolower((string) $r->product_group), 'mega'));
echo "main book rows: " . $main->count() . "  mega rows: " . $mega->count() . "\n";
foreach (['main' => $main, 'mega' => $mega] as $name => $set) {
    $cls = []; $stg = []; $ten = []; $dpdSrc = ['date' => 0, 'bucket_only' => 0, 'current' => 0];
    foreach ($set as $r) {
        $c = RbmReturnService::classify((int) $r->overdue_days, (int) $r->tenor);
        $cls[$c] = ($cls[$c] ?? 0) + 1;
        $s = (string) $r->ifrs9stage_post_qualitative; $stg[$s] = ($stg[$s] ?? 0) + 1;
        $t = (int) $r->tenor <= 12 ? 'short(<=12m)' : 'medium/long'; $ten[$t] = ($ten[$t] ?? 0) + 1;
        if ((int) $r->overdue_days > 0) { $dpdSrc['date']++; }
        elseif (array_sum(array_map(fn ($k) => (float) str_replace(',', '', (string) $r->{$k}), ['arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360'])) > 0) { $dpdSrc['bucket_only']++; }
        else { $dpdSrc['current']++; }
    }
    ksort($stg);
    echo strtoupper($name) . " RBM classes: " . json_encode($cls) . "\n";
    echo strtoupper($name) . " stages (post): " . json_encode($stg) . "\n";
    echo strtoupper($name) . " tenor class: " . json_encode($ten) . "\n";
    echo strtoupper($name) . " dpd source: " . json_encode($dpdSrc) . "\n";
    // dpd distribution among NPL
    $npl = $set->filter(fn ($r) => (int) $r->overdue_days >= 181)->map(fn ($r) => (int) $r->overdue_days)->sort()->values();
    echo strtoupper($name) . " dpd >=181: n=" . $npl->count() . " min=" . ($npl->first() ?? '-') . " max=" . ($npl->last() ?? '-') . " over365=" . $npl->filter(fn ($d) => $d > 365)->count() . " over746=" . $npl->filter(fn ($d) => $d > 746)->count() . "\n";
    echo strtoupper($name) . " carrying: " . number_format((float) $set->sum('carrying_amount'), 2) . "  ecl: " . number_format((float) $set->sum('ecl_value'), 2) . "\n";
}
// mega tenor values
echo "mega tenor values: " . json_encode($mega->groupBy('tenor')->map->count()) . "\n";
echo "mega product groups: " . json_encode($rows->filter(fn ($r) => str_contains(strtolower((string) $r->product_group), 'mega'))->groupBy('product_group')->map->count()) . "\n";
// governance settings of interest
foreach (['dpd_basis', 'stage3_missed_instalments', 'stage_cure_months', 'stage3_interest_basis'] as $k) {
    $v = DB::table('eir_governance_settings')->where('key', $k)->orderByDesc('effective_from')->first();
    echo "setting {$k}: " . ($v->value ?? '(none)') . "\n";
}
