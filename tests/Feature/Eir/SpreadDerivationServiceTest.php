<?php

namespace Tests\Feature\Eir;

use App\Models\AuditLog;
use App\Services\Eir\SpreadDerivationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The spread added to the prime rate (margin) is derived from the loan book
 * against the PLR series (decision D13) and checked for constancy (rule 4).
 * The PLR rows are the December 2025 to August 2026 stretch of the repaired
 * file; the loan books are synthetic, written in the mixed period shapes the
 * real table carries.
 */
class SpreadDerivationServiceTest extends TestCase
{
    protected $seed = false;

    /** PLR from the repaired file. Month-end rates: Jan 25.3, Feb 24.7, Mar 22.4, Apr 20.8, May 20.6, Jun 20.4, Jul 20.5, Aug 20.8. */
    private const PLR = [
        ['2025-11-01', 25.3], ['2026-02-04', 24.7], ['2026-03-05', 23.7], ['2026-03-09', 22.4],
        ['2026-04-20', 20.8], ['2026-05-06', 20.6], ['2026-06-03', 20.4], ['2026-07-03', 20.5], ['2026-08-05', 20.8],
    ];

    /** The eight month ends of 2026, each written the way some loan book writes it. */
    private const MONTHS = [
        '202601' => 25.3, '2026-02' => 24.7, '2026-03-31' => 22.4, '2026/04' => 20.8,
        '2026-05-31 00:00:00' => 20.6, '202606' => 20.4, '2026-07' => 20.5, '2026-08' => 20.8,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->nullable();
            $t->string('reporting_period')->nullable();
            $t->double('interest_rate')->default(0);
            $t->timestamps();
        });

        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->unique();
            $t->double('markup')->nullable();
            $t->double('spread_over_prime')->nullable();
            $t->string('spread_source', 10)->nullable();
            $t->boolean('spread_drift_flag')->default(false);
            $t->boolean('reprice_flag')->nullable();
            $t->timestamps();
        });

        Schema::create('reference_rate_series', function (Blueprint $t) {
            $t->increments('id');
            $t->string('index_code', 20)->default('PLR');
            $t->date('effective_date');
            $t->decimal('rate', 8, 5);
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->string('scope')->nullable();
            $t->string('reporting_period')->nullable();
            $t->integer('rows_affected')->nullable();
            $t->text('old_values')->nullable();
            $t->text('new_values')->nullable();
            $t->text('meta')->nullable();
            $t->string('ip_address')->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamps();
        });

        foreach (self::PLR as [$date, $rate]) {
            DB::table('reference_rate_series')->insert(['index_code' => 'PLR', 'effective_date' => $date, 'rate' => $rate]);
        }
    }

    private function contract(string $id, ?float $markup = null, ?bool $reprice = null): void
    {
        DB::table('contract_eir')->insert(['contract_id' => $id, 'markup' => $markup, 'reprice_flag' => $reprice]);
    }

    /** @param  callable(string,float):float  $rate  loan-book rate for (period, PLR at month end) */
    private function loanBooks(string $id, callable $rate): void
    {
        foreach (self::MONTHS as $period => $plr) {
            DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => (string) $period, 'interest_rate' => $rate((string) $period, $plr)]);
        }
    }

    private function stored(string $id): object
    {
        return DB::table('contract_eir')->where('contract_id', $id)->first();
    }

    public function test_a_loan_that_moves_exactly_with_the_plr_gets_a_constant_derived_spread(): void
    {
        $this->contract('A', 0.05, true);
        $this->loanBooks('A', fn ($period, $plr) => $plr + 5.0);

        $result = app(SpreadDerivationService::class)->derive();

        $this->assertSame(1, $result['derived']);
        $this->assertSame(0, $result['drifted']);
        $detail = $result['details']['A'];
        $this->assertSame(SpreadDerivationService::STATUS_DERIVED, $detail['status']);
        $this->assertSame(8, $detail['observations']);
        $this->assertSame('2026-01', $detail['first_period']);
        $this->assertSame('2026-08', $detail['last_period']);
        $this->assertEqualsWithDelta(5.0, $detail['spread'], 0.000001);
        $this->assertEqualsWithDelta(5.0, $detail['supplied_pp'], 0.000001);
        $this->assertTrue($detail['supplied_agrees']);
        $this->assertSame(1, $result['supplied_agree']);

        $stored = $this->stored('A');
        $this->assertEqualsWithDelta(5.0, (float) $stored->spread_over_prime, 0.000001);
        $this->assertSame('DERIVED', $stored->spread_source);
        $this->assertSame(0, (int) $stored->spread_drift_flag);

        $audit = AuditLog::where('action', 'EIR Spread Derivation')->first();
        $this->assertNotNull($audit);
        $this->assertSame(1, $audit->meta['result']['derived']);
        $this->assertSame('DERIVED', $audit->meta['result']['details']['A']['status']);
    }

    public function test_a_drifting_spread_is_flagged_and_given_no_spread(): void
    {
        // A fixed 10 percent loan while the PLR falls 4.9 points: the spread runs from -15.3 to -10.4.
        $this->contract('B', null, false);
        $this->loanBooks('B', fn () => 10.0);

        $result = app(SpreadDerivationService::class)->derive();

        $this->assertSame(1, $result['drifted']);
        $detail = $result['details']['B'];
        $this->assertSame(SpreadDerivationService::STATUS_DRIFT, $detail['status']);
        $this->assertNull($detail['spread']);
        $this->assertEqualsWithDelta(-15.3, $detail['min'], 0.000001);
        $this->assertEqualsWithDelta(-10.4, $detail['max'], 0.000001);
        $this->assertStringContainsString('held for review', $detail['note']);
        $this->assertStringContainsString('never reprices', $detail['note']);

        $stored = $this->stored('B');
        $this->assertNull($stored->spread_over_prime);
        $this->assertNull($stored->spread_source);
        $this->assertSame(1, (int) $stored->spread_drift_flag);
    }

    public function test_a_wobble_inside_the_tolerance_is_still_one_spread_and_the_median_is_stored(): void
    {
        $this->contract('C', 0.06);
        $this->loanBooks('C', fn ($period) => $period === '2026-03-31' ? 22.4 + 5.0 : self::MONTHS[$period] + 4.9);

        $result = app(SpreadDerivationService::class)->derive();

        $detail = $result['details']['C'];
        $this->assertSame(SpreadDerivationService::STATUS_DERIVED, $detail['status']);
        $this->assertEqualsWithDelta(4.9, $detail['spread'], 0.000001);
        $this->assertEqualsWithDelta(0.1, $detail['max'] - $detail['min'], 0.000001);
        // MAIIC said 6.00; the evidence says 4.90. Recorded, not adopted.
        $this->assertFalse($detail['supplied_agrees']);
        $this->assertSame(1, $result['supplied_disagree']);
        $this->assertStringContainsString('does not agree', $detail['note']);
        $this->assertEqualsWithDelta(4.9, (float) $this->stored('C')->spread_over_prime, 0.000001);
    }

    public function test_a_loan_with_a_moving_spread_just_over_the_tolerance_is_held(): void
    {
        $this->contract('D');
        $this->loanBooks('D', fn ($period, $plr) => $period === '2026-08' ? $plr + 5.16 : $plr + 5.0);

        $result = app(SpreadDerivationService::class)->derive();

        $this->assertSame(SpreadDerivationService::STATUS_DRIFT, $result['details']['D']['status']);
    }

    public function test_contracts_without_evidence_are_reported_and_left_untouched(): void
    {
        $this->contract('E');                       // no loan-book rows at all
        $this->contract('F');                       // rows, but before the series starts
        DB::table('loan_books')->insert(['contract_id' => 'F', 'reporting_period' => '202006', 'interest_rate' => 18.4]);
        DB::table('contract_eir')->where('contract_id', 'E')->update(['spread_over_prime' => 7.7, 'spread_source' => 'DERIVED']);

        $result = app(SpreadDerivationService::class)->derive();

        $this->assertSame(1, $result['no_data']);
        $this->assertSame(1, $result['no_reference_rate']);
        $this->assertSame(SpreadDerivationService::STATUS_NO_DATA, $result['details']['E']['status']);
        $this->assertSame(SpreadDerivationService::STATUS_NO_REFERENCE_RATE, $result['details']['F']['status']);
        $this->assertEqualsWithDelta(7.7, (float) $this->stored('E')->spread_over_prime, 0.000001, 'an earlier answer survives a run with no evidence');
    }

    public function test_the_as_at_period_limits_the_months_read_whatever_shape_they_are_written_in(): void
    {
        $this->contract('A');
        $this->loanBooks('A', fn ($period, $plr) => $plr + 5.0);

        $result = app(SpreadDerivationService::class)->derive('202603');

        $this->assertSame(3, $result['details']['A']['observations']);
        $this->assertSame('2026-03', $result['as_at_period']);
        $this->assertSame('2026-03', $result['details']['A']['last_period']);
    }

    public function test_an_empty_series_refuses_to_derive_anything(): void
    {
        DB::table('reference_rate_series')->delete();
        $this->contract('A');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No PLR reference rates are loaded');
        app(SpreadDerivationService::class)->derive();
    }
}
