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
 * Policy P (rate type FLOATING), first disbursement 23 Jul 2025 at 33.30 percent with the prime rate
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
        $this->loan(self::JAT, ['rate_type' => 'FLOATING', 'interest_policy' => 'P', 'portfolio' => 'MAIIC', 'product_type' => 'MAIIC Industrial Loans']);
        // each change on its own Reserve Bank date, as JAT's EMI chart regenerates; the default
        // (the whole month at the new rate) has its own test below
        $this->setting('plr_mid_period', 'Pro rata from the effective date');
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
        $this->assertSame('RATE_TYPE', $jat['rate_behaviour_source'], 'the test the EIR in force applies (D38)');
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

    public function test_on_the_default_the_whole_month_is_at_the_new_rate_and_two_changes_in_it_are_one_reset(): void
    {
        $this->setting('plr_mid_period', 'Whole month at the month-end rate (E-Banker)');

        $resets = collect($this->detect()['details'][self::JAT]['resets'])->keyBy('reset_date');

        $this->assertCount(10, $resets, 'the 5 and 9 March 2026 changes are one reset from 1 March (spec v4 section 3.1)');
        $march = $resets['2026-03-01'];
        $this->assertSame(24.7, $march['old_reference']);
        $this->assertSame(22.4, $march['new_reference']);
        $this->assertSame('2026-03-09', $march['plr_effective_date']);
        $this->assertArrayHasKey('2026-02-01', $resets->all(), 'the 4 February change charges all of February');

        // the rows are the register's too: one PLR_RESET each, awaiting a person's classification
        $row = RateResetEvent::whereDate('reset_date', '2026-03-01')->sole();
        $this->assertSame(['PLR_RESET', '2026-03', 'IN_CONTRACT', 'DETECTED', 'DETECTED', 'PLR|' . self::JAT . '|2026-03-01'],
            [$row->event_type, $row->reporting_period, $row->suggested_classification, $row->status, $row->classification_status, $row->detection_key]);
    }

    public function test_a_loan_with_no_rate_type_or_an_unknown_timing_rule_is_held_not_guessed(): void
    {
        $this->loan('104420000001', ['rate_type' => '', 'interest_policy' => 'P', 'portfolio' => 'MAIIC', 'product_type' => 'MAIIC Industrial Loans']);
        $held = $this->detect('104420000001')['details']['104420000001'];
        $this->assertSame(ResetDetectionService::STATUS_BLOCKED, $held['status']);
        $this->assertStringStartsWith('NO_RATE_TYPE', $held['reason'], 'never the Interest Policy or the product family in its place');

        $this->setting('plr_mid_period', 'Some other rule');
        $jat = $this->detect()['details'][self::JAT];
        $this->assertSame(ResetDetectionService::STATUS_BLOCKED, $jat['status']);
        $this->assertStringStartsWith('UNKNOWN_PLR_TIMING', $jat['reason']);
        $this->assertSame(0, RateResetEvent::count());
    }

    public function test_a_classified_reset_is_kept_and_a_register_held_move_is_taken_over(): void
    {
        $this->detect();
        // a person proposed the 1 Oct 2025 reset; the series is then corrected
        RateResetEvent::whereDate('reset_date', '2025-10-01')->update(['classification_status' => 'PROPOSED', 'classification' => 'IN_CONTRACT']);
        // the register recorded the 4 Feb 2026 move while the pipeline could not date it
        $feb = RateResetEvent::whereDate('reset_date', '2026-02-04')->sole();
        $feb->forceFill(['status' => 'BLOCKED', 'note' => 'Held from the reset pipeline', 'detection_key' => 'MOVE|' . self::JAT . '|2026-02'])->save();
        DB::table('reference_rate_series')->where('effective_date', '2025-10-01')->update(['rate' => 25.5]);

        $jat = $this->detect()['details'][self::JAT];

        $oct = RateResetEvent::whereDate('reset_date', '2025-10-01')->sole();
        $this->assertEqualsWithDelta(25.4, $oct->new_reference_rate, 1e-9, 'a classified reset is not rewritten');
        $this->assertNotEmpty(array_filter($jat['kept'], fn ($n) => str_contains($n, 'classified PROPOSED')));
        $feb = RateResetEvent::whereDate('reset_date', '2026-02-04')->sole();
        $this->assertSame(['DETECTED', 'PLR|' . self::JAT . '|2026-02-04', null], [$feb->status, $feb->detection_key, $feb->note], 'one row per reset');
        $this->assertSame(11, RateResetEvent::count());
    }

    public function test_a_fixed_loan_gets_one_period_and_no_resets(): void
    {
        $this->loan('104460000098', ['rate_type' => 'FIXED', 'portfolio' => 'FInES', 'product_type' => 'FInES Industrial Loans', 'contractual_rate' => 0.10]);

        $fixed = $this->detect('104460000098')['details']['104460000098'];

        $this->assertSame(ResetDetectionService::STATUS_FIXED, $fixed['status']);
        $this->assertSame('RATE_TYPE', $fixed['rate_behaviour_source'], 'the contract master states it fixed');
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
