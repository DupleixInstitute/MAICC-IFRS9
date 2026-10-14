<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\EirCoverageService;
use App\Services\Eir\EirReadinessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EirCoverageServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('instrument_type')->default('AMORTISED_LOAN'); $t->string('portfolio')->nullable(); $t->string('product_type')->nullable(); $t->date('origination_date')->nullable(); $t->double('drawn_amount')->default(0); $t->integer('payments_per_year')->default(12); $t->string('frequency_source')->nullable(); $t->string('schedule_approval_status')->default('NOT_GENERATED'); $t->string('calculation_status')->default('PENDING'); $t->string('locked_at')->nullable(); $t->string('schedule_source')->nullable(); $t->double('schedule_amortising_balance')->nullable(); $t->timestamps(); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->smallInteger('schedule_version')->default(1); $t->string('due_date')->nullable(); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); });
        Schema::create('contract_fees', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->double('amount')->default(0); $t->boolean('integral')->nullable(); $t->string('cashflow_direction')->nullable(); $t->string('classification_status')->default('PENDING'); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->double('carrying_amount')->default(0); });
    }

    /**
     * A contract carrying no blockers. The schedule is approved by default so
     * that a case built here is a control against which one overridden field
     * is the only difference — the point of every fixture in this file.
     */
    private function contract(string $id, array $overrides = []): void
    {
        DB::table('contract_eir')->insert(array_merge([
            'contract_id' => $id, 'instrument_type' => 'AMORTISED_LOAN', 'portfolio' => 'MAIIC',
            'origination_date' => '2025-01-01', 'drawn_amount' => 1000.0, 'payments_per_year' => 12,
            'frequency_source' => 'STATED', 'schedule_approval_status' => 'APPROVED',
            'calculation_status' => 'PENDING',
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    /** A schedule that reconciles to a 1,000 drawn amount. */
    private function schedule(string $id, array $dates = ['2025-02-01', '2025-03-01']): void
    {
        foreach ($dates as $i => $date) {
            DB::table('contract_cashflow_schedule')->insert(['contract_id' => $id, 'schedule_version' => 1,
                'due_date' => $date, 'principal_due' => 1000 / count($dates), 'interest_due' => 10]);
        }
    }

    private function exposure(string $id, float $amount, string $period = '2025-10'): void
    {
        DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => $period, 'carrying_amount' => $amount]);
    }

    public function test_a_clean_contract_is_ready_and_a_locked_one_counts_as_covered(): void
    {
        $this->contract('READY-1'); $this->schedule('READY-1'); $this->exposure('READY-1', 900);
        $this->contract('LOCKED-1', ['locked_at' => '2025-05-01']); $this->schedule('LOCKED-1'); $this->exposure('LOCKED-1', 1_100);

        $profile = (new EirCoverageService())->profile('2025-10');

        $this->assertSame(2, $profile['summary']['in_scope']);
        $this->assertSame(1, $profile['summary']['covered']);
        $this->assertSame(50.0, $profile['summary']['coverage_percent']);
        $this->assertEqualsWithDelta(1_100, $profile['summary']['exposure_covered'], 0.01);
        $this->assertEqualsWithDelta(55.0, $profile['summary']['exposure_coverage_percent'], 0.01);
        $this->assertSame(1, $profile['states']['READY']['contracts']);
    }

    public function test_a_tape_contract_with_no_profile_is_blocked_by_name(): void
    {
        $this->exposure('NO-PROFILE', 5_000);

        $profile = (new EirCoverageService())->profile('2025-10');

        $this->assertSame('BLOCKED', $profile['contracts'][0]['state']);
        $this->assertSame(['CONTRACT_PROFILE_MISSING'], $profile['contracts'][0]['issues']);
        $this->assertSame('CONTRACT_PROFILE_MISSING', $profile['issues'][0]['code']);
        $this->assertEqualsWithDelta(5_000, $profile['issues'][0]['exposure'], 0.01);
        $this->assertSame(100.0, $profile['issues'][0]['exposure_percent']);
    }

    public function test_blockers_are_ranked_by_exposure_not_by_contract_count(): void
    {
        // Many tiny contracts missing a stated frequency...
        for ($i = 0; $i < 5; $i++) {
            $this->contract("SMALL-{$i}", ['frequency_source' => null]);
            $this->schedule("SMALL-{$i}");
            $this->exposure("SMALL-{$i}", 100);
        }
        // ...against one large one with no schedule at all.
        $this->contract('BIG'); $this->exposure('BIG', 50_000);

        $issues = (new EirCoverageService())->profile('2025-10')['issues'];

        $this->assertSame('ORIGINAL_SCHEDULE_MISSING', $issues[0]['code']);
        $this->assertSame(1, $issues[0]['contracts']);
        $this->assertSame('FREQUENCY_ASSUMED', $issues[1]['code']);
        $this->assertSame(5, $issues[1]['contracts']);
        // The five-contract blocker loses to the one-contract blocker on money.
        $this->assertGreaterThan($issues[1]['exposure'], $issues[0]['exposure']);
    }

    public function test_sole_blocker_counts_identify_what_one_fix_would_release(): void
    {
        $this->contract('ONE-THING', ['frequency_source' => null]); $this->schedule('ONE-THING'); $this->exposure('ONE-THING', 100);
        $this->contract('MANY-THINGS', ['frequency_source' => null, 'origination_date' => null]); $this->exposure('MANY-THINGS', 100);

        $issues = collect((new EirCoverageService())->profile('2025-10')['issues'])->keyBy('code');

        $this->assertSame(2, $issues['FREQUENCY_ASSUMED']['contracts']);
        // Only one of the two becomes solvable if frequency alone is fixed.
        $this->assertSame(1, $issues['FREQUENCY_ASSUMED']['sole_blocker']);
    }

    public function test_equity_is_out_of_scope_and_excluded_from_coverage(): void
    {
        $this->contract('EQ', ['instrument_type' => 'EQUITY_EXCLUDED']); $this->exposure('EQ', 9_000);
        $this->contract('LOAN'); $this->schedule('LOAN'); $this->exposure('LOAN', 1_000);

        $profile = (new EirCoverageService())->profile('2025-10');

        $this->assertSame(1, $profile['summary']['in_scope']);
        $this->assertSame(1, $profile['states']['OUT_OF_SCOPE']['contracts']);
        $this->assertEqualsWithDelta(1_000, $profile['summary']['exposure_in_scope'], 0.01);
    }

    /**
     * The Mega Farm programme is out of the EIR engine (spec v4 section 16,
     * D30), so its loans have no EIR profile by design. They are counted as
     * out of scope with the reason, never as CONTRACT_PROFILE_MISSING, and
     * stay out of the in-scope book, the blockers' % of book and the
     * portfolio coverage.
     */
    public function test_mega_farm_loans_are_out_of_scope_not_missing_a_profile(): void
    {
        Schema::table('loan_books', function (Blueprint $t) { $t->string('product_code')->nullable(); $t->string('product_group')->nullable(); });
        $gl = \App\Services\MegaFarm\MegaFarmEclService::GLS[0];
        DB::table('loan_books')->insert(['contract_id' => 'MF-1', 'reporting_period' => '2025-10', 'carrying_amount' => 7_000, 'product_code' => $gl]);
        DB::table('loan_books')->insert(['contract_id' => 'MF-2', 'reporting_period' => '2025-10', 'carrying_amount' => 3_000, 'product_code' => $gl]);
        // A MAIIC loan with no profile is still a blocker, and a ready one is the control.
        DB::table('loan_books')->insert(['contract_id' => 'NO-PROFILE', 'reporting_period' => '2025-10', 'carrying_amount' => 500, 'product_code' => '1050101']);
        $this->contract('LOAN'); $this->schedule('LOAN');
        DB::table('loan_books')->insert(['contract_id' => 'LOAN', 'reporting_period' => '2025-10', 'carrying_amount' => 500, 'product_code' => '1050101']);

        $profile = (new EirCoverageService())->profile('2025-10');
        $rows = collect($profile['contracts'])->keyBy('contract_id');

        foreach (['MF-1', 'MF-2'] as $id) {
            $this->assertSame('OUT_OF_SCOPE', $rows[$id]['state']);
            $this->assertSame([], $rows[$id]['issues']);
            $this->assertSame('MEGA_FARM', $rows[$id]['scope_reason']);
        }
        $this->assertSame(['CONTRACT_PROFILE_MISSING'], $rows['NO-PROFILE']['issues']);

        $missing = collect($profile['issues'])->firstWhere('code', 'CONTRACT_PROFILE_MISSING');
        $this->assertSame(1, $missing['contracts']);
        $this->assertSame(50.0, $missing['exposure_percent']); // of the 1,000 in-scope book, not of 11,000

        $this->assertSame(2, $profile['summary']['in_scope']);
        $this->assertSame(2, $profile['summary']['out_of_scope']);
        $this->assertEqualsWithDelta(1_000, $profile['summary']['exposure_in_scope'], 0.01);
        $this->assertSame(2, $profile['states']['OUT_OF_SCOPE']['contracts']);
        $this->assertSame(1, $profile['states']['BLOCKED']['contracts']);

        $this->assertCount(1, $profile['out_of_scope']);
        $this->assertSame('MEGA_FARM', $profile['out_of_scope'][0]['code']);
        $this->assertStringContainsString('D30', $profile['out_of_scope'][0]['label']);
        $this->assertSame(2, $profile['out_of_scope'][0]['contracts']);
        $this->assertEqualsWithDelta(10_000, $profile['out_of_scope'][0]['exposure'], 0.01);

        $this->assertSame(2, array_sum(array_column($profile['portfolios'], 'contracts')));
    }

    public function test_a_contract_off_the_tape_stays_visible_in_its_own_report(): void
    {
        $this->contract('OFF-TAPE', ['locked_at' => '2025-05-01']); $this->schedule('OFF-TAPE');

        $profile = (new EirCoverageService())->profile('2025-10');

        $this->assertSame(1, $profile['summary']['off_tape']);
        $this->assertFalse($profile['contracts'][0]['on_tape']);
        $this->assertEqualsWithDelta(0, $profile['contracts'][0]['exposure'], 0.01);
    }

    /**
     * The bulk profile duplicates EirReadinessService's rules for speed. If the
     * two ever disagree the coverage report silently misstates the gap, so the
     * agreement is asserted rather than assumed.
     */
    public function test_the_bulk_profile_agrees_with_the_per_contract_readiness_gate(): void
    {
        $this->contract('CLEAN'); $this->schedule('CLEAN');
        $this->contract('NO-FREQ', ['frequency_source' => null]); $this->schedule('NO-FREQ');
        $this->contract('NO-SCHEDULE');
        $this->contract('BAD-PRINCIPAL'); $this->schedule('BAD-PRINCIPAL');
        DB::table('contract_cashflow_schedule')->where('contract_id', 'BAD-PRINCIPAL')->update(['principal_due' => 5]);
        $this->contract('NO-DATE', ['origination_date' => null]); $this->schedule('NO-DATE');
        $this->contract('PENDING-FEE'); $this->schedule('PENDING-FEE');
        DB::table('contract_fees')->insert(['contract_id' => 'PENDING-FEE', 'amount' => 10, 'classification_status' => 'PENDING']);
        $this->contract('DUP-DATES'); $this->schedule('DUP-DATES', ['2025-02-01', '2025-02-01']);
        $this->contract('DRAFT-SCHEDULE', ['schedule_approval_status' => 'DRAFT']); $this->schedule('DRAFT-SCHEDULE');

        $coverage = new EirCoverageService();
        $readiness = new EirReadinessService();
        $profile = collect($coverage->profile(null))->get('contracts');

        foreach ($profile as $row) {
            $expected = collect($readiness->assess($row['contract_id'])['issues'])
                ->pluck('code')->reject(fn ($c) => $c === 'EIR_LOCKED')->sort()->values()->all();
            $actual = collect($row['issues'])->sort()->values()->all();

            $this->assertSame($expected, $actual, "Blocker codes diverged for {$row['contract_id']}");
        }
    }

    /**
     * Every code the readiness gate can raise is labelled here, apart from the
     * two coverage reads as an outcome or a scope. Read from the gate's source,
     * so a new check there without a label here fails this test.
     */
    public function test_every_code_the_readiness_gate_raises_has_a_coverage_label(): void
    {
        preg_match_all("/'code' => '([A-Z_]+)'/", file_get_contents(app_path('Services/Eir/EirReadinessService.php')), $m);
        $codes = array_values(array_unique($m[1]));
        $this->assertContains('TAKEON_NOT_RECOMPUTED', $codes);
        $this->assertContains('SCHEDULE_NOT_ON_TRANCHES', $codes);
        foreach ($codes as $code) {
            if (in_array($code, EirCoverageService::NOT_BLOCKERS, true)) {
                continue;
            }
            $this->assertArrayHasKey($code, EirCoverageService::ISSUE_LABELS, "{$code} has no coverage label");
        }
    }

    /**
     * The take-on checks, the tranche check and the principal compared with the
     * balance a generated schedule amortises: the copy of the rules the
     * coverage used to carry had none of the three (documentation audit,
     * 14 October 2026). Coverage now runs the gate itself.
     */
    public function test_take_on_tranche_and_amortising_balance_checks_reach_the_coverage(): void
    {
        Schema::create('contract_takeon', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('basis')->nullable(); $t->string('eir_check')->nullable(); $t->string('eir_check_reason')->nullable(); $t->text('flags')->nullable(); $t->text('recompute_detail')->nullable(); });
        Schema::create('contract_disbursements', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('tranche_no')->nullable(); $t->date('disbursement_date'); $t->double('amount'); $t->string('reference')->nullable(); $t->string('sub_account_no')->nullable(); $t->string('source_system')->nullable(); $t->string('external_transaction_id')->nullable(); $t->timestamps(); });
        $takeon = \App\Services\Ebanker\TakeonLandingService::ROUTE;

        // a take-on loan still on its take-on balance, and one whose solve the build refused
        $this->contract('TK-BALANCE', ['schedule_source' => $takeon]); $this->schedule('TK-BALANCE'); $this->exposure('TK-BALANCE', 400);
        DB::table('contract_takeon')->insert(['contract_id' => 'TK-BALANCE', 'basis' => 'TAKEON_BALANCE', 'flags' => json_encode(['no origination chain'])]);
        $this->contract('TK-REFUSED', ['schedule_source' => $takeon]); $this->schedule('TK-REFUSED'); $this->exposure('TK-REFUSED', 300);
        DB::table('contract_takeon')->insert(['contract_id' => 'TK-REFUSED', 'basis' => 'RECOMPUTED', 'eir_check' => 'FAIL', 'eir_check_reason' => 'residual too large']);
        // first money advanced after an instalment falls due
        $this->contract('LATE-DRAW', ['schedule_source' => 'IMPORTED']); $this->schedule('LATE-DRAW'); $this->exposure('LATE-DRAW', 200);
        DB::table('contract_disbursements')->insert(['contract_id' => 'LATE-DRAW', 'disbursement_date' => '2025-02-15', 'amount' => 1000]);
        // a generated schedule amortising 1,100 (capitalised moratorium interest): 1,000 drawn is not the basis
        $this->contract('CAPITALISED', ['schedule_source' => 'GENERATED', 'schedule_amortising_balance' => 1100.0]); $this->exposure('CAPITALISED', 100);
        $this->schedule('CAPITALISED');
        DB::table('contract_cashflow_schedule')->where('contract_id', 'CAPITALISED')->update(['principal_due' => 550]);

        $rows = collect((new EirCoverageService())->profile('2025-10')['contracts'])->keyBy('contract_id');

        $this->assertSame(['TAKEON_NOT_RECOMPUTED'], $rows['TK-BALANCE']['issues']);
        $this->assertSame(['TAKEON_SOLVE_REFUSED'], $rows['TK-REFUSED']['issues']);
        $this->assertSame(['SCHEDULE_BEFORE_FIRST_DRAWDOWN'], $rows['LATE-DRAW']['issues']);
        $this->assertSame('READY', $rows['CAPITALISED']['state'], 'the principal is compared with the balance the schedule amortises, as the gate does');

        $readiness = new EirReadinessService();
        foreach ($rows as $id => $row) {
            $expected = collect($readiness->assess($id)['issues'])->pluck('code')
                ->reject(fn ($c) => in_array($c, EirCoverageService::NOT_BLOCKERS, true))->sort()->values()->all();
            $this->assertSame($expected, collect($row['issues'])->sort()->values()->all(), "Blocker codes diverged for {$id}");
        }
    }
}
