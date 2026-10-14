<?php

namespace Tests\Feature\Eir;

use App\Console\Commands\Bootstrap;
use App\Console\Commands\MaintenanceTask;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The restructure detection is wired into the bootstrap and the month-end as a
 * detection-only step (spec v4 3.9, P5; review item R10; built 14 October
 * 2026): eir:restructure-candidates runs after the scheme settings and before
 * staging, finds candidates and rate moves, and never classifies, proposes,
 * confirms or approves anything; a person's proposal or confirmation is kept
 * as it was. The bootstrap's line says how many candidates were found.
 *
 * The fixture is the rate-reset one: F1 floating, moved with the PLR in
 * February 2026 and re-priced in March; X1 fixed-rate, re-priced in March.
 */
class RestructureDetectionWiringTest extends TestCase
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

    public function test_the_month_end_detects_after_the_scheme_settings_and_before_staging(): void
    {
        $steps = array_column(MaintenanceTask::steps('month-end', '2026-08', ['--user' => '1']), 1);
        $detect = array_search('eir:restructure-candidates', $steps, true);
        $this->assertNotFalse($detect, 'the month-end runs the detection');
        $this->assertLessThan($detect, array_search('eir:load-schemes', $steps, true));
        $this->assertGreaterThan($detect, array_search('eir:stage', $steps, true));
        $this->assertGreaterThan($detect, array_search('eir:run-revenue', $steps, true));
        $this->assertSame(1, count(array_keys($steps, 'eir:restructure-candidates', true)));
        // the step is the command alone: no option of it confirms anything
        $step = MaintenanceTask::steps('month-end', '2026-08', [])[$detect];
        $this->assertSame([], $step[2]);
        $this->assertFalse($step[3] ?? false, 'a detection that cannot run stops the month-end');
        $this->assertNull(MaintenanceTask::steps('nonsense', '', []));
    }

    public function test_the_detection_step_finds_and_never_confirms_and_the_bootstrap_line_counts_the_candidates(): void
    {
        // a person has already proposed one candidate: detection keeps it as it is
        DB::table('restructure_events')->insert(['detection_key' => 'MANUAL|X1', 'contract_id' => 'X1', 'event_date' => '2026-01-15', 'source' => 'MODIFICATION_LOG',
            'suggested_class' => 'MODIFICATION', 'status' => 'PROPOSED', 'classification' => 'MODIFICATION', 'reason' => 'Term extended', 'proposed_by' => 7, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(0, Artisan::call('eir:restructure-candidates'));
        $this->assertStringContainsString('Nothing was confirmed', Artisan::output());

        $this->assertSame(0, DB::table('restructure_events')->where('status', 'CONFIRMED')->count());
        $this->assertSame(0, DB::table('contract_modifications')->count(), 'no schedule version N+1, no modification row');
        $kept = DB::table('restructure_events')->where('detection_key', 'MANUAL|X1')->first();
        $this->assertSame(['PROPOSED', 'Term extended', 7], [$kept->status, $kept->reason, (int) $kept->proposed_by]);
        $this->assertSame(['DETECTED'], DB::table('rate_reset_events')->distinct()->pluck('status')->all(), 'every move waits for a person');
        $this->assertSame(0, DB::table('rate_reset_events')->whereNotNull('approved_by')->count());
        $this->assertSame(1, DB::table('restructure_events')->where('status', 'CANDIDATE')->where('source', 'RATE_CHANGE')->count(), 'X1 re-priced in March');

        $line = Bootstrap::restructureLine();
        $this->assertStringStartsWith("1 candidate found and awaiting a person's classification", $line);
        $this->assertStringContainsString('0 from the reschedule history and the migration worksheet, 1 from rate moves of fixed-rate loans', $line);
        $this->assertStringContainsString('2 rate moves recorded on the rate-reset register (1 PLR resets, 1 spread changes), 2 awaiting classification', $line);
        $this->assertStringContainsString('nothing was confirmed or approved by the bootstrap (1 proposed and 0 confirmed by people are kept as they were)', $line);

        // run again: idempotent, and still nothing confirmed
        Artisan::call('eir:restructure-candidates');
        $this->assertSame($line, Bootstrap::restructureLine());
    }

    public function test_the_bootstrap_runs_the_detection_after_the_schedules_and_before_staging(): void
    {
        $src = file_get_contents(app_path('Console/Commands/Bootstrap.php'));
        $build = substr($src, strpos($src, 'private function stepBuild('), strpos($src, 'private function stepEngines(') - strpos($src, 'private function stepBuild('));
        $this->assertGreaterThan(strpos($build, "'5h schedules v1'"), strpos($build, "Artisan::call('eir:restructure-candidates')"), 'after the ledger, the chart history and the schedules are in');
        $this->assertStringNotContainsString('eir:restructure-candidates', substr($src, strpos($src, 'private function stepEngines(')), 'before staging, once');
        $this->assertStringNotContainsString('RestructureRegisterService', $src, 'the bootstrap never proposes, approves or confirms a restructure');
        $this->assertStringNotContainsString('RateResetService', $src, 'the bootstrap never proposes or approves a rate move');
    }
}
