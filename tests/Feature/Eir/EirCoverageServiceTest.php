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
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('instrument_type')->default('AMORTISED_LOAN'); $t->string('portfolio')->nullable(); $t->string('product_type')->nullable(); $t->date('origination_date')->nullable(); $t->double('drawn_amount')->default(0); $t->integer('payments_per_year')->default(12); $t->string('frequency_source')->nullable(); $t->string('calculation_status')->default('PENDING'); $t->string('locked_at')->nullable(); $t->timestamps(); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->smallInteger('schedule_version')->default(1); $t->string('due_date')->nullable(); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); });
        Schema::create('contract_fees', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->double('amount')->default(0); $t->boolean('integral')->nullable(); $t->string('cashflow_direction')->nullable(); $t->string('classification_status')->default('PENDING'); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->double('carrying_amount')->default(0); });
    }

    private function contract(string $id, array $overrides = []): void
    {
        DB::table('contract_eir')->insert(array_merge([
            'contract_id' => $id, 'instrument_type' => 'AMORTISED_LOAN', 'portfolio' => 'MAIIC',
            'origination_date' => '2025-01-01', 'drawn_amount' => 1000.0, 'payments_per_year' => 12,
            'frequency_source' => 'STATED', 'calculation_status' => 'PENDING',
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
}
