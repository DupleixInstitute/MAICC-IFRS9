<?php

namespace Tests\Feature\Eir;

use App\Models\EirRatePeriod;
use App\Models\RateResetEvent;
use App\Services\Eir\ResetDetectionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\Feature\Eir\Concerns\CreatesResetSchema;
use Tests\TestCase;

/**
 * Phase P5, step 2, on JAT Group (104430000087): MAIIC Industrial, Interest
 * Policy P, first disbursement 23 Jul 2025 at 33.30 percent with the prime rate
 * at 25.30, so the spread is 8.00. The series is the repaired prime-rate file
 * from 2025 on. E-Banker's loan books and EMI chart hold JAT at exactly prime
 * + 8.00 at every one of these changes. Private in-memory sqlite schema.
 */
class ResetDetectionServiceTest extends TestCase
{
    use CreatesGovernanceSchema;
    use CreatesResetSchema;

    protected $seed = false;

    private const JAT = '104430000087';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();

        $this->createResetSchema();
        $this->seedJatPrimeRates();
        $this->loan(self::JAT, ['interest_policy' => 'P', 'portfolio' => 'MAIIC', 'product_type' => 'MAIIC Industrial Loans']);
    }

    private function loan(string $id, array $attributes = []): void
    {
        DB::table('contract_eir')->insert($attributes + [
            'contract_id' => $id, 'sub_account_no' => '1', 'origination_date' => '2025-07-23',
            'maturity_date' => '2028-09-30', 'contractual_rate' => 0.333,
        ]);
    }

    private function setting(string $key, string $value): void
    {
        DB::table('governance_settings')->where('key', $key)->update(['value' => $value]);
    }

    private function detect(?string $contract = self::JAT, string $asOf = '2026-09-30'): array
    {
        return app(ResetDetectionService::class)->detect($contract, CarbonImmutable::parse($asOf));
    }

    public function test_jat_resets_at_every_prime_rate_change_on_its_locked_spread(): void
    {
        $result = $this->detect();
        $jat = $result['details'][self::JAT];

        $this->assertSame(ResetDetectionService::STATUS_FLOATING, $jat['status']);
        $this->assertSame('INTEREST_POLICY', $jat['rate_behaviour_source']);
        $this->assertSame('LIVE_AT_DRAWDOWN', $jat['spread_basis']);
        $this->assertEqualsWithDelta(8.00, $jat['spread'], 1e-9, '33.30 at origination less the prime rate of 25.30');

        // Eleven changes after 23 Jul 2025; the restatement of 25.30 on 3 Sep is not one.
        $this->assertCount(11, $jat['resets']);
        $this->assertSame(11, RateResetEvent::count());
        $this->assertSame('2025-10-01', $jat['resets'][0]['reset_date']);
        $this->assertEqualsWithDelta(33.40, $jat['resets'][0]['new_rate_pct'], 1e-9);

        // The rate in force at each instalment matches E-Banker's EMI chart.
        $periods = EirRatePeriod::orderBy('effective_from')->get();
        $this->assertCount(12, $periods);
        $rateOn = fn (string $date) => round($periods->first(fn ($p) => $p->effective_from->toDateString() <= $date
            && ($p->effective_to === null || $p->effective_to->toDateString() >= $date))->contractual_rate * 100, 2);
        foreach (['2025-07-23' => 33.30, '2025-10-23' => 33.40, '2026-01-23' => 33.30, '2026-02-23' => 32.70,
                  '2026-03-23' => 30.40, '2026-04-23' => 28.80, '2026-05-23' => 28.60, '2026-06-23' => 28.40,
                  '2026-07-23' => 28.50, '2026-08-23' => 28.80, '2026-09-23' => 29.20] as $date => $chart) {
            $this->assertSame($chart, $rateOn($date), "rate in force on {$date}");
        }

        // Contiguous, the last open, every one waiting for its EIR.
        $this->assertSame('2025-09-30', $periods[0]->effective_to->toDateString());
        $this->assertNull($periods->last()->effective_to);
        $this->assertSame([EirRatePeriod::STATUS_PENDING_EIR], $periods->pluck('status')->unique()->values()->all());
        $this->assertNotNull($periods[1]->rate_reset_event_id);
        $this->assertNull($periods[0]->rate_reset_event_id);
    }

    public function test_two_changes_in_one_month_are_one_reset_when_applied_at_month_end(): void
    {
        $this->setting('plr_mid_period', 'From the next month-end');

        $resets = collect($this->detect()['details'][self::JAT]['resets'])->keyBy('reset_date');

        $this->assertCount(10, $resets, 'the 5 and 9 March 2026 changes land on 31 March together');
        $march = $resets['2026-03-31'];
        $this->assertSame(24.7, $march['old_reference']);
        $this->assertSame(22.4, $march['new_reference']);
        $this->assertSame('2026-03-09', $march['plr_effective_date']);
    }

    public function test_a_fixed_loan_gets_one_period_and_no_resets(): void
    {
        $this->loan('104460000098', ['portfolio' => 'FInES', 'product_type' => 'FInES Industrial Loans', 'contractual_rate' => 0.10]);

        $fixed = $this->detect('104460000098')['details']['104460000098'];

        $this->assertSame(ResetDetectionService::STATUS_FIXED, $fixed['status']);
        $this->assertSame('PRODUCT_FAMILY', $fixed['rate_behaviour_source'], 'no Interest Policy supplied, so the product family decides');
        $this->assertSame(0, RateResetEvent::where('contract_id', '104460000098')->count());
        $period = EirRatePeriod::where('contract_id', '104460000098')->sole();
        $this->assertEqualsWithDelta(0.10, $period->contractual_rate, 1e-9);
        $this->assertNull($period->effective_to);
    }

    public function test_a_supplied_spread_that_is_missing_holds_the_loan(): void
    {
        $this->setting('margin_basis', 'SUPPLIED');

        $jat = $this->detect()['details'][self::JAT];

        $this->assertSame(ResetDetectionService::STATUS_BLOCKED, $jat['status']);
        $this->assertStringStartsWith('SPREAD_NOT_SUPPLIED', $jat['reason']);
        $this->assertSame(0, EirRatePeriod::count());
    }

    public function test_rerunning_is_safe_and_never_moves_a_period_already_solved(): void
    {
        $this->detect();
        $this->detect();
        $this->assertSame(11, RateResetEvent::count());
        $this->assertSame(12, EirRatePeriod::count());

        // The 1 Oct 2025 period has had its EIR solved; then the series is corrected.
        EirRatePeriod::whereDate('effective_from', '2025-10-01')->update(['status' => EirRatePeriod::STATUS_SOLVED]);
        RateResetEvent::whereDate('reset_date', '2025-10-01')->update(['status' => RateResetEvent::STATUS_SOLVED]);
        DB::table('reference_rate_series')->where('effective_date', '2025-10-01')->update(['rate' => 25.5]);

        $jat = $this->detect()['details'][self::JAT];

        $solved = EirRatePeriod::whereDate('effective_from', '2025-10-01')->sole();
        $this->assertEqualsWithDelta(0.334, $solved->contractual_rate, 1e-9, 'a solved period is not rewritten');
        $this->assertNotEmpty(array_filter($jat['kept'], fn ($n) => str_contains($n, '2025-10-01')), 'the disagreement is reported');
    }

    public function test_changes_after_the_as_of_date_are_not_applied(): void
    {
        $resets = $this->detect(self::JAT, '2026-03-31')['details'][self::JAT]['resets'];

        $this->assertSame('2026-03-09', end($resets)['reset_date']);
        $this->assertCount(5, $resets);
    }
}
