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

    /**
     * A take-on loan's version 1 schedule comes from Tamanda's workbook (spec
     * 6.9). The generator refuses to write a second version 1 beside it (which
     * doubled the scheduled principal of 41 take-on loans and blocked their
     * EIR on the fresh bootstrap of 13 October 2026) and labels the contract
     * with the source it holds.
     */
    public function test_the_generator_never_writes_over_a_take_on_workbook_schedule(): void
    {
        $this->createScheduleSchema();
        Scheme::create([
            'scheme_code' => 'MAIIC-IND', 'interest_calc_base' => 'B', 'installment_based_on' => 'disbursement',
            'emi_calc_type' => 'E', 'default_moratorium_type' => null, 'effective_from' => '2024-01-01',
        ]);
        $contract = $this->seedContract('C-TAKEON', ['schedule_source' => null, 'schedule_approval_status' => 'NOT_GENERATED']);
        DB::table('contract_cashflow_schedule')->insert(['contract_id' => 'C-TAKEON', 'schedule_version' => 1, 'effective_from' => '2024-02-24', 'due_date' => '2024-03-31',
            'principal_due' => 108_431_000, 'interest_due' => 900_000, 'fee_due' => 0, 'schedule_source' => 'TAKEON_WORKBOOK', 'source_system' => 'TAKEON_WORKBOOK', 'created_at' => now(), 'updated_at' => now()]);

        $result = app(ScheduleWorkflowService::class)->generateEligible();

        $this->assertSame(0, $result['generated']);
        $this->assertStringContainsString('holds its version 1 schedule from TAKEON_WORKBOOK', $result['exceptions']['C-TAKEON']);
        $this->assertSame(1, DB::table('contract_cashflow_schedule')->where('contract_id', 'C-TAKEON')->count());
        $this->assertEqualsWithDelta(108_431_000, (float) DB::table('contract_cashflow_schedule')->where('contract_id', 'C-TAKEON')->sum('principal_due'), 0.01);
        $this->assertSame('TAKEON_WORKBOOK', ContractEir::where('contract_id', 'C-TAKEON')->value('schedule_source'));
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
            'contractual_rate' => 0.10, // a fraction, as contract_eir holds it (audit L5)
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
            $t->integer('schedule_prepared_by')->nullable(); $t->string('schedule_approver_label')->nullable(); // maker-checker on the schedule (O18)
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

    /**
     * Results review of 13 October 2026, E1: a facility drawn in tranches is
     * generated on its drawdown profile. Neither the contract nor its scheme
     * states the interest basis, so it is read from the interest E-Banker
     * posted while the facility was partly drawn ("IntDR Fm 08-AUG-25 To
     * 31-AUG-25 @ 29%"), and the schedule retires exactly what was drawn.
     */
    public function test_a_tranche_facility_is_generated_on_its_drawdowns_with_the_basis_read_from_the_postings(): void
    {
        $this->createTrancheSchema();
        $contract = $this->seedTranche();
        $this->interestPosting('2025-08-31', 397653000 * 0.29 * 24 / 365, '08-AUG-25 To 31-AUG-25 @ 29%');
        $this->interestPosting('2025-09-30', 397653000 * 0.29 * 30 / 365, '01-SEP-25 To 30-SEP-25 @ 29%');

        $result = app(ScheduleWorkflowService::class)->generate($contract);

        $stored = ContractEir::where('contract_id', '104430000085')->first();
        $this->assertStringContainsString('interest_basis=OBSERVED_EBANKER_INTEREST', $stored->schedule_basis_sources);
        $this->assertStringContainsString('drawdown_profile=TRANCHES_AS_DRAWN', $stored->schedule_basis_sources);
        $this->assertEqualsWithDelta(1047653000, (float) $stored->schedule_amortising_balance, 0.01);
        $this->assertEqualsWithDelta(1047653000, (float) DB::table('contract_cashflow_schedule')->where('contract_id', '104430000085')->sum('principal_due'), 0.05);
        $first = DB::table('contract_cashflow_schedule')->where('contract_id', '104430000085')->orderBy('due_date')->first();
        $this->assertSame('2025-08-15', $first->due_date);
        $this->assertEqualsWithDelta(397653000 * 0.29 * 7 / 365, (float) $first->interest_due, 0.01);
        $log = json_decode(DB::table('audit_logs')->where('action', 'EIR Schedule Generated')->value('meta'), true);
        $this->assertSame('the drawn balance', $log['interest_basis_evidence']['read_as']);
        $this->assertSame('narration', $log['interest_basis_evidence']['months'][0]['rate_from']);
        $this->assertSame(24, $result['rows']);
    }

    public function test_a_tranche_facility_with_no_postings_to_read_is_refused_rather_than_assumed(): void
    {
        $this->createTrancheSchema();
        $contract = $this->seedTranche();

        $this->expectExceptionMessage('INTEREST_BASIS_MISSING: the facility was drawn in tranches');
        app(ScheduleWorkflowService::class)->generate($contract);
    }

    public function test_the_governed_balance_wise_option_takes_the_drawn_balance_without_reading_the_postings(): void
    {
        $this->createTrancheSchema();
        $contract = $this->seedTranche();
        DB::table('governance_settings')->where('key', 'partly_drawn_interest_basis')->update(['value' => 'Balance-wise everywhere, flag exceptions']);

        app(ScheduleWorkflowService::class)->generate($contract);

        $this->assertStringContainsString('interest_basis=GOVERNED_BALANCE_WISE', ContractEir::where('contract_id', '104430000085')->value('schedule_basis_sources'));
    }

    private function createTrancheSchema(): void
    {
        $this->createScheduleSchema();
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_09_24_000003_create_contract_disbursements', '2026_10_14_200000_eir_tranches_takeon_and_gl_fees'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('e', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        Scheme::create(['scheme_code' => 'MAIIC-IND', 'interest_calc_base' => null, 'installment_based_on' => null, 'emi_calc_type' => 'E', 'default_moratorium_type' => null, 'effective_from' => '2024-01-01']);
    }

    /** Mphunzitsi SACCO: signed 15 July 2025, drawn 8 August, 28 November and 2 February, in full. */
    private function seedTranche(): ContractEir
    {
        foreach ([['2025-08-08', 397653000], ['2025-11-28', 350000000], ['2026-02-02', 300000000]] as $i => [$date, $amount]) {
            DB::table('contract_disbursements')->insert(['contract_id' => '104430000085', 'tranche_no' => $i + 1, 'disbursement_date' => $date, 'amount' => $amount,
                'reference' => 'CUMVOUCH:' . $i, 'source_system' => 'MAIIC_DISBURSEMENTS', 'external_transaction_id' => 't' . $i, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $this->seedContract('104430000085', ['origination_date' => '2025-07-15', 'interest_start_date' => '2025-07-15', 'maturity_date' => '2027-07-15',
            'approved_amount' => 1047653000, 'drawn_amount' => 1047653000, 'contractual_rate' => 0.29, 'schedule_approval_status' => 'NOT_GENERATED']);
    }

    private function interestPosting(string $date, float $amount, string $span): void
    {
        $key = 'I' . $date;
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => 'P1_01', 'source_key' => $key, 'account' => '000104430000085', 'row_date' => $date,
            'payload' => json_encode(['CUMVOUCH_DET_ID' => $key, 'AC_GLCODE' => '1050102', 'TRANSAMT' => (string) round(-$amount, 2), 'TRANTYPE' => '303', 'PARTICULARS' => 'To Trf IntDR Fm ' . $span, 'DELETE_FLAG' => 'N']),
            'row_hash' => md5($key), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
}
