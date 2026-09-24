<?php

namespace Tests\Feature\Eir;

use App\Models\AuditLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The two P2 console commands, end to end on a private in-memory schema:
 * the fixture loads through eir:import-reference-rates against a named
 * user, and eir:derive-spreads reads the result back.
 */
class EirReferenceRateCommandsTest extends TestCase
{
    protected $seed = false;

    private const FIXTURE = __DIR__ . '/../../fixtures/eir/plr_reference_rates_corrected.csv';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->string('profile_photo_path')->nullable();
            $t->timestamps();
        });
        Schema::create('reference_rate_series', function (Blueprint $t) {
            $t->increments('id');
            $t->string('index_code', 20)->default('PLR');
            $t->date('effective_date');
            $t->decimal('rate', 8, 5);
            $t->string('source_row')->nullable();
            $t->string('as_delivered')->nullable();
            $t->string('interpretation')->nullable();
            $t->unsignedBigInteger('import_id')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['index_code', 'effective_date']);
        });
        Schema::create('import_mappings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('import_type');
            $t->string('source_header');
            $t->string('target_field');
            $t->string('transform')->nullable();
            $t->timestamps();
        });
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

        DB::table('users')->insert(['id' => 5, 'name' => 'Finance user', 'email' => 'finance@example.test']);
    }

    public function test_the_import_command_loads_the_fixture_against_the_named_user(): void
    {
        $this->artisan('eir:import-reference-rates', ['file' => self::FIXTURE, '--user' => '5'])
            ->expectsOutputToContain('Stored PLR series: 48 rows, 26 rate changes, 2020-03-04 to 2026-09-03, current rate 21.20%.')
            ->assertExitCode(0);

        $this->assertSame(48, DB::table('reference_rate_series')->count());
        $this->assertSame(48, DB::table('reference_rate_series')->where('created_by', 5)->count());
        $this->assertSame(5, (int) AuditLog::where('action', 'EIR Reference Rate Import')->value('user_id'));
    }

    public function test_the_import_command_refuses_to_run_without_a_real_user(): void
    {
        $this->artisan('eir:import-reference-rates', ['file' => self::FIXTURE])->assertExitCode(2);
        $this->artisan('eir:import-reference-rates', ['file' => self::FIXTURE, '--user' => '99'])->assertExitCode(2);
        $this->assertSame(0, DB::table('reference_rate_series')->count());
    }

    public function test_a_refused_file_fails_the_command_with_the_reason(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'eir_plr_') . '.csv';
        file_put_contents($path, "effective_date,rate\n2020-03-04,13.4\n13/05/2020,13.3\n");

        try {
            $this->artisan('eir:import-reference-rates', ['file' => $path, '--user' => '5'])
                ->expectsOutputToContain("effective_date on row 3 is '13/05/2020'")
                ->assertExitCode(1);
        } finally {
            @unlink($path);
        }
        $this->assertSame(0, DB::table('reference_rate_series')->count());
    }

    public function test_the_derive_command_reports_and_writes_the_spreads(): void
    {
        $this->artisan('eir:import-reference-rates', ['file' => self::FIXTURE, '--user' => '5'])->assertExitCode(0);
        DB::table('contract_eir')->insert([['contract_id' => 'A', 'markup' => 0.05], ['contract_id' => 'B', 'markup' => null]]);
        foreach (['2026-06' => 20.4, '2026-07' => 20.5, '2026-08' => 20.8] as $period => $plr) {
            DB::table('loan_books')->insert([
                ['contract_id' => 'A', 'reporting_period' => $period, 'interest_rate' => $plr + 5.0],
                ['contract_id' => 'B', 'reporting_period' => $period, 'interest_rate' => 10.0],
            ]);
        }

        $this->artisan('eir:derive-spreads', ['--period' => '2026-08', '--user' => '5'])
            ->expectsOutputToContain('Spreads derived against PLR up to 2026-08.')
            ->expectsOutputToContain('1 contract(s) need a look:')
            ->assertExitCode(0);

        $this->assertEqualsWithDelta(5.0, (float) DB::table('contract_eir')->where('contract_id', 'A')->value('spread_over_prime'), 0.000001);
        $this->assertSame(1, (int) DB::table('contract_eir')->where('contract_id', 'B')->value('spread_drift_flag'));
        $audit = AuditLog::where('action', 'EIR Spread Derivation')->first();
        $this->assertSame(5, (int) $audit->user_id);
        $this->assertSame(5, (int) $audit->meta['user_id']);
        $this->assertSame('DRIFT', $audit->meta['result']['details']['B']['status']);
    }
}
