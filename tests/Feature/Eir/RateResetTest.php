<?php

namespace Tests\Feature\Eir;

use App\Models\GovernanceSetting;
use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\RateResetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The writer of rate_reset_events and its maker-checker intake (spec v4 P5):
 * a floating loan's move with the PLR is a reset, a move of its spread is for
 * a person to classify, a fixed-rate loan's move is a restructure candidate;
 * one person proposes, another approves; a locked month is refused; a
 * negotiated move becomes a restructure candidate.
 */
class RateResetTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('customer_name')->nullable(); $t->string('external_identity_id')->nullable(); $t->double('interest_rate')->nullable(); });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('rate_type')->default('FIXED'); $t->timestamps(); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->smallInteger('schedule_version')->default(1); $t->date('effective_from')->nullable(); $t->date('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); $t->timestamps(); });
        Schema::create('reference_rate_series', function (Blueprint $t) { $t->increments('id'); $t->string('index_code'); $t->date('effective_date'); $t->double('rate'); $t->timestamps(); });
        Schema::create('rate_reset_events', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->date('reset_date'); $t->decimal('old_reference_rate', 8, 5); $t->decimal('new_reference_rate', 8, 5); $t->unsignedSmallInteger('new_schedule_version'); $t->integer('recorded_by')->nullable(); $t->timestamps(); });
        Schema::create('reporting_period_locks', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period'); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_14_230000_restructure_register_and_modifications.php'))->up();
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('t', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        // the PLR: 25.3 to 30 November 2025, 24.0 from 4 February 2026
        foreach ([['2025-06-01', 25.3], ['2026-02-04', 24.0]] as [$d, $r]) {
            DB::table('reference_rate_series')->insert(['index_code' => 'PLR', 'effective_date' => $d, 'rate' => $r]);
        }
        DB::table('contract_eir')->insert(['contract_id' => 'F1', 'rate_type' => 'FLOATING', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contract_eir')->insert(['contract_id' => 'X1', 'rate_type' => 'FIXED', 'created_at' => now(), 'updated_at' => now()]);
        $rates = [
            'F1' => ['2026-01' => 31.3, '2026-02' => 30.0, '2026-03' => 30.5], // moved with the PLR in February; spread 6.0 to 6.5 in March
            'X1' => ['2026-01' => 33.0, '2026-02' => 33.0, '2026-03' => 34.0], // a fixed-rate loan re-priced in March, no PLR move
        ];
        foreach ($rates as $c => $months) {
            foreach ($months as $p => $r) {
                DB::table('loan_books')->insert(['contract_id' => $c, 'reporting_period' => $p, 'customer_name' => "Loan {$c}", 'external_identity_id' => "0000{$c}", 'interest_rate' => $r]);
            }
        }
    }

    private function service(): RateResetService
    {
        return new RateResetService(new LandingZoneReader());
    }

    public function test_the_detector_splits_plr_resets_from_spread_changes_and_sends_a_fixed_rate_move_to_the_register(): void
    {
        $s = $this->service()->detect();

        $f = DB::table('rate_reset_events')->where('contract_id', 'F1')->orderBy('reporting_period')->get();
        $this->assertCount(2, $f);
        $this->assertSame('PLR_RESET', $f[0]->event_type);
        $this->assertSame('2026-02-04', $f[0]->reset_date, 'dated by the PLR\'s effective date');
        $this->assertEqualsWithDelta(25.3, $f[0]->old_reference_rate, 1e-9);
        $this->assertEqualsWithDelta(24.0, $f[0]->new_reference_rate, 1e-9);
        $this->assertSame('IN_CONTRACT', $f[0]->suggested_classification);
        $this->assertSame('SPREAD_CHANGE', $f[1]->event_type);
        $this->assertSame('2026-03-01', $f[1]->reset_date, 'no PLR move and nothing keyed: the whole month at the new rate (section 3.1)');
        $this->assertEqualsWithDelta(6.5, $f[1]->new_spread, 1e-9);
        $this->assertNull($f[1]->suggested_classification);
        $this->assertSame('DETECTED', $f[1]->status);

        $x = DB::table('restructure_events')->where('contract_id', 'X1')->first();
        $this->assertSame('RATE_CHANGE', $x->source);
        $this->assertSame('CANDIDATE', $x->status);
        $this->assertSame(1, $s['fixed_rate_candidates']);
        $this->assertSame(0, DB::table('rate_reset_events')->where('contract_id', 'X1')->count());

        // detection again changes nothing and never approves
        $this->service()->detect();
        $this->assertSame(2, DB::table('rate_reset_events')->count());
        $this->assertSame(0, DB::table('rate_reset_events')->where('status', '!=', 'DETECTED')->count());
    }

    public function test_maker_checker_on_a_reset_and_a_negotiated_move_becomes_a_restructure_candidate(): void
    {
        $this->service()->detect();
        $spread = (int) DB::table('rate_reset_events')->where('event_type', 'SPREAD_CHANGE')->value('id');
        $svc = $this->service();

        $svc->propose([$spread], 'NEGOTIATED', 'Rate cut agreed with the borrower', 1);
        try {
            $svc->approve([$spread], 1);
            $this->fail('The proposer approved their own classification.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot approve', $e->getMessage());
        }
        $svc->approve([$spread], 2);

        $r = DB::table('rate_reset_events')->where('id', $spread)->first();
        $this->assertSame('APPROVED', $r->status);
        $this->assertNotNull($r->restructure_event_id);
        $e = DB::table('restructure_events')->where('id', $r->restructure_event_id)->first();
        $this->assertSame('MODIFICATION', $e->suggested_class);
        $this->assertSame('CANDIDATE', $e->status, 'still a candidate on the register: two more people confirm it there');
    }

    public function test_a_reset_in_a_locked_month_is_refused(): void
    {
        $this->service()->detect();
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2026-02']);
        $plr = (int) DB::table('rate_reset_events')->where('event_type', 'PLR_RESET')->value('id');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('locked');
        $this->service()->propose([$plr], 'IN_CONTRACT', 'PLR change', 1);
    }

    public function test_every_change_is_a_reset_closes_the_negotiated_classification(): void
    {
        $this->service()->detect();
        GovernanceSetting::query()->where('key', 'rate_change_classification')->update(['value' => 'Every rate change is a reset']);
        $spread = (int) DB::table('rate_reset_events')->where('event_type', 'SPREAD_CHANGE')->value('id');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Every rate change is a reset');
        $this->service()->propose([$spread], 'NEGOTIATED', 'agreed', 1);
    }
}
