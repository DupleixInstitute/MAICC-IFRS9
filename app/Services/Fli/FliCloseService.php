<?php

namespace App\Services\Fli;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use App\Services\Reports\EclBuildUpService;
use App\Services\Scenario\ScenarioSetService;
use App\Support\Fli\AsAtSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Closing the forward-looking month (FLI module specification v1, decisions
 * D-F3 and D-F11, section 7).
 *
 * record() measures the ECL of every loan under every scenario of the set the
 * route applied, weights it, and checks that the weighted figure equals the
 * allowance booked on the post-FLI PD. Each scenario's ECL is the loan's
 * booked ECL re-based from the booked stage PD to the scenario's stage PD
 * (lifetime for Stage 2, 1 for Stage 3), which is the ECL engine's own
 * measure (EAD x LGD x stage PD) at the scenario's PD. The loan-level rows go
 * to ecl_scenario_results; the totals, the inputs in force and the movement
 * against the previous month-end go to one fli_run_snapshots row.
 *
 * signOff() is the second person's approval of that record. A record whose
 * weighted ECL does not reconcile cannot be signed off, and a movement above
 * the governed tolerance needs a commentary first.
 *
 * The discounted allowance (14 October 2026; D-F3, D-F11, spec v4 6.10.1
 * step 7). The month-end ECL is discounted by the time-phased engine, which
 * leaves ecl_value as the engine measure above and writes the discounted
 * allowance beside it with each scenario's shortfall (ecl_by_scenario). The
 * record therefore keeps the build-up before and after FLI on the engine
 * measure, and beside it the discounted allowance: weighted over the same
 * scenarios from the engine's own per-scenario figures and checked against
 * the discounted allowance booked, with the reconciliation from the engine
 * measure (the projection effect and the discounting effect) and the loans
 * that could not be discounted, named. A record whose discounted figures do
 * not reconcile cannot be signed off; a month with loans left undiscounted
 * needs a commentary saying why. The Mega Farm programme (D30) is measured by
 * its own engine and is shown as its own line, outside the record's figures.
 *
 * Nothing here changes a loan, a PD or an ECL.
 */
class FliCloseService
{
    public function __construct(private EclBuildUpService $buildUp, private FliModelRegistry $registry, private GovernanceService $governance)
    {
    }

