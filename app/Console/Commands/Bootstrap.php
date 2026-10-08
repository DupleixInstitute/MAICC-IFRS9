<?php

namespace App\Console\Commands;

use App\Models\ContractEir;
use App\Models\ContractFee;
use App\Models\EirFeeClassificationEvent;
use App\Services\Ebanker\ContractInputsBuildService;
use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Ebanker\PackLandingService;
use App\Services\Ebanker\TakeonLandingService;
use App\Services\Eir\EirCalculationService;
use App\Services\Eir\EirGlReconciliationService;
use App\Services\Eir\FeeRuleMatcher;
use App\Services\Eir\ScheduleWorkflowService;
use App\Services\Eir\StagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A clean install that loads itself and proves itself (spec v4 section 6.10,
 * decision D26).
 *
 *   php artisan eir:bootstrap --fresh --with-client-inputs --build --run-engines --verify
 *
 * Steps: 1 migrate:fresh (refused if user data exists unless --force-wipe);
 * 2 seed; 3 land the committed pack; 4 land the take-on workbook; 5 build the
 * loan books, the take-on population and the contract inputs, then the
 * version 1 schedules; 6 run the engine chain of 6.10.1 synchronously;
 * 7 verify the baselines and the golden numbers of 6.10.2, exit non-zero on
 * any FAIL. Every step is idempotent. Anything the bootstrap approves is
 * stamped with the automated label, never a MAIIC approval.
 */
class Bootstrap extends Command
{
    protected $signature = 'eir:bootstrap
        {--fresh : migrate:fresh first}
        {--force-wipe : Allow --fresh on a database that holds user data}
        {--with-client-inputs : Land the committed pack and the take-on workbook from docs/bootstrap}
        {--build : Build the loan books, the take-on population, the contract inputs and the version 1 schedules}
        {--run-engines : Run the engine chain end to end, synchronously}
        {--verify : Check the baselines and the golden numbers; exit non-zero on any FAIL}
        {--from=2024-07 : First period to build and run}
        {--to= : Last period (default: the last month-end in the pack)}
        {--user=1 : The system user the bootstrap records its loads against}
        {--inputs=docs/bootstrap : Folder of the committed inputs}';

    protected $description = 'Clean install: seed, land the committed client inputs, build, run the engines, verify the golden numbers';

    private array $report = [];

