<?php

namespace Tests\Feature\Eir;

use App\Models\GovernanceSetting;
use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\RateResetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The rate-reset register and its maker-checker intake (spec v4 P5), on the
 * one detector: the reset pipeline dates each prime-rate reset once, the
 * register sets it against the loan book; a move of the spread is its own
 * event for a person to classify, a fixed-rate loan's move is a restructure
 * candidate; one person proposes, another approves (classification_status;
 * status is the pipeline's); a locked month is refused; a negotiated move
 * becomes a restructure candidate.
 */
class RateResetTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        // these tests are of other behaviour; the evidence a workflow needs before approval is tested in ApprovalWorkflowsTest
        config(['approvals.require_evidence' => false]);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('customer_name')->nullable(); $t->string('external_identity_id')->nullable(); $t->double('interest_rate')->nullable(); });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('rate_type')->default('FIXED'); $t->string('instrument_type')->nullable(); $t->date('origination_date')->nullable(); $t->date('maturity_date')->nullable(); $t->double('contractual_rate')->nullable(); $t->double('markup')->nullable(); $t->string('sub_account_no')->nullable(); $t->timestamps(); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->smallInteger('schedule_version')->default(1); $t->date('effective_from')->nullable(); $t->date('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); $t->timestamps(); });
        Schema::create('reference_rate_series', function (Blueprint $t) { $t->increments('id'); $t->string('index_code'); $t->date('effective_date'); $t->double('rate'); $t->timestamps(); });
        Schema::create('rate_reset_events', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->date('reset_date'); $t->decimal('old_reference_rate', 8, 5); $t->decimal('new_reference_rate', 8, 5); $t->unsignedSmallInteger('new_schedule_version'); $t->integer('recorded_by')->nullable(); $t->timestamps(); });
        Schema::create('reporting_period_locks', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period'); $t->timestamps(); });
        // the two migrations that share rate_reset_events, in the order a fresh install runs them
        (require base_path('database/migrations/2026_10_01_000000_create_eir_rate_periods.php'))->up();
        (require base_path('database/migrations/2026_10_14_230000_restructure_register_and_modifications.php'))->up();
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('t', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        // the PLR: 25.3 to 30 November 2025, 24.0 from 4 February 2026
        foreach ([['2025-06-01', 25.3], ['2026-02-04', 24.0]] as [$d, $r]) {
            DB::table('reference_rate_series')->insert(['index_code' => 'PLR', 'effective_date' => $d, 'rate' => $r]);
        }
        // drawn 1 July 2025 at 31.3 percent with the PLR at 25.3: the spread locked at drawdown is 6.0
        DB::table('contract_eir')->insert(['contract_id' => 'F1', 'rate_type' => 'FLOATING', 'origination_date' => '2025-07-01', 'maturity_date' => '2030-06-30', 'contractual_rate' => 0.313, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contract_eir')->insert(['contract_id' => 'X1', 'rate_type' => 'FIXED', 'created_at' => now(), 'updated_at' => now()]);
        $rates = [
            'F1' => ['2026-01' => 31.3, '2026-02' => 30.0, '2026-03' => 30.5], // moved with the PLR in February; spread 6.0 to 6.5 in March
            'X1' => ['2026-01' => 33.0, '2026-02' => 33.0, '2026-03' => 34.0], // a fixed-rate loan re-priced in March, no PLR move
        ];
        foreach ($rates as $c => $months) {
            foreach ($months as $p => $r) {
                DB::table('loan_books')->insert(['contract_id' => $c, 'reporting_period' => $p, 'customer_name' => "Loan {$c}", 'external_identity_id' => "0000{$c}", 'interest_rate' => $r]);
            }
        }
    }

    private function service(): RateResetService
    {
        return new RateResetService(new LandingZoneReader());
    }

    public function test_the_detector_splits_plr_resets_from_spread_changes_and_sends_a_fixed_rate_move_to_the_register(): void
    {
        $s = $this->service()->detect();

        $f = DB::table('rate_reset_events')->where('contract_id', 'F1')->orderBy('reporting_period')->get();
        $this->assertCount(2, $f);
        $this->assertSame('PLR_RESET', $f[0]->event_type);
        $this->assertSame('2026-02-01', substr($f[0]->reset_date, 0, 10), 'the whole month at the new rate (plr_mid_period default, section 3.1)');
        $this->assertSame('2026-02-04', substr($f[0]->plr_effective_date, 0, 10), 'the PLR\'s effective date is kept');
        $this->assertSame('PLR|F1|2026-02-01', $f[0]->detection_key, 'the pipeline\'s row, carrying the loan book\'s evidence');
        $this->assertEqualsWithDelta(30.0, $f[0]->new_rate, 1e-9);
        $this->assertEqualsWithDelta(0.30, $f[0]->new_contractual_rate, 1e-9);
        $this->assertSame('0000F1', $f[0]->account);
        $this->assertEqualsWithDelta(25.3, $f[0]->old_reference_rate, 1e-9);
        $this->assertEqualsWithDelta(24.0, $f[0]->new_reference_rate, 1e-9);
        $this->assertSame('IN_CONTRACT', $f[0]->suggested_classification);
        $this->assertSame('SPREAD_CHANGE', $f[1]->event_type);
        $this->assertSame('2026-03-01', $f[1]->reset_date, 'no PLR move and nothing keyed: the whole month at the new rate (section 3.1)');
        $this->assertEqualsWithDelta(6.5, $f[1]->new_spread, 1e-9);
        $this->assertNull($f[1]->suggested_classification);
        $this->assertSame('DETECTED', $f[1]->classification_status);

        $x = DB::table('restructure_events')->where('contract_id', 'X1')->first();
        $this->assertSame('RATE_CHANGE', $x->source);
        $this->assertSame('CANDIDATE', $x->status);
        $this->assertSame(1, $s['fixed_rate_candidates']);
        $this->assertSame(0, DB::table('rate_reset_events')->where('contract_id', 'X1')->count());

        // detection again changes nothing and never approves
        $this->service()->detect();
        $this->assertSame(2, DB::table('rate_reset_events')->count());
        $this->assertSame(0, DB::table('rate_reset_events')->where('classification_status', '!=', 'DETECTED')->count());
        $this->assertSame(2, DB::table('eir_rate_periods')->where('contract_id', 'F1')->count(), 'the pipeline\'s rate periods: drawdown, then February');
    }

    public function test_maker_checker_on_a_reset_and_a_negotiated_move_becomes_a_restructure_candidate(): void
    {
        $this->service()->detect();
        $spread = (int) DB::table('rate_reset_events')->where('event_type', 'SPREAD_CHANGE')->value('id');
        $svc = $this->service();

        $svc->propose([$spread], 'NEGOTIATED', 'Rate cut agreed with the borrower', 1);
        try {
            $svc->approve([$spread], 1);
            $this->fail('The proposer approved their own classification.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot approve', $e->getMessage());
        }
        $svc->approve([$spread], 2);

        $r = DB::table('rate_reset_events')->where('id', $spread)->first();
        $this->assertSame('APPROVED', $r->classification_status);
        $this->assertNotNull($r->restructure_event_id);
        $e = DB::table('restructure_events')->where('id', $r->restructure_event_id)->first();
        $this->assertSame('MODIFICATION', $e->suggested_class);
        $this->assertSame('CANDIDATE', $e->status, 'still a candidate on the register: two more people confirm it there');
    }

    public function test_a_reset_in_a_locked_month_is_refused(): void
    {
        $this->service()->detect();
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2026-02']);
        $plr = (int) DB::table('rate_reset_events')->where('event_type', 'PLR_RESET')->value('id');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('locked');
        $this->service()->propose([$plr], 'IN_CONTRACT', 'PLR change', 1);
    }

    public function test_every_change_is_a_reset_closes_the_negotiated_classification(): void
    {
        $this->service()->detect();
        GovernanceSetting::query()->where('key', 'rate_change_classification')->update(['value' => 'Every rate change is a reset']);
        $spread = (int) DB::table('rate_reset_events')->where('event_type', 'SPREAD_CHANGE')->value('id');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Every rate change is a reset');
        $this->service()->propose([$spread], 'NEGOTIATED', 'agreed', 1);
    }

    /** The proposer withdraws; a rejection is a second person's act (documentation audit, 14 October 2026). */
    public function test_the_proposer_cannot_reject_their_own_reset_classification(): void
    {
        $this->service()->detect();
        $spread = (int) DB::table('rate_reset_events')->where('event_type', 'SPREAD_CHANGE')->value('id');
        $svc = $this->service();
        $svc->propose([$spread], 'NEGOTIATED', 'Rate cut agreed with the borrower', 1);
        try {
            $svc->close($spread, 1, 'reject', 'Not sure after all');
            $this->fail('The proposer rejected their own classification.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('withdraw it instead', $e->getMessage());
        }
        $svc->close($spread, 1, 'withdraw', 'Proposed in error');
        $this->assertSame('DETECTED', DB::table('rate_reset_events')->where('id', $spread)->value('classification_status'));
    }

    /**
     * One detector (reconciliation of 14 October 2026): the pipeline's detector and the register
     * run in either order, any number of times, and each reset is one row; a PLR reset and a
     * spread change in the same month are two events on one date; a fixed-rate loan whose rate
     * moved with the PLR is recorded, held from the pipeline with the reason.
     */
    public function test_each_reset_is_detected_once_and_both_legs_of_one_month_are_two_events(): void
    {
        // F2: the PLR fell 1.3 points in February and MAIIC raised the spread by 0.5 in the same month
        DB::table('contract_eir')->insert(['contract_id' => 'F2', 'rate_type' => 'FLOATING', 'origination_date' => '2025-07-01', 'maturity_date' => '2030-06-30', 'contractual_rate' => 0.313, 'created_at' => now(), 'updated_at' => now()]);
        // M1: Interest Policy M, the engine's rate type FIXED, re-priced with the PLR in February
        DB::table('contract_eir')->insert(['contract_id' => 'M1', 'rate_type' => 'FIXED', 'origination_date' => '2025-07-01', 'maturity_date' => '2030-06-30', 'contractual_rate' => 0.313, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['F2' => ['2026-01' => 31.3, '2026-02' => 30.5], 'M1' => ['2026-01' => 31.3, '2026-02' => 30.0]] as $c => $months) {
            foreach ($months as $p => $r) {
                DB::table('loan_books')->insert(['contract_id' => $c, 'reporting_period' => $p, 'customer_name' => "Loan {$c}", 'external_identity_id' => "0000{$c}", 'interest_rate' => $r]);
            }
        }

        app(\App\Services\Eir\ResetDetectionService::class)->detect();
        $this->service()->detect();
        $this->service()->detect();
        app(\App\Services\Eir\ResetDetectionService::class)->detect();

        $this->assertSame(1, DB::table('rate_reset_events')->where('contract_id', 'F1')->where('event_type', 'PLR_RESET')->count(), 'detected once');
        $f2 = DB::table('rate_reset_events')->where('contract_id', 'F2')->orderBy('event_type')->get();
        $this->assertSame(['PLR_RESET', 'SPREAD_CHANGE'], $f2->pluck('event_type')->all());
        $this->assertSame(['2026-02-01', '2026-02-01'], $f2->pluck('reset_date')->map(fn ($d) => substr($d, 0, 10))->all(), 'two events on one date: one classification each');
        $this->assertSame(['IN_CONTRACT', null], $f2->pluck('suggested_classification')->all());
        $this->assertEqualsWithDelta(6.5, $f2[1]->new_spread, 1e-9);
        $this->assertStringContainsString('prime plus the spread locked at drawdown gives 30%', $f2[0]->evidence);

        $m1 = DB::table('rate_reset_events')->where('contract_id', 'M1')->sole();
        $this->assertSame(['PLR_RESET', 'BLOCKED', 'DETECTED', 'MOVE|M1|2026-02'], [$m1->event_type, $m1->status, $m1->classification_status, $m1->detection_key]);
        $this->assertStringContainsString('fixed-rate', $m1->note);
        $this->assertSame(0, DB::table('restructure_events')->where('contract_id', 'M1')->count(), 'moved with the PLR: a reset to record, not a restructure');
    }

    /** Both migrations add each column only where it is missing, in either order and over the register's first version. */
    public function test_the_two_migrations_run_in_either_order_and_over_the_registers_first_version(): void
    {
        $columns = fn () => collect(Schema::getColumnListing('rate_reset_events'))->sort()->values()->all();
        $indexes = fn () => array_keys(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('rate_reset_events'));
        $expected = $columns();
        $this->assertContains('status', $expected);
        $this->assertContains('classification_status', $expected);
        $this->assertContains('uq_reset_contract_date_type', $indexes());
        $this->assertNotContains('uq_reset_contract_date', $indexes());

        $base = function () {
            Schema::dropIfExists('eir_rate_periods');
            Schema::dropIfExists('rate_reset_events');
            Schema::create('rate_reset_events', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->date('reset_date'); $t->decimal('old_reference_rate', 8, 5); $t->decimal('new_reference_rate', 8, 5); $t->unsignedSmallInteger('new_schedule_version'); $t->integer('recorded_by')->nullable(); $t->timestamps(); });
        };
        $a = fn () => (require base_path('database/migrations/2026_10_01_000000_create_eir_rate_periods.php'))->up();
        $b = fn () => (require base_path('database/migrations/2026_10_14_230000_restructure_register_and_modifications.php'))->up();

        // the register first, then the pipeline
        $base(); $b(); $a();
        $this->assertSame($expected, $columns());
        $this->assertContains('uq_reset_contract_date_type', $indexes());

        // the register's first version (its maker-checker state in `status`), then the pipeline
        $base();
        Schema::table('rate_reset_events', function (Blueprint $t) { $t->string('detection_key', 120)->nullable()->unique(); $t->string('event_type', 20)->nullable(); $t->string('status', 12)->default('DETECTED'); });
        DB::table('rate_reset_events')->insert(['contract_id' => 'F1', 'reset_date' => '2026-02-04', 'old_reference_rate' => 25.3, 'new_reference_rate' => 24.0, 'new_schedule_version' => 1,
            'detection_key' => 'RESET|F1|2026-02', 'event_type' => 'PLR_RESET', 'status' => 'PROPOSED']);
        $a(); $b();
        $this->assertSame($expected, $columns());
        $row = DB::table('rate_reset_events')->sole();
        $this->assertSame(['PROPOSED', 'DETECTED'], [$row->classification_status, $row->status], 'the person\'s proposal is kept as the classification; the pipeline starts at DETECTED');

        // running either again changes nothing
        $a(); $b();
        $this->assertSame($expected, $columns());
    }
}