    /** @return array<string,mixed> the snapshot row, decoded */
    public function record(string $period, ?int $userId = null): array
    {
        $all = $this->buildUp->loans($period);
        if ($all->isEmpty()) {
            throw new RuntimeException("No loan book is held for {$period}.");
        }
        [$programmeLoans, $loans] = $this->buildUp->split($all, $period);
        if ($loans->isEmpty()) {
            throw new RuntimeException("Every loan of {$period} is in the Mega Farm programme, which its own engine measures (D30); there is no MAIIC book to record.");
        }
        $up = $this->buildUp->buildUp($loans, $period);
        if (! $up['available'] || $up['basis'] !== 'post_fli') {
            throw new RuntimeException("The ECL of {$period} is not on the PD after FLI, so the month cannot be closed: " . ($up['reason'] ?? 'calculate the ECL on the post-FLI PD first.'));
        }
        $applied = DB::table('audit_logs')->where('action', 'FLI Route Applied')->where('reporting_period', $period)->max('created_at');
        $calculated = DB::table('loan_books')->where('reporting_period', $period)->max('ecl_calculated_at');
        if ($applied !== null && $calculated !== null && CarbonImmutable::parse($calculated)->lt(CarbonImmutable::parse($applied))) {
            throw new RuntimeException("The ECL of {$period} was calculated at {$calculated}, before the forward-looking route last ran at {$applied}. Calculate the ECL again, then close the month.");
        }
        $setIds = $loans->pluck('fli_set_id')->filter()->unique()->values();
        if ($setIds->count() > 1) {
            throw new RuntimeException("The loans of {$period} ran under more than one scenario set (" . $setIds->implode(', ') . '). Apply the route again so that every loan runs under the set in force.');
        }
        $set = $setIds->isEmpty() ? null : DB::table('governed_scenario_sets')->where('id', $setIds->first())->first();
        $scenarios = $set ? DB::table('governed_scenarios')->where('set_id', $set->id)->orderBy('order_position')->get(['name', 'weight', 'is_base']) : collect();
        if ($scenarios->isEmpty()) {
            throw new RuntimeException("No scenario set was applied to the loans of {$period}; apply the forward-looking route first.");
        }
        $weights = $scenarios->mapWithKeys(fn ($s) => [$s->name => (float) $s->weight])->all();
        $byScenarioRaw = DB::table('loan_books')->where('reporting_period', $period)->pluck('fli_by_scenario', 'id');
        $hasDiscColumn = DB::getSchemaBuilder()->hasColumn('loan_books', 'ecl_by_scenario');
        $discRaw = $hasDiscColumn ? DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('ecl_by_scenario')->pluck('ecl_by_scenario', 'id') : collect();
        $resultsDiscColumn = DB::getSchemaBuilder()->hasColumn('ecl_scenario_results', 'ecl_discounted');
        $disc = EclBuildUpService::discounted($loans);
        $perDisc = array_fill_keys(array_keys($weights), 0.0);
        $weightedDisc = 0.0;
        $bookedDisc = 0.0;
        $discMissing = [];
        $versionIds = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('fli_model_version_id')->distinct()->pluck('fli_model_version_id');
        $version = $versionIds->count() === 1 ? DB::table('fli_model_versions')->where('id', $versionIds->first())->first() : null;

        $rows = [];
        $per = array_fill_keys(array_keys($weights), 0.0);
        $byStage = [];
        $byPortfolio = [];
        $booked = 0.0;
        $weighted = 0.0;
        $counted = 0;
        foreach ($loans as $l) {
            $x = EclBuildUpService::lineage($l);
            if ($x['ecl'] === null) {
                continue;
            }
            $counted++;
            $shape = (object) ['stage' => (string) ($x['stage'] ?? ''), 'remaining_tenor' => $l->remaining_tenor];
            // the discounted shortfall of each scenario, as the time-phased engine measured it
            $isDisc = ($l->ecl_discount_status ?? null) === 'CALCULATED_TIME_PHASED' && $l->ecl_value_discounted !== null;
            $tpBy = $isDisc ? (json_decode((string) ($discRaw[$l->id] ?? ''), true) ?: null) : null;
            if ($isDisc) {
                $bookedDisc += (float) $l->ecl_value_discounted;
                if ($tpBy === null && (float) $l->ecl_value_discounted != 0.0) {
                    $discMissing[] = (string) $l->contract_id;
                }
            }
            $hPost = $x['horizon_pd_post'];
            $by = json_decode((string) ($byScenarioRaw[$l->id] ?? ''), true) ?: [];
            $loanWeighted = 0.0;
            $stage = $x['stage'] === null ? '-' : (string) $x['stage'];
            $portfolio = (int) ($l->loan_portfolio_id ?? 0);
            foreach ($weights as $name => $w) {
                $pd = isset($by[$name]['pd']) ? (float) $by[$name]['pd'] : null;
                if ($stage === '3' || $pd === null || $hPost <= 0) {
                    // Stage 3 is measured on the default that has happened: no scenario moves it
                    $ecl = (float) $x['ecl'];
                    $stagePd = $stage === '3' ? 1.0 : $hPost;
                } else {
                    $stagePd = ScenarioSetService::stagePd($shape, $pd);
                    $ecl = (float) $x['ecl'] * $stagePd / $hPost;
                }
                $per[$name] += $ecl;
                $loanWeighted += $ecl * $w / 100;
                $eclDisc = null;
                if ($isDisc) {
                    // a nil exposure has no scenario rows: its discounted loss is nil in each
                    $eclDisc = $tpBy === null ? ((float) $l->ecl_value_discounted == 0.0 ? 0.0 : null) : (isset($tpBy[$name]['discounted']) ? (float) $tpBy[$name]['discounted'] : null);
                    if ($eclDisc === null && $tpBy !== null) {
                        $discMissing[] = (string) $l->contract_id . " ({$name})";
                    }
                    $perDisc[$name] += (float) $eclDisc;
                    $weightedDisc += (float) $eclDisc * $w / 100;
                }
                $byStage[$stage][$name] = ($byStage[$stage][$name] ?? 0.0) + $ecl;
                $byPortfolio[$portfolio][$name] = ($byPortfolio[$portfolio][$name] ?? 0.0) + $ecl;
                $rows[] = ['reporting_period' => $period, 'loan_book_id' => $l->id, 'contract_id' => $l->contract_id, 'loan_portfolio_id' => $l->loan_portfolio_id, 'stage' => $stage,
                    'scenario' => mb_substr($name, 0, 64), 'weight' => $w, 'pd12' => $pd, 'stage_pd' => round($stagePd, 10), 'ecl' => round($ecl, 2)]
                    + ($resultsDiscColumn ? ['ecl_discounted' => $eclDisc === null ? null : round($eclDisc, 2)] : []);
            }
            $booked += (float) $x['ecl'];
            $weighted += $loanWeighted;
            $byStage[$stage]['_booked'] = ($byStage[$stage]['_booked'] ?? 0.0) + (float) $x['ecl'];
            $byPortfolio[$portfolio]['_booked'] = ($byPortfolio[$portfolio]['_booked'] ?? 0.0) + (float) $x['ecl'];
        }
        $difference = round($weighted - $booked, 2);
        // the post-FLI PD is stored to eight decimals, so the weighted figure ties to the booked one to that rounding
        $reconciles = abs($difference) <= max(1.0, 1e-6 * abs($booked));
        // each loan's discounted allowance is stored to the cent, so the weighted figure ties to half a cent a loan
        $discDifference = round($weightedDisc - $bookedDisc, 2);
        $discReconciles = $disc['discounted_loans'] === 0 ? null : ($discMissing === [] && abs($discDifference) <= max(1.0, 0.005 * $disc['discounted_loans'] + 1e-6 * abs($bookedDisc)));
        $unresolvedNamed = $this->unresolved($loans);

        $prior = DB::table('fli_run_snapshots')->where('reporting_period', '<', $period)->whereIn('status', ['RECORDED', 'SIGNED_OFF'])->orderByDesc('reporting_period')->orderByDesc('id')->first();
        $priorBooked = $prior ? (float) $prior->booked_ecl : null;
        $movement = $priorBooked && $priorBooked != 0.0 ? ($booked - $priorBooked) / $priorBooked : null;
        $tolerance = $this->tolerance($period);
        $needsCommentary = $movement !== null && $tolerance !== null && abs($movement) > $tolerance;

        $names = DB::table('loan_portfolios')->pluck('name', 'id')->all();
        $round = fn (array $a) => array_map(fn ($v) => round($v, 2), $a);
        $perScenario = [];
        foreach ($weights as $name => $w) {
            $perScenario[] = ['scenario' => $name, 'weight' => $w, 'is_base' => (bool) ($scenarios->firstWhere('name', $name)->is_base ?? false), 'ecl' => round($per[$name], 2), 'contribution' => round($per[$name] * $w / 100, 2)];
        }
        ksort($byStage);
        $inputs = [
            'model_version' => $version ? ['id' => (int) $version->id, 'version' => (int) $version->version, 'relationship' => "{$version->statistic_code} -> {$version->proxy_code} (lag {$version->lag_months})", 'effective_from' => $version->effective_from, 'calibrated_period' => $version->calibrated_period, 'r_squared' => (float) $version->r_squared] : null,
            'scenario_set' => $set ? ['id' => (int) $set->id, 'name' => $set->name, 'version' => (int) $set->version, 'status' => $set->status, 'weights' => $weights] : null,
            'overlays' => $up['overlay_lines'],
            'pd_segment_runs' => DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_segment_run_id')->distinct()->pluck('pd_segment_run_id')->all(),
            'ecl_runs' => DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('ecl_calculation_run_id')->distinct()->pluck('ecl_calculation_run_id')->all(),
            'loan_book_loads' => DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('build_load_id')->distinct()->pluck('build_load_id')->all(),
            'ecl_calculated_at' => $calculated, 'fli_applied_at' => $applied,
            'discount_basis' => 'TimePhasedEclService at the locked original EIR (EIR_ORIGINAL); a floating-rate loan at its current EIR at the month-end (EIR_CURRENT_FLOATING, IFRS 9 B5.5.44, D-F6), unresolved and named where it cannot be determined',
            'macro' => $this->vintage($period, $version?->statistic_code),
        ];

        $perScenarioDisc = [];
        foreach ($weights as $name => $w) {
            $perScenarioDisc[$name] = ['ecl' => round($perDisc[$name], 2), 'contribution' => round($perDisc[$name] * $w / 100, 2)];
        }
        $perScenario = array_map(fn ($r) => $r + ['ecl_discounted' => $disc['discounted_loans'] > 0 ? $perScenarioDisc[$r['scenario']]['ecl'] : null,
            'contribution_discounted' => $disc['discounted_loans'] > 0 ? $perScenarioDisc[$r['scenario']]['contribution'] : null], $perScenario);
        $discFields = DB::getSchemaBuilder()->hasColumn('fli_run_snapshots', 'booked_ecl_discounted') ? [
            'programme_loans' => $programmeLoans->count(), 'programme_ecl' => $programmeLoans->isEmpty() ? null : round((float) $programmeLoans->sum(fn ($l) => (float) ($l->ecl_value ?? 0)), 2),
            'discounted_loans' => $disc['discounted_loans'], 'discount_unresolved_loans' => $disc['unresolved_loans'], 'unresolved_ecl' => $disc['unresolved_ecl'],
            'projection_effect' => $disc['discounted_loans'] > 0 ? $disc['projection_effect'] : null, 'discounting_effect' => $disc['discounted_loans'] > 0 ? $disc['discounting_effect'] : null,
            'booked_ecl_discounted' => $disc['discounted_loans'] > 0 ? round($bookedDisc, 2) : null, 'weighted_ecl_discounted' => $disc['discounted_loans'] > 0 ? round($weightedDisc, 2) : null,
            'discounted_difference' => $disc['discounted_loans'] > 0 ? $discDifference : null, 'discounted_reconciles' => $discReconciles,
            'discount_detail' => json_encode(['engine_ecl_of_discounted' => $disc['engine_ecl'], 'undiscounted_time_phased' => $disc['undiscounted'], 'complete' => $disc['complete'],
                'unresolved' => $unresolvedNamed, 'without_scenario_figures' => array_slice($discMissing, 0, 20)]),
        ] : [];

        $snapshot = DB::transaction(function () use ($period, $version, $set, $loans, $up, $booked, $weighted, $difference, $reconciles, $perScenario, $byStage, $byPortfolio, $names, $round, $inputs, $priorBooked, $movement, $needsCommentary, $userId, $rows, $counted, $discFields) {
            // an earlier record of the same month is replaced by this one, and its loan rows with it
            $old = DB::table('fli_run_snapshots')->where('reporting_period', $period)->whereIn('status', ['RECORDED', 'SIGNED_OFF'])->pluck('id');
            DB::table('fli_run_snapshots')->whereIn('id', $old)->update(['status' => 'SUPERSEDED', 'updated_at' => now()]);
            DB::table('ecl_scenario_results')->whereIn('snapshot_id', $old)->delete();
            $id = (int) DB::table('fli_run_snapshots')->insertGetId([
                'reporting_period' => $period, 'model_version_id' => $version?->id, 'fit_id' => $version?->fli_fit_id, 'set_id' => $set?->id, 'set_version' => $set?->version,
                'route' => $loans->pluck('fli_route')->filter()->unique()->implode(', ') ?: null, 'method' => $loans->pluck('fli_method')->filter()->unique()->implode(', ') ?: null,
                'weighting' => $this->setting('scenario_weighting_method', $period), 'overlay_ids' => json_encode(array_column($up['overlay_lines'], 'overlay_id')),
                'macro_vintage' => mb_substr((string) ($inputs['macro']['label'] ?? ''), 0, 128) ?: null, 'loans' => $counted,
                'pre_fli_ecl' => $up['pre_fli'], 'model_effect' => $up['fli_model'], 'overlay_effect' => $up['overlays'], 'booked_ecl' => round($booked, 2), 'weighted_ecl' => round($weighted, 2),
                'difference' => $difference, 'reconciles' => $reconciles, 'per_scenario' => json_encode($perScenario),
                'by_stage' => json_encode(array_map($round, $byStage)),
                'by_portfolio' => json_encode(collect($byPortfolio)->map(fn ($v, $k) => ['portfolio_id' => (int) $k, 'portfolio' => $names[$k] ?? ($k ? "Portfolio {$k}" : 'No portfolio'), 'values' => $round($v)])->values()->all()),
                'inputs' => json_encode($inputs), 'prior_booked_ecl' => $priorBooked, 'movement_pct' => $movement === null ? null : round($movement, 6), 'needs_commentary' => $needsCommentary,
                'status' => 'RECORDED', 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ] + $discFields);
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('ecl_scenario_results')->insert(array_map(fn ($r) => $r + ['snapshot_id' => $id, 'created_at' => now(), 'updated_at' => now()], $chunk));
            }

            return $id;
        });
        AuditLoggerService::log('FLI Month Recorded', 'fli_run_snapshots', $snapshot, ['reporting_period' => $period, 'rows_affected' => count($rows),
            'new_values' => ['booked' => round($booked, 2), 'weighted' => round($weighted, 2), 'difference' => $difference, 'reconciles' => $reconciles, 'model_version' => $version?->version, 'set' => $set?->id, 'needs_commentary' => $needsCommentary,
                'booked_discounted' => $discFields['booked_ecl_discounted'] ?? null, 'weighted_discounted' => $discFields['weighted_ecl_discounted'] ?? null, 'discounted_reconciles' => $discReconciles, 'discount_unresolved' => $disc['unresolved_loans']],
            'meta' => ['user' => $userId]]);

