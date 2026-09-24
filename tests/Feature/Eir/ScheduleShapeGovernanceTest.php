<?php

namespace Tests\Feature\Eir;

use App\Exceptions\GovernanceSettingMissingException;
use App\Models\ContractEir;
use App\Models\Scheme;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\ScheduleGeneratorService;
use App\Services\Eir\ScheduleWorkflowService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The schedule generator's conventions come from the Governance Centre and its
 * shapes come from the contract or its scheme, on a private in-memory schema.
 * Four things are proved here that the pure unit tests cannot: the day count is
 * read from the Centre, a missing day count stops the generator, a change to
 * the day count never restates a schedule built under the old one, and the
 * scheme table supplies a basis the contract row leaves blank.
 */
class ScheduleShapeGovernanceTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('email');
            $t->timestamps();
        });
        Schema::create('schemes', function (Blueprint $t) {
            $t->increments('id');
            $t->string('scheme_code', 30);
            $t->string('product_code', 30)->nullable();
            $t->char('interest_policy', 1)->nullable();
            $t->char('floating_flag', 1)->nullable();
            $t->char('interest_calc_base', 1)->nullable();
            $t->string('installment_based_on', 20)->nullable();
            $t->char('emi_calc_type', 1)->nullable();
            $t->string('default_moratorium_type', 20)->nullable();
            $t->string('effective_from');
            $t->timestamps();
            $t->unique(['scheme_code', 'effective_from']);
        });
        DB::table('users')->insert([
            ['id' => 10, 'name' => 'Maker', 'email' => 'maker@example.test', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'name' => 'Checker', 'email' => 'checker@example.test', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        // The seeded defaults take effect on 1 January 2025. The loans in this
        // fixture were written in 2024, so the Centre also carries the same day
        // count from 2020, which is what MAIIC has to record for the cohort
        // written before the conventions were signed off.
        DB::table('governance_settings')->insert([
            'key' => 'day_count', 'value' => 'ACT/365',
            'options' => json_encode(['ACT/365', '30/360']),
            'label' => 'Day count', 'description' => 'The convention in force before the defaults were approved.',
            'effective_from' => '2020-01-01', 'set_by' => 10, 'approved_by' => 20,
            'approved_at' => now(), 'reason' => 'Day count in force for the pre-2025 cohort',
            'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function terms(array $overrides = []): array
    {
        return array_merge([
            'principal' => 108_431_000,
            'annual_rate' => 0.10,
            'payments_per_year' => 12,
            'n_payments' => 48,
            'start_date' => '2024-02-24',
            'moratorium_months' => 6,
            'moratorium_type' => 'Both (Interest + Principle)',
        ], $overrides);
    }

    public function test_the_day_count_is_read_from_the_governance_centre(): void
    {
        $generator = new ScheduleGeneratorService(new GovernanceService());

        $result = $generator->generate($this->terms());

        $this->assertSame('ACT/365', $result['day_count']);
        // Actual days over 365, not the annual rate over twelve.
        $this->assertEqualsWithDelta(5_520_265.82, $result['capitalised_interest'], 0.01);
        $this->assertSame([29, 31, 30, 31, 30, 31], array_column($result['moratorium_rows'], 'days'));
    }

    public function test_the_generator_stops_when_no_day_count_is_approved(): void
    {
        DB::table('governance_settings')->where('key', 'day_count')->delete();
        $generator = new ScheduleGeneratorService(new GovernanceService());

        $this->expectException(GovernanceSettingMissingException::class);
        $generator->generate($this->terms());
    }

    public function test_a_later_day_count_change_does_not_restate_an_earlier_schedule(): void
    {
        $governance = new GovernanceService();
        $proposal = $governance->propose('day_count', '30/360', '2026-01-01',
            'MAIIC elected thirty-day months from January 2026', 10);
        $governance->approve($proposal->id, 20);

        $generator = new ScheduleGeneratorService(new GovernanceService());

        $before = $generator->generate($this->terms(['as_of' => '2024-02-24']));
        $after = $generator->generate($this->terms(['start_date' => '2026-02-24', 'as_of' => '2026-02-24']));

        $this->assertSame('ACT/365', $before['day_count']);
        $this->assertSame('30/360', $after['day_count']);
        // Thirty-day months over 360: every month carries the same fraction of
        // a year, so each month's interest is the balance times the rate over
        // twelve and the balance grows only by what was capitalised.
        $first = 108_431_000 * 0.10 / 12;
        $this->assertEqualsWithDelta($first, $after['moratorium_rows'][0]['interest'], 0.01);
        $this->assertEqualsWithDelta((108_431_000 + round($first, 2)) * 0.10 / 12,
            $after['moratorium_rows'][1]['interest'], 0.01);
        $this->assertNotEquals($before['capitalised_interest'], $after['capitalised_interest']);
    }

    public function test_the_scheme_supplies_a_basis_the_contract_row_leaves_blank(): void
    {
        Scheme::create([
            'scheme_code' => 'MAIIC-IND',
            'product_code' => 'INDUSTRIAL',
            'interest_calc_base' => 'B',
            'installment_based_on' => 'disbursement',
            'emi_calc_type' => 'E',
            'default_moratorium_type' => 'Principle Only',
            'effective_from' => '2024-01-01',
        ]);

        $scheme = Scheme::inForce('MAIIC-IND', now());
        $this->assertNotNull($scheme);

        $generator = new ScheduleGeneratorService(new GovernanceService());
        $result = $generator->generate([
            'principal' => 297_161_905,
            'approved_amount' => 1_055_473_655,
            'annual_rate' => 0.3475,
            'payments_per_year' => 12,
            'n_payments' => 36,
            'start_date' => '2025-06-30',
            'scheme' => [
                'interest_calc_base' => $scheme->interest_calc_base,
                'installment_based_on' => $scheme->installment_based_on,
                'emi_calc_type' => $scheme->emi_calc_type,
                'default_moratorium_type' => $scheme->default_moratorium_type,
            ],
        ]);

        $this->assertSame('SCHEME', $result['basis_sources']['interest_basis']);
        $this->assertSame('SCHEME', $result['basis_sources']['instalment_basis']);
        $this->assertEqualsWithDelta(8_605_313.50, $result['rows'][0]['interest_due'], 0.01);
    }

    /**
     * The workflow service is the application's own path into the generator:
     * it reads the contract row and its scheme, records the shape it built on,
     * and refuses a contract whose shape is not stated anywhere.
     */
    public function test_the_workflow_records_the_shape_and_refuses_an_unstated_one(): void
    {
        $this->createScheduleSchema();
        Scheme::create([
            'scheme_code' => 'MAIIC-IND', 'interest_calc_base' => 'B', 'installment_based_on' => 'disbursement',
            'emi_calc_type' => 'E', 'default_moratorium_type' => null, 'effective_from' => '2024-01-01',
        ]);

        $workflow = app(ScheduleWorkflowService::class);

        $withShape = $this->seedContract('C-BOTH', ['moratorium_months' => 6, 'moratorium_type' => 'BOTH']);
        $result = $workflow->generate($withShape);
        $this->assertSame(48, $result['rows']);
        $this->assertSame('BOTH', $result['shape']['moratorium_type']);
        $this->assertSame('ACT/365', $result['shape']['day_count']);

        $stored = ContractEir::where('contract_id', 'C-BOTH')->first();
        $this->assertSame('BOTH', $stored->schedule_moratorium_type);
        $this->assertSame('ACT/365', $stored->schedule_day_count);
        $this->assertSame('B', $stored->schedule_interest_basis);
        $this->assertEqualsWithDelta(113_951_265.82, (float) $stored->schedule_amortising_balance, 0.01);
        $this->assertStringContainsString('moratorium_type=CONTRACT', (string) $stored->schedule_basis_sources);
        $this->assertSame(48, DB::table('contract_cashflow_schedule')->where('contract_id', 'C-BOTH')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'EIR Schedule Generated')->count());

        $withoutShape = $this->seedContract('C-BLANK', ['moratorium_months' => 6, 'moratorium_type' => null]);
        $readiness = $workflow->readiness($withoutShape);
        $this->assertFalse($readiness['ready']);
        $this->assertStringContainsString('A moratorium is stated but its type is not', implode('; ', $readiness['issues']));

        $this->expectException(InvalidArgumentException::class);
        $workflow->generate($withoutShape);
    }

    private function seedContract(string $contractId, array $overrides = []): ContractEir
    {
        return ContractEir::create(array_merge([
            'contract_id' => $contractId,
            'instrument_type' => 'LOAN',
            'scheme_code' => 'MAIIC-IND',
            'origination_date' => '2024-02-24',
            'maturity_date' => '2028-08-24',
            'approved_amount' => 108_431_000,
            'drawn_amount' => 108_431_000,
            'contractual_rate' => 10.0,
            'payments_per_year' => 12,
            'frequency_source' => 'STATED',
            'moratorium_months' => 0,
            'schedule_source' => 'GENERATED',
            'schedule_approval_status' => 'DRAFT',
        ], $overrides));
    }

    private function createScheduleSchema(): void
    {
        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->unique();
            $t->string('instrument_type')->nullable();
            $t->string('scheme_code', 30)->nullable();
            $t->string('origination_date')->nullable();
            $t->string('maturity_date')->nullable();
            $t->string('first_repayment_date')->nullable();
            $t->string('first_instalment_date')->nullable();
            $t->string('interest_start_date')->nullable();
            $t->string('moratorium_from')->nullable();
            $t->double('approved_amount')->nullable();
            $t->double('drawn_amount')->nullable();
            $t->double('contractual_rate')->nullable();
            $t->integer('payments_per_year')->nullable();
            $t->string('frequency_source')->nullable();
            $t->integer('moratorium_months')->default(0);
            $t->integer('grace_period_months')->nullable();
            $t->string('moratorium_type', 20)->nullable();
            $t->string('moratorium_type_verbatim', 40)->nullable();
            $t->char('interest_calc_base', 1)->nullable();
            $t->string('installment_based_on', 20)->nullable();
            $t->char('emi_calc_type', 1)->nullable();
            $t->string('schedule_source', 20)->nullable();
            $t->string('schedule_approval_status', 20)->nullable();
            $t->string('schedule_comparison_status', 30)->nullable();
            $t->text('schedule_review_notes')->nullable();
            $t->string('schedule_generated_at')->nullable();
            $t->string('schedule_approved_at')->nullable();
            $t->integer('schedule_approved_by')->nullable();
            $t->double('schedule_amortising_balance')->nullable();
            $t->string('schedule_moratorium_type', 20)->nullable();
            $t->char('schedule_emi_calc_type', 1)->nullable();
            $t->char('schedule_interest_basis', 1)->nullable();
            $t->string('schedule_instalment_basis', 20)->nullable();
            $t->string('schedule_day_count', 10)->nullable();
            $t->string('schedule_basis_sources')->nullable();
            $t->string('locked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->integer('schedule_version')->default(1);
            $t->string('effective_from')->nullable();
            $t->string('due_date');
            $t->double('principal_due')->default(0);
            $t->double('interest_due')->default(0);
            $t->double('fee_due')->default(0);
            $t->string('schedule_source', 20)->default('IMPORTED');
            $t->string('source_system')->nullable();
            $t->string('source_reference')->nullable();
            $t->string('external_transaction_id')->nullable();
            $t->timestamps();
        });
        Schema::create('contract_remaining_cashflow_schedule', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('due_date');
            $t->double('principal_due')->default(0);
            $t->double('interest_due')->default(0);
            $t->timestamps();
        });
    }
}
