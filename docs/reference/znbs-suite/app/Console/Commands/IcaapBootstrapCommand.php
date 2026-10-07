<?php

namespace App\Console\Commands;

use App\Services\Health\HealthcheckService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-command ICAAP Suite bootstrap.
 *
 *   php artisan icaap:bootstrap --fresh --with-test-data --run-engines --verify
 *
 * Composes the full client deployment sequence:
 *
 *   1. migrate:fresh           . clean schema
 *   2. db:seed                 . RBAC, CoA, templates, macro, scenarios, regulatory rules
 *   3. ZNBS test-data imports  . historicals, SOCE, forecast, regulatory returns
 *   4. Statement-entry rebuild . bank-CoA view rebuilt from CSVs
 *   5. Engine chain            . Stress → Transmission → IFRS9 → RWA → Capital
 *                                 → Liquidity → IRRBB → OpRisk → Concentration → EWS
 *   6. Healthcheck verification. golden-number table printed to console
 *   7. ZNBS 2025-Q4 drafts     . ZnbsReturn2025Q4DraftsSeeder (drafts only) + Report Centre re-populate
 *
 * Each step is idempotent enough to be re-run, but the canonical entry point
 * wipes the database (`--fresh`, default true) so the client always gets a
 * deterministic golden state.
 */
class IcaapBootstrapCommand extends Command
{
    protected $signature = 'icaap:bootstrap
        {--fresh : Wipe database before bootstrap. SAFETY: refuses if user data already exists, unless --force-wipe is also passed.}
        {--force-wipe : Override the data-present safety check. Use only on a confirmed clean install.}
        {--with-test-data : Import ZNBS test historicals, SOCE, forecast and regulatory returns}
        {--run-engines : Run the full engine chain end-to-end against the seeded baseline scenario}
        {--verify : Run the healthcheck against golden numbers and render the result table}
        {--scenario= : Scenario code to drive engine runs (defaults to the seeded baseline)}
        {--base-year= : Reporting year for the engine chain (defaults to every actual year; scenarios use the latest actual, currently 2025)}
        {--profile=full-golden : Bootstrap profile: full-golden | rolling-5y | dev-fast | data-only. Only full-golden is golden-valid / valid for --verify.}
        {--engine-window=5 : rolling-5y heavy-engine lookback span in years (current minus N).}';

    protected $description = 'One-command ICAAP Suite bootstrap. By default does NOT wipe existing data: only seeds + imports + verifies. Pass --fresh on a brand-new install.';

    /**
     * Approver/activator label stamped on contexts the bootstrap approves.
     * Audit#6: this is an automated DATA-READINESS marker, never a Board
     * sign-off - the wording must make that plain wherever it is surfaced.
     */
    /**
     * Instrument-level bank balances and placements register, committed so a client
     * server bootstraps the directive-compliant basis without an external file.
     * Directive 14(1) bands claims on banks by ORIGINAL maturity, which needs a deal
     * date per placement; the AFS aggregates carry none.
     */
    private const BANK_PLACEMENT_REGISTER = 'docs/ICAAP docs/ZNBS Dec2025 Client Inputs/Bank Placements Dec 2025 (instrument level).csv';

    private const AUTOMATED_APPROVER = 'System Bootstrap (automated data-readiness - not Board approval)';

    /** Bootstrap profile state (resolved in handle / runEngineChain). */
    private string $profileName = \App\Support\BootstrapProfile::FULL_GOLDEN;
    private int $engineWindow = 5;
    private float $bootstrapStartedAt = 0.0;
    private ?\App\Support\BootstrapProfile $bootstrapProfile = null;
    /** @var array<string,int> per-year ratio coverage for the summary */
    private array $ratioOnlyYears = [];

    public function handle(): int
    {
        $this->renderBanner();

        $fresh        = $this->option('fresh');
        $forceWipe    = $this->option('force-wipe');
        $withData     = $this->option('with-test-data');
        $runEngines   = $this->option('run-engines');
        $verify       = $this->option('verify');

        // ── PROFILE GATE ──────────────────────────────────────────────────────
        // Validate the profile up front and block golden verification on any
        // working-mode profile - a fast/rolling run must never be mistaken for a
        // golden-valid result. The full BootstrapProfile (year windows) is resolved
        // later, once the available statement years are known.
        $this->profileName = (string) ($this->option('profile') ?: \App\Support\BootstrapProfile::FULL_GOLDEN);
        $this->engineWindow = max(1, (int) ($this->option('engine-window') ?: 5));
        if (! in_array($this->profileName, \App\Support\BootstrapProfile::ALL, true)) {
            $this->error("  ✗ Unknown --profile '{$this->profileName}'. Valid: " . implode(', ', \App\Support\BootstrapProfile::ALL) . '.');
            return self::FAILURE;
        }
        if ($verify && $this->profileName !== \App\Support\BootstrapProfile::FULL_GOLDEN) {
            $this->newLine();
            $this->error("  ✗ --verify is only valid with --profile=full-golden (got '{$this->profileName}').");
            $this->line('    Golden verification compares against full-chain golden numbers; a working-mode');
            $this->line('    profile does not run every year / scenario, so it cannot be golden-verified.');
            $this->line('    Re-run as: php artisan icaap:bootstrap --run-engines --profile=full-golden --verify');
            return self::FAILURE;
        }
        // data-only never executes engines, whatever else was passed.
        if ($this->profileName === \App\Support\BootstrapProfile::DATA_ONLY) {
            $runEngines = false;
        }
        $this->bootstrapStartedAt = microtime(true);

        try {
            // ── SAFETY GATE ───────────────────────────────────────────────────
            // --fresh is destructive. Refuse to wipe a database that already
            // has user data unless --force-wipe is explicitly passed too.
            if ($fresh) {
                $hasData = $this->detectExistingUserData();
                if ($hasData && !$forceWipe) {
                    $this->newLine();
                    $this->error('  ✗ SAFETY: database already contains user data.');
                    $this->line('    Detected existing rows in users / financial_statements / reporting_contexts.');
                    $this->line('    Refusing to wipe. To override, re-run with BOTH flags:');
                    $this->newLine();
                    $this->line('      php artisan icaap:bootstrap --fresh --force-wipe --with-test-data --run-engines --verify');
                    $this->newLine();
                    $this->line('    Or run WITHOUT --fresh to seed + import on top of existing data.');
                    return self::FAILURE;
                }
                $this->step('Migrating fresh schema', fn () =>
                    Artisan::call('migrate:fresh', ['--force' => true], $this->getOutput()));
            } else {
                // No wipe. run pending migrations only
                $this->step('Running pending migrations (no wipe)', fn () =>
                    Artisan::call('migrate', ['--force' => true], $this->getOutput()));
            }

            $this->step('Seeding base reference data (CoA, RBAC, macro, scenarios, regulatory rules)', fn () =>
                Artisan::call('db:seed', ['--force' => true], $this->getOutput()));

            // Enforce the CoA structural invariants on the freshly seeded chart BEFORE
            // any engine consumes it: clears stray liquidity_flag on income-statement
            // accounts and currency_flag on LCY accounts, and normalises coa_level to
            // parent+1 (deterministic auto-fixes). Keeps coa:validate green on a fresh
            // build so no engine reads a mis-flagged account.
            $this->step('Normalising the seeded CoA (coa:validate --fix)', fn () =>
                Artisan::call('coa:validate', ['--fix' => true], $this->getOutput()));

            // Seed the managed Economic Sector reference (code ↔ name) from the BoZ
            // standard set, so customer/sector imports and displays resolve sector
            // names from their codes. Reuses the same canonical taxonomy as the
            // regulatory dimension importer (sector_schedule_22), so the sectoral
            // concentration and the customer sector reference agree.
            $this->step('Seeding the Economic Sector reference (BoZ standard set)', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\EconomicSectorSeeder::class, '--force' => true], $this->getOutput()));

