<?php

namespace Tests\Feature\Ecl;

use App\Services\Ecl\EclDiscountRateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EclDiscountRateServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->double('eir_effective_annual')->nullable(); $t->double('contractual_rate')->nullable(); $t->json('input_snapshot')->nullable(); $t->string('rate_type')->default('FIXED'); $t->string('locked_at')->nullable(); $t->timestamps(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period', 7); $t->double('eir_annual')->nullable(); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->double('interest_rate')->nullable(); });
    }

    private function lockedEir(string $id, float $eir, string $rateType = 'FIXED', ?float $contractual = null): void
    {
        DB::table('contract_eir')->insert(['contract_id' => $id, 'eir_effective_annual' => $eir, 'contractual_rate' => $contractual,
            'rate_type' => $rateType, 'locked_at' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function pairs(array $ids, string $period = '2025-10'): array
    {
        return array_map(fn ($id) => ['contract_id' => $id, 'period' => $period], $ids);
    }

    public function test_a_locked_fixed_rate_eir_is_applied_as_the_original(): void
    {
        $this->lockedEir('C-1', 0.3253);

        $result = (new EclDiscountRateService())->resolve($this->pairs(['C-1']), EclDiscountRateService::SOURCE_EIR);

        $this->assertSame([], $result['unresolved']);
        $this->assertEqualsWithDelta(0.3253, $result['rates']['C-1|2025-10']['rate'], 0.00001);
        $this->assertSame('EIR_ORIGINAL', $result['rates']['C-1|2025-10']['applied_source']);
    }

    /**
     * B5.5.44: a floating facility is discounted at its current EIR, as TimePhasedEclService
     * discounts the allowance (EirRevenueService::currentFloatingEir, decision D38): never
     * the original EIR labelled a proxy (as until 14 October 2026).
     */
    public function test_a_floating_facility_is_discounted_at_its_current_eir_not_the_original(): void
    {
        // locked EIR 10 percent on a schedule built at 9 percent, charged 12 percent in October 2025
        $this->lockedEir('C-F', 0.10, 'FLOATING', 0.09);
        DB::table('loan_books')->insert(['contract_id' => 'C-F', 'reporting_period' => '2025-10', 'interest_rate' => 12.0]);

        $result = (new EclDiscountRateService())->resolve($this->pairs(['C-F']), EclDiscountRateService::SOURCE_EIR);

        $this->assertSame([], $result['unresolved']);
        $this->assertSame('EIR_CURRENT_FLOATING', $result['rates']['C-F|2025-10']['applied_source']);
        $current = pow(1 + (12 * (pow(1.10, 1 / 12) - 1) + (0.12 - 0.09)) / 12, 12) - 1;
        $this->assertEqualsWithDelta($current, $result['rates']['C-F|2025-10']['rate'], 1e-9);

        // the EIR the roll-forward accrued at for the month is the current EIR, as the allowance reads it
        DB::table('eir_amortisation')->insert(['contract_id' => 'C-F', 'reporting_period' => '2025-10', 'eir_annual' => 0.1325]);
        $again = (new EclDiscountRateService())->resolve($this->pairs(['C-F']), EclDiscountRateService::SOURCE_EIR);
        $this->assertEqualsWithDelta(0.1325, $again['rates']['C-F|2025-10']['rate'], 1e-12);
    }

    public function test_a_floating_facility_with_no_current_eir_is_unresolved_never_discounted_at_the_original(): void
    {
        // no loan-book rate charged for the month
        $this->lockedEir('C-F', 0.10, 'FLOATING', 0.09);
        $result = (new EclDiscountRateService())->resolve($this->pairs(['C-F']), EclDiscountRateService::SOURCE_EIR);
        $this->assertSame([], $result['rates']);
        $this->assertStringContainsString('B5.5.44', $result['unresolved']['C-F|2025-10']);

        // no rate the locked schedule was built at
        $this->lockedEir('C-G', 0.10, 'FLOATING');
        DB::table('loan_books')->insert(['contract_id' => 'C-G', 'reporting_period' => '2025-10', 'interest_rate' => 12.0]);
        $result = (new EclDiscountRateService())->resolve($this->pairs(['C-G']), EclDiscountRateService::SOURCE_EIR);
        $this->assertSame([], $result['rates']);
        $this->assertStringContainsString('original EIR is not used', $result['unresolved']['C-G|2025-10']);
        $this->assertFalse(defined(EclDiscountRateService::class . '::APPLIED_EIR_FLOATING_PROXY'), 'the proxy label is retired');
    }

    public function test_an_unlocked_or_missing_eir_is_unresolved_never_defaulted(): void
    {
        // Calculated but never approved, so not an audited basis for an allowance.
        DB::table('contract_eir')->insert(['contract_id' => 'C-OPEN', 'eir_effective_annual' => 0.30,
            'rate_type' => 'FIXED', 'created_at' => now(), 'updated_at' => now()]);

        $result = (new EclDiscountRateService())->resolve(
            $this->pairs(['C-OPEN', 'C-ABSENT']), EclDiscountRateService::SOURCE_EIR
        );

        $this->assertSame([], $result['rates']);
        $this->assertCount(2, $result['unresolved']);
        $this->assertStringContainsString('locked original EIR', $result['unresolved']['C-OPEN|2025-10']);
    }

    public function test_loan_book_percentages_are_converted_to_decimals(): void
    {
        // The tape stores 34.19 meaning 34.19%; used raw it discounts at 3,419%.
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-10', 'interest_rate' => 34.19]);

        $result = (new EclDiscountRateService())->resolve($this->pairs(['C-1']), EclDiscountRateService::SOURCE_LOAN_BOOK);

        $this->assertEqualsWithDelta(0.3419, $result['rates']['C-1|2025-10']['rate'], 0.00001);
        $this->assertSame('LOAN_BOOK_CONTRACTUAL', $result['rates']['C-1|2025-10']['applied_source']);
    }

    public function test_a_missing_or_zero_loan_book_rate_is_unresolved_not_ten_percent(): void
    {
        DB::table('loan_books')->insert(['contract_id' => 'C-ZERO', 'reporting_period' => '2025-10', 'interest_rate' => 0]);

        $result = (new EclDiscountRateService())->resolve(
            $this->pairs(['C-ZERO', 'C-ABSENT']), EclDiscountRateService::SOURCE_LOAN_BOOK
        );

        $this->assertSame([], $result['rates']);
        $this->assertCount(2, $result['unresolved']);
    }

    public function test_manual_rates_apply_to_every_pair_and_require_a_value(): void
    {
        $service = new EclDiscountRateService();

        $supplied = $service->resolve($this->pairs(['C-1', 'C-2']), EclDiscountRateService::SOURCE_MANUAL, 0.125);
        $this->assertCount(2, $supplied['rates']);
        $this->assertEqualsWithDelta(0.125, $supplied['rates']['C-1|2025-10']['rate'], 0.00001);

        $missing = $service->resolve($this->pairs(['C-1']), EclDiscountRateService::SOURCE_MANUAL, null);
        $this->assertSame([], $missing['rates']);
        $this->assertCount(1, $missing['unresolved']);
    }

    public function test_loan_book_rates_are_matched_period_by_period(): void
    {
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-09', 'interest_rate' => 20.0]);
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-10', 'interest_rate' => 30.0]);

        $result = (new EclDiscountRateService())->resolve([
            ['contract_id' => 'C-1', 'period' => '2025-09'],
            ['contract_id' => 'C-1', 'period' => '2025-10-31'], // Full dates normalise.
        ], EclDiscountRateService::SOURCE_LOAN_BOOK);

        $this->assertEqualsWithDelta(0.20, $result['rates']['C-1|2025-09']['rate'], 0.00001);
        $this->assertEqualsWithDelta(0.30, $result['rates']['C-1|2025-10']['rate'], 0.00001);
    }

    public function test_an_unknown_source_resolves_nothing(): void
    {
        $this->lockedEir('C-1', 0.30);

        $result = (new EclDiscountRateService())->resolve($this->pairs(['C-1']), 'treasury_curve');

        $this->assertSame([], $result['rates']);
        $this->assertCount(1, $result['unresolved']);
    }
}
