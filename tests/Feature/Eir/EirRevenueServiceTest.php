<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\EirRevenueService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EirRevenueServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->double('eir_effective_annual')->nullable(); $t->json('input_snapshot')->nullable(); $t->double('opening_amortised_cost')->nullable(); $t->string('origination_date')->nullable(); $t->string('source_day_count_basis')->nullable(); $t->string('locked_at')->nullable(); $t->timestamps(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period', 7); $t->double('opening_gross'); $t->double('interest_accrued'); $t->string('interest_basis'); $t->double('unwind_amount'); $t->double('cash_received'); $t->string('cash_source'); $t->double('modification_gain_loss'); $t->double('closing_gross'); $t->double('ecl_allowance'); $t->timestamps(); $t->unique(['contract_id', 'reporting_period']); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->integer('calculated_ifrs9_stage')->nullable(); $t->integer('ifrs9stage_post_qualitative')->nullable(); $t->integer('ifrs9_stage')->nullable(); $t->double('expected_loss_provision')->default(0); });
        Schema::create('eir_actual_transactions', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('transaction_date'); $t->string('transaction_type', 50)->nullable(); $t->double('principal_component')->default(0); $t->double('interest_component')->default(0); $t->double('fee_component')->default(0); $t->double('total_amount')->default(0); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->smallInteger('schedule_version')->default(1); $t->string('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); });
        Schema::create('eir_amortisation_history', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period', 7); $t->double('opening_gross'); $t->double('interest_accrued'); $t->string('interest_basis'); $t->double('unwind_amount'); $t->double('cash_received'); $t->string('cash_source'); $t->double('modification_gain_loss'); $t->double('closing_gross'); $t->double('ecl_allowance'); $t->string('originally_created_at')->nullable(); $t->string('superseded_at')->nullable(); $t->integer('superseded_by')->nullable(); $t->string('supersession_reason', 500); $t->timestamps(); });
    }

    private function actual(string $id, string $date, string $type, float $total): void
    {
        DB::table('eir_actual_transactions')->insert(['contract_id' => $id, 'transaction_date' => $date,
            'transaction_type' => $type, 'total_amount' => $total]);
    }

    private function scheduled(string $id, string $dueDate, float $principal, float $interest = 0): void
    {
        DB::table('contract_cashflow_schedule')->insert(['contract_id' => $id, 'schedule_version' => 1,
            'due_date' => $dueDate, 'principal_due' => $principal, 'interest_due' => $interest]);
    }

    private function seedLocked(string $id = 'C-1', float $annualEir = 0.126825): void
    {
        DB::table('contract_eir')->insert(['contract_id' => $id, 'eir_effective_annual' => $annualEir,
            'input_snapshot' => json_encode(['initial_net_investment' => 1000]), 'locked_at' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function loan(string $id, string $period, int $stage, float $allowance = 0): void
    {
        DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => $period,
            'calculated_ifrs9_stage' => $stage, 'expected_loss_provision' => $allowance]);
    }

    public function test_stage_one_uses_monthly_equivalent_eir_and_rolls_forward(): void
    {
        $this->seedLocked(); // 12.6825% effective annual is approximately 1% monthly.
        $this->loan('C-1', '202501', 1);
        $this->actual('C-1', '2025-01-20', 'Principal+Interest', 100);

        $result = (new EirRevenueService())->run('C-1', '2025-01');
        $row = DB::table('eir_amortisation')->first();

        $this->assertSame('CREATED', $result['status']);
        $this->assertSame('GROSS', $row->interest_basis);
        $this->assertEqualsWithDelta(10, $row->interest_accrued, 0.01);
        $this->assertEqualsWithDelta(910, $row->closing_gross, 0.01);
        $this->assertSame('IMPORTED', $row->cash_source);
    }

    public function test_disbursements_are_never_netted_off_as_cash_received(): void
    {
        $this->seedLocked();
        $this->loan('C-1', '2025-01', 1);
        $this->actual('C-1', '2025-01-05', 'Disbursement', 5_000);
        $this->actual('C-1', '2025-01-20', 'Interest', 100);

        $result = (new EirRevenueService())->run('C-1', '2025-01');
        $row = DB::table('eir_amortisation')->first();

        // Counting the advance would drive the balance to zero on day one.
        $this->assertEqualsWithDelta(100, $row->cash_received, 0.01);
        $this->assertEqualsWithDelta(910, $row->closing_gross, 0.01);
        $this->assertEqualsWithDelta(0, $result['unclassified_cash'], 0.01);
    }

    public function test_unrecognised_transaction_types_are_excluded_and_reported(): void
    {
        $this->seedLocked();
        $this->loan('C-1', '2025-01', 1);
        $this->actual('C-1', '2025-01-20', 'Interest', 100);
        $this->actual('C-1', '2025-01-31', 'Other/Adjustment', 250);

        $result = (new EirRevenueService())->run('C-1', '2025-01');
        $row = DB::table('eir_amortisation')->first();

        $this->assertEqualsWithDelta(100, $row->cash_received, 0.01);
        $this->assertEqualsWithDelta(250, $result['unclassified_cash'], 0.01);
    }

    public function test_schedule_is_the_fallback_only_where_no_actuals_cover_the_period(): void
    {
        $this->seedLocked();
        $this->loan('C-1', '2025-01', 1);
        $this->scheduled('C-1', '2025-01-31', 90, 10);

        $result = (new EirRevenueService())->run('C-1', '2025-01');
        $row = DB::table('eir_amortisation')->first();

        $this->assertSame('DERIVED', $row->cash_source);
        $this->assertEqualsWithDelta(100, $row->cash_received, 0.01);
        $this->assertSame('DERIVED', $result['cash_source']);
    }

    public function test_a_silent_month_inside_the_actuals_window_is_zero_not_the_schedule(): void
    {
        $this->seedLocked();
        $this->loan('C-1', '2025-02', 1);
        // The feed covers January and March; the customer paid nothing in February.
        $this->actual('C-1', '2025-01-20', 'Interest', 100);
        $this->actual('C-1', '2025-03-20', 'Interest', 100);
        $this->scheduled('C-1', '2025-02-28', 90, 10);

        $row = (new EirRevenueService())->run('C-1', '2025-02');

        $this->assertSame('IMPORTED', $row['cash_source']);
        $this->assertEqualsWithDelta(0, DB::table('eir_amortisation')->value('cash_received'), 0.01);
    }

    public function test_stage_three_recognises_net_interest_and_discloses_unwind(): void
    {
        $this->seedLocked();
        $this->loan('C-1', '2025-01', 3, 200);
        $result = (new EirRevenueService())->run('C-1', '202501');
        $row = DB::table('eir_amortisation')->first();

        $this->assertSame('CREATED', $result['status']);
        $this->assertSame('NET', $row->interest_basis);
        $this->assertEqualsWithDelta(8, $row->interest_accrued, 0.01);
        $this->assertEqualsWithDelta(2, $row->unwind_amount, 0.01);
        $this->assertEqualsWithDelta(1010, $row->closing_gross, 0.01);
    }

    public function test_next_period_uses_prior_closing_and_rerun_is_idempotent(): void
    {
        $this->seedLocked();
        $this->loan('C-1', '2025-01', 1); $this->loan('C-1', '2025-02', 1);
        $service = new EirRevenueService();
        $service->run('C-1', '2025-01');
        $second = $service->run('C-1', '2025-02');
        $again = $service->run('C-1', '2025-02');

        $this->assertSame('CREATED', $second['status']);
        $this->assertSame('UNCHANGED', $again['status']);
        $this->assertSame(2, DB::table('eir_amortisation')->count());
        $this->assertEqualsWithDelta(1010, DB::table('eir_amortisation')->where('reporting_period', '2025-02')->value('opening_gross'), 0.01);
    }

    public function test_late_first_run_uses_pv_of_only_remaining_cashflows(): void
    {
        DB::table('contract_eir')->insert(['contract_id' => 'LATE', 'eir_effective_annual' => .12, 'origination_date' => '2024-01-01',
            'source_day_count_basis' => 'ACT/365', 'input_snapshot' => json_encode(['initial_net_investment' => 1000, 'cash_flows' => [
                ['due_date' => '2024-06-30', 'amount' => 600], ['due_date' => '2026-01-31', 'amount' => 600],
            ]]), 'locked_at' => '2024-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $this->loan('LATE', '2025-01', 1);

        (new EirRevenueService())->run('LATE', '2025-01');
        $opening = (float) DB::table('eir_amortisation')->where('contract_id', 'LATE')->value('opening_gross');

        $this->assertLessThan(600, $opening);
        $this->assertGreaterThan(500, $opening);
    }

    public function test_recalculation_archives_the_replaced_row_with_its_reason(): void
    {
        $this->seedLocked();
        $this->loan('C-1', '2025-01', 1);
        $service = new EirRevenueService();
        $service->run('C-1', '2025-01'); // No actuals: cash is derived as zero.

        $this->actual('C-1', '2025-01-20', 'Principal+Interest', 100); // Late delivery.
        $result = $service->run('C-1', '2025-01', true, 7, 'Actuals delivered after month end.');

        $this->assertSame('RECALCULATED', $result['status']);
        $this->assertSame(1, $result['superseded']);
        $this->assertEqualsWithDelta(910, DB::table('eir_amortisation')->value('closing_gross'), 0.01);
        $this->assertSame(1, DB::table('eir_amortisation')->count());

        $archived = DB::table('eir_amortisation_history')->first();
        $this->assertEqualsWithDelta(1010, $archived->closing_gross, 0.01);
        $this->assertEqualsWithDelta(0, $archived->cash_received, 0.01);
        $this->assertSame(7, (int) $archived->superseded_by);
        $this->assertSame('Actuals delivered after month end.', $archived->supersession_reason);
    }

    public function test_recalculating_a_period_voids_the_later_periods_it_fed(): void
    {
        $this->seedLocked();
        foreach (['2025-01', '2025-02', '2025-03'] as $p) $this->loan('C-1', $p, 1);
        $service = new EirRevenueService();
        foreach (['2025-01', '2025-02', '2025-03'] as $p) $service->run('C-1', $p);
        $this->assertSame(3, DB::table('eir_amortisation')->count());

        $result = $service->run('C-1', '2025-02', true, 7, 'Stage restated.');

        // February replaced and March voided; January is earlier and untouched.
        $this->assertSame(2, $result['superseded']);
        $this->assertSame(['2025-01', '2025-02'], DB::table('eir_amortisation')->orderBy('reporting_period')->pluck('reporting_period')->all());
        $this->assertSame(['2025-02', '2025-03'], DB::table('eir_amortisation_history')->orderBy('reporting_period')->pluck('reporting_period')->all());
    }

    public function test_recalculation_without_a_reason_is_refused(): void
    {
        $this->seedLocked();
        $this->loan('C-1', '2025-01', 1);
        $service = new EirRevenueService();
        $service->run('C-1', '2025-01');

        $result = $service->run('C-1', '2025-01', true, 7, '   ');

        $this->assertSame('BLOCKED', $result['status']);
        $this->assertStringContainsString('reason', $result['error']);
        $this->assertSame(0, DB::table('eir_amortisation_history')->count());
        $this->assertSame(1, DB::table('eir_amortisation')->count());
    }

    public function test_unlocked_contract_and_missing_stage_are_blocked(): void
    {
        DB::table('contract_eir')->insert(['contract_id' => 'OPEN', 'eir_effective_annual' => .12, 'input_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('BLOCKED', (new EirRevenueService())->run('OPEN', '2025-01')['status']);

        $this->seedLocked('NO-STAGE'); $this->loan('NO-STAGE', '2025-01', 0);
        $result = (new EirRevenueService())->run('NO-STAGE', '2025-01');
        $this->assertSame('BLOCKED', $result['status']);
        $this->assertStringContainsString('stage', $result['error']);
    }
}