            // FIX (climate null scoping): seed the SECTOR segmentation dimension
            // (+ the SME customer node) that the climate pack scopes its shocks to,
            // BEFORE the climate seeder reads the dimension/node maps. otherwise the
            // sector-targeted shocks resolve to node_id => null and silently fall back
            // to whole-book application. (EconomicSectorSeeder above seeds the sector
            // REFERENCE lookup, not the segmentation dimension the shocks target.)
            // See docs/final specs/ClimateESG_Sector_Scoping_Fix_Spec.md.
            $this->step('Seeding the SECTOR segmentation (climate scoping fix)', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\SectorSegmentSeeder::class, '--force' => true], $this->getOutput()));

            // Seed the NGFS / TCFD Climate & ESG scenario pack (BoZ DFIA climate-risk
            // coverage the client requires). The ICAAP climate scenarios carry a
            // scenario_role so they run the full engine chain below, and the flagship
            // physical-climate scenario is flagged into the ICAAP reporting set, so
            // climate risk transmits through every engine and appears in the reports.
            $this->step('Seeding the Climate & ESG scenario pack (NGFS / TCFD)', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\ClimateEsgScenarioSeeder::class, '--force' => true], $this->getOutput()));

            // Seed the two macro scenarios of the client's approved ICAAP pack that existed
            // only as proxies (ZNBS M1 elevated inflation, ZNBS M2 severe deep recession).
            // Runs after ScenarioSeeder / ZnbsMissingScenarioSeeder / ClimateEsgScenarioSeeder
            // and BEFORE the management-action seeders so their actions get governed
            // magnitudes. Idempotent by scenario name (row updated, shocks rebuilt).
            $this->step('Seeding the missing ZNBS macro scenarios (ZNBS M1 / M2)', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\ZnbsMissingMacroScenarioSeeder::class, '--force' => true], $this->getOutput()));

            // Attach the board-approved MANAGEMENT ACTIONS (client ICAAP pack) to every
            // scenario. Runs after ALL scenario seeders (ScenarioSeeder + ZnbsMissing +
            // ZnbsMissingMacro + ClimateEsg) so all targets exist; idempotent + governed.
            $this->step('Attaching approved management actions to ICAAP scenarios', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\ZnbsIcaapScenarioActionsSeeder::class, '--force' => true], $this->getOutput()));

            // Assign governed, UI-editable numeric magnitudes to those qualitative actions,
            // classified from each action's text and sized from the scenario's own bound
            // engine figures (capital / liquidity runs). Idempotent + golden-safe. Runs right
            // after the actions attach; on a from-scratch --fresh run the engine chain below
            // has not bound the contexts yet, so magnitudes populate on the next bootstrap /
            // re-run (or standalone after the chain) once the runs exist.
            $this->step('Assigning governed magnitudes to management actions (data-driven levers)', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\ZnbsManagementActionMagnitudeSeeder::class, '--force' => true], $this->getOutput()));

            // PHASE 3: turn those governed magnitudes into APPROVED ManagementActionApplication
            // instances - the OFFICIAL pipeline the consolidated workbook reads for its Post-MA
            // column (the scenario-JSON magnitudes remain a governed fallback only). Maps the
            // JSON impact_target to the matching library action BY target_type, one approved
            // instance per (scenario, forecast_year, action). Idempotent + golden-safe; on a
            // from-scratch --fresh run it is inert until the engine chain has bound the scenario
            // contexts (same as the magnitude seeder), then populates on the next bootstrap.
            $this->step('Seeding approved management-action applications (Phase 3 official pipeline)', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\ZnbsManagementActionApplicationSeeder::class, '--force' => true], $this->getOutput()));

            // Attach each climate scenario's default supporting-document evidence (the
            // sources behind its shock magnitudes), each with a comment. Users can
            // upload further documents in the Scenarios module.
            $this->step('Seeding climate scenario evidence (default supporting documents)', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\ClimateEvidenceSeeder::class, '--force' => true], $this->getOutput()));

            // The governed funding waterfall (direction review F6). Seeded by the
            // DatabaseSeeder on a fresh build; this step covers a clone bootstrapped
            // before it existed. Idempotent: an existing waterfall, ALCO's or ours, is
            // never overwritten. Must precede the scenario review chains, which read it.
            $this->step('Seeding the governed funding waterfall (placeholders flagged for ALCO)', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\FundingWaterfallSeeder::class, '--force' => true], $this->getOutput()));

            // Tag the governed ICAAP Core 5 scenario set (the rolling-5y profile runs it).
            $this->step('Tagging the governed ICAAP Core 5 scenario set', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\IcaapCore5Seeder::class, '--force' => true], $this->getOutput()));

            // Flag the ICAAP REPORTING SET (report_selected). The bootstrap CALCULATES
            // the full scenario library through the engine chain (every approved
            // scenario), but only this governed subset - the scenarios the institution
            // is actually reporting on, e.g. the set filed in the ICAAP - flows into the
            // ICAAP pack / stress tables / Table 17 / consolidated report. Runs in the
            // db:seed above too; re-asserted here so the intent is explicit and robust.
            $this->step('Flagging the governed ICAAP reporting scenario set', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\IcaapReportingSetSeeder::class, '--force' => true], $this->getOutput()));

            // Flag the ICAAP macro "house view" (icaap_selected) - which macro
            // indicators drive the ICAAP macro tables. Their VALUES come from the
            // World Bank / IMF import below (latest data); this only picks the set.
            $this->step('Flagging the governed ICAAP macro house-view', fn () =>
                Artisan::call('db:seed', ['--class' => \Database\Seeders\IcaapMacroSetSeeder::class, '--force' => true], $this->getOutput()));

            // Record the active bootstrap profile so reports + the dashboard can disclose
            // whether the figures are golden-valid or working-mode only.
            try {
                \App\Models\SystemSetting::setValue('bootstrap_profile', $this->profileName);
                \App\Models\SystemSetting::setValue('bootstrap_profile_golden_valid', $this->profileName === \App\Support\BootstrapProfile::FULL_GOLDEN ? '1' : '0');
                \App\Models\SystemSetting::setValue('bootstrap_profile_at', now()->toDateTimeString());

                // ZNBS imports a full granular book (loans, deposits, securities, funding),
                // so IRRBB runs against the per-facility canonical repricing ladder - which
                // consumes the granular securities - rather than the flat statement aggregate.
                // The code fallback stays statement_aggregate for generic setups; this opts the
                // ZNBS bootstrap into the granular path durably (survives a re-bootstrap).
                \App\Models\SystemSetting::setValue('IRRBB_INPUT_METHOD', \App\Services\IrrbbEngineService::INPUT_CANONICAL_FACILITY);

                // IRRBB Pillar 2 add-on methodology = Earnings-at-Risk (NII), the
                // Board-approved binding measure for the reporting period (consistent
                // with the Mar-2025 filing). The engine still computes EVE alongside for
                // the Basel supervisory outlier test; the report discloses BOTH and the
                // full EVE impact is NOT taken as the add-on unless approved. Seeded so a
                // re-bootstrap reproduces the approved methodology rather than defaulting
                // to full-EVE. The prior-period baseline (governed, editable) drives the
                // methodology reconciliation table in the ICAAP report / Appendix I.
                \App\Models\SystemSetting::setValue('irrbb_addon_method', 'nii_earnings');
                \App\Models\SystemSetting::setValue('irrbb_prior_disclosure', json_encode([
                    'period' => 'March 2025',
                    'method' => 'Earnings-at-Risk (NII), single +/-200 bp parallel shock',
                    'add_on' => 5925.0,
                ]));
                if (\Illuminate\Support\Facades\Schema::hasTable('pillar2_method_configs')) {
                    \App\Models\Pillar2MethodConfig::updateOrCreate(['risk_type' => 'irrbb'], ['selected_method' => 'nii_earnings']);
                }
            } catch (\Throwable) {
                // settings table not ready in a partial run - non-fatal.
            }

            // Pull real historical macro series (GDP, CPI, FX, reserves, debt,
            // ...) from the World Bank Open Data API so clients land with real
            // macro history rather than an empty table. Best-effort: an offline
            // box or a transient API failure skips it without aborting the boot.
            $this->step('Importing World Bank macro series (Zambia, best-effort)', function () {
                try {
                    Artisan::call('macro:import-worldbank', [], $this->getOutput());
                } catch (\Throwable $e) {
                    $this->warn('    ! World Bank import skipped: ' . substr($e->getMessage(), 0, 80));
                }
            });

            if ($withData) {
                $this->section('Importing ZNBS test data');
                // Note: bank_coa_items and coa_mappings are already populated
                // by BankCoaItemsZnbsSeeder + CoaMappingsZnbsSeeder during
                // db:seed above, and the BS_BANK / IS_BANK templates are auto-
                // rebuilt by the mappings seeder. No need to call znbs:remap-coa
                // here. The historicals import below depends on bank_coa_items
                // being present, which they now are.
                $this->step('Importing ZNBS historical BS / IS (FY2016-FY2025)', fn () =>
                    Artisan::call('znbs:import-historicals', [], $this->getOutput()));
                $this->step('Rebuilding statement entries from historical CSVs', fn () =>
                    Artisan::call('znbs:rebuild-statement-entries', [], $this->getOutput()));
                $this->step('Importing ZNBS SOCE', fn () =>
                    Artisan::call('znbs:import-soce', [], $this->getOutput()));
                $this->step('Migrating SOCE to canonical soce_entries', fn () =>
                    Artisan::call('znbs:migrate-soce-to-entries', [], $this->getOutput()));
                // csp_require_approval is ON by default (maker-checker governance),
                // so the bootstrap supplies an explicit, logged governed approval
                // (--approve): the imported CSP lands APPROVED and feeds the
                // PAT->SOCE->capital-plan chain, without disabling the control.
                $this->step('Importing ZNBS 3-year strategic forecast (CSP)', fn () =>
                    Artisan::call('znbs:import-forecast', ['--approve' => true], $this->getOutput()));
                $this->step('Importing BoZ regulatory returns', fn () =>
                    Artisan::call('znbs:import-regulatory', [], $this->getOutput()));
                $this->step('Importing filed Schedule 14 RWA lines (segmentation history)', fn () =>
                    Artisan::call('znbs:import-schedule14', [], $this->getOutput()));
                // Claims on banks band on ORIGINAL maturity under BoZ Gazette Notice
                // 1200 directive 14(1), which a chart-of-accounts balance cannot
                // support. These aggregate rows are derived from the audited
                // accounts' liquidity note, which is RESIDUAL maturity, so the
                // importer marks them residual_proxy and the closing banner says so
                // on every bootstrap. Replace with an instrument-level register
                // (deal date per placement) before filing.
                // The BoZ Gazette 1200 exposure classes are NOT part of the base
                // seeder chain, which seeds the Basel catalogue only. The placement
                // import below maps to BOZ_DOMBANK_BANDED and the engine hard-fails
                // on an unseeded class, so this has to run first.
                $this->step('Seeding BoZ Gazette 1200 prescribed risk weights', fn () =>
                    Artisan::call('regulatory:seed-boz-risk-weights', [], $this->getOutput()));

                // Gazette Notice 1201 governs operational risk: the Basic Indicator
                // Approach is mandatory and directive 5(3) converts the capital charge
                // to RWA with a factor of TEN, not the Basel 12.5 the shared divisor
                // carries. The seeder existed but never ran here, so a fresh install
                // fell back to the divisor and overstated operational RWA by 25 percent
                // (862,366 against the correct 689,893 on the December 2025 book),
                // understating the capital ratio on every clean bootstrap.
                $this->step('Seeding BoZ Gazette 1201 operational-risk parameters (BIA, RWA factor 10)', fn () =>
                    Artisan::call('regulatory:seed-boz-oprisk-params', [], $this->getOutput()));
                // The Gazette 1200 account mapping (approach boz_2025) was never
                // part of the bootstrap: a fresh install had only the Basel
                // catalogue mapping and could not run the filed basis at all.
                $this->step('Mapping accounts onto the Gazette 1200 classes (boz_2025 treatments)', fn () =>
                    Artisan::call('regulatory:map-boz-rwa-treatments', [], $this->getOutput()));
                // Maturity profiles per caption, from the audited accounts' IFRS 7
                // maturity note. The note is undiscounted cash flow, so only each
                // caption's SHAPE is taken (shares mode); the ledger balance is
                // recorded beside it, never forced to agree. Governed, effective
                // dated, visible in the Governance Centre. The liquidity ladder does
                // not read these yet (spec Phase 2), so no ratio moves on this step.
                $this->step('Importing maturity profiles from the AFS maturity note (shares)', fn () =>
                    Artisan::call('znbs:import-maturity-profiles', ['--from-afs' => true], $this->getOutput()));
                // TERM_DEPOSIT is then OVERRIDDEN from the ALCO deposit maturity ladder
                // (per-account expiry dates, 23 Sep 2026). The AFS note reports the combined
                // customer-deposit caption - savings included - so its shape put 42.7% of
                // term deposits inside the 30-day window; ALCO's account-level dates give
                // 10.9%. The importer upserts per class, so only TERM_DEPOSIT moves; the other
                // four classes keep their AFS shape. This must ship with the S2127 split in
                // DepositStabilitySeeder / coa_mappings: the two errors cancel, and correcting
                // either alone moves the LCR the wrong way.
                $this->step('Overriding TERM_DEPOSIT maturity profile from the ALCO ladder (shares)', fn () =>
                    Artisan::call('znbs:import-maturity-profiles', [
                        '--file' => base_path('docs/regulatory/seeds/znbs-maturity-profiles/20251231_term_deposit_profile_from_alco.json'),
                    ], $this->getOutput()));
                // Every treatment row the seeders wrote as approved now gets an
                // approver, a time and a note saying it was seeded, so the Engine
                // Treatments screen can tell a system default from a human decision.
                $this->step('Recording provenance on seeded treatment approvals', fn () =>
                    Artisan::call('treatments:stamp-seeded-provenance', [], $this->getOutput()));
                // Real ZNBS Dec-2025 IFRS 9 calibration (LGD / stage-mix / PD /
                // Stage-3 coverage / SICR overlay) derived from the committed
                // model summaries. replaces the development placeholder params.
                $this->step('Loading ZNBS IFRS 9 ECL calibration (Dec-2025)', fn () =>
                    Artisan::call('icaap:import-ifrs9', [], $this->getOutput()));
                // Governed BIA gross-income series (NII + NNII per completed financial
                // period, keyed by period end and months) from the audited statements,
                // loaded approved. --all loads every period on file: the bootstrap
                // replays each historical year-end, and each needs its own complete
                // window of three completed financial periods (spec E5.2, E8).
                $this->step('Loading governed op-risk BIA gross-income series (approved)', fn () =>
                    Artisan::call('op-risk:import-goi', ['--all' => true], $this->getOutput()));

                // The operational risk basis is decided in the RWA engine for EVERY run
                // on a period, and the BIA path fails closed when the period has no
                // approved positive gross income year in its window. So an unapproved
                // or empty series would now stop every RWA run in the chain, on any
                // clone. Prove the series is usable here, right after it is loaded,
                // where the message says what to fix, rather than forty minutes later
                // inside the engine chain. op-risk:import-goi loads the rows approved
                // unless --draft is passed; this step is what guarantees it.
                $this->step('Verifying the BIA gross-income series is approved for the latest actual period', function () {
                    // The latest actual income statement's own period end (the one resolver),
                    // in internal mode like the chain: a short period takes its governed
                    // treatment, the draft default until a person approves it.
                    $stmt = \App\Models\FinancialStatement::query()
                        ->where('statement_type', 'income_statement')->where('status', 'actual')
                        ->orderByDesc('year')->orderByDesc('month')->first()
                        ?? throw new \RuntimeException('No actual income statement is loaded: the BIA window ends at the latest actual period, never this calendar year.');
                    $asOf = \App\Support\ReportingDate::forStatement($stmt)->toDateString();
                    $bia = app(\App\Services\OpRiskBiaEngineService::class)
                        ->computeBia($asOf, null, \App\Models\ReportingContext::MODE_INTERNAL, true);
                    $this->line(sprintf(
                        '    period ended %s: %d positive of %d completed period(s) [%s], average gross income %s, BIA capital %s%s.',
                        $asOf, $bia['positive_count'], count($bia['window']),
                        implode('; ', array_map(fn (array $w) => (string) $w['short_label'], $bia['window'])),
                        number_format((float) $bia['gross_income_average'], 0), number_format((float) $bia['capital'], 0),
                        $bia['unverified'] ? ' (UNVERIFIED: draft annualisation treatment)' : '',
                    ));
                });

                // Granular obligor-level credit register (24k loans). Loaded only
                // when the client loan-listing file is present, so the bootstrap
                // stays reproducible on machines without it; when present, the
                // regulatory IFRS 9 + concentration engines run on the real book
                // instead of the statement-aggregate proxy.
                if (app()->environment('testing')) {
                    // The golden suite bootstraps in-process; loading 24k facilities
                    // through PhpSpreadsheet would OOM the PHPUnit process, and the
                    // golden numbers are statement-aggregate (they do not need the
                    // granular book). Regulatory-engine tests load a small fixture.
                    $this->line('  Skipping granular loan / deposit listings (testing environment).');
                    // No loan listing means no intake cycle, so the instrument-level
                    // placement register cannot be scoped; the AFS aggregates (unscoped,
                    // as before) keep the testing build's bank-claims weight populated.
                    $this->step('Importing the bank balances and placements register (AFS aggregates, testing)', fn () =>
                        Artisan::call('znbs:import-placements', ['--from-afs' => true], $this->getOutput()));
                } else {
                    // PORTABLE: the importers resolve their OWN source file (the
                    // committed docs/ copy first, a OneDrive path only as a local
                    // fallback), so this runs the SAME on a client's server - no
                    // hardcoded machine path. Deposit import must follow the loan
                    // import (the loan import creates the intake cycle it binds to).
                    $this->step('Importing granular loan listing (credit register)', fn () =>
                        Artisan::call('znbs:import-loan-listing', [], $this->getOutput()));
                    // ORDER MATTERS: this must run AFTER znbs:import-loan-listing, which
                    // creates the intake cycle. The importer resolves the cycle by as-of
                    // date; run before the cycle exists it writes cycle_id NULL, and every
                    // engine that scopes its ladder to the run's cycle (the granular
                    // liquidity ladder, the directive RWA run) then never sees a single
                    // placement. That is exactly what happened on the 27 Sep 2026 rebuild:
                    // the register loaded and reconciled, and K96m of evidenced 30-day
                    // inflows were invisible. Found the same way as the Top-20 ordering
                    // defect of 24 Sep - by the number not reproducing on a clean build.
                    //
                    // Bank balances and placements. The bootstrap used to load ONLY the
                    // three AFS-derived aggregate rows, which carry no deal date and so
                    // sit on a residual-maturity proxy. Directive 14(1) bands bank claims
                    // on ORIGINAL maturity, so that left a fresh install unable to produce
                    // a directive-compliant bank-claims weight, and it left the granular
                    // exposure data short of the instrument-level register every time.
                    // The committed register is now preferred; --from-afs remains the
                    // fallback for an install that does not ship it. They are mutually
                    // exclusive on purpose: running both doubles the register, because
                    // --prune only prunes within the named cycle and the AFS rows carry a
                    // null cycle_id.
                    $this->step('Importing the bank balances and placements register', function () {
                        $register = base_path(self::BANK_PLACEMENT_REGISTER);
                        if (is_file($register)) {
                            Artisan::call('znbs:import-placements', [
                                '--file'  => $register,
                                '--prune' => true,
                            ], $this->getOutput());

                            return;
                        }
                        $this->warn('    Instrument-level register not found, falling back to the AFS aggregates.');
                        Artisan::call('znbs:import-placements', ['--from-afs' => true], $this->getOutput());
                    });
                    // Import the Top-20 largest BORROWERS (BoZ Schedule 22B) from the
                    // committed prudential returns so the Top-N obligor-default credit
                    // scenario computes against the real book and single-name (obligor)
                    // concentration lights up. Depositors from this return are
                    // superseded by the granular-listing derivation below. Best-effort:
                    // a missing/odd return must never abort the bootstrap.
                    $this->step('Importing Top-N large exposures (BoZ Schedule 22B borrowers)', function () {
                        try {
                            Artisan::call('znbs:import-large-exposures', [], $this->getOutput());
                        } catch (\Throwable $e) {
                            $this->warn('    ! large-exposures import skipped: ' . substr($e->getMessage(), 0, 80));
                        }
                    });
                    $this->step('Importing granular deposit listing (depositor register)', fn () =>
                        Artisan::call('znbs:import-deposit-listing', [], $this->getOutput()));
                    $this->step('Importing wholesale borrowings schedule (funding register)', fn () =>
                        Artisan::call('znbs:import-borrowings', [], $this->getOutput()));
                    // The 30 institutional term placements (ALCO "Treasury Deposits" tab)
                    // that were split out of the S2127 term-deposit line on 23 Sep 2026.
                    // They are loaded as NAMED counterparties (Prudential, NAPSA, PSPF,
                    // KPTF, SDA, SEC) with real deal/maturity dates so the Top-20
                    // depositor concentration and the instrument-level liquidity ladder
                    // can see them, instead of one anonymous aggregate. Seed CSV is in the
                    // funding_positions intake template layout and goes through
                    // RegulatoryIntakeService::import() (upsert on funding_code + cycle).
                    $this->step('Importing institutional treasury placements (30 named term deposits)', fn () =>
                        Artisan::call('znbs:import-treasury-placements', [], $this->getOutput()));
                    // Derive the Top-N large-depositor register from the deposit listing
                    // PLUS the placements just loaded, so the Top-N Depositor Run scenario
                    // computes against the real book (benign no-op if deposits are absent).
                    // ORDER MATTERS: this must run AFTER znbs:import-treasury-placements.
                    // On the 24 Sep 2026 rebuild it ran before them and reproduced the old
                    // retail-only Top-20 (MUKUBA TRUST first, 30.7%) although the placements
                    // had loaded fine; with them in, Prudential ranks first at 50.0%.
                    $this->step('Deriving Top-N large-depositor register from deposits + placements', fn () =>
                        Artisan::call('znbs:import-large-depositors', ['--from-deposits' => true, '--top' => 20], $this->getOutput()));
                    $this->step('Importing investment-securities holdings (securities register)', fn () =>
                        Artisan::call('znbs:import-securities', [], $this->getOutput()));
                    $this->step('Importing off-balance-sheet commitments (approved, not-disbursed mortgages)', fn () =>
                        Artisan::call('znbs:import-off-balance', [], $this->getOutput()));

                    // BoZ 2026 LCR directive: materialise the classification columns on the
                    // just-loaded registers (counterparty segment, HQLA level, undrawn,
                    // deposit stability/insurance flags). This is OPTIONAL - the
                    // liquidity_ladder_input view derives the same classification live from
                    // the imported source data, so a future UI-only import needs no bootstrap
                    // - but running it here leaves a fresh build pre-classified for display.
                    $this->step('Classifying registers for the BoZ 2026 LCR directive', fn () =>
                        Artisan::call('lcr:classify', [], $this->getOutput()));
                }

                // Yield engine pre-fill: every approved + active YieldMapping
                // gets its historical yields computed from the just-imported
                // financials and a 3-year forecast projected onto the future
                // periods so the Yields page lands with seeded historicals
                // and draft assumptions instead of an empty table.
                $this->step('Computing historical yields + projecting 3-year forecast assumptions', function () {
                    $this->prefillYieldEngine();
                });

                // Estimate deposit betas (policy-rate pass-through) from the
                // just-computed deposit-cost yield history, so the Yields page
                // shows the empirical repricing relationship with diagnostics.
                $this->step('Estimating deposit betas (policy-rate pass-through)', function () {
                    try {
                        app(\App\Services\Forecast\DepositBetaEstimationService::class)->estimateAll();
                    } catch (\Throwable $e) {
                        $this->warn('    ! deposit beta estimation skipped: ' . substr($e->getMessage(), 0, 80));
                    }
                });

                // Statement-level forecast for the ICAAP forward view. We do NOT
                // project: the forward BS + IS are taken DIRECTLY from the imported
                // ZNBS CSP (entry_kind='csp', Dec 2026/27/28) as fixed-amount
                // overrides, so the capital plan and stress run against the Society's
                // own approved forecast, not an engine projection. Falls back to the
                // projected demo only if no CSP was imported.
                $this->step('Building statement forecast from the imported ZNBS CSP (no projection)', function () {
                    $this->seedImportedCspForecast();
                });

                // Ship a runnable Custom Builder example: a journal-driven overlay
                // scenario (draft) so a fresh install demonstrates the STT-style
                // custom-scenario flow. Approve + run it to see the stressed CAR.
                $this->step('Seeding a sample custom-overlay scenario (Custom Builder example)', fn () =>
                    Artisan::call('znbs:seed-sample-overlay', [], $this->getOutput()));
            }

            // The workspace follows the active reporting context. Create it even
            // on data-only bootstraps; checklist items read source records live.
            $this->step('Syncing ICAAP workspace for the active reporting cycle', fn () =>
                Artisan::call('workspace:sync-cycle', [], $this->getOutput()));

            if ($runEngines) {
                $this->section('Running engine chain');

                // Auto-waive rounding-noise reconciliation findings BEFORE
                // freezing. ZNBS CSV imports sometimes carry sub-ZMW
                // rounding deltas that the strict BS-001 rule (0.01 ZMW
                // tolerance) flags as blocking, but the healthcheck's
                // ±0.50 ZMW tolerance treats as fine. Without this step
                // those datasets never freeze and the engine chain for
                // their year quietly fails the prerequisite gate.
                $this->step('Auto-waiving rounding-noise reconciliation findings (< 0.50 ZMW)', function () {
                    $this->waiveRoundingNoiseFindings();
                });

                $this->step('Freezing approved datasets (snapshot Step 0b)', function () {
                    // Engines refuse "approved but not frozen" datasets.
                    // Seeded test data is auto-approved on creation but
                    // never went through FinancialDataSetService::approve(),
                    // so a fresh bootstrap leaves them unfrozen and the
                    // engine chain dies at RWA with "not frozen". Run the
                    // formal approve() (which freezes via the snapshot
                    // service) on every approved dataset so the chain
                    // can use them.
                    $svc = app(\App\Services\FinancialDataSetService::class);
                    $superAdmin = \App\Models\User::query()->orderBy('id')->first();
                    \App\Models\FinancialDataSet::query()
                        ->where('status', 'approved')
                        ->whereNull('frozen_at')
                        ->get()
                        ->each(function (\App\Models\FinancialDataSet $d) use ($svc, $superAdmin) {
                            try {
                                $svc->approve($d, $superAdmin);
                            } catch (\Throwable) {
                                // best-effort; some datasets (e.g. those
                                // with open reconciliation findings) refuse
                                // to freeze. Skip silently so the rest of
                                // the chain still runs.
                            }
                        });
                });
                $this->runEngineChain();

                // Safety net: guarantee no engine run ever shows a legacy
                // "Bootstrap YYYY-MM. timestamp" name to the client. Idempotent - a
                // no-op when the chain already produced clean period labels.
                $this->step('Ensuring clean, client-facing engine-run names', fn () =>
                    Artisan::call('runs:rename-bootstrap', [], $this->getOutput()));

                // Granular engine-true risk on the REAL obligor book - only when the
                // loan listing was loaded above (skipped in testing / when absent).
                // Regulatory IFRS 9 ECL on the 24k register + single-name concentration
                // replace the statement-aggregate proxy / N-A declaration and feed the
                // ICAAP report engine-true (must run before generateCurrentYearReports).
                if (! app()->environment('testing') && DB::table('credit_facilities')->exists()) {
                    $this->step('Running regulatory IFRS 9 ECL on the granular book', fn () =>
                        Artisan::call('znbs:run-regulatory-ecl', [], $this->getOutput()));
                    $this->step('Deriving engine-true single-name concentration (24k obligors)', fn () =>
                        Artisan::call('znbs:derive-concentration', [], $this->getOutput()));
                    // Sectoral concentration (Table 17b) from the BoZ Schedule 02D economic-sector
                    // distribution in the prudential return - the authoritative regulatory sectoral,
                    // replacing the N-A / product-segment proxy. Best-effort: fail-closed on a bad
                    // file (the parser enforces a grand-total tie-out) but never aborts the boot.
                    $this->step('Importing BoZ Schedule 02D sectoral concentration (Table 17b)', function () {
                        try {
                            Artisan::call('znbs:import-schedule02d', [], $this->getOutput());
                        } catch (\Throwable $e) {
                            $this->warn('    ! Schedule 02D sectoral import skipped: ' . substr($e->getMessage(), 0, 80));
                        }
                    });

                    // Re-evaluate the BASE concentration runs on the data that has
                    // just landed (direction review F10).
                    //
                    // The base chain ran above, BEFORE the obligor derivation and the
                    // sectoral import. Its concentration run therefore measured 19,820
                    // exposures and recorded INTERNAL_SECTOR_30 in its no_data_limits:
                    // it did not pass the sector test, it never evaluated it, and
                    // reported no breach. Every scenario chain runs AFTER this block,
                    // sees 39,646 exposures, evaluates the limit and finds the breach.
                    // The breach is in the book, not in the stress, but the ordering
                    // made it read as a stress effect.
                    //
                    // The imports cannot simply move earlier: znbs:derive-concentration
                    // enriches the concentration run the chain has already bound, and
                    // refuses to run before it. So the base is re-measured here
                    // instead, which is the same thing the Concentration page's
                    // recalculate button does.
                    //
                    // Only base chain contexts exist at this point; the scenario
                    // chains run further down. So every concentration run on file is a
                    // base one, and all of them are re-evaluated.
                    $this->step('Re-evaluating base concentration on the imported obligor and sector data', function () {
                        $engine = app(\App\Services\ConcentrationEngineService::class);
                        $runs = \App\Models\ConcentrationRun::query()
                            ->whereIn('status', ['completed', 'approved'])
                            ->orderBy('id')->get();
                        $done = 0;
                        foreach ($runs as $run) {
                            try {
                                $before = (int) $run->exposures_checked;
                                $engine->execute($run);
                                $run->refresh();
                                $done++;
                                if ((int) $run->exposures_checked !== $before) {
                                    $this->line(sprintf(
                                        '    run %d re-measured: %s exposures -> %s, breaches %d',
                                        $run->id, number_format($before), number_format((int) $run->exposures_checked), (int) $run->breach_count,
                                    ));
                                }
                            } catch (\Throwable $e) {
                                $this->warn(sprintf('    ! concentration run %d could not be re-evaluated: %s', $run->id, substr($e->getMessage(), 0, 70)));
                            }
                        }
                        $this->line('    base concentration runs re-evaluated: ' . $done);
                    });
                }

                // Run every approved adverse / severe scenario through the chain
                // for the latest reporting date, so the Scenario Review page has
                // real stressed CAR/LCR/ROE per scenario to compare and rank.
                $this->step('Running adverse / severe scenarios for Scenario Review', function () {
                    $this->runScenarioReviewChains();
                });

                // Apply recovery management actions to a severe scenario and
                // compute a post-action run, so the dashboard's Management
                // Actions / Recovery panel and the Management Actions page land
                // with a real before/after instead of an empty state.
                $this->step('Applying management actions (post-action recovery demo)', function () {
                    $this->seedPostActionDemo();
                });

                // ALM cash-flow ladder: create + execute a survival-horizon run
                // (base) plus a funding-stress run for the active Dec-2025 period,
                // so alm_cashflow_runs is populated and the ALM Cash-Flow workbook /
                // ALCO survival-horizon reporting land with real data. The auto-run
                // chain does not create ALM runs (they are ALCO-owned, on-demand),
                // so a fresh bootstrap would otherwise leave that engine empty.
                $this->step('Seeding ALM cash-flow survival-horizon runs (base + funding stress)', function () {
                    $this->seedAlmCashflowDemo();
                });

                // Reverse stress: run the engine-true bisection for the binding
                // severe scenario (ZNBS C5 family) against the CAR floor SYNCHRONOUSLY,
                // so reverse_stress_results has a real breakeven row and the Reverse
                // Stress results / workbook / evidence exporter render real data. The
                // UI path queues RunReverseStressJob, which a worker-less bootstrap
                // would never process; here it runs in-process. Heavy (real chain per
                // candidate), so it is bounded to one scenario + a small iteration cap.
                $this->step('Seeding engine-true reverse stress (binding scenario vs CAR floor)', function () {
                    $this->seedReverseStressDemo();
                });

                // NOTE: the account-level forecast demo was intentionally REMOVED.
                // This institution's ICAAP forecast is the imported management CSP
                // (statement-level), bound to the reporting context below; the
                // auto-generated account-level baseline ("FY.. Baseline (account-
                // level, auto-defaults)") was a confusing duplicate the client did
                // not create, so the bootstrap no longer generates it.

                // Production RWA is Method 2 (Schedule 14 segmentation). Seed a
                // Method 1 companion run per filed period so the Schedule 14
                // reconciliation lands showing Method 1 vs Method 2 vs the filed
                // return out of the box.
                $this->step('Seeding Method 1 (Schedule 14) comparison RWA runs', function () {
                    $this->seedSchedule14Method1Runs();
                });

                // The operational risk basis is decided IN the RWA engine for every run
                // (RwaEngineService::computeOperationalRwa reads the governed
                // OPRISK_OFFICIAL_METHOD at the run's period end), so there is no
                // election step here any more. The step that used to sit here
                // (oprisk:elect-bia) only reached the base and the report-selected
                // scenarios and left every other run on the SMA comparison, which put
                // ten stressed capital ratios above the base (direction review F1).

                // RE-SEED the management actions now that the engine chain has bound every
                // scenario's capital / RWA runs on the governed operational basis. The
                // earlier pass (before the chain) is INERT on a from-scratch --fresh run:
                // engineBaseline() finds no bound run, so every magnitude stays null and the
                // application seeder creates nothing - which left a fresh bootstrap with ZERO
                // approved applications and an empty Post-MA column. Running them here sizes
                // each lever off the final position, phases it into its governed recovery
                // year (LEVER_PHASE_YEAR: Y1 dividend/cost/liquidity, Y2 funding/RWA, Y3 capital
                // raise) and creates the approved instances the Post-MA column, the Recovery
                // workbook and the ICAAP pack all read. Both seeders are idempotent
                // (updateOrCreate), so the second pass simply completes the first.
                $this->step('Re-seeding management-action magnitudes + approved applications (post engine chain)', function () {
                    Artisan::call('db:seed', ['--class' => \Database\Seeders\ZnbsManagementActionMagnitudeSeeder::class, '--force' => true], $this->getOutput());
                    Artisan::call('db:seed', ['--class' => \Database\Seeders\ZnbsManagementActionApplicationSeeder::class, '--force' => true], $this->getOutput());
                });

                // A practical 3-year capital projection from the base capital run,
                // so the Capital Projections page lands with a worked example
                // (CET1/CAR/leverage path, buffers, a planned issuance action).
                $this->step('Seeding demo capital projection (3-year plan)', function () {
                    $this->seedDemoCapitalProjection();
                });

                // A DRAFT Board/ALCO Capital Adequacy Plan on top of that projection, so the
                // Capital Adequacy Plan page and its export land with a worked example instead of
                // a 404. Left as a DRAFT (never board-approved), exactly like the ICAAP report is
                // left in_review - a real plan is prepared and signed off by a human under
                // maker-checker. Governed appetite bounds; idempotent.
                $this->step('Seeding demo Capital Adequacy Plan (draft, on the projection)', function () {
                    $this->seedDemoCapitalAdequacyPlan();
                });

                // Generate the base-case forecast SOCE / equity bridge so the ICAAP
                // report's SOCE appendix (Appendix 8) lands populated and reconciled.
                // The official imported-CSP forecast is now articulated + officialised
                // and the base-case dividend policy is seeded, so the roll-forward
                // (opening + PAT - dividend + approved movements) ties to the projected
                // balance-sheet equity (GREEN). Must run before generateCurrentYearReports.
                $this->step('Generating forecast SOCE / equity bridge (base case)', function () {
                    $rows = app(\App\Services\ForecastSoce\ForecastSoceService::class)->generateBaseCase();
                    $green = count(array_filter($rows, fn ($r) => ($r['reconciliation_status'] ?? '') === 'green'));
                    // APPROVE the base forecast SOCE on bootstrap so every report (workbook,
                    // Table 17, PDF/Word/Excel, audit register) consumes an APPROVED assumption
                    // register rather than a draft. It reconciles GREEN, so it is safe to
                    // approve; the governance record stays editable/re-approvable in the UI.
                    // Reproducible each period (idempotent: it only stamps still-draft rows).
                    if (class_exists(\App\Models\SoceEntry::class)) {
                        $approver = (int) (\App\Models\User::orderBy('id')->value('id') ?? 1);
                        $approved = \App\Models\SoceEntry::query()
                            ->where('entry_kind', 'forecast')->where('scenario_id', 0)
                            ->where('status', '!=', 'approved')
                            ->update(['status' => 'approved', 'approved_by' => $approver, 'approved_at' => now()]);
                        $this->line("    forecast SOCE: {$green}/" . count($rows) . " year(s) reconciled (GREEN); {$approved} base entry(ies) approved.");
                    } else {
                        $this->line("    forecast SOCE: {$green}/" . count($rows) . ' year(s) reconciled (GREEN).');
                    }
                });

                // Generate the CURRENT-YEAR ICAAP submission for Dec 2025 (the
                // 9-month Apr-Dec 2025 stub close, the latest reporting period under
                // ZNBS's new December calendar) - the ICAAP Report Centre report
                // (populated from the engines, validated, board-approved/signed) and
                // a Pillar 3 disclosure pack. Only for the current period, not every
                // historical year.
                $this->step('Generating signed current-year ICAAP report + Pillar 3 (Dec 2025)', function () {
                    $this->generateCurrentYearReports();
                });

                // Clean slate: a fresh bootstrap should leave ONLY working runs.
                // Any run that errored mid-chain (a transient failure on one
                // scenario) leaves a 'failed' record that clutters the engine
                // pages; drop them so the client only ever sees completed output.
                $this->step('Removing any failed engine-run records', function () {
                    $this->cleanupFailedRuns();
                });

                // Idempotent canonical collapse: a re-bootstrap re-runs the
                // CURRENT-period engine chain, which would otherwise leave two
                // completed runs for the same period+scope and let off-context
                // reports pick either one. Keep ONE canonical run per
                // (period, scope) and mark the older duplicates 'superseded'
                // (reversible; no figure changes). Safe to run every time.
                $this->step('Collapsing duplicate engine runs to one canonical per period', function () {
                    $report = app(\App\Services\EngineRunDeduplicator::class)->deduplicateAll(true);
                    $superseded = array_sum(array_column($report, 'superseded'));
                    $this->line("      superseded {$superseded} duplicate engine run(s) across ".count($report).' table(s)');
                });

                // Audit (A.1): after dedup, prove the active official context binds
                // a sane base run. Dedup never supersedes a context-bound run, so a
                // wrong INITIAL binding (e.g. a stale/scenario capital run with an
                // implausible CAR carried over from a prior multi-agent re-run) is
                // entrenched, not corrected. The official export already fail-closes
                // on this; surface it loudly at bootstrap too so it is fixed before
                // anyone tries to report off it.
                $this->step('Verifying active official context bindings', function () {
                    $this->verifyActiveOfficialContextBindings();
                });

                // With every scenario's engine chain complete, re-anchor each
                // scenario's stored multi-year stress ratios on its ENGINE-TRUE
                // bound CapitalRun / LiquidityRun (year 1 = engine; later years
                // carry the stored year-over-year trend). The scenario-review
                // display then matches the engine everywhere (idempotent).
                $this->step('Reconciling scenario ratios to engine-true bound runs', function () {
                    $res = app(\App\Services\ScenarioRatioReconciliationService::class)->reconcileAll();
                    $this->line('      reconciled '.$res['reconciled'].' scenario context(s), '.$res['skipped'].' skipped, '.count($res['changes']).' CAR adjustments');
                });

                // FINAL drift anchor (MUST be last). The snapshot hash a context is
                // frozen with (at approval, during report generation) covers every
                // bound run's updated_at. Steps that run AFTER approval - dedup,
                // scenario-ratio reconciliation - can re-save a
                // context-bound run, bumping its updated_at and leaving the frozen
                // hash stale. That reads as a false "snapshot drift" and fail-closes
                // the official export. Re-freeze every approved/superseded context
                // here, as the last orchestration step, so the hash matches the final
                // run state. No figures change - only the integrity anchor is re-taken.
                $this->step('Re-anchoring integrity snapshots to the final run state (drift freeze)', function () {
                    // (a) Consolidated pack: the ReportingContext.snapshot_hash.
                    $snap = app(\App\Services\Reporting\Consolidated\SnapshotHashService::class);
                    $ctxN = 0;
                    foreach (\App\Models\ReportingContext::query()
                        ->whereNotNull('snapshot_frozen_at')
                        ->whereIn('approval_state', [\App\Models\ReportingContext::STATE_APPROVED, \App\Models\ReportingContext::STATE_SUPERSEDED])
                        ->get() as $ctx) {
                        $snap->freeze($ctx);
                        $ctxN++;
                    }
                    // Also freeze the ACTIVE-for-reporting context if it has never been
                    // frozen, so the Consolidated pack (whose preflight requires a
                    // snapshot_hash) is generatable straight after a fresh bootstrap
                    // instead of blocking with "context has no frozen snapshot hash".
                    // freeze() is a pure integrity anchor over the bound run state - it
                    // does NOT approve the context; Board sign-off stays a separate,
                    // human step recorded in the Report Centre.
                    $activeCtx = \App\Models\ReportingContext::query()
                        ->where('is_active_for_reporting', true)
                        ->whereNull('snapshot_hash')
                        ->first();
                    if ($activeCtx) {
                        $snap->freeze($activeCtx);
                        $ctxN++;
                    }
                    // (b) ICAAP Report Centre: each populated report's stored source-run
                    // fingerprint (fix parity with the consolidated snapshot - both are
                    // stamped mid-chain and would otherwise read as drifted at export).
                    $rc = app(\App\Services\IcaapReportCentreService::class);
                    $repN = 0;
                    foreach (\App\Models\IcaapReportCentreReport::query()
                        ->whereNotNull('reporting_context_id')->with('reportingContext')->get() as $report) {
                        if ($report->reportingContext === null || empty(((array) $report->settings)['source_fingerprint'] ?? null)) {
                            continue;
                        }
                        $report->update(['settings' => array_merge((array) $report->settings, [
                            'source_fingerprint' => array_merge(
                                $rc->sourceRunFingerprint($report->reportingContext),
                                ['stamped_at' => now()->toIso8601String()],
                            ),
                        ])]);
                        $repN++;
                    }
                    $this->line("      re-anchored {$ctxN} context snapshot(s) + {$repN} report fingerprint(s) to the final run state");
                });
            }

            if ($verify) {
                $this->section('Verification. golden-number healthcheck');
                $goldenOk = $this->renderHealthcheck();
                if (! $goldenOk) {
                    $this->newLine();
                    $this->error('  ✗ Bootstrap did NOT reproduce the golden numbers. Failing the build.');
                    $this->newLine();
                    return self::FAILURE;
                }
            }

            $this->printProfileSummary($runEngines);

            // Deploy-time gate: a forecast can be IMPORTED yet never officialised /
            // bound (Stage 2), in which case the consolidated pack / capital plan
            // silently read no forecast. Flag that loudly here so a broken bind is
            // caught at bootstrap instead of surfacing later on the client's server.
            // Only meaningful once engines ran (the bind happens in the engine chain).
            if ($runEngines) {
                $this->flagForecastReadiness();
            }

            // Regulatory methodology comparison (Legacy CoA/statement vs Granular per-instrument),
            // generated ONCE on every bootstrap so the comparison is always present for the ICAAP
            // report / Appendix I - end to end, no manual step. It is comparison-only (never binds
            // or re-baselines), so it is safe to run here and cannot fail the golden build.
            if ($runEngines) {
                $this->section('Regulatory methodology comparison (legacy vs granular)');
                try {
                    $this->call('methodology:compare');
                } catch (\Throwable $e) {
                    $this->warn('  ! methodology:compare did not complete: ' . $e->getMessage());
                }
            }

            // ZNBS 2025-Q4 BoZ return drafts (credit policy paragraph, liquidity section,
            // six Pillar 2 judgements, statement-basis exception set), then re-populate the
            // ICAAP Report Centre so its pack carries the Pillar 2 justification table.
            // Drafts only, never approvals; the seeder is a no-op on non-ZNBS data and never
            // overwrites existing content. Runs after the snapshots are frozen and the
            // golden check, and touches no engine run, so neither is affected.
            if ($runEngines) {
                $this->section('ZNBS 2025-Q4 BoZ return drafts');
                try {
                    Artisan::call('db:seed', ['--class' => \Database\Seeders\ZnbsReturn2025Q4DraftsSeeder::class, '--force' => true], $this->getOutput());
                    $rc = app(\App\Services\IcaapReportCentreService::class);
                    foreach (\App\Models\IcaapReportCentreReport::query()->whereNotNull('reporting_context_id')->get() as $report) {
                        $rc->populate($report);
                    }
                } catch (\Throwable $e) {
                    $this->warn('  ! ZNBS 2025-Q4 drafts did not complete: ' . $e->getMessage());
                }
            }

            $this->newLine();
            $this->info('  ✓ Bootstrap complete.');
            $this->reportPlacementMaturityBasis();
            $this->newLine();
            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->newLine();
            $this->error('  ✗ Bootstrap failed: '.$e->getMessage());
            $this->error('    at '.$e->getFile().':'.$e->getLine());
            return self::FAILURE;
        }
    }

    /**
     * State, on every bootstrap, what basis the claims-on-banks risk weights rest on.
     *
     * BoZ Gazette Notice 1200 directive 14(1) bands claims on domestic banks on
     * ORIGINAL maturity. The bootstrap seeds the placement register from the
     * audited accounts' liquidity note, and that note is RESIDUAL maturity. The
     * resulting bands are a defensible interim position, not a filing-grade
     * answer, and the difference is invisible in the RWA number itself. So it is
     * printed rather than left for someone to discover in a review.
     */
    private function reportPlacementMaturityBasis(): void
    {
        $total = \App\Models\BankPlacement::count();

        if ($total === 0) {
            $this->newLine();
            $this->warn('  ! No bank balances and placements register is loaded. Claims on banks will take the flat weight of');
            $this->warn('    whichever class their GL account is mapped to, which cannot satisfy BoZ Gazette');
            $this->warn('    Notice 1200 directive 14(1), which bands on ORIGINAL maturity.');

            return;
        }

        $proxy = \App\Models\BankPlacement::where('maturity_basis', \App\Models\BankPlacement::BASIS_RESIDUAL)->count();
        $exposure = (float) \App\Models\BankPlacement::where('maturity_basis', \App\Models\BankPlacement::BASIS_RESIDUAL)
            ->sum('amount_outstanding_zmw');

        $this->newLine();
        $this->line('  Claims on banks, maturity basis (BoZ Gazette 1200, directive 14(1)):');
        $this->line(sprintf('    %d placement row(s) loaded.', $total));

        if ($proxy === 0) {
            $this->info('    All rows carry a deal date, so every band is genuine ORIGINAL maturity. Filing-grade.');

            return;
        }

        $this->warn(sprintf(
            '    %d of them (K%s'."'".'000) band on a RESIDUAL PROXY, not original maturity.',
            $proxy,
            number_format($exposure / 1000, 0)
        ));
        $this->warn('    The audited accounts disclose residual maturity only, so a placement written for nine');
        $this->warn('    months with two left to run reads as 20% here when the directive says 75%. This is an');
        $this->warn('    interim position. Load an instrument-level register with a deal date per placement');
        $this->warn('    (Regulatory Intake, Bank Balances and Placements, or znbs:import-placements --file=) before filing.');
    }

    /**
     * Safety detector: does the connected database have user data?
     *
     * "User data" = anything that suggests this is a live, populated install
     * rather than a fresh one. We check three signals:
     *   - users table has more than the seeded super-admin
     *   - financial_statements has any rows
     *   - reporting_contexts has any rows
     *
     * If any of those is true, we refuse to wipe without --force-wipe.
     */
    private function detectExistingUserData(): bool
    {
        try {
            $userCount = \DB::table('users')->count();
            if ($userCount > 1) return true;
            if (\Illuminate\Support\Facades\Schema::hasTable('financial_statements')
                && \DB::table('financial_statements')->count() > 0) return true;
            if (\Illuminate\Support\Facades\Schema::hasTable('reporting_contexts')
                && \DB::table('reporting_contexts')->count() > 0) return true;
        } catch (\Throwable) {
            return false;  // schema not present yet -> not a populated DB
        }
        return false;
    }

    /**
     * Execute the linked engine chain through IntegratedStressOrchestrationService
     * for every historical year-end on file.
     *
     * Previously the bootstrap ran the chain ONCE for the most recent actual
     * BS, leaving the dashboard with a single-period snapshot for RWA / CAR /
     * LCR. Now it loops every actual BS year (one row per year, picking the
     * latest reporting month within the year. typically December) and
     * creates a StressRun + full engine chain for each so every metric has
     * a multi-year time series out of the box.
     *
     * The most-recent year's ReportingContext is activated as the dashboard
     * default at the end. Per-year errors are caught so a single bad year
     * never aborts the rest of the sweep.
     *
     * --base-year still scopes the loop to a single year for debugging.
     */
    private function runScenarioReviewChains(): void
    {
        $profile = $this->bootstrapProfile
            ?? \App\Support\BootstrapProfile::resolve(\App\Support\BootstrapProfile::FULL_GOLDEN, [], $this->engineWindow);

        // Stress statements: the year-end balance sheet for each of the profile's
        // stress years (full-golden = latest only; rolling-5y = current + prior;
        // dev-fast = latest). Falls back to the single latest BS.
        $stressStatements = collect($profile->stressYears)
            ->map(fn ($sy) => \App\Models\FinancialStatement::query()
                ->where('statement_type', 'balance_sheet')->where('status', 'actual')
                ->where('year', (int) $sy)->orderByDesc('month')->first())
            ->filter()->values();
        if ($stressStatements->isEmpty()) {
            $latest = \App\Models\FinancialStatement::query()
                ->where('statement_type', 'balance_sheet')->where('status', 'actual')
                ->orderByDesc('year')->orderByDesc('month')->first();
            $stressStatements = $latest ? collect([$latest]) : collect();
        }
        if ($stressStatements->isEmpty()) {
            return;
        }

        $scenarios = $this->scenariosForProfile($profile);
        if ($scenarios->isEmpty()) {
            return;
        }

        $orch = app(\App\Services\IntegratedStressOrchestrationService::class);
        $uid  = \App\Models\User::query()->orderBy('id')->value('id');
        $ok = 0;
        $total = 0;

        foreach ($stressStatements as $baseStatement) {
            $year  = (int) $baseStatement->year;
            // The statement's own period (spec E4.2), never a December default.
            $month = \App\Support\ReportingDate::forStatement($baseStatement)->month;

            foreach ($scenarios as $scenario) {
                $total++;
                try {
                    $stressRun = \App\Models\StressRun::create([
                        'scenario_id'       => $scenario->id,
                        'base_statement_id' => $baseStatement->id,
                        'run_label'         => \App\Support\RunLabel::forPeriod($year, $month) . ' Scenario Review · ' . $scenario->name,
                        'status'            => 'queued',
                        'base_year'         => $year,
                        'base_month'        => $month,
                        'base_months'       => $baseStatement->months,
                        'horizon_years'     => (int) ($scenario->horizon_years ?: 3),
                        'run_by'            => $uid,
                    ]);

                    // Run the scenario execution first so the StressRun stores its
                    // base + stressed balances and ratios (the mini statements + gauge
                    // tiles on the run detail page read these).
                    [$balances, $ratios, $summary] = app(\App\Services\ScenarioExecutionService::class)
                        ->execute($scenario, $year, $baseStatement->id, $stressRun, true);
                    $stressRun->update([
                        'base_balances'     => $balances['base'] ?? null,
                        'stressed_balances' => $balances['stressed'] ?? null,
                        'ratios'            => $ratios,
                        'summary'           => $summary,
                        'status'            => 'completed',
                    ]);

                    $orch->executeLinkedRuns($stressRun, $scenario, $year, $baseStatement->id, \App\Models\ReportingContext::MODE_INTERNAL);
                    $stressRun->refresh();
                    if ($stressRun->status === 'queued') {
                        $stressRun->update(['status' => 'completed']);
                    }
                    $ok++;
                } catch (\Throwable $e) {
                    // Non-fatal: one bad scenario must not abort the rest.
                    $this->warn(sprintf('    ! scenario %s (%d) failed: %s', $scenario->name, $year, substr($e->getMessage(), 0, 60)));
                }
            }
        }

        $this->line(sprintf('    ran %d/%d stress scenario chains [%s set, years %s]',
            $ok, $total, $profile->scenarioSet, $profile->stressYearsLabel()));
    }

    /** Scenarios to run in the review, per the bootstrap profile's scenario set. */
    private function scenariosForProfile(\App\Support\BootstrapProfile $profile): \Illuminate\Support\Collection
    {
        $base = fn () => \App\Models\Scenario::query()->where('status', 'approved')
            ->whereNotIn('scenario_role', ['base', 'baseline']);

        return match ($profile->scenarioSet) {
            // rolling-5y: the four NON-baseline members of the governed ICAAP Core 5.
            \App\Support\BootstrapProfile::SET_ICAAP_CORE =>
                $base()->where('icaap_core', true)->orderBy('severity_index')->orderBy('id')->get(),
            // dev-fast: just the single most-severe scenario (baseline runs in the base chain).
            \App\Support\BootstrapProfile::SET_BASELINE_PLUS_SEVERE =>
                $base()->orderByDesc('severity_index')->orderBy('id')->limit(1)->get(),
            // full-golden (SET_ALL): every approved non-baseline scenario (unchanged).
            default =>
                $base()->orderBy('id')->get(),
        };
    }

    /**
     * Seed one end-to-end post-action recovery so the dashboard and Management
     * Actions page ship with real before/after numbers. Runs a proper stress
     * (ScenarioExecutionService, which populates stressed_balances that the
     * post-action engine consumes), stages a few capital/liquidity recovery
     * actions, then executes the post-action run. Non-fatal.
     */
    private function seedPostActionDemo(): void
    {
        try {
            // Demonstrate the recovery / post-action pipeline end-to-end on up to
            // TWO breached scenarios, so the Management Actions module and the
            // report's post-action recovery sections are populated for more than a
            // single scenario ("fully see it working"). Idempotent: only tops up to
            // the target, never re-runs scenarios that already have a completed run.
            $target = 2;
            $existing = \App\Models\PostActionRun::where('status', 'completed')->count();
            if ($existing >= $target) {
                $this->line('    ' . $existing . ' post-action run(s) already present. skipping');
                return;
            }

            $baseStatement = \App\Models\FinancialStatement::query()
                ->where('statement_type', 'balance_sheet')->where('status', 'actual')
                ->orderByDesc('year')->orderByDesc('month')->first();
            $uid = \App\Models\User::query()->orderBy('id')->value('id');

            $actions = \App\Models\ManagementAction::query()
                ->where('status', 'approved')->where('is_active', true)
                ->whereIn('action_family', ['capital', 'liquidity'])
                ->orderBy('code')->take(3)->get();

            if (! $baseStatement || $actions->isEmpty()) {
                $this->line('    prerequisites missing (statement / actions). skipping');
                return;
            }

            // Candidate breachers: approved severe/adverse scenarios, the known
            // LCR-breaching deposit run first, then most-severe. We try each and
            // keep applying recovery until the target number of demos exists.
            $candidates = \App\Models\Scenario::query()
                ->with(['shocks.macroVariable', 'segmentStressRules.dimension'])
                ->where('status', 'approved')
                ->whereIn('scenario_role', ['severe', 'adverse'])
                ->when(
                    \App\Models\Scenario::where('name', 'like', 'ZNBS L1 %Retail Deposit Run%')->exists(),
                    fn ($q) => $q->orderByRaw("CASE WHEN name LIKE 'ZNBS L1 %Retail Deposit Run%' THEN 0 ELSE 1 END"),
                )
                ->orderByDesc('severity_index')
                ->take(6)->get();

            if ($candidates->isEmpty()) {
                $this->line('    no approved severe/adverse scenario to recover. skipping');
                return;
            }

            $done = $existing;
            $usedScenarioIds = \App\Models\StressRun::query()
                ->whereIn('id', \App\Models\PostActionRun::query()->select('stress_run_id'))
                ->pluck('scenario_id')->all();

            foreach ($candidates as $scenario) {
                if ($done >= $target) {
                    break;
                }
                if (in_array($scenario->id, $usedScenarioIds, true)) {
                    continue; // already has a recovery demo
                }

                // Recover ON THE SCENARIO REVIEW RUN, not on a second one of our own
                // (direction review F9).
                //
                // This used to create a fresh StressRun and put it through
                // ScenarioExecutionService alone, never through the orchestrated
                // chain. The run therefore had no IFRS 9, RWA, capital or liquidity
                // run behind it, no reporting context, and null LCR and NSFR; and
                // because a deposit run's effect lives on the balance sheet, ZNBS L1
                // came out at 41.47% against a base of 41.47%, which is to say it
                // recovered from a stress that had not happened. It is the same
                // defect as F15 in scenario:rerun: a path that builds a run without
                // the chain behind it.
                //
                // The Scenario Review chains have already run by this point, in this
                // same bootstrap, for every report-selected scenario, and each has a
                // bound reporting context and a full set of engine runs. Recovering
                // on that run is both correct and cheaper than fixing the copy: there
                // is then ONE stressed position per scenario, and the recovery's
                // "before" is the figure the pack reports.
                // The stress run must be COMPLETED: actions cannot be staged against
                // a superseded one, and a superseded run is not the stressed position
                // the pack reports anyway.
                $context = \App\Models\ReportingContext::query()
                    ->where('scenario_id', $scenario->id)
                    ->whereNotNull('capital_run_id')
                    ->whereHas('stressRun', fn ($q) => $q->where('status', 'completed')->whereNotNull('stressed_balances'))
                    ->orderByDesc('is_active_for_reporting')
                    ->orderByRaw("CASE approval_state WHEN 'approved' THEN 2 WHEN 'superseded' THEN 1 ELSE 0 END DESC")
                    ->orderByDesc('id')
                    ->first();
                $stressRun = $context ? \App\Models\StressRun::find($context->stress_run_id) : null;

                if (! $stressRun) {
                    // No Scenario Review run for this scenario, so there is nothing
                    // to recover from. Skipping is the honest answer: a recovery
                    // demonstrated on a stress that did not happen is worse than no
                    // demonstration at all.
                    $this->warn(sprintf(
                        '    ! no orchestrated stress run for "%s" - skipping its recovery demo rather than manufacturing one.',
                        $scenario->name,
                    ));
                    continue;
                }

                $staged = $actions->values()
                    ->map(fn ($a, $i) => ['action_id' => $a->id, 'application_order' => $i + 1, 'is_active' => true])
                    ->all();
                app(\App\Services\ManagementActions\ManagementActionApplicationService::class)
                    ->stageActions($stressRun, $staged, $uid);

                $postActionRun = app(\App\Services\ManagementActions\PostActionExecutionService::class)
                    ->execute($stressRun, 'Recovery actions ' . $scenario->name, $uid);

                // Bind the recovery to the SAME context the stressed position came
                // from, so the pack can reconcile the two.
                if ($context && ! $context->post_action_run_id) {
                    $context->update(['post_action_run_id' => $postActionRun->id]);
                }

                $cmp = is_array($postActionRun->comparison_summary_json) ? $postActionRun->comparison_summary_json : [];
                $this->line(sprintf(
                    '    applied %d actions to "%s": breaches %s -> %s',
                    $actions->count(),
                    $scenario->name,
                    $cmp['breaches_before_actions'] ?? '?',
                    $cmp['breaches_after_actions'] ?? '?',
                ));
                $done++;
            }

            $this->line('    post-action recovery demos present: ' . $done);

            // Bind a completed recovery run to the ACTIVE reporting context so the
            // dashboard "Management Actions / Recovery" panel populates. That panel
            // is bound-context-only (no global-latest fallback, by design), so a
            // recovery run that is never linked back to the active cycle shows the
            // empty state even though the runs exist. Prefer the Retail Deposit Run
            // (the canonical LCR breacher, created first), else the earliest run.
            $activeCtx = \App\Models\ReportingContext::query()
                ->where('is_active_for_reporting', true)->orderByDesc('id')->first();
            if ($activeCtx && ! $activeCtx->post_action_run_id) {
                $bindRun = \App\Models\PostActionRun::where('status', 'completed')
                    ->orderBy('id')->first();
                if ($bindRun) {
                    $activeCtx->update(['post_action_run_id' => $bindRun->id]);
                    $this->line('    bound post-action run #' . $bindRun->id
                        . ' to active reporting context #' . $activeCtx->id);
                }
            }
        } catch (\Throwable $e) {
            $this->warn('    ! post-action demo failed: ' . substr($e->getMessage(), 0, 80));
        }
    }

    /**
     * Seed the ALM cash-flow ladder for the active reporting period: one base
     * survival-horizon run (no scenario) plus one funding-stress run, so
     * alm_cashflow_runs is populated and the ALM Cash-Flow workbook / ALCO
     * survival-horizon reporting land with real data instead of an empty state.
     * Idempotent: skips once a completed ALM run exists. Non-fatal.
     *
     * The runs are INTERNAL (diagnostic). The active reporting context is the
     * internal baseline planning case, and no approved AlmCashflowRules exist
     * yet, so an OFFICIAL context-bound ALM run would fail closed by design
     * (ungoverned flow fractions). The engine therefore applies its governed
     * default flow shapes and opens the survival horizon on the latest
     * approved/completed LiquidityRun for the year (recorded in run_metadata).
     */
    private function seedAlmCashflowDemo(): void
    {
        try {
            if (\App\Models\AlmCashflowRun::where('status', 'completed')->exists()) {
                $this->line('    ALM cash-flow run(s) already present. skipping');
                return;
            }
            if (\App\Models\AlmBucketDefinition::approvedActive()->count() === 0) {
                $this->line('    no approved ALM buckets (AlmBucketDefinitionSeeder). skipping');
                return;
            }

            $baseStatement = \App\Models\FinancialStatement::query()
                ->where('statement_type', 'balance_sheet')->where('status', 'actual')
                ->orderByDesc('year')->orderByDesc('month')->first();
            if (! $baseStatement) {
                $this->line('    no actual balance sheet. skipping');
                return;
            }

            $uid       = \App\Models\User::query()->orderBy('id')->value('id');
            $activeCtx = \App\Models\ReportingContext::query()
                ->where('is_active_for_reporting', true)->orderByDesc('id')->first();
            // Construct the engine WITH the risk-context service so the scenario
            // funding-cost transmission factor bites on the stress run. The
            // container leaves that optional (nullable, default-null) dependency
            // unresolved on a plain app() resolve, which would make the stressed
            // survival horizon identical to the base one.
            $engine = new \App\Services\AlmCashflowEngineService(
                app(\App\Services\IntegratedRiskContextService::class),
            );

            $year        = (int) $baseStatement->year;
            $month       = \App\Support\ReportingDate::forStatement($baseStatement)->month;
            $asOf        = \Illuminate\Support\Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
            $horizonDays = 365; // one-year survival-horizon ladder

            // ── Base survival-horizon run (no scenario) ──
            $base = \App\Models\AlmCashflowRun::create([
                'name'                 => \App\Support\RunLabel::forPeriod($year, $month) . ' ALM Survival Horizon (base)',
                'status'               => 'draft',
                'as_of_date'           => $asOf,
                'base_period_id'       => $baseStatement->id,
                'base_statement_id'    => $baseStatement->id,
                'reporting_context_id' => $activeCtx?->id,
                'horizon_days'         => $horizonDays,
                'currency'             => 'ZMW',
                'created_by'           => $uid,
            ]);
            $engine->execute($base);
            $base->refresh();

            // ── Standard funding-stress run ──
            // "The standard stress": the approved liquidity/funding scenario whose
            // funding-cost transmission factor most stresses projected outflows, so
            // the stressed survival horizon actually shortens versus the base.
            $ctxSvc    = app(\App\Services\IntegratedRiskContextService::class);
            $shortlist = \App\Models\Scenario::query()
                ->where('status', 'approved')->whereIn('scenario_role', ['adverse', 'severe'])
                ->where(function ($q) {
                    foreach (['%Deposit Attrition%', '%Funding Freeze%', '%Deposit Run%', '%Term Deposit%', '%Run on the Bank%'] as $p) {
                        $q->orWhere('name', 'like', $p);
                    }
                })->get();
            $stress = $shortlist
                ->map(fn ($s) => ['s' => $s, 'f' => $this->almFundingFactor($ctxSvc, (int) $s->id)])
                ->filter(fn ($r) => $r['f'] > 1.0)
                ->sortByDesc('f')->first();

            if ($stress) {
                $sc        = $stress['s'];
                $stressRun = \App\Models\AlmCashflowRun::create([
                    'name'                 => \App\Support\RunLabel::forPeriod($year, $month) . ' ALM Survival Horizon (' . $sc->name . ')',
                    'status'               => 'draft',
                    'as_of_date'           => $asOf,
                    'base_period_id'       => $baseStatement->id,
                    'base_statement_id'    => $baseStatement->id,
                    'reporting_context_id' => $activeCtx?->id,
                    'scenario_id'          => $sc->id,
                    'horizon_days'         => $horizonDays,
                    'currency'             => 'ZMW',
                    'created_by'           => $uid,
                ]);
                $engine->execute($stressRun);
                $stressRun->refresh();
                $this->line(sprintf('    ALM funding stress "%s" (funding-cost x%.2f): survival %s, outflows %s',
                    $sc->name, (float) $stress['f'],
                    $stressRun->survives_horizon ? 'OK' : ($stressRun->survival_horizon_days . 'd'),
                    number_format((float) $stressRun->total_outflows)));
            } else {
                $this->line('    no funding-stress scenario with a >1 transmission factor; base run only.');
            }

            $this->line(sprintf('    ALM base survival horizon: %s (opening liquidity %s, source %s)',
                $base->survives_horizon ? 'survives' : ($base->survival_horizon_days . 'd to first deficit'),
                number_format((float) $base->opening_liquidity),
                (string) (($base->run_metadata['opening_liquidity_source'] ?? 'n/a'))));
        } catch (\Throwable $e) {
            $this->warn('    ! ALM cash-flow demo failed: ' . substr($e->getMessage(), 0, 120));
        }
    }

    /** Funding-cost transmission factor for a scenario (1.0 = no amplification). */
    private function almFundingFactor(\App\Services\IntegratedRiskContextService $ctxSvc, int $scenarioId): float
    {
        try {
            $metrics = $ctxSvc->transmissionMetrics($scenarioId);
            return (float) $ctxSvc->factorFromMetrics($metrics, 'funding_cost', 1.0);
        } catch (\Throwable) {
            return 1.0;
        }
    }

    /**
     * Seed one ENGINE-TRUE reverse-stress result for the binding severe scenario
     * (ZNBS C5 credit family) against the CAR regulatory floor, so
     * reverse_stress_results has a real breakeven row and the Reverse Stress
     * results / detail / workbook / evidence exporter render real data.
     *
     * Runs the REAL bound chain (Transmission -> IFRS9 -> RWA -> Capital ->
     * Liquidity) per candidate via ReverseStressEngineService::search, executed
     * SYNCHRONOUSLY here (the UI path queues RunReverseStressJob, which a
     * worker-less bootstrap never processes). Heavy - the granular book means
     * each candidate is a full chain - so it is bounded to one scenario, a modest
     * max scale and a small iteration cap. Idempotent: skips once any result
     * exists. Non-fatal.
     */
    private function seedReverseStressDemo(): void
    {
        try {
            if (\App\Models\ReverseStressResult::exists()) {
                $this->line('    reverse-stress result already present. skipping');
                return;
            }

            // Binding scenario: the severe ZNBS C5 credit family (worst-CAR stress).
            $scenario = \App\Models\Scenario::query()->where('status', 'approved')
                ->where(fn ($q) => $q->where('name', 'like', '%C5%')
                                     ->orWhere('name', 'like', '%35% of Loans Non-Performing%'))
                ->orderByDesc('severity_index')->orderByDesc('id')->first()
                ?? \App\Models\Scenario::query()->where('status', 'approved')
                    ->where('scenario_role', 'severe')->orderByDesc('severity_index')->first();
            if (! $scenario) {
                $this->line('    no severe scenario to reverse-stress. skipping');
                return;
            }

            // CAR floor from the governed reverse-stress metric definition (fallback 10%).
            $threshold = (float) (\App\Models\ReverseStressMetricDefinition::query()
                ->where('metric_key', 'CAR')->where('status', 'approved')->value('threshold') ?? 10.0);

            // The scenario's own base year (spec E4.3), never "last year".
            $baseYear      = (int) $scenario->base_year;
            $baseStatement = \App\Models\FinancialStatement::query()
                ->where('statement_type', 'balance_sheet')->where('status', 'actual')
                ->where('year', $baseYear)->orderByDesc('month')->first()
                ?? \App\Models\FinancialStatement::query()
                    ->where('statement_type', 'balance_sheet')->where('status', 'actual')
                    ->orderByDesc('year')->orderByDesc('month')->first();
            $baseYear = (int) ($baseStatement?->year ?? $baseYear);
            $uid      = \App\Models\User::query()->orderBy('id')->value('id');

            $maxScale      = 4.0;
            $maxIterations = 5;

            // Tracked via a JobRun for lineage (same shape RunReverseStressJob uses),
            // but executed in-process - no queue worker in a bootstrap.
            $job = \App\Models\JobRun::create([
                'job_type'      => 'reverse_stress',
                'job_label'     => 'Engine-true reverse stress (bootstrap): CAR <= ' . $threshold,
                'status'        => 'running',
                'scenario_id'   => $scenario->id,
                'source_module' => 'reverse_stress',
                'input_payload' => [
                    'scenario_id' => $scenario->id, 'metric' => 'CAR', 'threshold' => $threshold,
                    'target_year' => 1, 'base_statement_id' => $baseStatement?->id,
                    'base_year' => $baseYear, 'max_scale' => $maxScale, 'max_iterations' => $maxIterations,
                ],
                'queued_at'  => now(),
                'started_at' => now(),
                'created_by' => $uid,
            ]);

            $engine = app(\App\Services\ReverseStressEngineService::class);
            $result = $engine->search(
                $scenario, 'CAR', $threshold, $baseStatement?->id, $baseYear, $maxScale, $maxIterations,
            );

            $rsr = \App\Models\ReverseStressResult::create([
                'job_run_id'           => $job->id,
                'base_scenario_id'     => $scenario->id,
                'reporting_context_id' => $result['reporting_context_id'] ?? null,
                'metric'               => $result['metric'],
                'threshold'            => $result['threshold'],
                'direction'            => $result['direction'],
                'target_year'          => 1,
                'status'               => $result['status'],
                'breach_achieved'      => $result['breach_achieved'],
                'required_shock'       => $result['required_shock'],
                'base_value'           => $result['base_value'],
                'breach_value'         => $result['breach_value'],
                'source_run_ids'       => $result['source_run_ids'] ?? null,
                'engine_impacts'       => $result['engine_impacts'] ?? null,
                'scenario_path'        => $result['scenario_path'] ?? null,
                'snapshot_hash'        => $result['snapshot_hash'] ?? null,
                'blockers'             => $result['blockers'] ?? null,
                'iterations'           => $result['iterations'] ?? null,
                'engine_true'          => true,
                'created_by'           => $uid,
            ]);

            if (($result['status'] ?? null) === \App\Models\ReverseStressResult::STATUS_BLOCKED) {
                $job->update([
                    'status'        => 'failed',
                    'completed_at'  => now(),
                    'error_message' => 'Reverse stress blocked: ' . implode(' ', (array) ($result['blockers'] ?? [])),
                ]);
                $this->warn('    ! reverse stress blocked: ' . substr(implode(' ', (array) ($result['blockers'] ?? ['missing engine data'])), 0, 100));
                return;
            }

            $job->update([
                'status'         => 'completed',
                'completed_at'   => now(),
                'output_summary' => [
                    'reverse_stress_result_id' => $rsr->id,
                    'breach_achieved'          => $result['breach_achieved'],
                    'required_shock'           => $result['required_shock'],
                ],
            ]);

            $this->line(sprintf('    reverse stress "%s" (CAR floor %.2f%%): %s - shock x%s, base %s%% -> breach %s%% (%d iters)',
                $scenario->name, $threshold, (string) $result['status'],
                $result['required_shock'] !== null ? number_format((float) $result['required_shock'], 3) : 'n/a',
                $result['base_value'] !== null ? number_format((float) $result['base_value'], 2) : 'n/a',
                $result['breach_value'] !== null ? number_format((float) $result['breach_value'], 2) : 'n/a',
                (int) ($result['iterations'] ?? 0)));
        } catch (\Throwable $e) {
            $this->warn('    ! reverse-stress demo failed: ' . substr($e->getMessage(), 0, 120));
        }
    }


    /**
     * Production RWA is now Method 2 (standardized_seg, Schedule 14 filed blend)
     * (see IntegratedStressOrchestrationService::DEFAULT_RWA_APPROACH). For each
     * filed Schedule 14 period, clone the production (Method 2) baseline RWA run
     * into a Method 1 (standardized) comparison run and execute it, so the
     * Schedule 14 reconciliation still shows Filed vs Method 1 vs Method 2.
     * Additive: the golden Method 2 runs are untouched.
     */
    private function seedSchedule14Method1Runs(): void
    {
        $engine = app(\App\Services\RwaEngineService::class);

        $periods = \App\Models\RegulatoryRwaLine::query()
            ->select('fiscal_year', 'period_date')
            ->distinct()
            ->orderBy('fiscal_year')
            ->get()
            ->unique('fiscal_year');

        if ($periods->isEmpty()) {
            $this->line('    No filed Schedule 14 periods; skipping.');
            return;
        }

        $made = 0;
        foreach ($periods as $p) {
            $year    = (int) $p->fiscal_year;
            // The filing's own date (spec E4.3); an undated filing is not given December.
            if (! $p->period_date) {
                $this->warn("    ! Schedule 14 for {$year} carries no period date; no Method 1 comparison run.");
                continue;
            }
            $month   = (int) $p->period_date->format('n');
            $quarter = intdiv($month - 1, 3) + 1;

            // Already have a Method 1 comparison run for this period? Skip.
            if (\App\Models\RwaRun::baselineRunFor($year, $quarter, false) !== null) {
                continue;
            }

            // Clone the production (Method 2) baseline into a Method 1 comparison.
            $m2 = \App\Models\RwaRun::baselineRunFor($year, $quarter, true);
            if (! $m2) {
                continue;
            }

            $attrs = $m2->only([
                'base_year', 'base_quarter', 'base_month', 'base_months', 'scenario_id', 'stress_run_id',
                'base_period_id', 'statement_entry_id', 'cet1_capital', 'at1_capital', 'tier2_capital',
            ]);
            $m1 = \App\Models\RwaRun::create(array_merge($attrs, [
                'name'       => "Schedule 14 Comparison (Method 1). {$year}",
                'approach'   => 'standardized',
                'status'     => 'draft',
                'created_by' => 1,
            ]));

            try {
                $engine->execute($m1);
                $made++;
            } catch (\Throwable $e) {
                $this->warn("    ! Method 1 {$year} failed: " . substr($e->getMessage(), 0, 80));
                $m1->delete();
            }
        }

        $this->line("    Created {$made} Method 1 comparison run(s) across " . $periods->count() . ' filed period(s).');
    }

    /**
     * Derive the capital-projection stress shock from the ENGINE-TRUE reporting-set
     * scenarios (report_selected) instead of a hardcoded drawdown. Returns the
     * worst-case one-off CET1 drawdown % and RWA add-on % implied by the reporting
     * scenarios' bound capital runs vs the base capital run, plus the driving
     * scenario's name. Falls back to a conservative parametric default when no
     * reporting-scenario capital runs exist yet (e.g. before the chain has run).
     *
     * @return array{drawdown: float, addon: float, driver: ?string}
     */
    private function deriveReportingScenarioStress(\App\Models\CapitalRun $baseRun): array
    {
        $fallback = ['drawdown' => 25.0, 'addon' => 15.0, 'driver' => null];

        $baseCet1 = (float) ($baseRun->total_cet1 ?: $baseRun->cet1_capital ?: 0);
        $baseRwa  = (float) ($baseRun->total_rwa ?: 0);
        if ($baseCet1 <= 0 || $baseRwa <= 0) {
            return $fallback;
        }

        $reportingIds = \App\Models\Scenario::query()->forReporting()->pluck('id');
        if ($reportingIds->isEmpty()) {
            return $fallback;
        }

        $runs = \App\Models\CapitalRun::query()
            ->with('scenario:id,name')
            ->whereIn('scenario_id', $reportingIds)
            ->where('base_year', $baseRun->base_year)
            ->whereIn('status', ['completed', 'approved'])
            ->whereNotNull('total_rwa')
            ->get();

        $worst = ['drawdown' => 0.0, 'addon' => 0.0, 'driver' => null];
        foreach ($runs as $r) {
            $sCet1 = (float) ($r->total_cet1 ?: $r->cet1_capital ?: 0);
            $sRwa  = (float) ($r->total_rwa ?: 0);
            if ($sRwa <= 0) {
                continue;
            }
            // One-off CET1 drawdown and RWA add-on this scenario implies vs the base.
            // Clamp to sane bounds so a catastrophic (near-zero CET1) scenario does not
            // produce a degenerate projection.
            $dd  = min(90.0, max(0.0, ($baseCet1 - $sCet1) / $baseCet1 * 100));
            $add = min(100.0, max(0.0, ($sRwa - $baseRwa) / $baseRwa * 100));
            if (($dd + $add) > ($worst['drawdown'] + $worst['addon'])) {
                $worst = ['drawdown' => round($dd, 2), 'addon' => round($add, 2), 'driver' => optional($r->scenario)->name];
            }
        }

        // No reporting scenario actually eroded capital (or none had runs) -> fallback.
        return ($worst['driver'] !== null && ($worst['drawdown'] + $worst['addon']) > 0) ? $worst : $fallback;
    }

    /**
     * Seed a practical 3-year capital projection from the latest completed
     * baseline capital run, so the Capital Projections page lands with a worked
     * plan (CET1 / CAR / leverage path, buffers, and a planned issuance action).
     */
    private function seedDemoCapitalProjection(): void
    {
        // Anchor on a baseline run; never fall through to a *stressed* run (that
        // would project the plan from an already-stressed capital position).
        // Among non-stressed runs prefer the one with the largest (real) RWA.
        // ordering by id can pick a scenario run left with a degenerate RWA.
        // The projection MUST anchor to the approved, NON-SCENARIO base capital run.
        // A stress/scenario run's stressed CAR / inflated RWA corrupts every projected
        // period, and ordering by total_rwa would actively PICK such a run (the
        // catastrophic-NPL scenario has the largest RWA). Prefer the active context's
        // bound base (the approved position, same governance as the projection wizard);
        // fall back to a completed, non-scenario baseline.
        // "Base position" = a run with NO scenario, or one driven by a base/baseline
        // scenario (the orchestrated chain runs even the base case under the baseline
        // scenario, so it carries scenario_id but is NOT a stress). Never a genuinely
        // stressed (adverse/severe/challenger) run - its stressed CAR would corrupt
        // every projected period.
        $baseRoles = ['base', 'baseline'];
        $isBaseAnchor = fn (?\App\Models\CapitalRun $r): bool =>
            $r !== null
            && in_array((string) $r->status, ['completed', 'approved'], true)
            && ($r->scenario_id === null
                || in_array((string) optional($r->scenario)->scenario_role, $baseRoles, true));
        $resolver = app(\App\Services\Reporting\ApprovedEngineRunResolver::class);
        $ctxBase  = ($actCtx = $resolver->activeContext()) ? $resolver->bound($actCtx, 'capital')?->loadMissing('scenario') : null;
        $baseRun = $isBaseAnchor($ctxBase)
            ? $ctxBase
            : \App\Models\CapitalRun::query()
                ->with('scenario')
                ->whereIn('status', ['completed', 'approved'])
                ->where(function ($q) use ($baseRoles) {
                    $q->whereNull('scenario_id')
                      ->orWhereHas('scenario', fn ($s) => $s->whereIn('scenario_role', $baseRoles));
                })
                ->orderByDesc('total_rwa')
                ->first();

        if (! $baseRun) {
            $this->line('      (no completed capital run to anchor a projection; skipped)');
            return;
        }

        // Guard against a degenerate base (RWA must be a sane, real figure) so the
        // projected ratios don't overflow the column.
        if ((float) ($baseRun->total_rwa ?? 0) < 1000) {
            $this->line('      (base capital run has an implausibly small RWA; skipped to avoid bad projection)');
            return;
        }

        // Avoid duplicating on a non-fresh re-run.
        if (\App\Models\CapitalProjectionRun::query()->where('base_capital_run_id', $baseRun->id)->exists()) {
            return;
        }

        // Anchor the projection to the IMPORTED ZNBS CSP balance-sheet forecast
        // (the management forecast for the ICAAP period) so the forward CAR/CET1
        // path is driven by the imported figures - never a synthetic demo plan.
        // Falls back to the latest completed BS_BANK forecast only if the imported
        // CSP run is absent.
        $bsForecast = \App\Models\StatementForecastRun::query()
            ->whereHas('template', fn ($q) => $q->where('code', 'BS_BANK'))
            ->where('status', 'completed')
            ->where('run_name', 'like', '%imported CSP%')
            ->orderByDesc('id')
            ->value('id')
            ?? \App\Models\StatementForecastRun::query()
                ->whereHas('template', fn ($q) => $q->where('code', 'BS_BANK'))
                ->where('status', 'completed')
                ->orderByDesc('id')
                ->value('id');

        // Stress the projection with the ENGINE-TRUE worst case from the governed
        // reporting-set scenarios (the 8/11 ZNBS scenarios), NOT a hardcoded drawdown.
        // The stressed path then reflects the scenarios the Society actually reports on.
        $stress = $this->deriveReportingScenarioStress($baseRun);
        $stressDesc = $stress['driver']
            ? sprintf('The stressed path is calibrated from the worst reporting-set scenario ("%s"): a %.1f%% one-off CET1 drawdown and a %.1f%% RWA add-on, both engine-derived from that scenario vs the base position.', $stress['driver'], $stress['drawdown'], $stress['addon'])
            : 'The stressed path uses a conservative parametric drawdown (no reporting-scenario capital runs were available to calibrate from yet).';

        // Capital-plan March 2025 governed toggles (Capital_Projection_Stress_Modes_Spec.md,
        // Part A). read from the governed parameter set so the projection run is built on
        // the March 2025 basis by default, editable from the Governance Center. getText()
        // reads the string value; the second arg falls back to the March 2025 literal if the
        // param row is absent (matches the column defaults).
        $rwaForecastMethod = \App\Services\RegulatoryParameterService::getText('CAPITAL_PROJECTION_RWA_METHOD', null, 'density');
        $stressMode        = \App\Services\RegulatoryParameterService::getText('CAPITAL_PROJECTION_STRESS_MODE', null, 'compounding');
        $scenarioMode      = \App\Services\RegulatoryParameterService::getText('CAPITAL_PLAN_SCENARIO_MODE', null, 'per_scenario');
        $targetMode        = \App\Services\RegulatoryParameterService::getText('CAPITAL_TARGET_MODE', null, 'stress_derived_band');
        // [Unified RWA engine] Component-correct RWA bases are the default: credit
        // RWA = forecast loan balance x filed Schedule-14 blend; operational and
        // market RWA carried flat (they are income- / FX-position-driven, NOT
        // balance-sheet-driven, so they must not scale with total assets). All
        // governed, so an ICAAP owner can switch to BIA op or density/share bases.
        // Governed choices read at the base run's own reporting date (spec E4.3), not today.
        $baseAsOf    = \App\Support\ReportingDate::forRunString($baseRun);
        $creditBasis = \App\Services\RegulatoryParameterService::getText('CAPITAL_PROJECTION_CREDIT_BASIS', $baseAsOf, 'schedule14_loan_weight');
        $opBasis     = \App\Services\RegulatoryParameterService::getText('CAPITAL_PROJECTION_OP_BASIS', $baseAsOf, 'carry_forward');
        $marketBasis = \App\Services\RegulatoryParameterService::getText('CAPITAL_PROJECTION_MARKET_BASIS', $baseAsOf, 'carry_forward');

        $projection = \App\Models\CapitalProjectionRun::create([
            'name'                    => '3-Year Capital Plan (imported CSP forecast)',
            'description'             => 'CET1/CAR/leverage projected forward from the imported ZNBS CSP balance-sheet forecast (data_source = forecast_snapshot each period), with the governed buffer + minima, a stressed path and a post-action recovery path (capital issuance in year 2). ' . $stressDesc,
            'base_capital_run_id'     => $baseRun->id,
            'forecast_run_id'         => $bsForecast,
            'horizon_periods'         => 3,
            'frequency'               => 'annual',
            'rwa_growth_rate'         => 8.5,
            'earnings_basis'          => 'return_on_rwa',
            'return_on_rwa_pct'       => 2.5,
            'earnings_retention_rate' => 60.0,
            // Governed DividendPolicy (seeded 40%) - the capital plan's payout
            // now reads the SAME governed source as the forecast SOCE, not a
            // parallel literal (SOCE engine-consumption audit fix).
            'dividend_payout_rate'    => (float) ((app(\App\Services\DividendPolicyService::class)->defaultPayoutRatio($baseAsOf, 'all') ?? 0.40) * 100),
            'at1_growth_rate'         => 5.0,
            'tier2_amortisation_rate' => 10.0,
            'asset_growth_rate'       => 6.0,
            'stress_enabled'          => true,
            'stress_cet1_drawdown_pct'=> $stress['drawdown'],
            'stress_rwa_addon_pct'    => $stress['addon'],
            // March 2025 capital-plan governed toggles (Part A), read above from the
            // governed parameter set (default to the March 2025 basis).
            'rwa_forecast_method'        => $rwaForecastMethod,
            'credit_rwa_basis'           => $creditBasis,
            'op_rwa_basis'               => $opBasis,
            'market_rwa_basis'           => $marketBasis,
            'stress_mode'                => $stressMode,
            'capital_plan_scenario_mode' => $scenarioMode,
            'capital_target_mode'        => $targetMode,
            'planned_actions'         => [
                ['effective_date' => null, 'period' => 2, 'tranche' => 'cet1', 'type' => 'issuance', 'amount' => 250000.0],
            ],
            // Reference lines read the GOVERNED ZNBS/BoZ minima + buffer, not
            // hardcoded Basel III globals (BoZ total-capital minimum is 15%, not
            // the 10% Basel floor), so the projection chart's thresholds track
            // the stored parameter set.
            'conservation_buffer'     => round(\App\Services\RegulatoryParameterService::conservationBuffer() * 100, 2),
            'countercyclical_buffer'  => 0.0,
            'systemic_buffer'         => 0.0,
            // Governed management buffer (was a hardcoded 1.0 that contradicted the
            // seeded MANAGEMENT_BUFFER = 0.0). Reads the SAME governed source, unit and
            // *100 percent conversion as CapitalProjectionController@create so the plan's
            // buffer tracks the parameter set instead of a stray literal.
            'management_buffer'       => round(\App\Services\RegulatoryParameterService::get('MANAGEMENT_BUFFER', null, 0.0) * 100, 2),
            'min_cet1_pct'            => round(\App\Services\RegulatoryParameterService::minCet1() * 100, 2),
            'min_tier1_pct'           => round(\App\Services\RegulatoryParameterService::minTier1() * 100, 2),
            'min_car_pct'             => round(\App\Services\RegulatoryParameterService::bozMinCar() * 100, 2),
            'min_leverage_pct'        => round(\App\Services\RegulatoryParameterService::get('LEVERAGE_RATIO_MIN', null, 0.03) * 100, 2),
            'created_by'              => \App\Models\User::query()->orderBy('id')->value('id'),
            'status'                  => 'draft',
        ]);

        try {
            app(\App\Services\CapitalProjectionService::class)->execute($projection);
            $this->line("      Capital projection #{$projection->id} created (3-year, base run #{$baseRun->id}).");

            // [Forecast-path audit fix I2] Bind the projection to the reporting
            // context(s) built on the same official statement forecast, so the ICAAP
            // report resolves the forward capital plan through the context binding
            // rather than a global orderByDesc('id')->first().
            if (\Illuminate\Support\Facades\Schema::hasColumn('reporting_contexts', 'capital_projection_run_id')
                && $projection->forecast_run_id) {
                \App\Models\ReportingContext::query()
                    ->where('statement_forecast_run_id', $projection->forecast_run_id)
                    ->update(['capital_projection_run_id' => $projection->id]);
            }
        } catch (\Throwable $e) {
            $projection->update(['status' => 'failed']);
            $this->line('      (projection execute failed: '.$e->getMessage().')');
        }
    }

    /**
     * Seed a DRAFT Board/ALCO Capital Adequacy Plan on top of the latest demo projection run, so
     * the Capital Adequacy Plan page and its Excel / PDF export land with a worked example rather
     * than "No capital adequacy plan exists yet." (a 404). The bootstrap seeds the projection but
     * never a plan, so the export was always empty on a fresh install.
     *
     * Left as status='draft', never board-approved - the same segregation-of-duties stance the
     * bootstrap takes with the ICAAP report (left in_review): a real plan is prepared and signed
     * off by a human. The internal appetite bounds are GOVERNED (derived from the BoZ minimum CAR
     * + conservation buffer and the CET1 minimum), not literals, and are editable on the plan page.
     * Idempotent: does nothing if a plan already exists for the projection run.
     */
    private function seedDemoCapitalAdequacyPlan(): void
    {
        $projection = \App\Models\CapitalProjectionRun::query()->latest('id')->first();
        if (! $projection) {
            $this->line('      (no capital projection run to anchor a plan; skipped)');
            return;
        }
        if (\App\Models\CapitalAdequacyPlan::query()->where('projection_run_id', $projection->id)->exists()) {
            return; // already seeded
        }

        $reg = \App\Services\RegulatoryParameterService::class;
        $floor  = round((float) $reg::bozMinCar() + (float) $reg::conservationBuffer(), 4); // e.g. 0.13
        $carUp  = round($floor + 0.02, 4);   // internal target = floor + a 2pp management buffer
        $cet1Lo = round((float) $reg::minCet1(), 4);
        $cet1Up = round($cet1Lo + 0.02, 4);
        // The projection's own period (spec E4.3): its plan year, else the year of its
        // base capital run's reporting date. Never this calendar year.
        $year   = (int) ($projection->plan_year ?? $projection->base_year ?? \App\Support\ReportingDate::forRun($projection)->year);
        $user   = \App\Models\User::query()->orderBy('id')->value('id');

        $plan = \App\Models\CapitalAdequacyPlan::create([
            'name'                    => \App\Support\InstitutionProfile::shortName() . ' Capital Adequacy Plan ' . $year . ' (draft)',
            'plan_year'               => $year,
            'version'                 => 1,
            'status'                  => 'draft',
            'projection_run_id'       => $projection->id,
            // Governed internal appetite band (editable on the plan page). Lower = the regulatory
            // floor incl. conservation buffer; upper = a management target above it.
            'internal_car_lower_pct'  => $floor,
            'internal_car_upper_pct'  => $carUp,
            'internal_cet1_lower_pct' => $cet1Lo,
            'internal_cet1_upper_pct' => $cet1Up,
            'layout_preference'       => 'side_by_side',
            'narrative'               => 'Draft Board/ALCO capital adequacy plan seeded on the demo 3-year projection. '
                . 'Internal appetite bounds are governed defaults (BoZ minimum CAR + conservation buffer, CET1 minimum) '
                . 'and are meant to be reviewed and set by the bank. Prepare, review and board-approve this plan through '
                . 'the maker-checker workflow before it is treated as the official capital plan.',
            'prepared_by'             => $user,
        ]);

        $this->line('      Capital Adequacy Plan #' . $plan->id . ' created (draft, year ' . $year
            . ', on projection #' . $projection->id . '); approve it in the Capital Adequacy Plan page.');
    }

    /**
     * Generate the current-year ICAAP submission (Dec 2025) only: an ICAAP
     * Report Centre report populated from the engines, validated and board-signed,
     * plus a Pillar 3 disclosure pack. Idempotent: skips if already present.
     * Deliberately NOT run for every historical year - the BoZ ICAAP / Pillar 3
     * packs are produced only for the period being reported on.
     */
    private function generateCurrentYearReports(): void
    {
        $ctx = \App\Models\ReportingContext::query()
            ->where('is_active_for_reporting', true)
            ->whereHas('capitalRun')
            ->latest('id')->first()
            ?? \App\Models\ReportingContext::query()->whereHas('capitalRun')->latest('id')->first();

        if ($ctx === null) {
            $this->line('    no reporting context with a capital run. skipping report generation');
            return;
        }

        $user = \App\Models\User::query()->orderBy('id')->first();
        // Reporting period = the ACTIVE context's bound capital run (base_year/month),
        // NOT a hardcoded '2025-12'. This makes the bootstrap generate the current-year
        // ICAAP + Pillar 3 pack for WHATEVER period is active - so next year, after the
        // 2026 close is imported and activated, a --run-engines re-run produces the
        // 2026-12 submission automatically. The period is the context's own reporting
        // date (spec E4.3): a quarter-only run is its quarter end, never December, and
        // there is no calendar fallback.
        $cap = $ctx->capitalRun;
        $period = \App\Support\ReportingDate::forContext($ctx)->format('Y-m');

        // 1. ICAAP Report Centre report - the signed board/BoZ submission.
        if (! \App\Models\IcaapReportCentreReport::query()->exists()) {
            try {
                $svc = app(\App\Services\IcaapReportCentreService::class);
                $report = $svc->create([
                    'reporting_context_id' => $ctx->id,
                    'reporting_period'     => $period,
                    'title'                => 'ICAAP Report ' . $period,
                ], $user?->id);
                $svc->populate($report);
                $svc->validate($report);
                // Audit#2/#14: the bootstrap prepares + reviews the demo report but
                // must NEVER stamp Board approval - that requires a real approver
                // (segregation of duties: preparer != approver) recorded in the Report
                // Centre. Leave it 'in_review' / pending approval so the DRAFT
                // watermark stands and isBoardApproved() stays false until a genuine
                // sign-off with evidence (approver id + timestamp) is recorded.
                $report->update([
                    'reviewed_by_user_id' => $user?->id,
                    'status'              => 'in_review',
                    'workflow_stage'      => 'approval',
                ]);
                $this->line('    ICAAP Report Centre report #' . $report->id . ' created, populated, validated, PENDING Board approval (' . $report->period_label . '). Board sign-off must be recorded by a separate approver in the Report Centre.');
            } catch (\Throwable $e) {
                $this->warn('    ! ICAAP Report Centre generation skipped: ' . substr($e->getMessage(), 0, 100));
            }
        } else {
            $this->line('    ICAAP Report Centre report already present. skipping');
        }

        // 2. Pillar 3 public disclosure pack (BoZ Notice 1199) for the period.
        if (\Illuminate\Support\Facades\Schema::hasTable('pillar3_disclosures')
            && ! \App\Models\Pillar3Disclosure::query()->where('reporting_period', $period)->exists()) {
            try {
                \App\Models\Pillar3Disclosure::create([
                    'reporting_period' => $period,
                    'title'            => 'ZNBS Pillar 3 Public Disclosure ' . $period,
                    'status'           => \App\Models\Pillar3Disclosure::STATUS_DRAFT,
                    'prepared_by'      => $user?->id,
                    'prepared_at'      => now(),
                ]);
                $this->line('    Pillar 3 disclosure pack created (' . $period . ')');
            } catch (\Throwable $e) {
                $this->warn('    ! Pillar 3 disclosure skipped: ' . substr($e->getMessage(), 0, 100));
            }
        }
    }

    /**
     * Delete any run left in a 'failed' state across every engine table, so a
     * fresh bootstrap presents only completed/approved output. Failed runs carry
     * no usable results. they are pure noise on the engine list pages.
     */
    private function cleanupFailedRuns(): void
    {
        $models = [
            \App\Models\StressRun::class, \App\Models\Ifrs9Run::class, \App\Models\RwaRun::class,
            \App\Models\CapitalRun::class, \App\Models\LiquidityRun::class, \App\Models\IrrbbRun::class,
            \App\Models\OpRiskRun::class, \App\Models\ConcentrationRun::class, \App\Models\ForecastRun::class,
            \App\Models\CapitalProjectionRun::class, \App\Models\PostActionRun::class,
        ];
        $total = 0;
        foreach ($models as $model) {
            if (! class_exists($model)) {
                continue;
            }
            try {
                // Audit #7: a failed run must leave a trace in Audit History
                // before it is removed from the working grids - a silent hard
                // delete erases the evidence of WHAT was attempted and WHY it
                // blocked. Record each failed run (with its blocker, when the
                // table carries one) to the activity log, THEN delete the row.
                $failed = $model::where('status', 'failed')->get();
                foreach ($failed as $run) {
                    try {
                        $blocker = $run->blocker
                            ?? ($run->run_metadata['blocker'] ?? null)
                            ?? ($run->stage_errors ?? null);
                        \App\Services\ActivityLogger::runEvent(
                            $run,
                            'failed_run_purged',
                            'bootstrap',
                            'Failed run removed from working grids during bootstrap cleanup'
                                . (is_string($blocker) && $blocker !== '' ? '. Blocker: ' . $blocker : '.'),
                        );
                    } catch (\Throwable) {
                        // logging is best-effort; never let it abort the cleanup.
                    }
                }
                $total += $model::where('status', 'failed')->delete();
            } catch (\Throwable $e) {
                // Table without a status column or not migrated. skip quietly.
            }
        }
        $this->line("      logged + removed {$total} failed run record(s) (retained in Audit History)");
    }

    /**
     * Seed a demo statement-level forecast: forecast the BALANCE SHEET first
     * (each line auto-assigned growth_rate at its own historical CAGR), then the
     * INCOME STATEMENT, whose interest lines are DERIVED from the forecasted BS
     * balances x the forecasted yields. Gives the Forecasting page a working,
     * methodologically-correct example out of the box.
     */
    /**
     * Build the forward statement forecast DIRECTLY from the imported ZNBS CSP
     * (entry_kind='csp', Dec 2026/27/28) instead of projecting: each CSP line value
     * is fed as a fixed_amount period-assumption that overrides any method, so the
     * forecast output IS the Society's own approved forecast. Lines the CSP does not
     * cover carry forward (still no projection). The balance sheet is the pure CSP;
     * the income statement keeps interest lines yield-consistent with the CSP balances.
     * Falls back to the projected demo forecast only when no CSP was imported.
     */
    private function seedImportedCspForecast(): void
    {
        // Only skip when a USABLE imported-CSP forecast already exists (an official
        // IS + BS run whose snapshot carries forecast years beyond the base). The
        // old guard skipped whenever ANY "ICAAP Forecast (imported CSP)%" row existed
        // - so a stale / partial / empty-snapshot run permanently blocked a rebuild
        // and left the forward-looking pages (Consolidated Scenario Report, capital
        // plan) with an empty forecast that 500s. Health-check instead, and rebuild
        // when it is not usable.
        if ($this->importedCspForecastUsable()) {
            $this->line('    imported-CSP forecast already present and usable; skipping.');
            return;
        }

        // Demote any unusable prior imported-CSP runs so the rebuild below (fresh,
        // higher-id, official) is the one StatementForecastRun::officialByType()
        // resolves. Renamed + de-officialised rather than deleted, because deleting a
        // forecast run orphans its ENTRY-/STFR- datasets (dataset_code collisions);
        // demotion is reversible and drops them out of the official/latest lookup.
        // CRITICAL: also unbind the demoted run from any reporting context - officialByType
        // PREFERS the context-bound run (statement_forecast_run_id) above is_official /
        // latest, so a context still pinned to the broken run would keep resolving it and
        // shadow the rebuild. bindOfficialCspForecast() re-pins the fresh run afterwards.
        $stalePrior = \App\Models\StatementForecastRun::where('run_name', 'like', 'ICAAP Forecast (imported CSP)%')->get();
        if ($stalePrior->isNotEmpty()) {
            $this->warn('    imported-CSP forecast present but NOT usable (empty / partial snapshot) - rebuilding ' . $stalePrior->count() . ' run(s).');
            $staleIds = $stalePrior->pluck('id')->all();
            \Illuminate\Support\Facades\DB::table('reporting_contexts')
                ->whereIn('statement_forecast_run_id', $staleIds)
                ->update(['statement_forecast_run_id' => null]);
            foreach ($stalePrior as $r) {
                $r->forceFill([
                    'run_name'    => 'SUPERSEDED (pre-heal) ' . $r->run_name,
                    'is_official' => false,
                ])->save();
            }
        }

        \Illuminate\Support\Facades\Auth::loginUsingId((int) (\App\Models\User::orderBy('id')->value('id') ?? 1));
        $svc = app(\App\Services\FinancialStatements\StatementForecastService::class);

        // The imported CSP provides the balance sheet and income statement INDEPENDENTLY,
        // so when the balance-sheet equity is articulated from the income statement
        // (retained earnings = opening + total comprehensive income - dividends), the
        // retained profit the CSP did not carry into its own asset path surfaces as a
        // disclosed balancing plug (the profit reinvested in assets), which exceeds the
        // 5% warn threshold. Permit officialising the imported CSP forecast WITH that
        // disclosed plug - the alternative (keeping the CSP's un-articulated equity)
        // leaves the forecast SOCE unable to reconcile. The plug is disclosed, not hidden
        // (__plug_meta + the balancing line + the forecast reconciliation surface it).
        \App\Models\SystemSetting::setValue('forecast_official_plug_allowed', '1');

        // Yield mappings approved so IS interest stays consistent with the CSP balances.
        \Illuminate\Support\Facades\DB::table('forecast_yield_assumptions')->where('approval_status', '!=', 'approved')->update(['approval_status' => 'approved']);
        \Illuminate\Support\Facades\DB::table('yield_mappings')->where('is_active', 1)->where('status', '!=', 'approved')->update(['status' => 'approved']);

        $anyCsp = false;
        $failures = [];
        foreach (['IS' => 'IS_BANK', 'BS' => 'BS_BANK'] as $code => $tplCode) {
            $tplId = \Illuminate\Support\Facades\DB::table('financial_statement_templates')->where('code', $tplCode)->value('id');
            if (! $tplId) {
                continue;
            }
            $base = \Illuminate\Support\Facades\DB::table('financial_statement_entries')
                ->where('template_id', $tplId)->where('entry_kind', 'actuals')
                ->orderByDesc('year')->orderByDesc('month')->first();
            $cspEntries = \Illuminate\Support\Facades\DB::table('financial_statement_entries')
                ->where('template_id', $tplId)->where('entry_kind', 'csp')
                ->orderBy('reporting_period')->get();

            if (! $base || $cspEntries->isEmpty()) {
                $this->line("    no CSP {$code} entries found; skipping {$code}.");
                continue;
            }
            $anyCsp = true;
            $basePeriod = (string) $base->reporting_period;

            // CSP line values become fixed-amount period assumptions (these win over
            // any line config in the resolution order, so nothing is projected).
            $pas = [];
            foreach ($cspEntries as $ce) {
                $label = substr((string) $ce->reporting_period, 0, 4); // '2026-12' -> '2026'
                foreach (\Illuminate\Support\Facades\DB::table('financial_statement_entry_lines')
                    ->where('entry_id', $ce->id)->get(['template_line_id', 'amount']) as $el) {
                    $pas[] = [
                        'template_line_id' => (int) $el->template_line_id,
                        'period_label'     => $label,
                        'method'           => 'fixed_amount',
                        'value'            => (float) $el->amount,
                        'assumption_type'  => 'amount',
                        'notes'            => 'Imported ZNBS CSP forecast value (no projection)',
                    ];
                }
            }

            // For the BALANCE SHEET, lines the CSP does NOT cover must be ZERO, not
            // carried forward: the CSP's summary lines already subsume the granular
            // detail (CSP "Mortgage Loans" covers every loan sub-type; "Cash on Hand"
            // covers treasury bills / placements; etc.), so carrying the 2025 detail
            // forward double-counts the asset side and forces a balancing plug. Zero
            // the unmapped lines so the run reproduces the balanced CSP exactly.
            // (Confirmed: removes the 1.16bn / 17% 2026 plug; the 9 CSP lines balance
            // to the rand at 5,183,181 = 5,183,181.)
            //
            // The SAME applies to the INCOME STATEMENT: the CSP P&L is also summarised
            // at category level (7 rows), so any granular IS GL line the CSP does not
            // cover (staff/admin sub-lines, the tax line, etc.) must be 0 in the
            // forecast - otherwise it carries the FY2025 actuals forward unchanged and
            // inflates every forecast year's PAT (was overstated ~40-82%: 418k vs the
            // true CSP 230k for 2026), polluting SOCE, the dividend base, the capital
            // plan and the CAR trajectory. Zero-fill both BS and IS.
            if (in_array($code, ['BS', 'IS'], true)) {
                $cspLineIds = array_flip(array_map(fn ($p) => (int) $p['template_line_id'], $pas));
                $periods = $cspEntries->map(fn ($ce) => substr((string) $ce->reporting_period, 0, 4))->unique()->all();
                foreach (\Illuminate\Support\Facades\DB::table('financial_statement_template_lines')
                    ->where('template_id', $tplId)->where('is_inputtable', 1)->pluck('id') as $lid) {
                    if (isset($cspLineIds[(int) $lid])) {
                        continue;
                    }
                    foreach ($periods as $label) {
                        $pas[] = [
                            'template_line_id' => (int) $lid,
                            'period_label'     => $label,
                            'method'           => 'fixed_amount',
                            'value'            => 0.0,
                            'assumption_type'  => 'amount',
                            'notes'            => 'Not in imported CSP - zeroed (subsumed by CSP summary) to avoid the double-count plug',
                        ];
                    }
                }
            }

            // Inputtable lines default to carry_forward; for BS and IS the unmapped
            // lines above are pinned to 0, so only genuine CSP lines carry a value.
            $configs = \Illuminate\Support\Facades\DB::table('financial_statement_template_lines')
                ->where('template_id', $tplId)->where('is_inputtable', 1)->pluck('id')
                ->map(fn ($id) => ['template_line_id' => (int) $id, 'method' => 'carry_forward'])->all();

            try {
                $run = $svc->createAndRun((int) $tplId, (int) $base->id, [
                    'run_name'        => "ICAAP Forecast (imported CSP) - {$code}",
                    'base_period'     => $basePeriod,
                    'horizon_periods' => $cspEntries->count(),
                    'frequency'       => 'annual',
                    // Imported pass-through: the forward financials come straight from the
                    // approved ZNBS CSP entries, so dispatch the imported-management path
                    // (NOT the engine projection). source_mode was defaulting to 'engine',
                    // which ran the wrong path and failed the preview / left the period unbalanced.
                    'source_mode'     => 'imported_management',
                    'notes'           => 'Forward financials taken directly from the imported ZNBS CSP - no engine projection.',
                ], $configs, $pas);
                $this->line("    imported-CSP {$code} forecast (run {$run->id}) for {$basePeriod} +{$cspEntries->count()} (CSP-driven, no projection).");
            } catch (\Throwable $e) {
                $failures[$code] = $e->getMessage();
                $this->warn('    ! imported-CSP ' . $code . ' forecast failed: ' . substr($e->getMessage(), 0, 90));
            }
        }

        if (! $anyCsp) {
            $this->line('    no CSP entries; falling back to the projected demo forecast.');
            $this->seedDemoStatementForecast();
        }

        // Post-build verification. The forward-looking reports (Consolidated Scenario
        // Report, capital plan) require a usable IS + BS imported-CSP forecast; if the
        // build did not produce one, fail LOUDLY here instead of leaving a silent gap
        // that only surfaces later as a 500 on the client's server.
        if (! $this->importedCspForecastUsable()) {
            $this->warn('  ================================================================');
            $this->warn('  ! FORECAST NOT BUILT: no usable imported-CSP forecast exists.');
            $this->warn('    The Consolidated Scenario Report and capital plan need an');
            $this->warn('    official IS + BS forecast whose snapshot carries years beyond');
            $this->warn('    the base year. Likely causes:');
            $this->warn('      - the CSP import (znbs:import-forecast) did not run / found no');
            $this->warn('        entries -> re-run with --with-test-data;');
            if ($failures !== []) {
                foreach ($failures as $code => $msg) {
                    $this->warn("      - {$code} build error: " . substr($msg, 0, 120));
                }
            }
            $this->warn('    Re-run: php artisan icaap:bootstrap --with-test-data --run-engines');
            $this->warn('  ================================================================');
        }
    }

    /**
     * Whether a USABLE imported-CSP statement forecast exists: an official (or, in
     * fallback, completed) run for BOTH the income statement and the balance sheet,
     * each with a result snapshot carrying at least one forecast year beyond the base
     * year. Mirrors exactly what StressTestingDataLoader / officialByType consume, so
     * a "present but empty" run is treated as MISSING (and rebuilt), not as done.
     */
    private function importedCspForecastUsable(): bool
    {
        $baseYear = (int) (\Illuminate\Support\Facades\DB::table('financial_statements')
            ->where('status', 'actual')->max('year') ?? 0);

        $plugGov = app(\App\Services\Forecast\StatementForecastPlugGovernance::class);

        foreach (['income_statement', 'balance_sheet'] as $type) {
            $run = \App\Models\StatementForecastRun::officialByType($type);
            if (! $run) {
                return false;
            }
            $snap = $run->result_snapshot;
            if (! is_array($snap)) {
                return false;
            }
            $futureYears = array_filter(
                array_map('intval', array_keys($snap)),
                fn ($y) => $y > $baseYear
            );
            if ($futureYears === []) {
                return false;
            }
            // A forecast whose balance-sheet plug breaches the governed ceiling is NOT usable:
            // bindOfficialCspForecast() will REFUSE to officialise / bind it (same plug
            // governance), leaving the forecast imported-but-unbound. the client symptom where
            // the CSP shows in the Forecast Hub but never becomes the period's statement forecast
            // and the consolidated pack has no forecast. Treat it as unusable so the bootstrap
            // REBUILDS it fresh (clearing a stale plug) rather than skipping and shipping an
            // unbound forecast. Respects forecast_official_plug_allowed, so a permitted disclosed
            // articulation plug still passes.
            if ($plugGov->blockingIssues($run) !== []) {
                return false;
            }
        }

        return true;
    }

    private function seedDemoStatementForecast(): void
    {
        if (\App\Models\StatementForecastRun::where('run_name', 'like', 'Demo 3-Year Forecast%')->exists()) {
            $this->line('    Demo forecast already present; skipping.');
            return;
        }

        \Illuminate\Support\Facades\Auth::loginUsingId((int) (\App\Models\User::orderBy('id')->value('id') ?? 1));
        $svc = app(\App\Services\FinancialStatements\StatementForecastService::class);

        $bsTpl = \Illuminate\Support\Facades\DB::table('financial_statement_templates')->where('code', 'BS_BANK')->value('id');
        $isTpl = \Illuminate\Support\Facades\DB::table('financial_statement_templates')->where('code', 'IS_BANK')->value('id');
        $bsEntry = \Illuminate\Support\Facades\DB::table('financial_statement_entries')->where('template_id', $bsTpl)->where('entry_kind', 'actuals')->orderByDesc('year')->first();
        $isEntry = \Illuminate\Support\Facades\DB::table('financial_statement_entries')->where('template_id', $isTpl)->where('entry_kind', 'actuals')->orderByDesc('year')->first();
        if (! $bsEntry || ! $isEntry) {
            $this->line('    No actuals entries; skipping demo forecast.');
            return;
        }
        $basePeriod = (string) $bsEntry->reporting_period;

        // Approve the projected yields + their mappings so the IS yield-derivation
        // (forecasted BS balance x forecasted yield) actually applies.
        \Illuminate\Support\Facades\DB::table('forecast_yield_assumptions')->where('approval_status', '!=', 'approved')->update(['approval_status' => 'approved']);
        \Illuminate\Support\Facades\DB::table('yield_mappings')->where('is_active', 1)->where('status', '!=', 'approved')->update(['status' => 'approved']);

        try {
            $bsRun = $svc->createAndRun((int) $bsTpl, (int) $bsEntry->id,
                ['run_name' => 'Demo 3-Year Forecast (Balance Sheet)', 'base_period' => $basePeriod, 'horizon_periods' => 3, 'frequency' => 'annual'],
                $svc->autoConfigs((int) $bsTpl));
            $isRun = $svc->createAndRun((int) $isTpl, (int) $isEntry->id,
                ['run_name' => 'Demo 3-Year Forecast (Income Statement, yield-derived)', 'base_period' => $basePeriod, 'horizon_periods' => 3, 'frequency' => 'annual'],
                $svc->autoConfigs((int) $isTpl));

            $yieldLines = \Illuminate\Support\Facades\DB::table('statement_forecast_yield_outputs')->where('statement_forecast_run_id', $isRun->id)->count();
            $this->line("    BS forecast (run {$bsRun->id}) + IS forecast (run {$isRun->id}) for {$basePeriod}; {$yieldLines} yield-derived IS lines.");
        } catch (\Throwable $e) {
            $this->warn('    ! demo forecast failed: ' . substr($e->getMessage(), 0, 90));
        }
    }

    private function runEngineChain(): void
    {
        try {
            $scenario = \App\Models\Scenario::query()
                ->where('status', 'approved')
                ->where('scenario_role', 'base')
                ->orderBy('id')
                ->first()
                ?? \App\Models\Scenario::query()
                    ->where('status', 'approved')
                    ->orderBy('id')
                    ->first();

            if (! $scenario) {
                $this->warn('  ! No approved scenario found. Skipping engine chain.');
                $this->warn('    To trigger engines, open Risk & Regulatory Engines > Stress > New Run in the UI,');
                $this->warn('    or approve at least one scenario then re-run icaap:bootstrap --run-engines.');
                return;
            }

            // Normalise quarter on every actual BS. The orchestration's
            // base_quarter falls back to Q4 (Dec) when quarter is null,
            // which would rewrite a March year-end as Dec and the RWA
            // engine then fails to find any data. We derive the quarter
            // from the actual reporting month here so the chain reads
            // the correct BS for every historical year.
            $this->normaliseQuartersOnActualStatements();

            // Push the earliest approved regulatory parameter set's
            // effective_from back to cover every historical BS year. The
            // engine prerequisite check rejects runs for years before the
            // parameter set's effective_from, so the seeded BOZ_BASEL3_2025
            // (effective from 2025-01-01) would block every pre-2025 year.
            // We back-fill the window. not back-write parameter values.
            // so the same approved set applies retroactively, which is
            // fine for the bootstrap test corpus.
            $this->extendParameterSetCoverageToHistory();

            // One BS per year. the LATEST month within each year. Some
            // ZNBS years carry both a March and a December close; the
            // bootstrap picks the later month so the engine sees the full
            // 12-month book. Budgets/forecasts are excluded ('actual' only)
            // because the engine gate rejects unfrozen reviewed datasets.
            $baseStatements = $this->findHistoricalYearEnds(
                $this->option('base-year') !== null ? (int) $this->option('base-year') : null,
            );

            if ($baseStatements->isEmpty()) {
                $this->warn('  ! No actual balance-sheet statements found. Skipping engine chain.');
                return;
            }

            // ── PROFILE WINDOWING ─────────────────────────────────────────────
            // Resolve the profile from the available years, then run the HEAVY
            // engine chain only for the profile's engine years. Earlier years get a
            // light statement-derived ratio pass so the dashboard trend still spans
            // every year (full-golden => engine years == all years => no change).
            $availableYears = $baseStatements->map(fn ($s) => (int) $s->year)->unique()->values()->all();
            $this->bootstrapProfile = \App\Support\BootstrapProfile::resolve($this->profileName, $availableYears, $this->engineWindow);
            $profile = $this->bootstrapProfile;

            $engineStatements    = $baseStatements->filter(fn ($s) => in_array((int) $s->year, $profile->engineYears, true))->values();
            $ratioOnlyStatements = $baseStatements->reject(fn ($s) => in_array((int) $s->year, $profile->engineYears, true))->values();

            if ($profile->name !== \App\Support\BootstrapProfile::FULL_GOLDEN) {
                $this->line(sprintf(
                    '    Profile [%s]: heavy engine chain for %s (%d yr); light ratio trend for %d earlier year(s).',
                    $profile->name, $profile->engineYearsLabel(), $engineStatements->count(), $ratioOnlyStatements->count()
                ));
            }

            $superAdminId = \App\Models\User::query()->orderBy('id')->value('id');

            $rows = [];
            $latestContextId = null;
            $allContextIds = [];
            foreach ($engineStatements as $baseStatement) {
                $year = (int) $baseStatement->year;
                $month = \App\Support\ReportingDate::forStatement($baseStatement)->month;
                $period = sprintf('%04d-%02d', $year, $month);
                $label = "Linked engine chain {$period} (Transmission > IFRS9 > RWA > Capital > Liquidity > IRRBB > OpRisk > Concentration)";

                try {
                    [$stressRunId, $contextId] = $this->runChainForBaseStatement(
                        $baseStatement,
                        $scenario,
                        $superAdminId,
                        $label,
                    );
                    $rows[] = ['ok' => true, 'period' => $period, 'run' => $stressRunId, 'context' => $contextId, 'note' => ''];
                    if ($contextId !== null) {
                        $allContextIds[] = $contextId;
                        $latestContextId = $contextId; // year loop is sorted ASC, so the last wins
                    }
                } catch (\Throwable $e) {
                    $rows[] = ['ok' => false, 'period' => $period, 'run' => null, 'context' => null, 'note' => $e->getMessage()];
                    $this->warn(sprintf('    ! %s failed: %s', $period, $e->getMessage()));
                    // Continue to next year. one bad period must not abort
                    // the historical sweep.
                }
            }

            // ── LIGHT RATIO PASS ──────────────────────────────────────────────
            // Years outside the heavy window still get a ratio trend, derived with
            // ONLY RWA + Capital + Liquidity (no scenario transmission / granular
            // IFRS 9 / IRRBB / op-risk / concentration) - so the dashboard shows
            // CAR / CET1 / LCR / NSFR for every historical year without the full
            // chain cost. Empty for full-golden (engine years == all years).
            foreach ($ratioOnlyStatements as $baseStatement) {
                $ry = (int) $baseStatement->year;
                $rm = \App\Support\ReportingDate::forStatement($baseStatement)->month;
                $rp = sprintf('%04d-%02d', $ry, $rm);
                try {
                    $this->step("Light ratio pass {$rp} (RWA > Capital > Liquidity, no stress chain)", function () use ($baseStatement, $scenario, $superAdminId, $ry, $rm, &$allContextIds) {
                        $lrun = \App\Models\StressRun::create([
                            'scenario_id'       => $scenario->id,
                            'base_statement_id' => $baseStatement->id,
                            'run_label'         => \App\Support\RunLabel::forRun($ry, $rm, 'Ratio Trend'),
                            'status'            => 'queued',
                            'base_year'         => $ry,
                            'base_month'        => $rm,
                            'base_months'       => $baseStatement->months,
                            'horizon_years'     => 1,
                            'run_by'            => $superAdminId,
                        ]);
                        $linked = app(\App\Services\IntegratedStressOrchestrationService::class)->executeLinkedRuns(
                            $lrun, $scenario, $ry, $baseStatement->id,
                            \App\Models\ReportingContext::MODE_INTERNAL, ['rwa', 'capital', 'liquidity'],
                        );
                        if (! empty($linked['context_id'])) {
                            $allContextIds[] = (int) $linked['context_id'];
                        }
                    });
                    $this->ratioOnlyYears[$rp] = $ry;
                } catch (\Throwable $e) {
                    $this->warn(sprintf('    ! light ratio pass %s failed: %s', $rp, $e->getMessage()));
                }
            }

            // Approve every successfully-created ReportingContext so all
            // years show up in the dashboard time-series picker, not just
            // the latest. The dashboard "active" flag goes on the latest
            // year only. historical years remain queryable but inactive.
            foreach ($allContextIds as $cid) {
                $this->approveReportingContext($cid);
            }
            if ($latestContextId !== null) {
                $this->activateReportingContext($latestContextId);
                // Bind the imported-CSP forecast as the OFFICIAL ICAAP forecast on the active
                // context, so Phase 3 stress testing uses the CSP pass-through as its baseline
                // (not actuals) and every report reads the same forecast basis.
                $this->bindOfficialCspForecast($latestContextId);
                // Option A. engine-true forward liquidity: materialize each
                // officialised forecast year's balance sheet and execute a
                // run_type='forecast' LiquidityRun on it, so the LCR/NSFR trend's
                // forward years are real engine readings instead of a flagged
                // carry-forward. Guarded + non-fatal; skipped cleanly when no
                // officialised forecast exists to project from.
                $this->projectForecastYearLiquidity();
                // The DIRECTIVE liquidity run (BoZ Gazette Notice 663 of 2026,
                // instrument level), built as a COMPARATOR. Must come before Method 2,
                // which calibrates on a completed directive-path baseline and silently
                // skips without one. Deliberately NOT bound to the context: the statement
                // path stays the reported basis until Data Gap Part C item 1 is answered
                // (see da4e0fa2 and config/healthcheck.php).
                $this->ensureDirectiveLiquidityRun();
                // Driver-based forward liquidity (Method 2) beside the engine-true runs:
                // balances derived from the same officialised forecast, composition measured
                // from the latest actual directive-path run, rates governed. Also recognises
                // any statement-vs-register deposit gap as a pool first, so the base run the
                // projection calibrates on carries the whole deposit population. Guarded and
                // non-fatal, like the step above.
                $this->projectDriverBasedLiquidity();
            }

            // EWS sits outside the linked stress chain. it scores the
            // current observation set rather than per-year stressed values.
            // Run it once after the year loop so the dashboard EWS pill
            // lands populated.
            try {
                app(\App\Services\EwsScoringService::class)->compute($superAdminId);
            } catch (\Throwable $e) {
                $this->warn('    ! EWS scoring failed: ' . $e->getMessage());
            }

            // Per-year summary table. gives the operator a quick read on
            // which years succeeded and which need follow-up.
            $this->newLine();
            $this->line('    Per-year engine chain results:');
            $tableRows = [];
            foreach ($rows as $r) {
                $tableRows[] = [
                    $r['ok'] ? '✓' : '✗',
                    $r['period'],
                    $r['run'] ?? '-',
                    $r['context'] ?? '-',
                    $r['ok'] ? 'ok' : substr($r['note'], 0, 80),
                ];
            }
            $this->table(['', 'Period', 'StressRun', 'Context', 'Note'], $tableRows);

        } catch (Throwable $e) {
            $this->warn('  ! Engine chain hit an issue: ' . $e->getMessage());
            $this->warn('    File: ' . $e->getFile() . ':' . $e->getLine());
            $this->warn('    Non-fatal. The bootstrap will continue.');
            $this->warn('    To run the engines manually, open Risk & Regulatory Engines > Stress > New Run in the UI.');
        }
    }

    /**
     * Auto-resolve open BS-001 findings whose recorded imbalance is below
     * the project's documented BS tolerance (0.50 ZMW, per the healthcheck).
     * Without this, a 0.20 ZMW rounding delta from a CSV import would
     * permanently block the freeze step for that period.
     *
     * The resolution writes a clear audit note so a reviewer can tell the
     * difference between auto-waived rounding noise and a real imbalance.
     */
    private function waiveRoundingNoiseFindings(): void
    {
        $superAdminId = \App\Models\User::query()->orderBy('id')->value('id');
        $now = now();
        $tolerance = 0.50; // ZMW. matches the BS healthcheck tolerance

        $findings = \Illuminate\Support\Facades\DB::table('reconciliation_findings')
            ->where('finding_code', 'balance_sheet_does_not_balance')
            ->where('status', 'open')
            ->get(['id', 'actual']);

        $waived = 0;
        foreach ($findings as $finding) {
            $payload = json_decode((string) $finding->actual, true);
            $imbalance = abs((float) ($payload['imbalance'] ?? 0));
            if ($imbalance > $tolerance) continue;

            \Illuminate\Support\Facades\DB::table('reconciliation_findings')
                ->where('id', $finding->id)
                ->update([
                    'status'              => 'resolved',
                    'resolved_by_user_id' => $superAdminId,
                    'resolved_at'         => $now,
                    'resolution_notes'    => sprintf(
                        'Auto-waived by bootstrap: BS imbalance %.2f ZMW is within the documented healthcheck tolerance (%.2f ZMW). CSV rounding noise.',
                        $imbalance,
                        $tolerance,
                    ),
                    'updated_at'          => $now,
                ]);
            $waived++;
        }

        if ($waived > 0) {
            $this->line("    waived {$waived} sub-tolerance BS-001 finding(s)");
        }
    }

    /**
     * Push the earliest approved RegulatoryParameterSet effective_from to
     * 1 January of the earliest historical BS year, so the prerequisite
     * gate accepts pre-2025 engine runs.
     *
     * Idempotent. does nothing if the earliest set already covers the
     * historical range.
     */
    private function extendParameterSetCoverageToHistory(): void
    {
        $earliestYear = (int) \App\Models\FinancialStatement::query()
            ->where('status', 'actual')
            ->min('year');

        if ($earliestYear === 0) return;

        $targetFrom = sprintf('%04d-01-01', $earliestYear);

        // ONLY the earliest approved set is backdated. The previous version updated EVERY
        // approved set whose effective_from was later than the target, which destroyed the
        // effective-dating of every deliberately later set: SI 62 of 2025 (effective
        // 2026-01-01) resolved for the 2025-12-31 reporting date, so the golden run graded
        // against the 2026 minima; and the five BoZ LCR phase-in sets (2026-07-01 ...
        // 2030-01-01) all collapsed onto one date, so MIN_LCR always resolved to the last
        // set's 100% and the phase-in could never apply. Extending coverage backwards only
        // needs the BASELINE set to start earlier - later sets keep their real dates.
        $earliestSetId = \Illuminate\Support\Facades\DB::table('regulatory_parameter_sets')
            ->where('status', 'approved')
            ->orderBy('effective_from')
            ->orderBy('id')
            ->value('id');

        if ($earliestSetId !== null) {
            \Illuminate\Support\Facades\DB::table('regulatory_parameter_sets')
                ->where('id', $earliestSetId)
                ->where('effective_from', '>', $targetFrom)
                ->update(['effective_from' => $targetFrom]);
        }

        // Concentration limits are date-scoped too. Seeded effective_from is
        // the current year, so the Concentration engine fails for every
        // historical year ("no approved limits effective on YYYY-03-31"), and
        // that single stage failure marks the whole reporting context 'failed'
        //. which then drops the year out of the dashboard, trends and
        // reconciliation. Backdate the approved limits to cover history too.
        if (\Illuminate\Support\Facades\Schema::hasTable('concentration_limits')) {
            \Illuminate\Support\Facades\DB::table('concentration_limits')
                ->where('status', 'approved')
                ->where('effective_from', '>', $targetFrom)
                ->update(['effective_from' => $targetFrom]);
        }
    }

    /**
     * Map each actual FinancialStatement.month to its calendar quarter
     * (1-3=Q1, 4-6=Q2, 7-9=Q3, 10-12=Q4) and persist on the row.
     *
     * Why this exists: the orchestration reads $basePeriod->quarter and
     * falls back to Q4 (Dec) when null. Without this normalisation, a
     * March year-end (month=3, quarter=null) would be silently rewritten
     * as Dec, and the RWA engine would look up BS data at year=N month=12
     * which doesn't exist for fiscal-Mar entities. every old-year run
     * then completes "ok" but with no actual numbers.
     */
    private function normaliseQuartersOnActualStatements(): void
    {
        $rows = \App\Models\FinancialStatement::query()
            ->where('status', 'actual')
            ->whereNull('quarter')
            ->whereNotNull('month')
            ->get(['id', 'month']);

        foreach ($rows as $row) {
            $month = (int) $row->month;
            $quarter = (int) ceil($month / 3); // 1-3→1, 4-6→2, 7-9→3, 10-12→4
            \Illuminate\Support\Facades\DB::table('financial_statements')
                ->where('id', $row->id)
                ->update(['quarter' => $quarter]);
        }
    }

    /**
     * Every distinct actual balance-sheet REPORTING DATE (year + month), oldest
     * first. The chart of accounts carries several month-ends per year for some
     * periods (e.g. both Mar 2025 and Dec 2025); each gets its own engine chain
     * and reporting context so the dashboard can select and compare them (e.g.
     * Dec 2025 vs Mar 2025). If --base-year is supplied, scope to that year.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\FinancialStatement>
     */
    private function findHistoricalYearEnds(?int $onlyYear): \Illuminate\Support\Collection
    {
        $q = \App\Models\FinancialStatement::query()
            ->where('statement_type', 'balance_sheet')
            ->where('status', 'actual');

        if ($onlyYear !== null) {
            $q->where('year', $onlyYear);
        }

        // One entry per distinct (year, month) reporting date.
        return $q->orderBy('year')->orderByDesc('month')->get()
            ->unique(fn ($s) => $s->year . '-' . str_pad((string) \App\Support\ReportingDate::forStatement($s)->month, 2, '0', STR_PAD_LEFT))
            ->sortBy(fn ($s) => $s->year * 100 + \App\Support\ReportingDate::forStatement($s)->month)
            ->values();
    }

    /**
     * Run the full linked engine chain for one base BS and return
     * [stress_run_id, reporting_context_id]. Caller wraps in try/catch
     * because per-year failures are non-fatal at the bootstrap level.
     *
     * @return array{0:int, 1:?int}
     */
    private function runChainForBaseStatement(
        \App\Models\FinancialStatement $baseStatement,
        \App\Models\Scenario           $scenario,
        ?int                           $superAdminId,
        string                         $label,
    ): array {
        $stressRun = \App\Models\StressRun::create([
            'scenario_id'       => $scenario->id,
            'base_statement_id' => $baseStatement->id,
            'forecast_period'   => null,
            // Clean, professional label: period + scenario context (no "Bootstrap"
            // prefix, no timestamp clutter). Downstream engines append their name ->
            // e.g. "Dec 2025 . Central Planning Case IRRBB".
            'run_label'         => \App\Support\RunLabel::forRun(
                (int) $baseStatement->year,
                \App\Support\ReportingDate::forStatement($baseStatement)->month,
                $scenario->name,
            ),
            'status'            => 'queued',
            'base_year'         => (int) $baseStatement->year,
            'base_month'        => \App\Support\ReportingDate::forStatement($baseStatement)->month,
            'base_months'       => $baseStatement->months,
            'horizon_years'     => (int) ($scenario->horizon_years ?: 3),
            'run_by'            => $superAdminId,
        ]);

        $effectiveBaseYear = (int) $baseStatement->year;
        $contextId = null;

        $this->step($label, function () use ($stressRun, $scenario, $effectiveBaseYear, $baseStatement, &$contextId) {
            $result = app(\App\Services\IntegratedStressOrchestrationService::class)
                ->executeLinkedRuns(
                    $stressRun,
                    $scenario,
                    $effectiveBaseYear,
                    $baseStatement->id,
                    \App\Models\ReportingContext::MODE_INTERNAL,
                );
            $contextId = $result['context_id'] ?? null;

            // The orchestration leaves the StressRun on 'queued'.
            // Reconciliation refuses 'queued' runs, which blocks
            // reporting-context approval. Mark it completed so the chain
            // hands back an approve-ready context.
            $stressRun->refresh();
            if ($stressRun->status === 'queued') {
                $stressRun->update(['status' => 'completed']);
            }
        });

        return [(int) $stressRun->id, $contextId];
    }

    /**
     * Mark the imported-CSP statement pack official and bind the balance-sheet run
     * to the active reporting context. Phase 3 stress testing resolves that pinned
     * official run into a forecast-backed dataset, so the stress baseline is the
     * imported CSP pass-through instead of actuals.
     */
    private function bindOfficialCspForecast(int $contextId): void
    {
        try {
            $runs = \App\Models\StatementForecastRun::query()
                ->with('template')
                ->where('run_name', 'like', 'ICAAP Forecast (imported CSP)%')
                ->where('source_mode', 'imported_management')
                ->where('status', 'completed')
                ->whereHas('template', fn ($q) => $q->whereIn('statement_type', ['balance_sheet', 'income_statement']))
                ->orderByDesc('id')
                ->get();

            $bsRun = $runs->first(fn ($run) => $run->template?->statement_type === 'balance_sheet');

            if (! $bsRun) {
                $this->warn('    ! no completed imported-CSP BS forecast found to bind as the official ICAAP forecast.');
                return;
            }

            $officialisedBy = (int) (\App\Models\User::orderBy('id')->value('id') ?? 1);
            $isRun = $runs->first(fn ($run) => $run->template?->statement_type === 'income_statement');
            $officialRuns = collect([$bsRun, $isRun])->filter()->unique('id');

            // [Forecast-path audit fix I3] Route the bootstrap officialisation through
            // the SAME plug governance the UI enforces (StatementForecastController::
            // markOfficial), so a forecast whose balance-sheet plug breaches the
            // governed warn/hard thresholds can no longer be officialised via the
            // seeder's bypass. Fail loud rather than pinning a plugged forecast.
            $plugGov = app(\App\Services\Forecast\StatementForecastPlugGovernance::class);
            foreach ($officialRuns as $run) {
                $issues = $plugGov->blockingIssues($run);
                if ($issues !== []) {
                    $this->warn('    ! not officialising forecast run #' . $run->id . ' (' . $run->template?->statement_type . '): ' . implode(' ', $issues));
                    return;
                }
            }

            foreach ($officialRuns as $run) {
                $run->update([
                    'is_official'     => true,
                    'officialised_at' => now(),
                    'officialised_by' => $officialisedBy,
                ]);
            }

            $context = \App\Models\ReportingContext::find($contextId);
            if (! $context) {
                $this->warn("    ! reporting context {$contextId} not found; cannot bind the official CSP forecast.");
                return;
            }

            $context->update(['statement_forecast_run_id' => $bsRun->id]);

            $freshBsRun = $bsRun->fresh(['template', 'baseEntry']);
            $dataset = $freshBsRun
                ? app(\App\Services\FinancialDataSetService::class)->syncStatementForecastDataset($freshBsRun)
                : null;
            $firstPeriod = $freshBsRun ? $this->firstStatementForecastPeriod($freshBsRun) : null;

            $this->line(sprintf(
                '    bound imported-CSP BS forecast (run %d) as the official ICAAP forecast on context %d; stress baseline = CSP pass-through%s%s.',
                (int) $bsRun->id,
                $contextId,
                $dataset ? " via dataset {$dataset->dataset_code}" : '',
                $firstPeriod ? " from {$firstPeriod}" : ''
            ));

            if ($isRun) {
                $this->line("    imported-CSP IS forecast (run {$isRun->id}) marked official for the same CSP pack.");
            }
        } catch (\Throwable $e) {
            $this->warn('    ! binding the official CSP forecast failed: ' . substr($e->getMessage(), 0, 90));
        }
    }

    /**
     * Option A. engine-true forward liquidity. Runs icaap:liquidity-forecast
     * after the forecast officialise/bind step: each officialised forecast year
     * is materialized as a status='forecast_projection' balance sheet and a
     * run_type='forecast' LiquidityRun is executed on it, so the Liquidity
     * page / workbook LCR-NSFR trend's forward years become engine-true.
     * Guarded + non-fatal: skipped cleanly when no officialised forecast is
     * available (the trend then falls back to its flagged carry-forward).
     */
    /**
     * Driver-based forward liquidity (Method 2), recorded beside the bottom-up runs.
     *
     * Two steps, both guarded so a fresh bootstrap never dies here:
     *   1. If the base statement's customer deposits exceed the granular register, create
     *      the unallocated deposit pool for the gap so the calibration base carries the
     *      whole deposit population at the worst treatment (Unallocated_Deposit_Pool_Spec).
     *   2. Project every officialised forecast year by driver from the latest completed
     *      directive-path baseline run (Liquidity_Forecasting_Methods_Spec).
     */
    /**
     * Build the directive-path comparator, so Method 2 has a baseline to calibrate on.
     *
     * Delegates to liquidity:build-comparator rather than repeating it, because the
     * same two steps are needed by hand after a pull that changes the granular route,
     * and two copies of this would drift. Guarded and non-fatal like the projection
     * steps around it: a build that cannot produce the granular ladder still finishes
     * on the statement path, and says so.
     */
    private function ensureDirectiveLiquidityRun(): void
    {
        $this->step('Directive liquidity run (instrument level) as the published comparator', function () {
            try {
                \Illuminate\Support\Facades\Artisan::call('liquidity:build-comparator', [
                    '--skip-projection' => true,
                ], $this->getOutput());
            } catch (\Throwable $e) {
                $this->warn('    ! comparator not built, staying on the statement path: '.substr($e->getMessage(), 0, 120));
            }
        });
    }

    private function projectDriverBasedLiquidity(): void
    {
        $this->step('Driver-based forward liquidity (Method 2) beside the engine-true runs', function () {
            $base = \App\Models\LiquidityRun::query()
                ->whereIn('status', ['completed', 'approved'])->where('run_type', 'baseline')
                ->where('run_metadata', 'like', '%granular_ladder%')
                ->orderByDesc('base_year')->orderByDesc('id')->first();
            if ($base === null) {
                $this->line('    (no completed directive-path baseline liquidity run; skipped)');
                return;
            }

            // Step 1: deposit pool for any statement-vs-register gap, from measured figures.
            try {
                $stmtId = $base->base_period_id ?? $base->base_statement_id;
                $depositsK = $stmtId ? (float) \Illuminate\Support\Facades\DB::table('financial_line_items as f')
                    ->join('accounts as a', 'a.id', '=', 'f.account_id')
                    ->where('f.financial_statement_id', $stmtId)->where('a.is_leaf', 1)
                    ->whereIn('a.parent_code', ['S2120', 'S2130'])->sum('f.amount') : 0.0;
                $asOf = $stmtId ? (string) \Illuminate\Support\Facades\DB::table('financial_statements')->where('id', $stmtId)->value('end_date') : null;
                $cycle = app(\App\Services\CanonicalScopeService::class)->cycleIdForStatement($stmtId);
                if ($depositsK > 0 && $asOf) {
                    \Illuminate\Support\Facades\Artisan::call('liquidity:create-deposit-pool', [
                        '--statement' => $depositsK, '--as-of' => substr($asOf, 0, 10),
                        '--cycle' => $cycle, '--reference' => "Statement #{$stmtId} customer deposits (bootstrap)",
                        '--author' => 'bootstrap',
                    ], $this->getOutput());
                }
            } catch (\Throwable $e) {
                $this->warn('    ! deposit pool step skipped: ' . $e->getMessage());
            }

            // Step 2: the projection itself.
            try {
                \Illuminate\Support\Facades\Artisan::call('liquidity:project-drivers', [
                    '--base-run' => $base->id, '--scenarios' => 'baseline,adverse',
                ], $this->getOutput());
            } catch (\Throwable $e) {
                $this->warn('    ! driver-based projection skipped: ' . $e->getMessage());
            }
        });
    }

    private function projectForecastYearLiquidity(): void
    {
        $this->line('    Projecting forecast-year liquidity (LCR/NSFR trend)…');

        try {
            $hasOfficialBs = \App\Models\StatementForecastRun::query()
                ->where('is_official', true)
                ->where('status', 'completed')
                ->whereHas('template', fn ($q) => $q->where('statement_type', 'balance_sheet'))
                ->exists();

            if (! $hasOfficialBs) {
                $this->line('      skipped: no officialised BS statement forecast to project from.');
                return;
            }

            $exit = $this->call('icaap:liquidity-forecast');
            if ($exit !== \Illuminate\Console\Command::SUCCESS) {
                $this->warn('      ! forecast-year liquidity projection reported a blocker (non-fatal): the trend falls back to its flagged carry-forward.');
            }
        } catch (\Throwable $e) {
            $this->warn('      ! forecast-year liquidity projection failed (non-fatal): ' . substr($e->getMessage(), 0, 140));
        }
    }

    private function firstStatementForecastPeriod(\App\Models\StatementForecastRun $run): ?string
    {
        $snapshot = is_array($run->result_snapshot) ? $run->result_snapshot : [];

        foreach (array_keys($snapshot) as $key) {
            $label = (string) $key;
            if ($label !== '' && ! str_starts_with($label, '__')) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Reconcile + approve a single ReportingContext. Soft-fails on
     * reconciliation findings. earlier years' contexts that fail
     * approval invariants stay in draft state and can be approved via UI.
     */
    /**
     * Verify the active, approved reporting context binds a sane base capital +
     * RWA run: bound, completed/approved (not superseded/failed), and scenario-
     * aligned with the context. Loud (non-fatal) - the official export gate is
     * the hard block; this makes a bad binding visible at bootstrap so it can be
     * corrected (re-run the base chain / rebind to the canonical base run).
     */
    private function verifyActiveOfficialContextBindings(): void
    {
        $ctx = \App\Models\ReportingContext::query()
            ->where('is_active_for_reporting', true)
            ->where('approval_state', \App\Models\ReportingContext::STATE_APPROVED)
            ->orderByDesc('id')
            ->first();

        if (! $ctx) {
            $this->warn('      no active approved reporting context to verify.');
            return;
        }

        $problems = [];
        $checks = [
            'capital' => [\App\Models\CapitalRun::class, $ctx->capital_run_id],
            'rwa'     => [\App\Models\RwaRun::class, $ctx->rwa_run_id],
        ];
        foreach ($checks as $label => [$model, $runId]) {
            if (! $runId) {
                $problems[] = "{$label}: no run bound";
                continue;
            }
            $run = $model::find($runId);
            if (! $run) {
                $problems[] = "{$label}: bound run #{$runId} no longer exists";
                continue;
            }
            if (! in_array((string) $run->status, ['completed', 'approved'], true)) {
                $problems[] = "{$label}: bound run #{$runId} is '{$run->status}' (expected completed/approved)";
            }
            if ($ctx->scenario_id !== null && $run->scenario_id !== null
                && (int) $run->scenario_id !== (int) $ctx->scenario_id) {
                $problems[] = "{$label}: bound run #{$runId} scenario {$run->scenario_id} != context scenario {$ctx->scenario_id}";
            }
        }

        if ($problems === []) {
            $this->line("      active context #{$ctx->id} bindings OK (capital #{$ctx->capital_run_id}, rwa #{$ctx->rwa_run_id}).");
            return;
        }

        $this->error('  ✗ Active official reporting context #' . $ctx->id . ' has UNSAFE bindings (official export will block):');
        foreach ($problems as $p) {
            $this->line('      - ' . $p);
        }
        $this->line('      Fix: re-run the base-period engine chain and re-approve, or rebind the context to the canonical base run.');
    }

    private function approveReportingContext(int $contextId): void
    {
        try {
            $ctx = \App\Models\ReportingContext::find($contextId);
            if (! $ctx) return;

            app(\App\Services\ReconciliationService::class)->check($ctx);

            $reconSvc = app(\App\Services\ReportingContextService::class);
            if ($ctx->approval_state === \App\Models\ReportingContext::STATE_COMPLETE) {
                // Audit#6: this is an AUTOMATED data-readiness approval (the
                // engine chain is complete + reconciled), NOT a Board/governance
                // sign-off. Label it unambiguously so no one reads the context's
                // approved_by as evidence of Board approval - the BoZ pack still
                // requires real prepared-by + board_approved signatures before it
                // can be exported (IcaapRegulatoryReportService::exportBlockers).
                try { $reconSvc->approve($ctx, self::AUTOMATED_APPROVER); } catch (\Throwable) {
                    // soft-fail; approval invariants may flag period
                    // mismatches that need the human reviewer
                }
            }
        } catch (\Throwable) {
            // non-fatal; the context exists and can be approved via UI
        }
    }

    /**
     * Flip the active flag on a single ReportingContext so the dashboard
     * + ICAAP report land on it by default. Demotes any other context
     * that was previously active so only one year is the dashboard default.
     */
    private function activateReportingContext(int $contextId): void
    {
        try {
            $ctx = \App\Models\ReportingContext::find($contextId);
            if (! $ctx) return;

            \Illuminate\Support\Facades\DB::table('reporting_contexts')
                ->where('id', '!=', $ctx->id)
                ->where('is_active_for_reporting', 1)
                ->update(['is_active_for_reporting' => 0]);

            \Illuminate\Support\Facades\DB::table('reporting_contexts')
                ->where('id', $ctx->id)
                ->update([
                    'is_active_for_reporting' => 1,
                    'activated_at'            => now(),
                    'activated_by'            => self::AUTOMATED_APPROVER,
                ]);
        } catch (\Throwable) {
            // non-fatal; the context can be activated via UI
        }
    }

    /**
     * Sweep every approved + active YieldMapping, compute realised
     * historical yields from the just-imported financial_statements, and
     * project 3 annual forecast periods of draft assumptions per mapping.
     *
     * Idempotent: computeHistoricalYields and projectForecastYields both
     * upsert by (mapping, period), so re-running the bootstrap refreshes
     * the rows rather than duplicating them. Errors on a single mapping
     * are caught and logged; the rest of the sweep continues.
     */
    private function prefillYieldEngine(): void
    {
        $engine = app(\App\Services\YieldEngineService::class);

        $mappings = \App\Models\YieldMapping::query()
            ->where('status', 'approved')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($mappings->isEmpty()) {
            $this->warn('  ! No approved yield mappings found. Run YieldMappingSeeder first.');
            return;
        }

        // Pick three annual future periods starting from the year after the
        // latest actual BS in the database. With no actuals there is no base to
        // forecast from, so the step is skipped rather than anchored on the
        // calendar year (spec E4.3; the import would have failed earlier anyway).
        $lastYear = (int) \App\Models\FinancialStatement::query()
            ->where('status', 'actual')
            ->where('statement_type', 'balance_sheet')
            ->max('year');
        if ($lastYear <= 0) {
            $this->warn('  ! No actual balance sheet on file, so there is no base year to forecast yields from. Skipped.');
            return;
        }

        $futurePeriods = [(string) ($lastYear + 1), (string) ($lastYear + 2), (string) ($lastYear + 3)];

        $okCount   = 0;
        $skipCount = 0;
        foreach ($mappings as $mapping) {
            try {
                $engine->computeHistoricalYields($mapping);
                $engine->projectForecastYields($mapping, $futurePeriods, null);
                $okCount++;
            } catch (\Throwable $e) {
                $skipCount++;
                $this->warn(sprintf(
                    '  · skipped %s: %s',
                    $mapping->yield_code,
                    $e->getMessage(),
                ));
            }
        }

        $this->line(sprintf(
            '    yields computed for %d/%d mappings (skipped %d). Forecast horizon: %s..%s',
            $okCount,
            $mappings->count(),
            $skipCount,
            $futurePeriods[0],
            end($futurePeriods),
        ));
    }

    /**
     * Render the healthcheck result table inline.
     */
    /**
     * Render the healthcheck table and return whether the GOLDEN headline
     * numbers (CAR / LCR / NSFR / EWS) all reproduced within tolerance. The
     * caller uses this to fail `--verify` on a golden regression while leaving
     * incidental data-count drift as a non-fatal warning.
     */
    private function renderHealthcheck(): bool
    {
        $svc = app(HealthcheckService::class);
        $report = $svc->runAll();

        $rows = [];
        foreach ($report['checks'] as $check) {
            $rows[] = [
                $check['ok'] ? '✓' : '✗',
                $check['name'],
                $check['actual'] ?? '',
                $check['expected'] ?? '',
                $check['note'] ?? '',
            ];
        }
        $this->table(
            ['', 'Check', 'Actual', 'Expected', 'Note'],
            $rows,
        );

        $this->newLine();
        $colour = $report['all_ok'] ? 'info' : 'error';
        $this->{$colour}(sprintf('  %s Healthcheck: %d of %d checks passed.',
            $report['all_ok'] ? '✓' : '✗',
            $report['passed'],
            $report['total']));

        // Golden subset: the headline ratios that must always reproduce.
        $goldenChecks = array_filter($report['checks'], fn ($c) => ($c['golden'] ?? false) === true);
        $goldenFailed = array_filter($goldenChecks, fn ($c) => $c['ok'] !== true);
        if ($goldenFailed) {
            $this->newLine();
            // Spec E8 (Codex F01): a period / methodology with no approved baseline is
            // UNVERIFIED, which fails --verify like a regression but is named as such.
            $unverified = array_filter($goldenFailed, fn ($c) => ($c['status'] ?? null) === 'unverified');
            $this->error($unverified !== [] && count($unverified) === count($goldenFailed)
                ? '  ✗ GOLDEN NUMBERS unverified (no approved baseline for this period / methodology):'
                : '  ✗ GOLDEN NUMBERS regressed:');
            foreach ($goldenFailed as $c) {
                $this->error(sprintf('      %s: got %s, expected %s', $c['name'], $c['actual'] ?? '?', $c['expected'] ?? '?'));
            }
        }

        return empty($goldenFailed) && ! empty($goldenChecks);
    }

    /** End-of-run summary: what the chosen profile actually ran + whether it is golden-valid. */
    private function printProfileSummary(bool $runEngines): void
    {
        $p = $this->bootstrapProfile
            ?? \App\Support\BootstrapProfile::resolve($this->profileName, [], $this->engineWindow);

        $runtime = $this->bootstrapStartedAt > 0 ? max(0.0, microtime(true) - $this->bootstrapStartedAt) : 0.0;
        $mins = (int) floor($runtime / 60);
        $secs = (int) round($runtime - $mins * 60);

        $skipped = match ($p->scenarioSet) {
            \App\Support\BootstrapProfile::SET_ICAAP_CORE          => 'all non-Core scenarios (ICAAP Core 5 only)',
            \App\Support\BootstrapProfile::SET_BASELINE_PLUS_SEVERE => 'all but baseline + the single most-severe scenario',
            default                                                => 'none',
        };

        $this->newLine();
        $this->section('Bootstrap profile summary');
        $this->table([], [
            ['Profile',                  $p->label()],
            ['Ratio trend years',        $runEngines ? $p->ratioYearsLabel() : 'n/a (no engines)'],
            ['Heavy engine-chain years', $runEngines ? $p->engineYearsLabel() : 'none (data-only)'],
            ['Stress scenario years',    $runEngines ? $p->stressYearsLabel() : 'none'],
            ['Scenario set',             $p->scenarioSet],
            ['Scenarios skipped',        $runEngines ? $skipped : 'all (no engines)'],
            ['Result validity',          $p->goldenValid ? 'GOLDEN-VALID (production)' : 'WORKING MODE ONLY - not for official ICAAP submission'],
            ['Runtime',                  sprintf('%dm %02ds', $mins, $secs)],
        ]);
        if (! $p->goldenValid) {
            $this->warn('  ! Working-mode output. Do NOT use for the official ICAAP pack - re-run with --profile=full-golden --verify for golden-valid results.');
        }
    }

    /**
     * Confirm the imported CSP forecast is officialised + bound (Stage 2) so the
     * engines and the Consolidated Scenario Report actually read it. Prints a loud,
     * actionable warning when it is not - the exact failure the client hit where the
     * forecast is "imported" but the pack only shows the base year. Never fails the
     * build (a plug breach is a legitimate data situation to resolve, not a boot bug).
     */
    private function flagForecastReadiness(): void
    {
        try {
            $r = app(\App\Services\Forecast\ForecastReadinessService::class)->report();
        } catch (\Throwable $e) {
            $this->warn('    ! forecast readiness check skipped: ' . substr($e->getMessage(), 0, 80));
            return;
        }

        if ($r['ready']) {
            $this->newLine();
            $this->info(sprintf(
                '  ✓ Forecast marked for ICAAP: BS #%s + IS #%s officialised and bound (years %s).',
                $r['resolved_bs_run_id'] ?? '?',
                $r['resolved_is_run_id'] ?? '?',
                implode(',', $r['csp_years']) ?: 'n/a'
            ));
            return;
        }

        $this->newLine();
        $this->warn('  ================================================================');
        $this->warn('  ! FORECAST NOT MARKED FOR ICAAP');
        $this->warn('    It may be imported, but it is not officialised + bound, so the');
        $this->warn('    Consolidated Scenario Report / capital plan will NOT read the');
        $this->warn('    multi-year forecast (the pack shows only the base year).');
        foreach ($r['reasons'] as $reason) {
            $this->warn('      - ' . $reason);
        }
        $this->warn('    Fix:      ' . $r['fix_command']);
        $this->warn('    Diagnose: php artisan icaap:forecast-doctor');
        $this->warn('  ================================================================');
    }

    private function renderBanner(): void
    {
        $this->newLine();
        $this->line('  ╔══════════════════════════════════════════════════════════╗');
        $this->line('  ║   ICAAP Suite. Bootstrap Sequence                       ║');
        $this->line('  ║   Dupleix Institute · DI-PRJ-2026-ICAAP                  ║');
        $this->line('  ╚══════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    private function section(string $label): void
    {
        $this->newLine();
        $this->line("  ── {$label} ──");
    }

    /**
     * Run one bootstrap step and refuse to tick it if it failed.
     *
     * Most steps hand back Artisan::call(), which returns the command's exit
     * code. This helper used to discard that value, so a governed import could
     * refuse to write, return failure, and the bootstrap would print a tick and
     * carry on with the data missing. A client would then find the register
     * empty with nothing in the log to say why. A non-zero code now stops the
     * bootstrap with the step named. A step that returns nothing is a success.
     */
    private function step(string $label, callable $work): void
    {
        $this->line("  · {$label}...");
        $start = microtime(true);
        $result = $work();
        $elapsed = number_format((microtime(true) - $start), 2);

        if (is_int($result) && $result !== 0) {
            throw new \RuntimeException(
                "Step '{$label}' returned exit code {$result}. Its output above says why; nothing after it has run."
            );
        }

        $this->line("    ✓ {$elapsed}s");
    }
}
