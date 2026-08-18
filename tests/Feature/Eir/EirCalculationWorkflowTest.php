<?php

namespace Tests\Feature\Eir;

use App\Jobs\CalculateEirJob;
use App\Services\Eir\CalculateEirService;
use App\Services\Eir\EirCalculationService;
use App\Services\Eir\EirContractInputService;
use App\Services\Eir\EirReadinessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class EirCalculationWorkflowTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id')->unique(); $t->string('instrument_type')->default('AMORTISED_LOAN');
            $t->string('rate_type')->default('FIXED'); $t->string('origination_date')->nullable(); $t->double('drawn_amount')->nullable();
            $t->integer('payments_per_year')->default(12); $t->string('frequency_source')->default('STATED'); $t->string('schedule_source')->nullable();
            $t->double('eir_period')->nullable(); $t->double('eir_nominal_annual')->nullable(); $t->double('eir_effective_annual')->nullable();
            $t->enum('rate_source', ['SOLVED_EIR', 'CONTRACTUAL_PROXY'])->default('CONTRACTUAL_PROXY'); $t->integer('solver_iterations')->nullable(); $t->double('solver_residual')->nullable();
            $t->string('solver_method')->nullable(); $t->json('input_snapshot')->nullable(); $t->string('calculation_status')->default('PENDING');
            $t->text('calculation_error')->nullable(); $t->string('calculated_at')->nullable(); $t->integer('calculated_by')->nullable();
            $t->string('locked_at')->nullable(); $t->integer('locked_by')->nullable(); $t->timestamps();
        });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version')->default(1); $t->string('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); });
        Schema::create('contract_fees', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('fee_type')->default('other'); $t->string('description')->nullable(); $t->double('amount'); $t->boolean('integral')->nullable(); $t->string('classification_status')->default('PENDING'); $t->string('cashflow_direction')->nullable(); $t->string('transaction_date')->nullable(); $t->string('source_reference')->nullable(); $t->string('gl_account_ref')->nullable(); });
    }

    private function service(): EirCalculationService
    {
        $readiness = new EirReadinessService();
        return new EirCalculationService(new EirContractInputService($readiness), new CalculateEirService());
    }

    private function seedContract(string $id = 'C-1', bool $ready = true): void
    {
        DB::table('contract_eir')->insert(['contract_id' => $id, 'origination_date' => '2025-01-01', 'drawn_amount' => 1000, 'payments_per_year' => 12, 'frequency_source' => $ready ? 'STATED' : 'ASSUMED', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contract_cashflow_schedule')->insert([
            ['contract_id' => $id, 'due_date' => '2025-02-01', 'principal_due' => 500, 'interest_due' => 50],
            ['contract_id' => $id, 'due_date' => '2025-03-01', 'principal_due' => 500, 'interest_due' => 25],
        ]);
        DB::table('contract_fees')->insert(['contract_id' => $id, 'amount' => 30, 'integral' => true, 'classification_status' => 'REVIEWED', 'cashflow_direction' => 'RECEIVED']);
    }

    public function test_calculation_persists_reviewable_result_and_complete_snapshot(): void
    {
        $this->seedContract();
        $result = $this->service()->calculate('C-1', 10);
        $row = DB::table('contract_eir')->where('contract_id', 'C-1')->first();

        $this->assertSame('CALCULATED', $result['status']);
        $this->assertSame('CALCULATED', $row->calculation_status);
        $this->assertSame('SOLVED_EIR', $row->rate_source);
        $this->assertSame(10, $row->calculated_by);
        $this->assertNotNull($row->calculated_at);
        $this->assertNotNull($row->eir_effective_annual);
        $this->assertContains($row->solver_method, ['NEWTON_RAPHSON', 'BISECTION']);
        $snapshot = json_decode($row->input_snapshot, true);
        $this->assertSame('C-1', $snapshot['contract_id']);
        $this->assertArrayHasKey('solver', $snapshot);
    }

    public function test_batch_continues_and_records_a_named_block_reason(): void
    {
        $this->seedContract('READY');
        $this->seedContract('BLOCKED', false);
        (new CalculateEirJob(['BLOCKED', 'READY'], 10))->handle($this->service());

        $this->assertSame('CALCULATED', DB::table('contract_eir')->where('contract_id', 'READY')->value('calculation_status'));
        $this->assertSame('BLOCKED', DB::table('contract_eir')->where('contract_id', 'BLOCKED')->value('calculation_status'));
        $this->assertStringContainsString('Payment frequency was assumed', DB::table('contract_eir')->where('contract_id', 'BLOCKED')->value('calculation_error'));
    }

    public function test_checker_must_differ_from_maker_and_lock_is_final(): void
    {
        $this->seedContract();
        $service = $this->service();
        $service->calculate('C-1', 10);

        $this->expectException(LogicException::class);
        try {
            $service->lock('C-1', 10);
        } finally {
            $locked = $service->lock('C-1', 20);
            $this->assertSame('LOCKED', $locked->calculation_status);
            $this->assertSame(20, $locked->locked_by);
            $retry = $service->calculate('C-1', 30);
            $this->assertSame('BLOCKED', $retry['status']);
            $this->assertSame('LOCKED', DB::table('contract_eir')->where('contract_id', 'C-1')->value('calculation_status'));
        }
    }

    public function test_bulk_lock_approves_eligible_contracts_and_reports_ineligible_ones(): void
    {
        $this->seedContract('OWN');
        $this->seedContract('OTHER');
        $this->seedContract('NO-MAKER');
        $service = $this->service();
        $service->calculate('OWN', 10);
        $service->calculate('OTHER', 11);
        $service->calculate('NO-MAKER');

        $result = $service->lockMany(['OWN', 'OTHER', 'NO-MAKER'], 10);

        $this->assertCount(1, $result['locked']);
        $this->assertSame('OTHER', $result['locked'][0]->contract_id);
        $this->assertSame(['OWN', 'NO-MAKER'], array_keys($result['skipped']));
        $this->assertSame('LOCKED', DB::table('contract_eir')->where('contract_id', 'OTHER')->value('calculation_status'));
        $this->assertSame('CALCULATED', DB::table('contract_eir')->where('contract_id', 'OWN')->value('calculation_status'));
        $this->assertSame('CALCULATED', DB::table('contract_eir')->where('contract_id', 'NO-MAKER')->value('calculation_status'));
    }

    public function test_admin_override_can_lock_own_and_unattributed_calculations(): void
    {
        $this->seedContract('OWN');
        $this->seedContract('NO-MAKER');
        $service = $this->service();
        $service->calculate('OWN', 10);
        $service->calculate('NO-MAKER');

        $result = $service->lockMany(['OWN', 'NO-MAKER'], 10, true);

        $this->assertCount(2, $result['locked']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame('LOCKED', DB::table('contract_eir')->where('contract_id', 'OWN')->value('calculation_status'));
        $this->assertSame('LOCKED', DB::table('contract_eir')->where('contract_id', 'NO-MAKER')->value('calculation_status'));
        $this->assertSame(10, DB::table('contract_eir')->where('contract_id', 'OWN')->value('locked_by'));
        $this->assertSame(10, DB::table('contract_eir')->where('contract_id', 'NO-MAKER')->value('locked_by'));
    }
}
