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
use App\Services\Ebanker\TrialBalanceLandingService;
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
 * version 1 schedules, then detect the restructure candidates and the rate
 * moves (detection only: the bootstrap never classifies, confirms or
 * approves one); 6 run the engine chain of 6.10.1 synchronously;
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
        {--inputs=docs/bootstrap : Folder of the committed inputs}
        {--live-macro : Fetch the World Bank series live instead of the committed snapshot (the results then depend on the day it runs)}';

    protected $description = 'Clean install: seed, land the committed client inputs, build, run the engines, verify the golden numbers';

    private array $report = [];

    public function handle(): int
    {
        $start = microtime(true);
        $inputs = base_path((string) $this->option('inputs'));
        $user = (int) $this->option('user');
        config(['queue.default' => 'sync']);
        // the pack is parsed whole before it lands (the gates look across its files); a 128M php.ini default runs out on the committed pack
        ini_set('memory_limit', '-1');
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
        $classes = ['CountriesTableSeeder', 'PermissionsTableSeeder', 'RolesTableSeeder', 'MaiicAdminPermissionsSeeder', 'TicketsPermissionsSeeder', 'LegacyPermissionsCleanupSeeder', 'UsersTableSeeder',
            'CurrenciesTableSeeder', 'MaiicCurrencySeeder', 'TimezonesTableSeeder', 'SettingsTableSeeder', 'BranchesTableSeeder', 'LegalTypesTableSeeder', 'ChartOfAccountsTableSeeder', 'GovernanceSettingsSeeder', 'StagingThresholdSeeder', 'SicrQualitativeFactorsSeeder',
            'EbankerQuerySeeder', 'MacroSeriesSeeder', 'MaiicTransitionProfileSeeder', 'HelpContentSeeder', 'HelpAdminContentSeeder', 'EirAccountingRuleSeeder', 'GlAccountScopeSeeder', 'ScenarioSetSeeder',
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
        $packGates = array_filter($r['gates']['pack'] ?? [], fn ($g) => is_array($g) && isset($g['result']));
        $this->note('3 land pack', "{$r['status']} (load {$r['load_id']}): {$files} files; gates " . implode(', ', array_map(fn ($name, $g) => "{$name} {$g['result']}" . (isset($g['detail']) ? " ({$g['detail']})" : ''), array_keys($packGates), $packGates))
            . ($r['status'] === 'QUARANTINED' ? ' QUARANTINED: ' . json_encode(array_map(fn ($f) => $f['failures'] ?? [], array_filter($r['gates']['files'] ?? [], fn ($f) => ($f['failures'] ?? []) !== [])) + array_map(fn ($g) => $g['failures'], array_filter($packGates, fn ($g) => $g['result'] === 'FAIL'))) : ''));
        if ($r['status'] === 'QUARANTINED') {
            throw new \RuntimeException('The committed pack did not pass its gates.');
        }
        $this->landLaterPacks($inputs, $user);
        $takeon = $inputs . DIRECTORY_SEPARATOR . 'takeon';
        $original = $this->find($takeon, 'as received');
        $mapping = $this->find($takeon, 'with mapping');
        if ($original && $mapping) {
            $t = app(TakeonLandingService::class)->land($original, $mapping, $user);
            $this->note('4 land take-on', "{$t['status']}: {$t['blocks']} blocks, {$t['lines']} lines; " . json_encode($t['gates']['summary'] ?? []));
        } else {
            $this->note('4 land take-on', 'SKIPPED: workbooks not found under ' . $takeon);
        }
        // the trial balances enter by the same door (spec 6.3; system audit of 9 October 2026, finding M1):
        // one load per file with its hash and gates, the GL lines as raw rows, gl_trial_balance_lines derived
        $tb = $inputs . DIRECTORY_SEPARATOR . 'trial-balances' . DIRECTORY_SEPARATOR . 'monthly';
        if (is_dir($tb)) {
            $afs = glob($inputs . '/trial-balances/afs-bridge-2025-12/2026-09-10/*.xlsx') ?: [];
            $t = app(TrialBalanceLandingService::class)->landDirectory($tb, $user, $afs[0] ?? null);
            $landed = count(array_filter($t['files'], fn ($f) => $f['status'] !== 'QUARANTINED'));
            $this->note('3b trial balances', "{$landed} files landed, " . count($t['failures']) . ' quarantined; ' . $t['derived']['lines'] . ' GL lines derived over ' . count($t['derived']['periods']) . ' periods'
                . ($t['failures'] !== [] ? ' QUARANTINED: ' . json_encode($t['failures']) : ''));
            if ($t['failures'] !== []) {
                throw new \RuntimeException('A committed trial balance did not pass its gates.');
            }
        }
    }

    /**
     * The packs MAIIC sent after the first two, each with its own manifest, in the
     * order they were received (the date in the name): a folder under docs/bootstrap
     * (ebanker-pack-YYYY-MM-DD/manifest.json), or a zip under docs/bootstrap/packs/
     * for a pack too large to commit as files (pack 4 of 12 October 2026: 143 MB of
     * CSVs, 12.4 MB zipped), unzipped into storage first through the same entry-name
     * check as an uploaded pack. Each lands through the gates like the first; a pack
     * already landed (the same hash) is recognised and not loaded twice.
     */
    private function landLaterPacks(string $inputs, int $user): void
    {
        $packs = [];
        foreach (glob($inputs . '/ebanker-pack-*/manifest.json') ?: [] as $m) {
            $packs[basename(dirname($m))] = dirname($m);
        }
        foreach (glob($inputs . '/packs/ebanker-pack-*.zip') ?: [] as $zipPath) {
            $name = basename($zipPath, '.zip');
            if (isset($packs[$name])) {
                continue;
            }
            $dir = storage_path('app/bootstrap-packs');
            if (! is_dir($dir . '/' . $name)) {
                if (! is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $zip = new \ZipArchive();
                if ($zip->open($zipPath) !== true) {
                    throw new \RuntimeException("The committed pack {$name}.zip is not a readable zip.");
                }
                \App\Services\Ebanker\FeedZip::extract($zip, $dir, 'bootstrap');
                $zip->close();
            }
            if (is_file($dir . '/' . $name . '/manifest.json')) {
                $packs[$name] = $dir . '/' . $name;
            }
        }
        ksort($packs);
        foreach ($packs as $name => $dir) {
            // the 7 and 8 October folders are listed by the top-level manifest and landed with it
            if (! is_file($dir . '/manifest.json')) {
                continue;
            }
            $r = app(PackLandingService::class)->land($dir, $user, PackLandingService::ROUTE_MANUAL);
            $files = count(array_filter($r['files'] ?? [], fn ($f) => is_array($f) && isset($f['new'])));
            $this->note('3 land ' . $name, "{$r['status']} (load {$r['load_id']}): {$files} files");
            if ($r['status'] === 'QUARANTINED') {
                throw new \RuntimeException("The committed pack {$name} did not pass its gates.");
            }
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
            // the Build with approval (spec 6.9): proposed, then approved under the bootstrap label (not a MAIIC approval)
            $takeon = app(TakeonLandingService::class);
            $request = $takeon->proposeBuild($user, 'eir:bootstrap');
            $t = $takeon->approveBuild($request['request'], $user, LoanBookBuildService::BOOTSTRAP_LABEL);
            $this->note('5b take-on population', "build request {$request['request']} approved under the bootstrap label; " . "{$t['accounts']} accounts: {$t['recomputed']} recomputed, {$t['takeon_balance']} at take-on balance, {$t['refused']} refused; take-on EIR check {$t['eir_check_pass']} pass, {$t['eir_check_refused']} refused; take-on fees " . json_encode(array_intersect_key($t['fees'] ?? [], array_flip(['loaded_rows', 'amounts_changed']))) . ' landed PENDING');
        } catch (Throwable $e) {
            $this->note('5b take-on population', 'SKIPPED: ' . $e->getMessage());
        }

        $inputs = app(ContractInputsBuildService::class);
        $cm = $inputs->contractMaster();
        $this->note('5c contract master', "{$cm['rows']} rows: {$cm['result']['created']} created, {$cm['result']['updated']} updated, {$cm['result']['unchanged']} unchanged, " . count($cm['result']['held']) . ' held, ' . count($cm['result']['skipped']) . ' skipped'
            . '; drawn above the sanction, flagged for Credit (spec 7.3): ' . ($cm['result']['over_sanction']['flagged'] ?? 0));
        // the scheme settings, so a schedule reads the interest and instalment basis its scheme states (spec v3 6.3)
        $sc = $inputs->schemes();
        $this->note('5c scheme settings', "{$sc['rows']} schemes in SM_01 and DD_10: {$sc['upserted']} loaded ({$sc['from_dd10']} with DD_10), " . count($sc['refused']) . ' refused'
            . ($sc['refused'] !== [] ? ': ' . json_encode($sc['refused']) : ''));
        $rr = $inputs->referenceRates($user);
        $this->note('5d PLR series', "{$rr['rows']} rows: " . ($rr['result']['loaded_rows'] ?? 0) . ' loaded, ' . ($rr['result']['rate_changes'] ?? 0) . ' rate changes');
        $fe = $inputs->fees();
        $this->note('5e fees', "{$fe['rows']} rows: {$fe['result']['loaded_rows']} loaded, {$fe['result']['skipped_rows']} skipped; " . json_encode($fe['result']['totals_by_type'] ?? []));
        // the rulebook suggests each fee's treatment; the bootstrap applies the
        // rule and reviews under its label, leaving a line with no rule PENDING
        // The rulebook suggests; it does not review. Fee Classification is a
        // Governance Centre screen (spec 11.3) and the bootstrap gives no
        // approval there (spec 6.10; audit M8): a suggestion stays a suggestion
        // until a person reviews it, and the rulebook itself suggests nothing
        // until MAIIC approves it.
        // the GL fee route (spec 7.8.3 to 7.8.5; results review E3): the fee postings of
        // GL_04 tied to their loans by the batch, the rest held with the reason, and the
        // governed precedence so a fee in GL_04 and in the charges table counts once
        try {
            $gf = app(\App\Services\Ebanker\GlFeeBuildService::class)->build($user);
            $this->note('5e GL fee postings', "{$gf['legs']} fee postings: {$gf['assigned']['lines']} on a loan (" . number_format($gf['assigned']['amount'], 2) . "), {$gf['held']['lines']} held (" . number_format($gf['held']['amount'], 2) . ') ' . json_encode($gf['held']['by_reason'])
                . "; {$gf['loaded']['loaded_rows']} loaded PENDING; precedence " . json_encode($gf['precedence']['by_winner']) . ", {$gf['precedence']['superseded_lines']} lines superseded, " . count($gf['precedence']['exceptions']) . ' cross-source differences; GL gate '
                . "{$gf['gate']['agree']} months agree, {$gf['gate']['differ']} differ, {$gf['gate']['no_tb']} without a trial balance");
        } catch (Throwable $e) {
            $this->note('5e GL fee postings', 'NOT RUN: ' . substr($e->getMessage(), 0, 160));
        }
        $sweep = app(FeeRuleMatcher::class)->sweepPending();
        $this->note('5e fee rulebook', "{$sweep['examined']} examined, {$sweep['matched']} matched an approved rule (suggested, not reviewed: the review is a person's), " . ContractFee::where('classification_status', 'PENDING')->count() . ' PENDING');
        $gi = $inputs->glInterest($from, null);
        $this->note('5f interest posted (ledger)', "{$gi['rows']} account-months: {$gi['result']['loaded_rows']} loaded, {$gi['result']['restated_rows']} restated, total " . number_format($gi['result']['total_posted'], 2));

        $tx = $inputs->actualTransactions();
        $this->note('5f cash movements (ledger)', "{$tx['rows']} postings: " . ($tx['result']['actual_rows_loaded'] ?? json_encode(array_intersect_key($tx['result'], array_flip(['actual_rows_loaded', 'held', 'duplicate_source_rows'])))) . ' actual transactions loaded, ' . count($tx['result']['held'] ?? []) . ' accounts held (not in the loan book)');
        $dd = $inputs->disbursements();
        $this->note('5f drawdowns (ledger)', "{$dd['rows']} type-301 postings: " . json_encode(array_intersect_key($dd['result'], array_flip(['loaded_rows', 'already_stored', 'contracts', 'total_amount']))) . '; facilities drawn in more than one tranche: ' . DB::table('contract_disbursements')->selectRaw('contract_id, count(*) n')->groupBy('contract_id')->havingRaw('count(*) > 1')->get()->count());
        Artisan::call('eir:derive-spreads', ['--user' => $user]);
        $this->note('5g spreads', trim(preg_replace('/\s+/', ' ', substr(Artisan::output(), 0, 200))));

        $workflow = app(ScheduleWorkflowService::class);
        $gen = $workflow->generateEligible();
        $approved = 0; $refused = [];
        foreach (ContractEir::query()->whereIn('schedule_approval_status', ['DRAFT', 'PENDING_REVIEW'])->get() as $c) {
            try {
                // labelled, never a MAIIC approval: the label is recorded on the contract (schedule_approver_label)
                $workflow->approve($c, $user, LoanBookBuildService::BOOTSTRAP_LABEL, LoanBookBuildService::BOOTSTRAP_LABEL);
                $approved++;
            } catch (Throwable $e) {
                $refused[] = $c->contract_id . ': ' . $e->getMessage();
            }
        }
        $this->note('5h schedules v1', json_encode(array_intersect_key($gen, array_flip(['generated', 'skipped', 'eligible', 'held']))) . "; approved under the bootstrap label: {$approved}; refused: " . count($refused));

        // 5i restructure candidates and rate moves (spec v4 section 3.9, P5): detection only, once the ledger,
        // the instalment chart history, the PLR series and the contract master are in, and before staging.
        // Nothing is classified, proposed, confirmed or approved here, under any label (review item R10): each
        // candidate and each move waits for one person's classification and another's approval.
        try {
            $code = Artisan::call('eir:restructure-candidates');
            $out = trim(preg_replace('/\s+/', ' ', Artisan::output()));
            if ($code !== 0) {
                throw new \RuntimeException($out);
            }
            $this->note('5i restructure candidates', self::restructureLine());
        } catch (Throwable $e) {
            $this->note('5i restructure candidates', 'NOT RUN: ' . substr($e->getMessage(), 0, 200));
        }

        return $to;
    }

    /**
     * The bootstrap's line for the restructure detection, from the register itself: the
     * candidates found (by evidence), those that cannot be booked as they stand, and the rate
     * moves recorded; and that nothing is confirmed. Public for the wiring test.
     */
    public static function restructureLine(): string
    {
        $events = DB::table('restructure_events');
        $candidates = (clone $events)->where('status', 'CANDIDATE')->count();
        $fromRates = (clone $events)->where('status', 'CANDIDATE')->where('source', 'RATE_CHANGE')->count();
        $incomplete = (clone $events)->where('status', 'CANDIDATE')->whereNotNull('incomplete_reason')->count();
        $proposed = (clone $events)->where('status', 'PROPOSED')->count();
        $confirmed = (clone $events)->where('status', 'CONFIRMED')->count();
        $moves = DB::getSchemaBuilder()->hasColumn('rate_reset_events', 'event_type')
            ? DB::table('rate_reset_events')->whereNotNull('detection_key')->selectRaw('event_type, classification_status, count(*) n')->groupBy('event_type', 'classification_status')->get()
            : collect();
        $byType = fn (string $t) => (int) $moves->where('event_type', $t)->sum('n');

        return sprintf('%d candidate%s found and awaiting a person\'s classification (%d from the reschedule history and the migration worksheet, %d from rate moves of fixed-rate loans; %d cannot be booked as they stand: no date, old terms or new schedule); '
            . '%d rate move%s recorded on the rate-reset register (%d PLR resets, %d spread changes), %d awaiting classification; nothing was confirmed or approved by the bootstrap (%d proposed and %d confirmed by people are kept as they were).',
            $candidates, $candidates === 1 ? '' : 's', $candidates - $fromRates, $fromRates, $incomplete,
            (int) $moves->sum('n'), (int) $moves->sum('n') === 1 ? '' : 's', $byType('PLR_RESET'), $byType('SPREAD_CHANGE'), (int) $moves->where('classification_status', 'DETECTED')->sum('n'),
            $proposed, $confirmed);
    }

    private function stepEngines(int $user, ?string $to): void
    {
        // 1 macro statistics: the committed snapshot, so every install starts from the same series and
        // reproduces the same results; --live-macro fetches from the World Bank instead
        Artisan::call('macro:import-worldbank', ['--user' => $user, '--from' => 2000] + ($this->option('live-macro') ? [] : ['--offline' => true]));
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
                $calc->lock($c->contract_id, $user, false, LoanBookBuildService::BOOTSTRAP_LABEL);
                $locked++;
            } catch (Throwable $e) {
                $failed[] = $c->contract_id . ': ' . substr($e->getMessage(), 0, 80);
            }
        }
        $this->note('6.9 EIR solved and locked', "{$solved} solved, {$locked} locked under the bootstrap label (automated data-readiness, not a MAIIC approval); " . count($failed) . ' not solved' . ($failed !== [] ? ': ' . implode(' | ', array_slice($failed, 0, 3)) : ''));
        // The book split into its programmes (ifrs9:segment-portfolios): the
        // Mega Farm schemes get their own portfolio, so MAIIC's PD, LGD and ECL
        // are measured on MAIIC's loans only and the programme is provided by
        // the Mega Farm module (spec v4 section 16, D30). Until 13 October 2026
        // the chain ran every engine on one blended portfolio, so the
        // programme's defaults (95 percent of its book in 2025) entered MAIIC's
        // transition PDs and cohort LGD, and the programme was provided twice.
        Artisan::call('ifrs9:segment-portfolios');
        $this->note('6.0 programme portfolios', trim(preg_replace('/\s+/', ' ', substr(Artisan::output(), -220))));

        // 4, 5, 6, 7 for every period, the month-end path: the PD and the LGD by
        // segment on the governed basis, the forward-looking route (with no
        // approved fit on a clean install, the overlay at zero, spec 6.10.1 step
        // 6), the ECL on the post-FLI PD discounted at the EIR (IFRS 9
        // 5.5.17(b); spec 6.10.1 step 7), then the Mega Farm programme, so the
        // revenue step that follows nets the opening allowance on Stage 3
        // (audit C6) and every month carries the allowance the month-end books.
        // A month without a twelve-month window or a Stage 3 cohort is noted and
        // left without an allowance.
        $eclPeriods = []; $eclSkipped = []; $mfPeriods = []; $mfDeclined = [];
        $mega = app(\App\Services\MegaFarm\MegaFarmEclService::class);
        foreach ($periods as $p) {
            try {
                app(\App\Services\Pd\PdSegmentationService::class)->run($p, null, 12, $user, 'bootstrap');
                app(\App\Services\Lgd\LgdSegmentationService::class)->run($p, null, 12, $user);
                app(\App\Services\Fli\FliRouteService::class)->apply($p, $user);
                $code = Artisan::call('ifrs9:segment-ecl', ['from' => $p, '--pd' => 'pd_post_fli', '--discounting' => 'discounted', '--prune-stale' => true]);
                set_time_limit(0); // the ECL controller lowers it to 300 s for the web (audit H14)
                if ($code !== 0) {
                    throw new \RuntimeException(trim(preg_replace('/\s+/', ' ', substr(Artisan::output(), 0, 200))));
                }
                $eclPeriods[] = $p;
            } catch (Throwable $e) {
                $eclSkipped[$p] = substr($e->getMessage(), 0, 90);
            }
            try {
                if ($mega->loans($p)->exists()) {
                    $mf = $mega->run($p, $user, LoanBookBuildService::BOOTSTRAP_LABEL);
                    $mf['declined'] ? $mfDeclined[$p] = substr($mf['declined'], 0, 90) : $mfPeriods[] = $p;
                }
            } catch (Throwable $e) {
                $mfDeclined[$p] = substr($e->getMessage(), 0, 90);
            }
        }
        $this->note('6.7 ECL by period (month-end path, discounted)', count($eclPeriods) . ' periods with a PD, an LGD and an ECL' . ($eclPeriods !== [] ? " ({$eclPeriods[0]}..{$eclPeriods[count($eclPeriods) - 1]})" : '') . '; ' . count($eclSkipped) . ' without' . ($eclSkipped !== [] ? ': ' . implode(' | ', array_slice(array_map(fn ($k, $v) => "{$k} {$v}", array_keys($eclSkipped), $eclSkipped), 0, 3)) : ''));
        $this->note('6.7 Mega Farm by period', count($mfPeriods) . ' periods provided' . ($mfPeriods !== [] ? " ({$mfPeriods[0]}..{$mfPeriods[count($mfPeriods) - 1]})" : '') . '; ' . count($mfDeclined) . ' declined' . ($mfDeclined !== [] ? ': ' . implode(' | ', array_slice(array_map(fn ($k, $v) => "{$k} {$v}", array_keys($mfDeclined), $mfDeclined), 0, 3)) : ''));

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

        // the PD and LGD of the last period were measured by segment above; the
        // pooled single-portfolio runs that stood here would overwrite them
        // with figures that include the Mega Farm programme
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
        // 6b the route, applied once per scenario. Spec 6.10.1 step 6: on a clean
        // install no fit is approved, so the overlay is zero and the post-FLI PD
        // is marked "no adjustment: bootstrap"; the regression is used once two
        // people approve a fit on the FLI Adjustments screen (audit C9: the
        // bootstrap had proposed and approved the best fit itself).
        try {
            $routeSvc = app(\App\Services\Fli\FliRouteService::class);
            $fr = $routeSvc->apply($to, $user);
            $this->note('6.6 forward-looking route', "route '{$fr['route']}', method '{$fr['method']}'" . ($fr['fit'] ? ", fit {$fr['fit']} {$fr['fit_relationship']}" : '') . '; adjustments ' . json_encode(array_map(fn ($x) => $x['adjustment'], $fr['scenarios'])) . "; {$fr['adjusted']} loans adjusted, {$fr['held']} held" . ($fr['note'] ? '; ' . $fr['note'] : ''));
        } catch (Throwable $e) {
            $this->note('6.6 forward-looking route', 'NOT RUN: ' . substr($e->getMessage(), 0, 160));
        }
        // 7 ECL on the post-FLI PDs of the last period, now under the seeded scenario set, discounted
        try {
            Artisan::call('ifrs9:segment-ecl', ['from' => $to, '--pd' => 'pd_post_fli', '--discounting' => 'discounted', '--prune-stale' => true]);
            $out = Artisan::output();
            $disc = DB::table('loan_books')->where('reporting_period', $to)->whereNotIn('product_code', \App\Services\MegaFarm\MegaFarmEclService::GLS)
                ->selectRaw('sum(ecl_value) ecl, sum(ecl_value_discounted) disc, sum(ecl_value_discounted is not null) n, sum(ecl_value is not null and ecl_value_discounted is null) un')->first();
            $this->note('6.7 ECL', $to . ': MAIIC book undiscounted ' . number_format((float) $disc->ecl, 0) . "; discounted at the EIR on {$disc->n} loans: " . number_format((float) $disc->disc, 0) . "; {$disc->un} loans not discountable (named on the run)" . (str_contains($out, 'fail closed') ? ' | ' . trim(preg_replace('/\s+/', ' ', substr($out, 0, 200))) : ''));
        } catch (Throwable $e) {
            $this->note('6.7 ECL', 'NOT RUN: ' . substr($e->getMessage(), 0, 160));
        }
        // the scenario sensitivity, now that the loans carry a PD and an LGD
        try {
            $setId = DB::table('governed_scenario_sets')->where('reporting_period', $to)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->value('id');
            if ($setId) {
                $sens = app(\App\Services\Scenario\ScenarioSetService::class)->sensitivity((int) $setId);
                $this->note('6.2 scenario sensitivity', "set {$setId}: {$sens['loans']} loans; weighted ECL " . number_format($sens['weighted_ecl'], 0) . '; ' . collect($sens['per_scenario'])->map(fn ($p, $n) => "{$n} " . number_format($p['ecl'], 0))->implode(', '));
            }
        } catch (Throwable $e) {
            $this->note('6.2 scenario sensitivity', 'NOT RUN: ' . substr($e->getMessage(), 0, 160));
        }
        // 7b the Mega Farm programme of the last period, as run in the loop above (spec section 16, D30)
        $last = DB::table('megafarm_ecl_runs')->where('reporting_period', $to)->orderByDesc('id')->first();
        $this->note('6.7 Mega Farm', $last === null ? 'no Mega Farm loans in the book for ' . $to : ($last->declined ? "declined: {$last->declined}" : "{$last->loans} loans, programme ECL " . number_format((float) $last->programme_ecl, 0) . ', MAIIC share ' . ((float) $last->share * 100) . '% = ' . number_format((float) $last->maiic_ecl, 0)));
        // 11 stress testing: the approved set's scenarios as saved stress runs
        try {
            $st = app(\App\Services\Stress\StressTestService::class)->runSet($to, null, $user, LoanBookBuildService::BOOTSTRAP_LABEL);
            $this->note('6.11 stress testing', $st === [] ? 'no approved scenario set' : collect($st)->map(fn ($r, $n) => "{$n} " . number_format($r['stress_ecl'], 0) . ' (' . ($r['delta_pct'] >= 0 ? '+' : '') . $r['delta_pct'] . '%)')->implode('; '));
        } catch (Throwable $e) {
            $this->note('6.11 stress testing', 'NOT RUN: ' . substr($e->getMessage(), 0, 160));
        }
        // 12 the reports: every IFRS 9 report rendered for the period
        // in its own process: rendering thirty reports and the manual's PDF after the whole chain exhausts this one's memory
        try {
            $proc = new \Symfony\Component\Process\Process([PHP_BINARY, base_path('artisan'), '--env=' . app()->environment(), 'ifrs9:smoke-reports', $to], base_path(), null, null, 1200);
            $proc->run();
            $out = $proc->getOutput() . $proc->getErrorOutput();
            $this->note('6.12 reports', ($proc->getExitCode() === 0 ? 'every report rendered for ' . $to : 'FAILED') . ': ' . trim(preg_replace('/\s+/', ' ', substr($out, -220))));
        } catch (Throwable $e) {
            $this->note('6.12 reports', 'NOT RUN: ' . substr($e->getMessage(), 0, 160));
        }
        // 12b the compliance register from the committed workbooks
        try {
            $reg = app(\App\Services\Compliance\ComplianceAuditService::class)->load();
            $this->note('6.12 compliance register', collect($reg)->map(fn ($n, $k) => "{$k} {$n}")->implode(', ') . ' rows');
        } catch (Throwable $e) {
            $this->note('6.12 compliance register', 'NOT RUN: ' . substr($e->getMessage(), 0, 160));
        }
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
        $checks = app(\App\Services\Eir\BaselineService::class)->checks();
        $failed = 0;
        $rows = [];
        foreach ($checks as $c) {
            $rows[] = [$c['what'], $c['expected'], $c['actual'], $c['ok'] ? 'PASS' : 'FAIL'];
            $failed += $c['ok'] ? 0 : 1;
        }
        $this->table(['Golden number / baseline', 'Expected', 'Actual', 'Result'], $rows);
        $this->note('7 verify', count($checks) . ' checks, ' . $failed . ' FAIL');

        return $failed;
    }

    // ----- helpers ----------------------------------------------------------

    private function note(string $step, string $what): void
    {
        // the controllers the chain calls lower the time limit to 300 s for the web; on
        // the console that cap stopped a full bootstrap part-way (audit H14), so each
        // step lifts it again
        set_time_limit(0);
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
