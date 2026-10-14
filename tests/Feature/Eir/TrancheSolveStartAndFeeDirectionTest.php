<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirFeeClassificationController;
use App\Models\ContractEir;
use App\Models\User;
use App\Services\Ebanker\ContractInputsBuildService;
use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\CalculateEirService;
use App\Services\Eir\DisbursementService;
use App\Services\Eir\EirContractInputService;
use App\Services\Eir\EirReadinessService;
use App\Services\Eir\FeeSourcePrecedence;
use App\Services\Eir\ScheduleGeneratorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Feature\Eir\Concerns\CreatesFeeSchema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * No new EIR could be calculated after the fees were reviewed (UI end-to-end
 * audit of 14 October 2026, item 2).
 *
 *  - 104460000112: signed 17 July 2025, nothing drawn at signing, four
 *    tranches on 23 July (the advance and the capitalised fee debits). The
 *    solve started on the first row of 23 July and left the other three as
 *    "later" drawdowns dated on the solve date itself, which the solver
 *    refuses ("Every future contractual cash flow must fall after
 *    origination"). Every tranche of the first drawdown day is now the
 *    initial investment.
 *  - 104430000068: an approved schedule generated before the tranche register
 *    was read (drawn in full at signing) had instalments before the first
 *    money went out. The screen gate checked the dates against the signing
 *    date while the solve started at the first drawdown, so it passed what
 *    the solver refused. The gate now reads the same start, and an approved
 *    schedule generated without the tranches is named and may be generated
 *    again (to a draft a second person approves) while the EIR is unlocked.
 *  - 104430000073/74: LOS charge lines were loaded with no cash-flow
 *    direction and, once reviewed, could not be corrected. The loader states
 *    RECEIVED (a charge taken from the borrower), a line already decided
 *    without it goes back to classification, an integral line with no
 *    direction cannot be approved, and a reviewed line can be returned to
 *    classification with the reason.
 */
class TrancheSolveStartAndFeeDirectionTest extends TestCase
{
    use CreatesGovernanceSchema, CreatesFeeSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable(); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        $this->createFeeSchema();
        Schema::create('eir_fee_classification_events', function (Blueprint $t) { $t->increments('id'); $t->integer('contract_fee_id'); $t->string('action'); $t->boolean('integral')->nullable(); $t->text('reason')->nullable(); $t->integer('accounting_rule_id')->nullable(); $t->integer('performed_by')->nullable(); $t->timestamps(); });
        (require base_path('database/migrations/2026_09_24_000003_create_contract_disbursements.php'))->up();
        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id')->unique(); $t->string('instrument_type')->default('AMORTISED_LOAN'); $t->string('rate_type')->default('FIXED');
            $t->string('origination_date')->nullable(); $t->double('drawn_amount')->nullable(); $t->double('approved_amount')->nullable(); $t->double('contractual_rate')->nullable();
            $t->integer('payments_per_year')->default(12); $t->string('frequency_source')->default('STATED'); $t->string('schedule_source')->nullable();
            $t->string('schedule_approval_status')->default('APPROVED'); $t->double('schedule_amortising_balance')->nullable(); $t->string('schedule_basis_sources')->nullable();
            $t->string('source_day_count_basis')->nullable(); $t->string('locked_at')->nullable(); $t->timestamps();
        });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version')->default(1); $t->string('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); $t->string('schedule_source')->nullable(); });
        DB::table('users')->insert([['id' => 2, 'name' => 'Maker', 'email' => 'm@x.test', 'created_at' => now(), 'updated_at' => now()], ['id' => 3, 'name' => 'Checker', 'email' => 'c@x.test', 'created_at' => now(), 'updated_at' => now()]]);
    }

    private function tranches(string $id, array $tranches): void
    {
        foreach ($tranches as $i => [$date, $amount]) {
            DB::table('contract_disbursements')->insert(['contract_id' => $id, 'tranche_no' => $i + 1, 'disbursement_date' => $date, 'amount' => $amount, 'reference' => 'CUMVOUCH:' . $i,
                'source_system' => 'MAIIC_DISBURSEMENTS', 'external_transaction_id' => $id . $i, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** 104460000112's shape: 50,000,000 at 10 percent over 24 months, signed 17 July, drawn in four rows on 23 July. */
    private function seedSameDayFacility(string $basis = 'drawdown_profile=TRANCHES_AS_DRAWN'): void
    {
        $tranches = [['2025-07-23', 48243900.0], ['2025-07-23', 500000.0], ['2025-07-23', 855000.0], ['2025-07-23', 401100.0]];
        $r = (new ScheduleGeneratorService())->generate([
            'principal' => 50000000.0, 'approved_amount' => 50000000.0, 'annual_rate' => 0.10, 'payments_per_year' => 12, 'n_payments' => 24,
            'start_date' => '2025-07-17', 'interest_start_date' => '2025-07-17', 'first_due_date' => '2025-08-17', 'day_count' => 'ACT/365',
            'drawdowns' => array_map(fn ($t) => ['date' => $t[0], 'amount' => $t[1]], $tranches),
        ]);
        DB::table('contract_eir')->insert(['contract_id' => 'SD', 'origination_date' => '2025-07-17', 'drawn_amount' => 50000000, 'approved_amount' => 50000000, 'contractual_rate' => 0.10,
            'schedule_source' => 'GENERATED', 'schedule_amortising_balance' => $r['retires'], 'schedule_basis_sources' => $basis, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($r['rows'] as $row) {
            DB::table('contract_cashflow_schedule')->insert(['contract_id' => 'SD', 'due_date' => $row['due_date'], 'principal_due' => $row['principal_due'], 'interest_due' => $row['interest_due'], 'schedule_source' => 'GENERATED']);
        }
        $this->tranches('SD', $tranches);
        // the two fee debits, received from the borrower out of the advance (the GL route, the source of record)
        foreach ([500000, 855000] as $i => $fee) {
            DB::table('contract_fees')->insert(['contract_id' => 'SD', 'fee_type' => 'arrangement', 'amount' => $fee, 'source_system' => FeeSourcePrecedence::SOURCE_GL, 'external_transaction_id' => 'g' . $i,
                'classification_status' => 'REVIEWED', 'integral' => 1, 'cashflow_direction' => 'RECEIVED']);
        }
    }

    public function test_every_tranche_of_the_first_drawdown_day_is_the_initial_investment(): void
    {
        $this->seedSameDayFacility();
        $plan = app(DisbursementService::class)->solvePlan('SD', '2025-07-17');
        $this->assertSame('2025-07-23', $plan['solve_from']);
        $this->assertEquals(50000000.0, $plan['drawn']);
        $this->assertSame([], $plan['later']);

        $ready = app(EirReadinessService::class)->assess('SD');
        $this->assertTrue($ready['ready'], json_encode($ready['issues']));
        $input = app(EirContractInputService::class)->assemble('SD');
        $this->assertSame('2025-07-23', $input['metadata']['origination_date']);
        $this->assertSame(0, $input['metadata']['later_drawdowns']);
        $this->assertEquals(50000000 - 1355000, $input['initial_net_investment']);

        // the solver accepts the vector it refused before
        $solved = (new CalculateEirService())->calculateDated($input['initial_net_investment'], $input['cash_flows'], 12, $input['metadata']['origination_date'], 'ACT/365');
        $this->assertGreaterThan(0.10, $solved['eir_nominal_annual']); // the fees lift it above the contractual 10 percent
        $this->assertLessThan(0.15, $solved['eir_nominal_annual']);
    }

    public function test_a_schedule_generated_without_the_tranches_is_named_and_the_gate_reads_the_solve_start(): void
    {
        // 104430000068's shape: signed 14 August 2024, generated as drawn in full then; drawn 27 November and 18 February
        DB::table('contract_eir')->insert(['contract_id' => 'OLD', 'origination_date' => '2024-08-14', 'drawn_amount' => 200, 'contractual_rate' => 0.29, 'schedule_source' => 'GENERATED',
            'schedule_basis_sources' => 'emi_calc_type=ASSUMED_LEVEL_INSTALMENT_FACT_F7, interest_basis=DRAWN_IN_FULL_BASES_AGREE', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['2024-09-14', '2024-10-14', '2024-11-14', '2024-12-14'] as $d) {
            DB::table('contract_cashflow_schedule')->insert(['contract_id' => 'OLD', 'due_date' => $d, 'principal_due' => 50, 'interest_due' => 5]);
        }
        $this->tranches('OLD', [['2024-11-27', 25.0], ['2025-02-18', 175.0]]);

        $codes = array_column(app(EirReadinessService::class)->assess('OLD')['issues'], 'message', 'code');
        $this->assertStringContainsString('3 instalment(s)', $codes['SCHEDULE_BEFORE_FIRST_DRAWDOWN']);
        $this->assertStringContainsString('2024-11-27', $codes['SCHEDULE_BEFORE_FIRST_DRAWDOWN']);
        $this->assertStringContainsString('generated without the tranche register', $codes['SCHEDULE_NOT_ON_TRANCHES']);
        $this->assertNotNull(app(EirReadinessService::class)->trancheMismatch(ContractEir::where('contract_id', 'OLD')->first()));

        // a locked EIR is never reopened this way, and a schedule built on the tranches is not named
        DB::table('contract_eir')->where('contract_id', 'OLD')->update(['locked_at' => '2026-01-01']);
        $this->assertNull(app(EirReadinessService::class)->trancheMismatch(ContractEir::where('contract_id', 'OLD')->first()));
        $this->seedSameDayFacility();
        $this->assertNull(app(EirReadinessService::class)->trancheMismatch(ContractEir::where('contract_id', 'SD')->first()));
    }

    public function test_the_los_charges_load_as_received_and_a_line_decided_without_a_direction_goes_back(): void
    {
        DB::table('contract_eir')->insert(['contract_id' => 'L1', 'origination_date' => '2025-04-23', 'drawn_amount' => 100, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contract_eir')->insert(['contract_id' => 'LK', 'origination_date' => '2025-04-23', 'drawn_amount' => 100, 'locked_at' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        // loaded before the loader stated the direction: one reviewed, one pending, one superseded, one on a locked EIR
        foreach ([['L1', 'REVIEWED', null, 'P2_07:1'], ['L1', 'PENDING', null, 'P2_07:2'], ['L1', 'REVIEWED', 'GL posting', 'P2_07:3'], ['LK', 'REVIEWED', null, 'P2_07:4']] as [$c, $status, $sup, $ext]) {
            DB::table('contract_fees')->insert(['contract_id' => $c, 'fee_type' => 'legal', 'amount' => 10, 'source_system' => ContractInputsBuildService::LOS_SOURCE, 'external_transaction_id' => $ext,
                'classification_status' => $status, 'integral' => $status === 'REVIEWED' ? 1 : null, 'reviewed_by' => $status === 'REVIEWED' ? 3 : null, 'superseded_by_source' => $sup]);
        }
        $zone = Mockery::mock(LandingZoneReader::class);
        $zone->shouldReceive('family')->with(LandingZoneReader::CHARGES)->andReturn([
            ['payload' => ['ACTUAL_CHARGES_AMT' => '15000000', 'DISB_CHARGES_NAME' => 'Arrangement Fee', 'INCOME_GL' => '4101'], 'account' => 'L2', 'source_key' => '9', 'query_id' => 'P2_07'],
        ]);
        app()->instance(LandingZoneReader::class, $zone);
        $out = app(ContractInputsBuildService::class)->fees();

        $this->assertSame('RECEIVED', DB::table('contract_fees')->where('contract_id', 'L2')->value('cashflow_direction'));
        $this->assertSame(['filled' => 3, 'returned' => 1], $out['result']['direction_filled']);
        $reviewed = DB::table('contract_fees')->where('external_transaction_id', 'P2_07:1')->first();
        $this->assertSame(['RECEIVED', 'PENDING', null], [$reviewed->cashflow_direction, $reviewed->classification_status, $reviewed->reviewed_by]);
        $this->assertSame('REOPENED', DB::table('eir_fee_classification_events')->where('contract_fee_id', $reviewed->id)->value('action'));
        $superseded = DB::table('contract_fees')->where('external_transaction_id', 'P2_07:3')->first();
        $this->assertSame(['RECEIVED', 'REVIEWED'], [$superseded->cashflow_direction, $superseded->classification_status]);
        $this->assertNull(DB::table('contract_fees')->where('external_transaction_id', 'P2_07:4')->value('cashflow_direction'));
    }

    private function act(string $method, array $body, int $user): \Symfony\Component\HttpFoundation\Response
    {
        $this->actingAs(User::findOrFail($user));
        $request = Request::create('/eir-fee-classification/' . $method, 'POST', $body);
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);

        return app(EirFeeClassificationController::class)->{$method}($request);
    }

    public function test_an_integral_line_without_a_direction_is_not_approved_and_a_reviewed_line_can_be_returned(): void
    {
        DB::table('contract_eir')->insert(['contract_id' => 'R1', 'origination_date' => '2025-04-23', 'drawn_amount' => 100, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contract_fees')->insert([
            ['id' => 1, 'contract_id' => 'R1', 'fee_type' => 'legal', 'amount' => 10, 'classification_status' => 'CLASSIFIED', 'integral' => 1, 'cashflow_direction' => null, 'classified_by' => 2],
            ['id' => 2, 'contract_id' => 'R1', 'fee_type' => 'arrangement', 'amount' => 20, 'classification_status' => 'REVIEWED', 'integral' => 1, 'cashflow_direction' => null, 'classified_by' => 2],
        ]);
        // the governed override is off: no role tables are needed to read it here
        $gov = Mockery::mock(\App\Services\Eir\GovernanceService::class)->makePartial();
        $gov->shouldReceive('adminOverrideAllowed')->andReturn(false);
        app()->instance(\App\Services\Eir\GovernanceService::class, $gov);
        $this->act('review', ['fee_ids' => [1]], 3);
        $this->assertSame('CLASSIFIED', DB::table('contract_fees')->where('id', 1)->value('classification_status'));
        $this->assertStringContainsString('no cash-flow direction', session('errors')->first('fee_ids'));

        $this->act('returnToClassification', ['fee_ids' => [2], 'reason' => 'Reviewed without a direction; the LOS charge is received.'], 3);
        $this->assertSame('PENDING', DB::table('contract_fees')->where('id', 2)->value('classification_status'));
        $this->assertStringContainsString('Returned to classification (was reviewed)', DB::table('eir_fee_classification_events')->where('contract_fee_id', 2)->value('reason'));

        // a line inside a locked EIR stays as it is
        DB::table('contract_fees')->where('id', 2)->update(['classification_status' => 'REVIEWED']);
        DB::table('contract_eir')->where('contract_id', 'R1')->update(['locked_at' => '2026-01-01']);
        $this->act('returnToClassification', ['fee_ids' => [2], 'reason' => 'Try again on a locked EIR.'], 3);
        $this->assertSame('REVIEWED', DB::table('contract_fees')->where('id', 2)->value('classification_status'));
    }
}
