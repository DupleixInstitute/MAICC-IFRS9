<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\EirRevenueService;
use App\Services\Eir\ModificationAccountingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesFeeSchema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * IFRS 9 accounting for a confirmed restructure in the revenue run (spec v4
 * P6 and P7): 5.4.3 modification at the original EIR with its gain or loss,
 * the modification test (modification_threshold, O16) and its qualitative
 * factors, derecognition and version N+1 at fair value with a new EIR, a
 * POCI asset failing closed, and the B5.4.6 catch-up.
 *
 * The loan: EIR 12.6825 percent effective (1 percent a month), 1,000 at the
 * start of January 2025, 10 paid in January, so 1,000 before any event at
 * 31 January 2025. The modified flows are one payment a year later, so their
 * present value at the original EIR is the payment over 1.126825.
 */
class ModificationAccountingTest extends TestCase
{
    use CreatesGovernanceSchema, CreatesFeeSchema;

    protected $seed = false;

    private const EIR = 0.126825;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('rate_type')->nullable(); $t->double('contractual_rate')->nullable(); $t->double('eir_effective_annual')->nullable(); $t->json('input_snapshot')->nullable(); $t->double('opening_amortised_cost')->nullable(); $t->string('origination_date')->nullable(); $t->string('source_day_count_basis')->nullable(); $t->string('locked_at')->nullable(); $t->timestamps(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period', 7); $t->double('opening_gross'); $t->double('advances')->default(0); $t->string('opening_basis', 12)->nullable(); $t->double('eir_annual')->nullable(); $t->double('opening_arrears')->default(0); $t->double('interest_accrued'); $t->string('interest_basis'); $t->double('unwind_amount'); $t->double('cash_received'); $t->string('cash_source'); $t->double('modification_gain_loss'); $t->double('closing_gross'); $t->double('ecl_allowance'); $t->timestamps(); $t->unique(['contract_id', 'reporting_period']); });
        Schema::create('eir_amortisation_history', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period', 7); $t->double('opening_gross'); $t->double('advances')->default(0); $t->string('opening_basis', 12)->nullable(); $t->double('eir_annual')->nullable(); $t->double('opening_arrears')->default(0); $t->double('interest_accrued'); $t->string('interest_basis'); $t->double('unwind_amount'); $t->double('cash_received'); $t->string('cash_source'); $t->double('modification_gain_loss'); $t->double('closing_gross'); $t->double('ecl_allowance'); $t->string('originally_created_at')->nullable(); $t->string('superseded_at')->nullable(); $t->integer('superseded_by')->nullable(); $t->string('supersession_reason', 500); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->integer('calculated_ifrs9_stage')->nullable(); $t->integer('ifrs9stage_post_qualitative')->nullable(); $t->integer('ifrs9_stage')->nullable(); $t->double('expected_loss_provision')->default(0); $t->double('ecl_value')->nullable(); $t->double('interest_rate')->nullable(); $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->double('ecl_value_discounted')->nullable(); $t->string('ecl_discount_status')->nullable(); $t->string('product_code')->nullable(); });
        Schema::create('eir_actual_transactions', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('transaction_date'); $t->string('transaction_type', 50)->nullable(); $t->double('principal_component')->default(0); $t->double('interest_component')->default(0); $t->double('fee_component')->default(0); $t->double('total_amount')->default(0); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->smallInteger('schedule_version')->default(1); $t->date('effective_from')->nullable(); $t->string('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); $t->string('schedule_source')->nullable(); $t->timestamps(); });
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        (require base_path('database/migrations/2026_10_14_230000_restructure_register_and_modifications.php'))->up();
        DB::table('contract_eir')->insert(['contract_id' => 'C-1', 'eir_effective_annual' => self::EIR, 'input_snapshot' => json_encode(['initial_net_investment' => 1000]), 'locked_at' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['2025-01', '2025-02'] as $p) {
            DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => $p, 'calculated_ifrs9_stage' => 1]);
        }
        DB::table('eir_actual_transactions')->insert(['contract_id' => 'C-1', 'transaction_date' => '2025-01-20', 'transaction_type' => 'Principal+Interest', 'total_amount' => 10]);
        DB::table('eir_actual_transactions')->insert(['contract_id' => 'C-1', 'transaction_date' => '2025-02-20', 'transaction_type' => 'Principal+Interest', 'total_amount' => 0]);
    }

    /** A confirmed event of January 2025 whose new schedule pays $payment on 31 January 2026. */
    private function confirmed(string $class, float $payment, array $extra = []): int
    {
        DB::table('restructure_events')->insert(['id' => 1, 'detection_key' => 'K', 'contract_id' => 'C-1', 'event_date' => '2025-01-15', 'source' => 'CHART_REPLACED', 'suggested_class' => 'MODIFICATION',
            'status' => 'CONFIRMED', 'classification' => $class, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contract_cashflow_schedule')->insert(['contract_id' => 'C-1', 'schedule_version' => 2, 'effective_from' => '2025-01-15', 'due_date' => '2026-01-31', 'principal_due' => $payment, 'interest_due' => 0]);

        return (int) DB::table('contract_modifications')->insertGetId($extra + ['restructure_event_id' => 1, 'contract_id' => 'C-1', 'modification_date' => '2025-01-15', 'reporting_period' => '2025-01',
            'classification' => $class, 'old_schedule_version' => 1, 'new_schedule_version' => 2, 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function pv(float $payment): float
    {
        $end = CarbonImmutable::parse('2025-01-31');

        return ModificationAccountingService::presentValue([['due_date' => '2026-01-31', 'amount' => $payment]], $end, self::EIR);
    }

    public function test_a_modification_below_the_threshold_resets_the_gross_carrying_amount_at_the_original_eir_with_a_gain_or_loss(): void
    {
        $payment = 980 * 1.126825; // present value 980 at the original EIR: a 2 percent change
        $this->confirmed('MODIFICATION', $payment);

        $r = (new EirRevenueService())->run('C-1', '2025-01');
        $this->assertSame('CREATED', $r['status'], $r['error'] ?? '');
        $row = DB::table('eir_amortisation')->first();
        $m = DB::table('contract_modifications')->first();

        $this->assertSame('APPLIED', $m->status);
        $this->assertSame('MODIFICATION', $m->treatment);
        $this->assertEqualsWithDelta(1000, $m->carrying_amount_before, 0.01);
        $this->assertEqualsWithDelta($this->pv($payment), $m->pv_new_flows_at_original_eir, 0.01);
        $this->assertEqualsWithDelta(-20, $m->gain_loss, 0.05, 'the modification loss to profit or loss (5.4.3)');
        $this->assertEqualsWithDelta(10, $m->threshold_pct, 1e-9, 'the governed threshold in force (modification_threshold)');
        $this->assertEqualsWithDelta(980, $row->closing_gross, 0.05);
        // the row ties: opening + interest - cash + adjustment = closing
        $this->assertEqualsWithDelta($row->opening_gross + $row->interest_accrued - $row->cash_received + $row->modification_adjustment, $row->closing_gross, 0.02);
        $this->assertSame('MODIFICATION', $row->adjustment_kind);
        $this->assertSame(2, (int) $row->schedule_version);

        // the original EIR continues the next month
        (new EirRevenueService())->run('C-1', '2025-02');
        $feb = DB::table('eir_amortisation')->where('reporting_period', '2025-02')->first();
        $this->assertEqualsWithDelta(self::EIR, $feb->eir_annual, 1e-8);
    }

    public function test_a_change_at_or_above_the_threshold_derecognises_and_fails_closed_without_a_fair_value(): void
    {
        $this->confirmed('MODIFICATION', 850 * 1.126825); // a 15 percent change

        $r = (new EirRevenueService())->run('C-1', '2025-01');
        $this->assertSame('BLOCKED', $r['status']);
        $this->assertStringContainsString('fair value', $r['error']);
        $this->assertSame(0, DB::table('eir_amortisation')->count(), 'nothing is written for the month');
        $m = DB::table('contract_modifications')->first();
        $this->assertSame('BLOCKED', $m->status, 'the register shows why');
        $this->assertStringContainsString('15.00 percent', $m->blocked_reason);
    }

    public function test_derecognition_recognises_version_two_at_its_fair_value_with_a_new_eir(): void
    {
        $payment = 850 * 1.126825;
        $this->confirmed('MODIFICATION', $payment, ['fair_value' => 900]);

        $this->assertSame('CREATED', (new EirRevenueService())->run('C-1', '2025-01')['status']);
        $m = DB::table('contract_modifications')->first();
        $this->assertSame('DERECOGNITION', $m->treatment);
        $this->assertEqualsWithDelta(-100, $m->gain_loss, 0.05, 'fair value 900 against 1,000 before: the derecognition loss (IFRS 7.20A)');
        $newEir = $payment / 900 - 1; // one payment a year away
        $this->assertEqualsWithDelta($newEir, $m->revised_eir, 1e-6);
        $this->assertEqualsWithDelta(900, DB::table('eir_amortisation')->value('closing_gross'), 0.01);

        // February accrues at the new EIR on the fair value
        (new EirRevenueService())->run('C-1', '2025-02');
        $feb = DB::table('eir_amortisation')->where('reporting_period', '2025-02')->first();
        $this->assertEqualsWithDelta($newEir, $feb->eir_annual, 1e-6);
        $this->assertEqualsWithDelta(900 * (pow(1 + $newEir, 1 / 12) - 1), $feb->interest_accrued, 0.01);
    }

    public function test_a_qualitative_factor_derecognises_whatever_the_ten_percent_test_says(): void
    {
        $this->confirmed('MODIFICATION', 990 * 1.126825, ['qualitative_factors' => json_encode(['counterparty']), 'fair_value' => 990]);

        (new EirRevenueService())->run('C-1', '2025-01');
        $m = DB::table('contract_modifications')->first();
        $this->assertSame('DERECOGNITION', $m->treatment, 'a new borrower is a new asset (3.2.3)');
        $this->assertEqualsWithDelta(1, $m->change_pct, 0.01);
    }

    public function test_a_credit_impaired_loan_derecognised_is_poci_and_fails_closed(): void
    {
        DB::table('loan_books')->where('reporting_period', '2025-01')->update(['calculated_ifrs9_stage' => 3]);
        // the booked allowance at the December month-end, which the net basis nets (IFRS 9 5.4.1(b))
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2024-12', 'calculated_ifrs9_stage' => 3, 'ecl_value' => 290, 'ecl_value_discounted' => 300, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED']);
        $this->confirmed('MODIFICATION', 700 * 1.126825, ['fair_value' => 650]);

        $r = (new EirRevenueService())->run('C-1', '2025-01');
        $this->assertSame('BLOCKED', $r['status']);
        $this->assertStringContainsString('purchased or originated credit-impaired', $r['error']);
        $this->assertSame('BLOCKED', DB::table('contract_modifications')->value('status'));
    }

    public function test_a_revised_estimate_takes_the_b546_catch_up_and_keeps_the_eir(): void
    {
        $this->confirmed('REVISED_ESTIMATE', 950 * 1.126825);

        (new EirRevenueService())->run('C-1', '2025-01');
        $m = DB::table('contract_modifications')->first();
        $this->assertSame('CATCH_UP', $m->treatment);
        $this->assertNull($m->change_pct, 'no derecognition test for a revision of estimates');
        $this->assertEqualsWithDelta(-50, $m->gain_loss, 0.05);
        $this->assertEqualsWithDelta(950, DB::table('eir_amortisation')->value('closing_gross'), 0.05);
        (new EirRevenueService())->run('C-1', '2025-02');
        $this->assertEqualsWithDelta(self::EIR, DB::table('eir_amortisation')->where('reporting_period', '2025-02')->value('eir_annual'), 1e-8);
    }

    public function test_fees_on_a_modification_adjust_the_carrying_amount_and_revise_the_eir(): void
    {
        $payment = 980 * 1.126825;
        $this->confirmed('MODIFICATION', $payment, ['modification_fees' => 10]);

        (new EirRevenueService())->run('C-1', '2025-01');
        $m = DB::table('contract_modifications')->first();
        $this->assertEqualsWithDelta(-20, $m->gain_loss, 0.05, 'the fee is not in the gain or loss');
        $this->assertEqualsWithDelta(970, $m->carrying_amount_after, 0.05);
        $this->assertEqualsWithDelta($payment / 970 - 1, $m->revised_eir, 1e-6, 'amortised over the remaining term through the EIR (5.4.3)');
        (new EirRevenueService())->run('C-1', '2025-02');
        $this->assertEqualsWithDelta($payment / 970 - 1, DB::table('eir_amortisation')->where('reporting_period', '2025-02')->value('eir_annual'), 1e-6);
    }

    public function test_a_later_month_is_refused_while_a_confirmed_event_before_it_is_not_booked(): void
    {
        $this->confirmed('MODIFICATION', 850 * 1.126825); // derecognised, no fair value: January is blocked

        $this->assertSame('BLOCKED', (new EirRevenueService())->run('C-1', '2025-01')['status']);
        $feb = (new EirRevenueService())->run('C-1', '2025-02');
        $this->assertSame('BLOCKED', $feb['status']);
        $this->assertStringContainsString('not booked yet', $feb['error']);
        $this->assertSame(0, DB::table('eir_amortisation')->count());
    }

    public function test_a_reversed_event_books_nothing(): void
    {
        $id = $this->confirmed('MODIFICATION', 980 * 1.126825);
        DB::table('contract_modifications')->where('id', $id)->update(['status' => 'REVERSED']);

        (new EirRevenueService())->run('C-1', '2025-01');
        $row = DB::table('eir_amortisation')->first();
        $this->assertEqualsWithDelta(1000, $row->closing_gross, 0.01);
        $this->assertEqualsWithDelta(0, $row->modification_adjustment, 0.001);
    }
}
