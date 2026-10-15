p = r'C:\xampp\htdocs\MAICC-IFRS9\app\Services\Fli\FliRouteService.php'
s = open(p, encoding='utf-8').read()
a = s.index("        if ($fit !== null && $set !== null) {\n            $paths = $this->sets->paths((int) $set->id);")
b = s.index("        } elseif ($fit === null && ! str_contains($route, 'overlay')) {")
s = s[:a] + '''        $baseDriver = null;
        if ($fit !== null && $set !== null) {
            [$baseDriver, $scenarios] = $this->regressionAdjustments($period, $fit, $this->sets->paths((int) $set->id));
''' + s[b:]

old = """        $loans = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_prefli')->get(['id', 'contract_id', 'pd_prefli', 'ifrs9stage_post_qualitative', 'product_group']);"""
new = """        $loans = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_prefli')->get(['id', 'contract_id', 'pd_prefli', 'ifrs9stage_post_qualitative', 'calculated_ifrs9_stage', 'ifrs9stage_pre_qualitative', 'remaining_tenor', 'product_group']);"""
assert old in s; s = s.replace(old, new)

old = """                    $post = 0.0; $by = []; $weightSum = 0.0;
                    foreach ($scenarios as $name => $s) {
                        $a = $s['adjustment'] + $loanAdj;
                        $p = $this->methods->apply($method, $pre, ['adjustment' => $a, 'segment_adjustment' => $a]);
                        if ($p === null) {
                            continue;
                        }
                        $by[$name] = ['weight' => $s['weight'], 'pd' => round($p, 8)];
                        $post += $p * $s['weight'] / 100; $weightSum += $s['weight'];
                    }
                    $post = $weightSum > 0 ? max(0.0, min(1.0, $post / ($weightSum / 100))) : $pre;
                }"""
new = """                    // Weighting the loss, not the PD (spec 15.5; IFRS 9 5.5.17(a),
                    // B5.5.42): the ECL engine measures a loan as EAD x LGD x its
                    // stage PD, so the probability-weighted ECL across scenarios is
                    // EAD x LGD x the weighted STAGE PD. The post-FLI PD is the
                    // twelve-month PD whose stage PD is that weighted stage PD, so
                    // the allowance the ECL engine books on it equals the weighted
                    // ECL the scenario sensitivity reports. Averaging the twelve-
                    // month PDs instead overstated the allowance wherever the stage
                    // PD is concave in the twelve-month PD (Stage 2 lifetime over
                    // more than a year; Jensen's inequality, the 0.17 percent gap
                    // of August 2026). Each scenario's PD is capped at 100 percent
                    // before it is weighted, as its own ECL would be.
                    $stageRow = (object) ['stage' => $loan->ifrs9stage_post_qualitative ?? $loan->calculated_ifrs9_stage ?? $loan->ifrs9stage_pre_qualitative, 'remaining_tenor' => $loan->remaining_tenor];
                    $pdSum = 0.0; $stageSum = 0.0; $by = []; $weightSum = 0.0;
                    foreach ($scenarios as $name => $s) {
                        $a = $s['adjustment'] + $loanAdj;
                        $p = $this->methods->apply($method, $pre, ['adjustment' => $a, 'segment_adjustment' => $a]);
                        if ($p === null) {
                            continue;
                        }
                        $p = round($p, 8);
                        $by[$name] = ['weight' => $s['weight'], 'pd' => $p];
                        $pdSum += max(0.0, min(1.0, $p)) * $s['weight'] / 100;
                        $stageSum += ScenarioSetService::stagePd($stageRow, $p) * $s['weight'] / 100;
                        $weightSum += $s['weight'];
                    }
                    if ($weightSum > 0) {
                        $norm = $weightSum / 100;
                        // a loan whose stage resolves to 3 has no inverse; its ECL does not move with the PD, so the weighted PD is recorded
                        $post = ScenarioSetService::pd12ForStagePd($stageRow, $stageSum / $norm) ?? max(0.0, min(1.0, $pdSum / $norm));
                    } else {
                        $post = $pre;
                    }
                }"""
assert old in s; s = s.replace(old, new)

old = """'fit_relationship' => $fit ? "{$fit->statistic_code} -> {$fit->proxy_code} (lag {$fit->lag_months})" : null,"""
new = """'fit_relationship' => $fit ? "{$fit->statistic_code} -> {$fit->proxy_code} (lag {$fit->lag_months})" : null, 'base_driver' => $baseDriver,"""
assert old in s; s = s.replace(old, new)

method = open(r'C:\Users\DELL\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\c9a0f915-2064-4f18-9b98-77857ef71421\scratchpad\fli\method.php', encoding='utf-8').read()
old = """    private function setting(string $key, string $default, CarbonImmutable $asOf): string"""
assert old in s; s = s.replace(old, method + old)
s = s.replace("use App\\Services\\Scenario\\ScenarioSetService;", "use App\\Services\\Scenario\\ScenarioSetService;\nuse App\\Support\\Fli\\AsAtSeries;\nuse App\\Support\\Fli\\SeriesAligner;", 1)
open(p, 'w', encoding='utf-8').write(s)
print('ok')
