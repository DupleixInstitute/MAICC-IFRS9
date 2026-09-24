<?php

namespace Tests\Feature\Eir;

use App\Models\AuditLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The P4 console command end to end on a private in-memory schema: a file with
 * E-Banker's own headings loads with no mapping, the command refuses to run
 * without a named user, and a day-first date fails the command with the reason.
 */
class EirDisbursementCommandTest extends TestCase
{
    protected $seed = false;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->string('profile_photo_path')->nullable();
            $t->timestamps();
        });
        Schema::create('contract_disbursements', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('sub_account_no', 20)->nullable();
            $t->integer('tranche_no')->nullable();
            $t->string('disbursement_date');
            $t->double('amount');
            $t->string('reference')->nullable();
            $t->string('source_system', 40)->nullable();
            $t->string('source_reference')->nullable();
            $t->string('external_transaction_id', 120)->nullable();
            $t->integer('import_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
            $t->unique(['source_system', 'external_transaction_id']);
        });
        Schema::create('import_mappings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('import_type');
            $t->string('source_header');
            $t->string('target_field');
            $t->string('transform')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id')->nullable();
            $t->string('action');
            $t->string('entity_type');
            $t->integer('entity_id')->nullable();
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

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'eir_drawdowns_') . '.csv';
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    public function test_the_command_loads_ebankers_own_headings_against_the_named_user(): void
    {
        $path = $this->file(
            "Loan Account Number,Disbursement Date,Disbursed Amount,Tranche No,Reference\n" .
            "104450000103,2025-06-30,150000000,1,PV-1\n" .
            "104450000103,2025-09-15,147161905,2,PV-2\n"
        );

        $this->artisan('eir:import-disbursements', ['file' => $path, '--user' => '5'])
            ->expectsOutputToContain('Drawdowns in the file run from 2025-06-30 to 2025-09-15.')
            ->assertExitCode(0);

        $this->assertSame(2, DB::table('contract_disbursements')->count());
        $this->assertSame(2, DB::table('contract_disbursements')->where('created_by', 5)->count());
        $this->assertEqualsWithDelta(297_161_905.0, (float) DB::table('contract_disbursements')->sum('amount'), 0.01);
        $this->assertSame(5, (int) AuditLog::where('action', 'EIR Disbursement Import')->value('user_id'));
    }

    public function test_the_command_refuses_to_run_without_a_real_user(): void
    {
        $path = $this->file("contract_id,disbursement_date,amount\n104450000103,2025-06-30,150000000\n");

        $this->artisan('eir:import-disbursements', ['file' => $path])->assertExitCode(2);
        $this->artisan('eir:import-disbursements', ['file' => $path, '--user' => '99'])->assertExitCode(2);
        $this->assertSame(0, DB::table('contract_disbursements')->count());
    }

    public function test_a_day_first_date_fails_the_command_with_the_reason(): void
    {
        $path = $this->file(
            "contract_id,disbursement_date,amount\n" .
            "104450000103,2025-06-30,150000000\n" .
            "104450000103,15/09/2025,147161905\n"
        );

        $this->artisan('eir:import-disbursements', ['file' => $path, '--user' => '5'])
            ->expectsOutputToContain("disbursement_date on row 3 is '15/09/2025'")
            ->assertExitCode(1);

        $this->assertSame(0, DB::table('contract_disbursements')->count());
    }

    public function test_an_unreadable_file_is_named_rather_than_loaded(): void
    {
        $this->artisan('eir:import-disbursements', ['file' => 'C:/no/such/drawdowns.csv', '--user' => '5'])
            ->assertExitCode(2);
    }
}