    public function handle(): int
    {
        $start = microtime(true);
        $inputs = base_path((string) $this->option('inputs'));
        $user = (int) $this->option('user');
        config(['queue.default' => 'sync']);
        try {
            $this->stepFresh();
            $this->stepSeed();
            $to = null;
            if ($this->option('with-client-inputs')) {
                $this->stepLand($inputs, $user);
            }
            if ($this->option('build')) {
                $to = $this->stepBuild($user);
            }
            if ($this->option('run-engines')) {
                $this->stepEngines($user, $to);
            }
            $failed = $this->option('verify') ? $this->stepVerify() : 0;
        } catch (Throwable $e) {
            $this->error('Bootstrap stopped: ' . $e->getMessage());
            $this->error($e->getFile() . ':' . $e->getLine());
            $this->printReport();
            return self::FAILURE;
        }
        $this->printReport();
        $this->info(sprintf('Bootstrap finished in %.0f s.', microtime(true) - $start));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ----- steps -----------------------------------------------------------

    private function stepFresh(): void
    {
        if (! $this->option('fresh')) {
            return;
        }
        if (! $this->option('force-wipe') && DB::getSchemaBuilder()->hasTable('loan_books') && DB::table('loan_books')->count() > 0) {
            throw new \RuntimeException('The database holds user data; pass --force-wipe to wipe it.');
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->note('1 migrate:fresh', 'done');
    }

    private function stepSeed(): void
    {
        Artisan::call('migrate', ['--force' => true]);
        $classes = ['PermissionsTableSeeder', 'RolesTableSeeder', 'MaiicAdminPermissionsSeeder', 'UsersTableSeeder', 'GovernanceSettingsSeeder', 'StagingThresholdSeeder',
            'EbankerQuerySeeder', 'MacroSeriesSeeder', 'HelpContentSeeder', 'HelpAdminContentSeeder', 'EirAccountingRuleSeeder', 'GlAccountScopeSeeder', 'ScenarioSetSeeder', 'EclScenarioAssumptionSeeder',
            'IndustryTypeSeeder', 'CreditLossDefinitionSeeder', 'TransitionProfileDefinitionSeeder', 'TransitionProfileOptionSeeder'];
        $ran = [];
        foreach ($classes as $c) {
            if (! class_exists("Database\\Seeders\\{$c}")) {
                continue;
            }
            try {
                Artisan::call('db:seed', ['--class' => $c, '--force' => true]);
                $ran[] = $c;
            } catch (Throwable $e) {
                $this->warn("  seeder {$c}: " . $e->getMessage());
            }
        }
        if (! DB::table('loan_portfolios')->exists()) {
            DB::table('loan_portfolios')->insert(['name' => 'Loans', 'created_by_id' => (int) $this->option('user'), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->note('2 seed', count($ran) . ' seeders: ' . implode(', ', $ran));
    }

    private function stepLand(string $inputs, int $user): void
    {
        $r = app(PackLandingService::class)->land($inputs, $user, PackLandingService::ROUTE_MANUAL);
        $files = count(array_filter($r['files'] ?? [], fn ($f) => is_array($f) && isset($f['new'])));
        $this->note('3 land pack', "{$r['status']} (load {$r['load_id']}): {$files} files" . ($r['status'] === 'QUARANTINED' ? ' QUARANTINED: ' . json_encode(array_map(fn ($f) => $f['failures'] ?? [], array_filter($r['gates']['files'] ?? [], fn ($f) => ($f['failures'] ?? []) !== []))) : ''));
        if ($r['status'] === 'QUARANTINED') {
            throw new \RuntimeException('The committed pack did not pass its gates.');
        }
        $takeon = $inputs . DIRECTORY_SEPARATOR . 'takeon';
        $original = $this->find($takeon, 'as received');
        $mapping = $this->find($takeon, 'with mapping');
        if ($original && $mapping) {
            $t = app(TakeonLandingService::class)->land($original, $mapping, $user);
            $this->note('4 land take-on', "{$t['status']}: {$t['blocks']} blocks, {$t['lines']} lines; " . json_encode($t['gates']['summary'] ?? []));
        } else {
            $this->note('4 land take-on', 'SKIPPED: workbooks not found under ' . $takeon);
        }
        $tb = $inputs . DIRECTORY_SEPARATOR . 'trial-balances' . DIRECTORY_SEPARATOR . 'monthly';
        if (is_dir($tb)) {
            $afs = glob($inputs . '/trial-balances/afs-bridge-2025-12/2026-09-10/*.xlsx') ?: [];
            Artisan::call('eir:import-trial-balances', ['directory' => $tb] + ($afs !== [] ? ['--afs' => $afs[0]] : []));
            $this->note('3b trial balances', trim(preg_replace('/\s+/', ' ', substr(Artisan::output(), 0, 300))));
        }
    }

    private function stepBuild(int $user): string
    {
        $zone = app(LandingZoneReader::class);
        $ends = $zone->runMonthEnds();
        $last = $zone->lastLedgerDate();
        $to = $this->option('to') ?? substr(end($ends) ?: substr((string) $last, 0, 7), 0, 7);
        $from = (string) $this->option('from');
        $build = app(LoanBookBuildService::class);
        $r = $build->build($from, $to, null, null, null, LoanBookBuildService::BOOTSTRAP_LABEL, false, true);
        $rows = array_sum(array_map(fn ($p) => $p['rows'], $r['periods']));
        $flagged = array_sum(array_map(fn ($p) => $p['flagged'], $r['periods']));
        $methods = implode(',', array_unique(array_map(fn ($p) => $p['method'], $r['periods'])));
        $this->note('5a loan books', "{$r['status']} (build {$r['build_id']}): " . count($r['periods']) . " periods {$from}..{$to} by {$methods}, {$rows} rows, {$flagged} flagged");

        try {
            $t = app(TakeonLandingService::class)->build($user);
            $this->note('5b take-on population', "{$t['accounts']} accounts: {$t['recomputed']} recomputed, {$t['takeon_balance']} at take-on balance, {$t['refused']} refused");
        } catch (Throwable $e) {
            $this->note('5b take-on population', 'SKIPPED: ' . $e->getMessage());
        }

        $inputs = app(ContractInputsBuildService::class);
        $cm = $inputs->contractMaster();
        $this->note('5c contract master', "{$cm['rows']} rows: {$cm['result']['created']} created, {$cm['result']['updated']} updated, {$cm['result']['unchanged']} unchanged, " . count($cm['result']['held']) . ' held, ' . count($cm['result']['skipped']) . ' skipped');
        $rr = $inputs->referenceRates($user);
        $this->note('5d PLR series', "{$rr['rows']} rows: " . ($rr['result']['loaded_rows'] ?? 0) . ' loaded, ' . ($rr['result']['rate_changes'] ?? 0) . ' rate changes');
        $fe = $inputs->fees();
        $this->note('5e fees', "{$fe['rows']} rows: {$fe['result']['loaded_rows']} loaded, {$fe['result']['skipped_rows']} skipped; " . json_encode($fe['result']['totals_by_type'] ?? []));
        // the rulebook suggests each fee's treatment; the bootstrap applies the
        // rule and reviews under its label, leaving a line with no rule PENDING
        $sweep = app(FeeRuleMatcher::class)->sweepPending();
        $classified = 0;
        foreach (ContractFee::where('classification_status', 'PENDING')->whereNotNull('suggested_rule_id')->get() as $fee) {
            $fee->update(['integral' => (bool) $fee->suggested_integral, 'classification_status' => 'REVIEWED',
                'classification_reason' => LoanBookBuildService::BOOTSTRAP_LABEL . ' [applied by rule: ' . ($fee->suggestedRule?->name ?? 'unknown') . ']',
                'classified_by' => $user, 'classified_at' => now(), 'reviewed_by' => $user, 'reviewed_at' => now()]);
            EirFeeClassificationEvent::create(['contract_fee_id' => $fee->id, 'action' => 'REVIEWED', 'integral' => $fee->integral, 'reason' => $fee->classification_reason, 'performed_by' => $user]);
            $classified++;
        }
        $this->note('5e fee rulebook', "{$sweep['examined']} examined, {$sweep['matched']} matched a rule, {$classified} classified and reviewed under the bootstrap label, " . ContractFee::where('classification_status', 'PENDING')->count() . ' left PENDING');
        $gi = $inputs->glInterest($from, null);
        $this->note('5f interest posted (ledger)', "{$gi['rows']} account-months: {$gi['result']['loaded_rows']} loaded, {$gi['result']['restated_rows']} restated, total " . number_format($gi['result']['total_posted'], 2));

        $tx = $inputs->actualTransactions();
        $this->note('5f cash movements (ledger)', "{$tx['rows']} postings: " . ($tx['result']['actual_rows_loaded'] ?? json_encode(array_intersect_key($tx['result'], array_flip(['actual_rows_loaded', 'held', 'duplicate_source_rows'])))) . ' actual transactions loaded, ' . count($tx['result']['held'] ?? []) . ' accounts held (not in the loan book)');
        Artisan::call('eir:derive-spreads', ['--user' => $user]);
        $this->note('5g spreads', trim(preg_replace('/\s+/', ' ', substr(Artisan::output(), 0, 200))));

        $workflow = app(ScheduleWorkflowService::class);
        $gen = $workflow->generateEligible();
        $approved = 0; $refused = [];
        foreach (ContractEir::query()->whereIn('schedule_approval_status', ['DRAFT', 'PENDING_REVIEW'])->get() as $c) {
            try {
                $workflow->approve($c, $user, LoanBookBuildService::BOOTSTRAP_LABEL);
                $approved++;
            } catch (Throwable $e) {
                $refused[] = $c->contract_id . ': ' . $e->getMessage();
            }
        }
        $this->note('5h schedules v1', json_encode(array_intersect_key($gen, array_flip(['generated', 'skipped', 'eligible', 'held']))) . "; approved under the bootstrap label: {$approved}; refused: " . count($refused));

        return $to;
    }

    private function stepEngines(int $user, ?string $to): void
    {
        // 1 macro statistics: live from the World Bank, else the committed snapshot
        Artisan::call('macro:import-worldbank', ['--user' => $user, '--from' => 2000]);
        $out = Artisan::output();
        $this->note('6.1 macro statistics', trim(preg_replace('/\s+/', ' ', (string) (preg_match('/(\d+ series fetched live.*?skipped\.)/s', $out, $m) ? $m[1] : substr($out, -200)))));

        $build = app(LoanBookBuildService::class);
        $from = (string) $this->option('from');
        $to ??= $this->option('to') ?? (string) DB::table('loan_books')->whereNotNull('build_method')->max('reporting_period');
        $periods = $build->periods($from, $to);

        // 3 staging first: the revenue roll-forward reads the stage (Stage 3 interest on the net basis)
        $staging = app(StagingService::class);
        $s = ['stage1' => 0, 'stage2' => 0, 'stage3' => 0];
        foreach ($periods as $p) {
            $c = $staging->stage($p, $user);
            foreach ($s as $k => $v) { $s[$k] += $c[$k]; }
        }
        $this->note('6.3 staging', count($periods) . ' periods; row-months by stage ' . json_encode($s));

        // 8-9 EIR before the ECL: the ECL is discounted at it
        $calc = app(EirCalculationService::class);
        $solved = 0; $locked = 0; $failed = [];
        foreach (ContractEir::query()->where('schedule_approval_status', 'APPROVED')->whereNull('locked_at')->get() as $c) {
            try {
                $calc->calculate($c->contract_id, $user);
                $solved++;
                $calc->lock($c->contract_id, $user, true);
                $locked++;
            } catch (Throwable $e) {
                $failed[] = $c->contract_id . ': ' . substr($e->getMessage(), 0, 80);
            }
        }
        $this->note('6.9 EIR solved and locked', "{$solved} solved, {$locked} locked (administrator override under the bootstrap label); " . count($failed) . ' not solved' . ($failed !== [] ? ': ' . implode(' | ', array_slice($failed, 0, 3)) : ''));
        $before = DB::table('eir_amortisation')->count();
        $errors = [];
        foreach ($periods as $p) {
            $code = Artisan::call('eir:run-revenue', ['period' => $p, '--user' => $user]);
            if ($code !== 0) {
                $errors[] = $p . ': ' . trim(preg_replace('/\s+/', ' ', substr(Artisan::output(), 0, 120)));
            }
        }
        $rows = DB::table('eir_amortisation')->count();
        $this->note('6.9 revenue', count($periods) . " periods run {$from}..{$to}; " . ($rows - $before) . " roll-forward rows written, {$rows} in all" . ($errors !== [] ? '; failed: ' . implode(' | ', array_slice($errors, 0, 3)) : ''));

        // 4-7 PD, LGD, FLI, ECL: the engines that exist run for the last period
        $portfolio = (int) DB::table('loan_portfolios')->orderBy('id')->value('id');
        try {
            Artisan::call('ifrs9:recalculate-ecl', ['period' => $to, '--level' => 'portfolio', '--portfolio' => $portfolio, '--pd' => 'pd_prefli']);
            $this->note('6.7 ECL', $to . ': ' . trim(preg_replace('/\s+/', ' ', substr(Artisan::output(), 0, 240))));
        } catch (Throwable $e) {
            $this->note('6.7 ECL', 'NOT RUN: ' . substr($e->getMessage(), 0, 160));
        }
        // 2 the scenario set: the first set of 15.8 seeded as proposed and approved under the bootstrap label so the chain can run
        try {
            $sets = app(\App\Services\Scenario\ScenarioSetService::class);
            $setId = $sets->seedFirstSet($to, $user);
            if (DB::table('governed_scenario_sets')->where('id', $setId)->value('status') === 'PROPOSED') {
                $sets->approve($setId, null, \App\Services\Scenario\ScenarioSetService::BOOTSTRAP_LABEL);
            }
            $this->note('6.2 scenario set', "set {$setId} for {$to}: " . DB::table('governed_scenario_sets')->where('id', $setId)->value('status') . ' under the bootstrap label (not a MAIIC approval)');
        } catch (Throwable $e) {
            $this->note('6.2 scenario set', 'NOT RUN: ' . substr($e->getMessage(), 0, 160));
        }
        // 6 forward-looking chain: bridge, profile, sweep, fit; applied fits are proposals, not adjustments
        try {
            Artisan::call('fli:correlate', ['period' => $to, '--top' => 0]);
            $out = Artisan::output();
            $this->note('6.6 forward-looking chain', trim(preg_replace('/\s+/', ' ', (preg_match('/(Auto-Correlate complete[^
]*)/', $out, $m) ? $m[1] : '') . ' ' . (preg_match('/(Regression: [^|
]*)/', $out, $m2) ? $m2[1] : ''))));
        } catch (Throwable $e) {
            $this->note('6.6 forward-looking chain', 'NOT RUN: ' . substr($e->getMessage(), 0, 160));
        }
        $this->note('6.4-6.5 PD, LGD', 'PD transition matrices and LGD run from their screens today; the bootstrap records them as pending until their services are callable without a request');

        // 10 reconciliation
        try {
            $rec = app(EirGlReconciliationService::class)->forPeriod($to);
            $sum = $rec['summary'] ?? $rec;
            $this->note('6.10 GL reconciliation', $to . ': ' . json_encode(array_intersect_key(is_array($sum) ? $sum : [], array_flip(['rows', 'agree', 'explained', 'unexplained', 'posted', 'contractual', 'eir']))));
        } catch (Throwable $e) {
            $this->note('6.10 GL reconciliation', 'NOT RUN: ' . substr($e->getMessage(), 0, 160));
        }
    }

    /** @return int failures */
    private function stepVerify(): int
    {
        $checks = [];
        $zone = app(LandingZoneReader::class);

        // contractual interest from the ledger, Jan 2025 to Jul 2026 (section 9)
        $total = 0.0;
        foreach ($zone->ledgerByAccount('2026-07-31') as $posts) {
            foreach ($posts as $p) {
                if (in_array((string) ($p['payload']['TRANTYPE'] ?? ''), LoanBookBuildService::TYPE_INTEREST, true) && $p['row_date'] >= '2025-01-01') {
                    $total -= (float) str_replace(',', '', (string) ($p['payload']['TRANSAMT'] ?? 0));
                }
            }
        }
        $checks[] = ['Interest posted Jan 2025 to Jul 2026 (ledger, types 303 and 120)', '5,293,988,207.06', number_format($total, 2), abs($total - 5293988207.06) < 0.01];

        // method B ties to the stored report on every account-month but the one-cent row
        $flagged = DB::table('loan_books')->where('build_method', 'B')->whereNotNull('stored_carrying_amount')->whereRaw('abs(carrying_amount - stored_carrying_amount) > 0.02')->count();
        $tied = DB::table('loan_books')->where('build_method', 'B')->whereNotNull('stored_carrying_amount')->count();
        $checks[] = ['Derived carrying amount against the stored report (over 2 cents)', '0 differences', "{$flagged} of {$tied} account-months", $flagged === 0];

        // the Diff-Int year-end batch nets to the two income GLs (section 3.4)
        $net = ['4215' => 0.0, '4216' => 0.0];
        foreach ($zone->family(['GL_03']) as $r) {
            $gl = (string) ($r['payload']['AC_GLCODE'] ?? '');
            if (isset($net[$gl])) {
                $net[$gl] += (float) ($r['payload']['TRANSAMT'] ?? 0);
            }
        }
        $checks[] = ['Year-end interest batch contra on 4215', '32,956,675.55', number_format($net['4215'], 2), abs($net['4215'] - 32956675.55) < 0.01];
        $checks[] = ['Year-end interest batch contra on 4216', '-21,733,262.70', number_format($net['4216'], 2), abs($net['4216'] + 21733262.70) < 0.01];

        // loan balances by GL at 31 Dec 2025 against the audited mapping (when the TB bridge is loaded)
        $dec = DB::table('loan_books')->where('reporting_period', '2025-12')->whereIn('product_code', array_keys(LandingZoneReader::LOAN_GLS))->selectRaw('product_code, round(sum(carrying_amount), 2) ca')->groupBy('product_code')->pluck('ca', 'product_code');
        $tbTable = DB::getSchemaBuilder()->hasTable('gl_trial_balance_lines');
        // the keyed GL openings of section 3.5, accepted on the load until Finance corrects them
        $accepted = ['1050201' => -400000.00, '1050202' => 1000000.00];
        foreach ($dec as $gl => $ca) {
            if (! $tbTable) {
                continue;
            }
            $line = DB::table('gl_trial_balance_lines')->where('period', 'like', '2025-12%')->where('gl_code', $gl)->orderByDesc('id')->first();
            $tb = $line ? (float) $line->debit - (float) $line->credit : null;
            if ($tb === null && abs((float) $ca) < 0.005) {
                continue; // no balance and no line: nothing to tie
            }
            $diff = $tb === null ? null : round((float) $ca - $tb, 2);
            $ok = $diff !== null && abs($diff) < 1;
            $label = "Loan book {$gl} at 31 Dec 2025 against the trial balance";
            if (! $ok && $diff !== null && isset($accepted[$gl]) && abs($diff - $accepted[$gl]) < 1) {
                $label .= ' (accepted exception, spec 3.5: ' . number_format($accepted[$gl], 2) . ')';
                $ok = true;
            }
            $checks[] = [$label, $tb === null ? 'no TB line' : number_format($tb, 2), number_format((float) $ca, 2), $ok];
        }

        $failed = 0;
        $rows = [];
        foreach ($checks as [$what, $expected, $actual, $ok]) {
            $rows[] = [$what, $expected, $actual, $ok ? 'PASS' : 'FAIL'];
            $failed += $ok ? 0 : 1;
        }
        $this->table(['Golden number / baseline', 'Expected', 'Actual', 'Result'], $rows);
        $this->note('7 verify', count($checks) . ' checks, ' . $failed . ' FAIL');

        return $failed;
    }

    // ----- helpers ----------------------------------------------------------

    private function note(string $step, string $what): void
    {
        $this->report[] = [$step, $what];
        $this->line("<info>[{$step}]</info> {$what}");
    }

    private function printReport(): void
    {
        $this->newLine();
        $this->table(['Step', 'Result'], array_map(fn ($r) => [$r[0], mb_substr($r[1], 0, 140)], $this->report));
    }

    private function find(string $dir, string $needle): ?string
    {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.xlsx') ?: [] as $f) {
            if (stripos(basename($f), $needle) !== false) {
                return $f;
            }
        }

        return null;
    }
}