        return $this->find($snapshot);
    }

    /** The second person signs the record off; a commentary is needed when the month moved beyond the tolerance. */
    public function signOff(int $snapshotId, ?int $userId, ?string $commentary = null, ?string $label = null): void
    {
        $s = DB::table('fli_run_snapshots')->where('id', $snapshotId)->first() ?? throw new RuntimeException("No record {$snapshotId}.");
        if ($s->status !== 'RECORDED') {
            throw new RuntimeException("The record of {$s->reporting_period} is {$s->status}; only a recorded month can be signed off.");
        }
        if (! $s->reconciles) {
            throw new RuntimeException("The weighted ECL of {$s->reporting_period} differs from the booked ECL by {$s->difference}; record the month again after the ECL run before signing it off.");
        }
        if (property_exists($s, 'discounted_reconciles') && $s->discounted_reconciles !== null && ! $s->discounted_reconciles) {
            throw new RuntimeException("The discounted allowance of {$s->reporting_period} weighted over the scenarios differs from the one booked by {$s->discounted_difference}, or a loan carries no per-scenario figures: run the ECL again, record the month again, then sign it off.");
        }
        $commentary = trim((string) ($commentary ?? $s->commentary ?? ''));
        if ($s->needs_commentary && $commentary === '') {
            throw new RuntimeException('The ECL moved by ' . round((float) $s->movement_pct * 100, 1) . ' percent against the previous month-end, more than the governed tolerance: say why before signing off.');
        }
        if (property_exists($s, 'discount_unresolved_loans') && (int) $s->discount_unresolved_loans > 0 && $commentary === '') {
            throw new RuntimeException("{$s->discount_unresolved_loans} loan(s) of {$s->reporting_period} have no discounted allowance (named on the record, mostly no locked original EIR), so the discounted allowance covers part of the book: say why the month is signed off before they are resolved.");
        }
        if ($label === null && $userId !== null && (int) $s->created_by === $userId) {
            throw new RuntimeException('Maker-checker: the person who recorded the month cannot sign it off.');
        }
        DB::table('fli_run_snapshots')->where('id', $snapshotId)->update(['status' => 'SIGNED_OFF', 'commentary' => $commentary === '' ? null : $commentary, 'signed_off_by' => $userId, 'approver_label' => $label, 'signed_off_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('FLI Month Signed Off', 'fli_run_snapshots', $snapshotId, ['reporting_period' => $s->reporting_period, 'new_values' => ['commentary' => $commentary, 'label' => $label], 'meta' => ['user' => $userId]]);
    }

    /** Save the commentary without signing off (the reviewer explains the movement first). */
    public function comment(int $snapshotId, ?int $userId, string $commentary): void
    {
        DB::table('fli_run_snapshots')->where('id', $snapshotId)->where('status', 'RECORDED')->update(['commentary' => trim($commentary), 'updated_at' => now()]);
        AuditLoggerService::log('FLI Month Commentary', 'fli_run_snapshots', $snapshotId, ['new_values' => ['commentary' => trim($commentary)], 'meta' => ['user' => $userId]]);
    }

    /**
     * The loans the time-phased engine could not discount, with the reason
     * it gave (the exceptions of their projection run), at most 50.
     *
     * @return list<array{contract_id:string,ecl:?float,status:?string,reason:?string}>
     */
    private function unresolved($loans): array
    {
        $out = [];
        $reasons = [];
        $runs = DB::getSchemaBuilder()->hasTable('ecl_projection_runs');
        foreach ($loans as $l) {
            if ($l->ecl_value === null || (($l->ecl_discount_status ?? null) === 'CALCULATED_TIME_PHASED' && $l->ecl_value_discounted !== null)) {
                continue;
            }
            $run = $l->ecl_calculation_run_id ?? null;
            if ($runs && $run !== null && ! array_key_exists($run, $reasons)) {
                $reasons[$run] = json_decode((string) DB::table('ecl_projection_runs')->where('run_id', $run)->value('exceptions'), true) ?: [];
            }
            $out[] = ['contract_id' => (string) $l->contract_id, 'ecl' => (float) $l->ecl_value, 'status' => $l->ecl_discount_status ?? null,
                'reason' => $reasons[$run][(string) $l->contract_id] ?? (($l->ecl_discount_status ?? null) === 'NOT_REQUESTED' ? 'The ECL was run undiscounted.' : null)];
            if (count($out) >= 50) {
                break;
            }
        }

        return $out;
    }

    /** @return array<string,mixed> */
    public function find(int $id): array
    {
        $s = (array) DB::table('fli_run_snapshots as s')->leftJoin('users as c', 'c.id', '=', 's.created_by')->leftJoin('users as o', 'o.id', '=', 's.signed_off_by')
            ->where('s.id', $id)->first(['s.*', 'c.name as recorder', 'o.name as signer']);
        foreach (['per_scenario', 'by_stage', 'by_portfolio', 'inputs', 'overlay_ids', 'discount_detail'] as $k) {
            $s[$k] = json_decode((string) ($s[$k] ?? ''), true);
        }

        return $s;
    }

    /** The latest record of a month that is not superseded. */
    public function latest(string $period): ?array
    {
        $id = DB::table('fli_run_snapshots')->where('reporting_period', $period)->whereIn('status', ['RECORDED', 'SIGNED_OFF'])->orderByDesc('id')->value('id');

        return $id ? $this->find((int) $id) : null;
    }

    /** The governed movement tolerance as a share (0.10 for ten percent), or null when none is in force. */
    public function tolerance(string $period): ?float
    {
        $v = $this->setting('fli_movement_commentary', $period);

        return $v !== null && preg_match('/(\d+(?:\.\d+)?)\s*percent/i', $v, $m) ? (float) $m[1] / 100 : null;
    }

    /** What macro data the month read: the latest observation of each driver knowable at the month-end and its vintage. */
    private function vintage(string $period, ?string $driver): array
    {
        $codes = array_values(array_filter(array_unique([$driver, 'PLR', 'GDP_GROWTH', 'CPI', 'MWK_USD'])));
        $rows = AsAtSeries::macroRows(null, AsAtSeries::ym($period), ['actual', 'estimate', 'forecast'], $codes);
        $out = [];
        foreach ($rows as $code => $obs) {
            if ($obs === []) {
                continue;
            }
            $actual = array_filter($obs, fn ($r) => ($r['type'] ?? '') === 'actual');
            $forecast = array_filter($obs, fn ($r) => ($r['type'] ?? '') === 'forecast');
            $out[$code] = ['last_actual' => $actual === [] ? null : (string) max(array_keys($actual)), 'forecasts_known' => count($forecast)];
        }
        $vintages = DB::table('macro_series')->whereIn('statistic_code', $codes)->whereNotNull('vintage')->distinct()->pluck('vintage')->take(5)->implode('; ');

        return ['series' => $out, 'label' => $vintages];
    }

    private function setting(string $key, string $period): ?string
    {
        try {
            return $this->governance->get($key, CarbonImmutable::parse($period . '-01')->endOfMonth());
        } catch (Throwable) {
            return null;
        }
    }
}
