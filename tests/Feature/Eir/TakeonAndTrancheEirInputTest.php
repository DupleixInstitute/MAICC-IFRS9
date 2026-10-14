<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\CalculateEirService;
use App\Services\Eir\EirContractInputService;
use App\Services\Eir\EirReadinessService;
use App\Services\Eir\FeeSourcePrecedence;
use App\Services\Eir\ScheduleGeneratorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesFeeSchema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The EIR vector of a facility drawn in tranches and of a take-on loan
 * (results review of 13 October 2026, E1 and E2).
 *
 *  - A facility signed before any money went out is solved from its first
 *    drawdown, not from the signing date, with each later tranche paid out on
 *    its own date, and only the fees of the source of record enter it.
 *  - A take-on loan is solved on the balance chain the take-on build checked,
 *    with the take-on workbook's fees once reviewed, from the date its
 *    schedule starts; a solve the check refused is named, not used, and a
 *    loan E-Banker now shows closed still has its EIR.
 */
class TakeonAndTrancheEirInputTest extends TestCase
{
    use CreatesGovernanceSchema, CreatesFeeSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        $this->createFeeSchema();
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_08_200000_create_takeon_tables', '2026_10_09_500000_takeon_recompute_from_origination', '2026_09_24_000003_create_contract_disbursements', '2026_10_14_200000_eir_tranches_takeon_and_gl_fees'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id')->unique(); $t->string('instrument_type')->default('AMORTISED_LOAN'); $t->string('rate_type')->default('FLOATING');
            $t->string('origination_date')->nullable(); $t->double('drawn_amount')->nullable(); $t->double('approved_amount')->nullable(); $t->double('contractual_rate')->nullable();
            $t->integer('payments_per_year')->default(12); $t->string('frequency_source')->default('STATED'); $t->string('schedule_source')->nullable();
            $t->string('schedule_approval_status')->default('APPROVED'); $t->double('schedule_amortising_balance')->nullable(); $t->string('source_day_count_basis')->nullable();
            $t->string('locked_at')->nullable(); $t->timestamps();
        });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version')->default(1); $t->string('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); $t->string('schedule_source')->nullable(); });
    }

    private function fee(string $contract, float $amount, string $source, string $status = 'REVIEWED', ?string $supersededBy = null, string $type = 'arrangement'): void
    {
        DB::table('contract_fees')->insert(['contract_id' => $contract, 'fee_type' => $type, 'amount' => $amount, 'source_system' => $source, 'external_transaction_id' => uniqid(),
            'classification_status' => $status, 'integral' => $status === 'REVIEWED' ? 1 : null, 'cashflow_direction' => 'RECEIVED', 'superseded_by_source' => $supersededBy]);
    }

    /** Mphunzitsi SACCO: signed 15 July 2025, drawn 8 August, 28 November and 2 February. */
    private function seedTrancheFacility(): array
    {
        $tranches = [['2025-08-08', 397653000.0], ['2025-11-28', 350000000.0], ['2026-02-02', 300000000.0]];
        $r = (new ScheduleGeneratorService())->generate([
            'principal' => 1047653000.0, 'approved_amount' => 1047653000.0, 'annual_rate' => 0.29, 'payments_per_year' => 12, 'n_payments' => 24,
            'start_date' => '2025-07-15', 'interest_start_date' => '2025-07-15', 'first_due_date' => '2025-08-15', 'day_count' => 'ACT/365',
            'drawdowns' => array_map(fn ($t) => ['date' => $t[0], 'amount' => $t[1]], $tranches), 'interest_calc_base_observed' => ['basis' => 'B', 'evidence' => []],
        ]);
        DB::table('contract_eir')->insert(['contract_id' => 'MPH', 'origination_date' => '2025-07-15', 'drawn_amount' => 1047653000, 'approved_amount' => 1047653000, 'contractual_rate' => 0.29,
            'schedule_source' => 'GENERATED', 'schedule_amortising_balance' => $r['retires'], 'created_at' => now(), 'updated_at' => now()]);
        foreach ($r['rows'] as $row) {
            DB::table('contract_cashflow_schedule')->insert(['contract_id' => 'MPH', 'due_date' => $row['due_date'], 'principal_due' => $row['principal_due'], 'interest_due' => $row['interest_due'], 'schedule_source' => 'GENERATED']);
        }
        foreach ($tranches as $i => [$date, $amount]) {
            DB::table('contract_disbursements')->insert(['contract_id' => 'MPH', 'tranche_no' => $i + 1, 'disbursement_date' => $date, 'amount' => $amount, 'reference' => 'CUMVOUCH:' . $i,
                'source_system' => 'MAIIC_DISBURSEMENTS', 'external_transaction_id' => 'x' . $i, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $r;
    }

    public function test_a_tranche_facility_is_solved_from_its_first_drawdown_with_the_fees_of_the_source_of_record(): void
    {
        $this->seedTrancheFacility();
        $this->fee('MPH', 47653000, FeeSourcePrecedence::SOURCE_GL);
        // the same fee held in the charges table, reviewed too, but superseded: it never enters the EIR
        $this->fee('MPH', 47653000, FeeSourcePrecedence::SOURCE_LOS, 'REVIEWED', FeeSourcePrecedence::TIER_GL);

        $ready = app(EirReadinessService::class)->assess('MPH');
        $this->assertTrue($ready['ready'], json_encode($ready['issues']));
        $this->assertEquals(47653000, $ready['metrics']['integral_fees_received']);

        $input = app(EirContractInputService::class)->assemble('MPH');
        $this->assertSame('2025-08-08', $input['metadata']['origination_date']);
        $this->assertSame('2025-07-15', $input['metadata']['contract_origination_date']);
        $this->assertSame(2, $input['metadata']['later_drawdowns']);
        $this->assertEquals(350000000, $input['initial_net_investment']);
        $this->assertCount(1, $input['fee_adjustments']['lines']);

        $solved = (new CalculateEirService())->calculateDated($input['initial_net_investment'], $input['cash_flows'], 12, $input['metadata']['origination_date'], 'ACT/365');
        // the contractual 29 percent plus the effect of 47.7 million of fees on a two-year facility (41.3 percent with no fee before)
        $this->assertGreaterThan(0.32, $solved['eir_nominal_annual']);
        $this->assertLessThan(0.38, $solved['eir_nominal_annual']);
    }

    public function test_with_no_fee_reviewed_the_tranche_facility_solves_to_its_contractual_rate(): void
    {
        $this->seedTrancheFacility();
        $this->fee('MPH', 47653000, FeeSourcePrecedence::SOURCE_GL, 'REJECTED');
        $input = app(EirContractInputService::class)->assemble('MPH');
        $solved = (new CalculateEirService())->calculateDated($input['initial_net_investment'], $input['cash_flows'], 12, $input['metadata']['origination_date'], 'ACT/365');
        $this->assertEqualsWithDelta(0.29, $solved['eir_nominal_annual'], 0.003);
    }

    public function test_a_pending_fee_blocks_and_a_superseded_one_does_not(): void
    {
        $this->seedTrancheFacility();
        $this->fee('MPH', 30000000, FeeSourcePrecedence::SOURCE_LOS, 'PENDING', FeeSourcePrecedence::TIER_GL);
        $this->assertTrue(app(EirReadinessService::class)->assess('MPH')['ready']);
        $this->fee('MPH', 30000000, FeeSourcePrecedence::SOURCE_GL, 'PENDING');
        $issues = array_column(app(EirReadinessService::class)->assess('MPH')['issues'], 'message', 'code');
        $this->assertSame('1 fee/cost line(s) are not independently reviewed.', $issues['FEE_CLASSIFICATION_PENDING']);
    }

    private function seedTakeon(string $check = 'PASS', ?string $reason = null): void
    {
        // a loan E-Banker shows closed today (drawn amount nil in the latest run), taken on in July 2024
        DB::table('contract_eir')->insert(['contract_id' => 'TK', 'origination_date' => '2024-07-31', 'drawn_amount' => 0, 'contractual_rate' => 0.287,
            'schedule_source' => 'TAKEON_WORKBOOK', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['2022-09-30' => 600000, '2022-10-31' => 600000] as $due => $amount) {
            DB::table('contract_cashflow_schedule')->insert(['contract_id' => 'TK', 'due_date' => $due, 'principal_due' => $amount, 'interest_due' => 100000, 'schedule_source' => 'TAKEON_WORKBOOK']);
        }
        $chain = ['start' => '2022-09-01', 'opening' => 1000000, 'flows' => [
            ['due_date' => '2022-09-30', 'amount' => 516666.67],
            ['due_date' => '2022-10-01', 'amount' => -200000, 'flow_type' => CalculateEirService::FLOW_DISBURSEMENT, 'note' => 'advanced between two lines (row 9)'],
            ['due_date' => '2022-10-31', 'amount' => 702277.78],
        ]];
        DB::table('contract_takeon')->insert(['account' => '000000000000TK', 'contract_id' => 'TK', 'basis' => $check === 'PASS' ? 'RECOMPUTED' : 'TAKEON_BALANCE',
            'eir_check' => $check, 'eir_check_reason' => $reason, 'nofee_eir' => 0.2196, 'recompute_detail' => json_encode(['chain' => $chain]), 'flags' => json_encode($reason ? ['take-on EIR refused: ' . $reason] : []),
            'setting_value' => 'x', 'built_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_takeon_loan_is_solved_on_its_checked_chain_with_its_reviewed_takeon_fees(): void
    {
        $this->seedTakeon();
        $this->fee('TK', 20000, FeeSourcePrecedence::SOURCE_TAKEON);
        // a fee charged on the account after the take-on belongs to a later event, not to the origination
        $this->fee('TK', 99999, FeeSourcePrecedence::SOURCE_GL);

        $ready = app(EirReadinessService::class)->assess('TK');
        $this->assertTrue($ready['ready'], json_encode($ready['issues']));

        $input = app(EirContractInputService::class)->assemble('TK');
        $this->assertSame('2022-09-01', $input['metadata']['origination_date']);
        $this->assertSame('TAKEON_WORKBOOK', $input['metadata']['schedule_source']);
        $this->assertEquals(980000, $input['initial_net_investment']);
        $this->assertSame(1, $input['metadata']['later_drawdowns']);
        $this->assertEqualsWithDelta(12 * (pow(1.2196, 1 / 12) - 1), $input['metadata']['schedule_rate'], 1e-9);
        $this->assertSame(CalculateEirService::FLOW_DISBURSEMENT, $input['cash_flows'][1]['flow_type']);
        $solved = (new CalculateEirService())->calculateDated($input['initial_net_investment'], $input['cash_flows'], 12, '2022-09-01', 'ACT/365');
        $this->assertGreaterThan(0.2, $solved['eir_effective_annual']);
    }

    public function test_a_takeon_solve_the_check_refused_is_named_and_not_used(): void
    {
        $this->seedTakeon('REFUSED', 'the schedule does not retire the loan: 9,203,503.73 is still owed at its last line');
        $issues = array_column(app(EirReadinessService::class)->assess('TK')['issues'], 'message', 'code');
        $this->assertArrayHasKey('TAKEON_NOT_RECOMPUTED', $issues);
        $this->assertStringContainsString('does not retire the loan', $issues['TAKEON_NOT_RECOMPUTED']);
        $this->assertArrayNotHasKey('DRAWN_AMOUNT_MISSING', $issues);
    }
}
