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
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->double('eir_effective_annual')->nullable(); $t->string('rate_type')->default('FIXED'); $t->string('locked_at')->nullable(); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->double('interest_rate')->nullable(); });
    }

    private function lockedEir(string $id, float $eir, string $rateType = 'FIXED'): void
    {
        DB::table('contract_eir')->insert(['contract_id' => $id, 'eir_effective_annual' => $eir,
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

    public function test_a_floating_facility_is_labelled_a_proxy_not_the_current_rate(): void
    {
        $this->lockedEir('C-F', 0.1047, 'FLOATING');

        $result = (new EclDiscountRateService())->resolve($this->pairs(['C-F']), EclDiscountRateService::SOURCE_EIR);

        $this->assertSame('EIR_ORIGINAL_FLOATING_PROXY', $result['rates']['C-F|2025-10']['applied_source']);
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
