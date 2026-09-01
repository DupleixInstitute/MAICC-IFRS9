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
            $t->string('schedule_approval_status')->default('NOT_GENERATED');
            $t->double('eir_period')->nullable(); $t->double('eir_nominal_annual')->nullable(); $t->double('eir_effective_annual')->nullable();
            $t->enum('rate_source', ['SOLVED_EIR', 'CONTRACTUAL_PROXY'])->default('CONTRACTUAL_PROXY'); $t->integer('solver_iterations')->nullable(); $t->double('solver_residual')->nullable();
            $t->string('solver_method')->nullable(); $t->json('input_snapshot')->nullable(); $t->string('calculation_status')->default('PENDING');
            $t->text('calculation_error')->nullable(); $t->string('calculated_at')->nullable(); $t->integer('calculated_by')->nullable();
            $t->string('locked_at')->nullable(); $t->integer('locked_by')->nullable(); $t->timestamps();
        });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version')->default(1); $t->string('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); });
        Schema::create('contract_fees', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('fee_type')->default('other'); $t->string('description')->nullable(); $t->double('amount'); $t->boolean('integral')->nullable(); $t->string('classification_status')->default('PENDING'); $t->string('cashflow_direction')->nullable(); $t->string('transaction_date')->nullable(); $t->string('source_reference')->nullable(); $t->string('gl_account_ref')->nullable(); });
        Schema::create('eir_calculation_history', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->double('eir_period')->nullable(); $t->double('eir_nominal_annual')->nullable(); $t->double('eir_effective_annual')->nullable(); $t->string('rate_source')->nullable(); $t->integer('solver_iterations')->nullable(); $t->double('solver_residual')->nullable(); $t->string('solver_method')->nullable(); $t->json('input_snapshot')->nullable(); $t->string('calculation_status'); $t->text('calculation_error')->nullable(); $t->string('calculated_at')->nullable(); $t->integer('calculated_by')->nullable(); $t->string('locked_at')->nullable(); $t->integer('locked_by')->nullable(); $t->string('archive_action'); $t->string('archive_reason'); $t->integer('archived_by')->nullable(); $t->string('archived_at'); $t->timestamps(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->double('opening_gross'); $t->double('interest_accrued'); $t->string('interest_basis'); $t->double('unwind_amount'); $t->double('cash_received'); $t->string('cash_source'); $t->double('modification_gain_loss'); $t->double('closing_gross'); $t->double('ecl_allowance'); $t->timestamps(); });
        Schema::create('eir_amortisation_history', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->double('opening_gross'); $t->double('interest_accrued'); $t->string('interest_basis'); $t->double('unwind_amount'); $t->double('cash_received'); $t->string('cash_source'); $t->double('modification_gain_loss'); $t->double('closing_gross'); $t->double('ecl_allowance'); $t->string('originally_created_at')->nullable(); $t->string('superseded_at'); $t->integer('superseded_by')->nullable(); $t->string('supersession_reason'); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->double('ecl_value_discounted')->nullable(); $t->double('ecl_discounting_effect')->nullable(); $t->double('ecl_discount_rate')->nullable(); $t->string('ecl_discount_rate_source')->nullable(); $t->string('ecl_discount_status')->default('NOT_CALCULATED'); $t->double('ecl_discount_horizon_years')->nullable(); $t->string('ecl_calculation_run_id')->nullable(); $t->string('ecl_calculated_at')->nullable(); });
    }

    private function service(): EirCalculationService
    {
        $readiness = new EirReadinessService();
        return new EirCalculationService(new EirContractInputService($readiness), new CalculateEirService());
    }

    private function seedContract(string $id = 'C-1', bool $ready = true): void
    {
        DB::table('contract_eir')->insert(['contract_id' => $id, 'origination_date' => '2025-01-01', 'drawn_amount' => 1000, 'payments_per_year' => 12, 'frequency_source' => $ready ? 'STATED' : 'ASSUMED', 'schedule_approval_status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
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
        $this->assertContains($row->solver_method, ['DATED_NEWTON_RAPHSON', 'DATED_BISECTION']);
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

    public function test_unlocked_recalculation_archives_the_previous_result(): void
    {
        $this->seedContract();
        $service = $this->service();
        $service->calculate('C-1', 10);
        $original = DB::table('contract_eir')->where('contract_id', 'C-1')->value('eir_effective_annual');

        $service->calculate('C-1', 11);

        $history = DB::table('eir_calculation_history')->first();
        $this->assertSame('RECALCULATED', $history->archive_action);
        $this->assertSame(11, (int) $history->archived_by);
        $this->assertEqualsWithDelta($original, $history->eir_effective_annual, 0.000001);
        $this->assertSame(11, (int) DB::table('contract_eir')->where('contract_id', 'C-1')->value('calculated_by'));
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

    public function test_reopen_archives_locked_eir_and_invalidates_downstream_results(): void
    {
        $this->seedContract();
        $service = $this->service();
        $service->calculate('C-1', 10);
        $locked = $service->lock('C-1', 20);
        $oldEir = $locked->eir_effective_annual;

        DB::table('eir_amortisation')->insert(['contract_id'=>'C-1','reporting_period'=>'2025-01','opening_gross'=>1000,
            'interest_accrued'=>10,'interest_basis'=>'GROSS','unwind_amount'=>0,'cash_received'=>0,'cash_source'=>'DERIVED',
            'modification_gain_loss'=>0,'closing_gross'=>1010,'ecl_allowance'=>25,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('loan_books')->insert(['contract_id'=>'C-1','ecl_value_discounted'=>80,'ecl_discounting_effect'=>20,
            'ecl_discount_rate'=>$oldEir,'ecl_discount_rate_source'=>'EIR_ORIGINAL','ecl_discount_status'=>'CALCULATED_TIME_PHASED']);

        $reopened = $service->reopen('C-1', 30, 'Correcting an approved fee classification.');

        $this->assertSame('REOPENED', $reopened->calculation_status);
        $this->assertNull($reopened->locked_at);
        $this->assertNull($reopened->eir_effective_annual);
        $this->assertEqualsWithDelta($oldEir, DB::table('eir_calculation_history')->value('eir_effective_annual'), 0.000001);
        $this->assertSame('Correcting an approved fee classification.', DB::table('eir_calculation_history')->value('archive_reason'));
        $this->assertSame(0, DB::table('eir_amortisation')->count());
        $this->assertSame(1, DB::table('eir_amortisation_history')->count());
        $this->assertSame('STALE_EIR_REOPENED', DB::table('loan_books')->value('ecl_discount_status'));
        $this->assertNull(DB::table('loan_books')->value('ecl_value_discounted'));

        $recalculated = $service->calculate('C-1', 40);
        $this->assertSame('CALCULATED', $recalculated['status']);
    }
}
