<?php

namespace Tests\Feature\Eir;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * eir:run-revenue on the console has no signed-in user, so it used to record
 * every run against nobody. It now takes the user it runs as and refuses
 * without one (spec v3 section 6.4, item 5).
 */
class RunEirRevenueCommandTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->timestamps(); });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('locked_at')->nullable(); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        DB::table('users')->insert(['id' => 7, 'name' => 'Finance', 'email' => 'finance@example.test', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_command_refuses_to_run_without_a_user(): void
    {
        $this->artisan('eir:run-revenue', ['period' => '2025-01'])
            ->expectsOutputToContain('--user=<id> is required')
            ->assertExitCode(Command::INVALID);

        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_the_command_refuses_a_user_that_does_not_exist(): void
    {
        $this->artisan('eir:run-revenue', ['period' => '2025-01', '--user' => '99'])
            ->expectsOutputToContain('No user with id 99 exists')
            ->assertExitCode(Command::INVALID);

        $this->artisan('eir:run-revenue', ['period' => '2025-01', '--user' => 'seven'])
            ->assertExitCode(Command::INVALID);

        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_the_command_runs_and_audits_when_a_real_user_is_named(): void
    {
        $this->artisan('eir:run-revenue', ['period' => '2025-01', '--user' => '7'])
            ->expectsOutputToContain('EIR revenue run completed for 2025-01')
            ->assertExitCode(Command::SUCCESS);

        $audit = DB::table('audit_logs')->where('action', 'EIR Revenue Run Completed')->first();
        $this->assertNotNull($audit);
        $this->assertSame('2025-01', $audit->reporting_period);
        $this->assertSame(0, json_decode($audit->meta, true)['requested']);
    }
}
