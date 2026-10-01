<?php

namespace Tests\Feature\Eir;

use App\Models\RateResetEvent;
use App\Services\Eir\ResetDetectionService;
use App\Services\Eir\ResetScheduleService;
use App\Services\Eir\ScheduleGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\Feature\Eir\Concerns\CreatesResetSchema;
use Tests\TestCase;

/**
 * Phase P5, step 3, on JAT Group (104430000087): version 1 is twelve quarterly
 * instalments of 28,586,464.82 at 33.30 percent from 23 Oct 2025, and the
 * prime rate changes eleven times after the loan was drawn. E-Banker's EMI
 * chart sized the instalment after the 1 Oct 2025 change at 28,624,982, which
 * the first Reset version must reproduce. Private in-memory sqlite schema.
 */
class ResetScheduleServiceTest extends TestCase
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

        DB::table('contract_eir')->insert([
            'contract_id' => self::JAT, 'sub_account_no' => '1', 'interest_policy' => 'P', 'portfolio' => 'MAIIC',
            'origination_date' => '2025-07-23', 'maturity_date' => '2028-09-30', 'contractual_rate' => 0.333,
            'payments_per_year' => 4, 'emi_calc_type' => 'E',
        ]);
        $this->versionOne();
        app(ResetDetectionService::class)->detect(self::JAT, CarbonImmutable::parse('2026-09-30'));
    }

    /** JAT's version 1, as the generator builds it from the imported master. */
    private function versionOne(): void
    {
        $v1 = app(ScheduleGeneratorService::class)->generate([
            'principal' => 209_336_236, 'approved_amount' => 209_336_236, 'annual_rate' => 0.333, 'payments_per_year' => 4,
            'n_payments' => 12, 'start_date' => '2025-07-23', 'interest_start_date' => '2025-07-23', 'first_due_date' => '2025-10-23',
            'emi_calc_type' => 'E', 'interest_calc_base' => 'B', 'installment_based_on' => 'sanction', 'as_of' => '2025-07-23',
        ]);
        foreach ($v1['rows'] as $r) {
            DB::table('contract_cashflow_schedule')->insert(['contract_id' => self::JAT, 'schedule_version' => 1,
                'schedule_type' => 'ORIGINAL', 'effective_from' => '2025-07-23', 'due_date' => $r['due_date'],
                'principal_due' => $r['principal_due'], 'interest_due' => $r['interest_due'], 'schedule_source' => 'GENERATED']);
        }
    }

    private function build(): array
    {
        return app(ResetScheduleService::class)->build(self::JAT);
    }

    private function version(int $n)
    {
        return DB::table('contract_cashflow_schedule')->where('contract_id', self::JAT)->where('schedule_version', $n)->orderBy('due_date')->get();
    }

    public function test_every_reset_gets_its_own_version_and_version_one_is_untouched(): void
    {
        $before = $this->version(1)->toArray();

        $result = $this->build();

        $this->assertSame(11, $result['built']);
        $this->assertSame(0, $result['blocked']);
        $this->assertEquals($before, $this->version(1)->toArray(), 'version 1 is the promise at origination');
        $this->assertSame(range(2, 12), DB::table('contract_cashflow_schedule')->where('schedule_type', 'RESET')
            ->distinct()->orderBy('schedule_version')->pluck('schedule_version')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(11, RateResetEvent::where('status', RateResetEvent::STATUS_SCHEDULED)->count());
        $this->assertSame([1], DB::table('eir_rate_periods')->where('period_no', 1)->pluck('schedule_version')->map(fn ($v) => (int) $v)->all());
    }

    public function test_the_first_reset_reproduces_ebankers_emi_chart(): void
    {
        $this->build();

        // 1 Oct 2025: prime 25.40 + 8.00 = 33.40%, before the first instalment.
        $reset = $this->version(2);
        $this->assertCount(12, $reset);
        $this->assertSame('2025-10-01', substr($reset[0]->effective_from, 0, 10));
        $this->assertEqualsWithDelta(28_624_982.23, $reset[0]->principal_due + $reset[0]->interest_due, 0.01, 'the EMI chart: 28,624,982');
        $this->assertEqualsWithDelta(209_336_236, $reset->sum('principal_due'), 0.01);
    }

    public function test_each_version_starts_from_the_principal_the_one_before_left(): void
    {
        $this->build();

        // 1 Nov 2025 re-amortises what the 1 Oct version still owed after 23 Oct.
        $owed = $this->version(2)->where('due_date', '>=', '2025-11-01')->sum('principal_due');
        $this->assertEqualsWithDelta($owed, $this->version(3)->sum('principal_due'), 0.01);
        $this->assertCount(11, $this->version(3));

        // 5 and 9 Mar 2026 fall between the Jan and Apr instalments: both start
        // from the 23 Jan instalment, with ten left.
        foreach ([5, 6] as $v) {
            $rows = $this->version($v);
            $this->assertCount(10, $rows);
            $this->assertSame('2026-04-23', $rows[0]->due_date);
            $this->assertEqualsWithDelta($this->version(4)->sum('principal_due'), $rows->sum('principal_due'), 0.01);
        }
        $this->assertTrue($this->version(5)->first()->interest_due > $this->version(6)->first()->interest_due, '31.70% charges more than 30.40%');
    }

    public function test_rebuilding_gives_the_same_versions(): void
    {
        $this->build();
        $first = DB::table('contract_cashflow_schedule')->orderBy('schedule_version')->orderBy('due_date')
            ->get(['schedule_version', 'due_date', 'principal_due', 'interest_due'])->toArray();

        $this->build();

        $this->assertEquals($first, DB::table('contract_cashflow_schedule')->orderBy('schedule_version')->orderBy('due_date')
            ->get(['schedule_version', 'due_date', 'principal_due', 'interest_due'])->toArray());
    }

    public function test_a_version_used_by_a_solved_period_is_kept_and_later_ones_build_on_it(): void
    {
        $this->build();
        $kept = $this->version(4)->toArray();
        RateResetEvent::whereDate('reset_date', '2026-02-04')->update(['status' => RateResetEvent::STATUS_SOLVED]);

        $result = $this->build();

        $this->assertSame(1, $result['kept']);
        $this->assertSame(10, $result['built']);
        $this->assertEquals($kept, $this->version(4)->toArray());
        $next = (int) RateResetEvent::whereDate('reset_date', '2026-03-05')->value('new_schedule_version');
        $this->assertNotSame(4, $next);
        $this->assertEqualsWithDelta($this->version(4)->sum('principal_due'), $this->version($next)->sum('principal_due'), 0.01,
            'the reset after the kept one starts from the balance the kept one leaves');
    }

    public function test_discarding_returns_the_resets_to_detected(): void
    {
        $this->build();

        app(ResetScheduleService::class)->discard(self::JAT);

        $this->assertSame(0, DB::table('contract_cashflow_schedule')->where('schedule_type', 'RESET')->count());
        $this->assertSame(12, $this->version(1)->count());
        $this->assertSame(11, RateResetEvent::where('status', RateResetEvent::STATUS_DETECTED)->whereNull('new_schedule_version')->count());
    }

    public function test_without_version_one_nothing_is_built(): void
    {
        DB::table('contract_cashflow_schedule')->delete();

        $result = $this->build();

        $this->assertSame('BLOCKED', $result['details'][self::JAT]['status']);
        $this->assertStringStartsWith('NO_VERSION_1', $result['details'][self::JAT]['reason']);
    }
}
