<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\EirGlReconciliationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EirGlReconciliationServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('portfolio')->nullable(); $t->double('drawn_amount')->default(0); $t->double('contractual_rate')->nullable(); $t->double('eir_effective_annual')->nullable(); $t->timestamps(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period', 7); $t->double('opening_gross'); $t->double('interest_accrued'); $t->string('interest_basis')->default('GROSS'); $t->double('unwind_amount')->default(0); $t->double('cash_received')->default(0); $t->string('cash_source')->default('IMPORTED'); $t->double('modification_gain_loss')->default(0); $t->double('closing_gross')->default(0); $t->double('ecl_allowance')->default(0); $t->timestamps(); });
        Schema::create('gl_interest_postings', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('gl_account_code')->nullable(); $t->integer('period_year'); $t->integer('period_month'); $t->double('interest_income_posted'); $t->timestamps(); });
    }

    /** A facility with no integral fees: the solved EIR is the contractual rate compounded. */
    private function contract(string $id, float $drawn, float $rate, string $portfolio = 'MAIIC'): void
    {
        DB::table('contract_eir')->insert(['contract_id' => $id, 'portfolio' => $portfolio, 'drawn_amount' => $drawn,
            'contractual_rate' => $rate, 'eir_effective_annual' => pow(1 + $rate / 12, 12) - 1,
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function accrual(string $id, string $period, float $opening, float $interest): void
    {
        DB::table('eir_amortisation')->insert(['contract_id' => $id, 'reporting_period' => $period,
            'opening_gross' => $opening, 'interest_accrued' => $interest, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function posting(string $id, int $year, int $month, float $posted): void
    {
        DB::table('gl_interest_postings')->insert(['contract_id' => $id, 'period_year' => $year,
            'period_month' => $month, 'interest_income_posted' => $posted, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_two_effects_reconcile_the_variance_exactly(): void
    {
        // Ledger accrues flat on the original 1,000,000; the facility has
        // amortised down to 700,000.
        $this->contract('C-1', 1_000_000, 0.24);
        $monthly = 0.24 / 12;
        $this->posting('C-1', 2025, 10, 1_000_000 * $monthly);
        $this->accrual('C-1', '2025-10', 700_000, 700_000 * $monthly);

        $result = (new EirGlReconciliationService())->forPeriod('2025-10');
        $row = $result['rows'][0];

        $this->assertSame('VARIANCE', $row['status']);
        $this->assertEqualsWithDelta(-6_000, $row['variance'], 0.01);
        $this->assertEqualsWithDelta(-6_000, $row['base_effect'], 0.01);
        $this->assertEqualsWithDelta(0, $row['rate_effect'], 0.01);
        $this->assertEqualsWithDelta(0, $row['unexplained'], 0.01);
        $this->assertEqualsWithDelta(0, $row['base_effect'] + $row['rate_effect'] - $row['variance'], 0.01);
    }

    public function test_a_fee_bearing_eir_shows_the_uplift_as_rate_effect(): void
    {
        // Same balance on both sides, so only the yield uplift can move it.
        DB::table('contract_eir')->insert(['contract_id' => 'C-2', 'portfolio' => 'MAIIC', 'drawn_amount' => 1_000_000,
            'contractual_rate' => 0.24, 'eir_effective_annual' => pow(1 + 0.03, 12) - 1, // 3% monthly, fees included
            'created_at' => now(), 'updated_at' => now()]);
        $this->posting('C-2', 2025, 10, 1_000_000 * 0.02);
        $this->accrual('C-2', '2025-10', 1_000_000, 1_000_000 * 0.03);

        $row = (new EirGlReconciliationService())->forPeriod('2025-10')['rows'][0];

        $this->assertEqualsWithDelta(0, $row['base_effect'], 0.01);
        $this->assertEqualsWithDelta(10_000, $row['rate_effect'], 0.01);
        $this->assertEqualsWithDelta(10_000, $row['variance'], 0.01);
    }

    public function test_postings_without_a_counterpart_stay_out_of_the_bridge(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2025, 10, 20_000);
        $this->accrual('C-1', '2025-10', 1_000_000, 20_000);
        $this->contract('C-GAP', 500_000, 0.24);
        $this->posting('C-GAP', 2025, 10, 10_000); // Never calculated.

        $result = (new EirGlReconciliationService())->forPeriod('2025-10');
        $bridge = $result['bridge'];

        $this->assertSame(1, $result['summary']['not_calculated']);
        $this->assertEqualsWithDelta(30_000, $bridge['gl_total'], 0.01);
        $this->assertEqualsWithDelta(10_000, $bridge['gl_without_counterpart'], 0.01);
        $this->assertEqualsWithDelta(20_000, $bridge['gl_matched'], 0.01);
        // The uncovered posting must not appear as a measurement difference.
        $this->assertEqualsWithDelta(0, $bridge['net_variance'], 0.01);
        $this->assertEqualsWithDelta(
            $bridge['gl_matched'] + $bridge['base_effect'] + $bridge['rate_effect'] + $bridge['unexplained'],
            $bridge['eir_total'], 0.01
        );
    }

    public function test_small_differences_fall_inside_the_tolerance_band(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2025, 10, 20_000);
        $this->accrual('C-1', '2025-10', 1_000_000, 20_050); // 0.25%

        $rows = (new EirGlReconciliationService())->forPeriod('2025-10')['rows'];

        $this->assertSame('WITHIN_TOLERANCE', $rows[0]['status']);
    }

    public function test_periods_are_listed_newest_first_and_default_to_the_latest(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2025, 9, 20_000);
        $this->posting('C-1', 2025, 10, 20_000);
        $service = new EirGlReconciliationService();

        $this->assertSame(['2025-10', '2025-09'], $service->availablePeriods());
        $this->assertSame('2025-10', $service->forPeriod()['period']);
    }

    public function test_portfolio_filter_narrows_the_bridge(): void
    {
        $this->contract('C-1', 1_000_000, 0.24, 'MAIIC');
        $this->posting('C-1', 2025, 10, 20_000);
        $this->accrual('C-1', '2025-10', 1_000_000, 20_000);
        $this->contract('C-2', 1_000_000, 0.12, 'FInES');
        $this->posting('C-2', 2025, 10, 10_000);
        $this->accrual('C-2', '2025-10', 1_000_000, 10_000);

        $result = (new EirGlReconciliationService())->forPeriod('2025-10', 'FInES');

        $this->assertCount(1, $result['rows']);
        $this->assertEqualsWithDelta(10_000, $result['bridge']['gl_total'], 0.01);
    }
}
