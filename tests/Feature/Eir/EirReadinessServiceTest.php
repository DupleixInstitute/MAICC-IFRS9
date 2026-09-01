<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\EirReadinessService;
use App\Services\Eir\EirContractInputService;
use App\Exceptions\EirContractNotReadyException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EirReadinessServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('instrument_type')->default('AMORTISED_LOAN'); $t->string('rate_type')->default('FIXED'); $t->string('origination_date')->nullable(); $t->double('drawn_amount')->nullable(); $t->integer('payments_per_year')->default(12); $t->string('frequency_source')->default('ASSUMED'); $t->string('schedule_source')->nullable(); $t->string('schedule_approval_status')->default('NOT_GENERATED'); $t->string('locked_at')->nullable(); $t->timestamps(); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version')->default(1); $t->string('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); });
        Schema::create('contract_fees', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('fee_type')->default('other'); $t->string('description')->nullable(); $t->double('amount'); $t->boolean('integral')->nullable(); $t->string('classification_status')->default('PENDING'); $t->string('cashflow_direction')->nullable(); $t->string('transaction_date')->nullable(); $t->string('source_reference')->nullable(); $t->string('gl_account_ref')->nullable(); });
    }

    private function seedReadyContract(): void
    {
        DB::table('contract_eir')->insert(['contract_id' => 'C-1', 'instrument_type' => 'AMORTISED_LOAN', 'origination_date' => '2025-01-01', 'drawn_amount' => 1000, 'payments_per_year' => 12, 'frequency_source' => 'STATED', 'schedule_approval_status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contract_cashflow_schedule')->insert([
            ['contract_id' => 'C-1', 'schedule_version' => 1, 'due_date' => '2025-02-01', 'principal_due' => 500, 'interest_due' => 50],
            ['contract_id' => 'C-1', 'schedule_version' => 1, 'due_date' => '2025-03-01', 'principal_due' => 500, 'interest_due' => 25],
        ]);
        DB::table('contract_fees')->insert(['contract_id' => 'C-1', 'amount' => 30, 'integral' => true, 'classification_status' => 'REVIEWED', 'cashflow_direction' => 'RECEIVED']);
    }

    public function test_complete_contract_is_ready_and_computes_initial_net(): void
    {
        $this->seedReadyContract();
        $result = (new EirReadinessService())->assess('C-1');
        $this->assertTrue($result['ready']);
        $this->assertSame('READY', $result['status']);
        $this->assertSame(970.0, $result['metrics']['initial_net_investment']);
    }

    /**
     * payments_per_year keeps its monthly default, so a facility whose source
     * never stated a frequency stores the same 12 as a genuinely monthly one.
     * Only frequency_source separates them — and a quarterly facility solved
     * monthly returns a plausible rate that is not the contract's.
     */
    public function test_assumed_frequency_blocks_even_though_the_value_is_valid(): void
    {
        $this->seedReadyContract();
        DB::table('contract_eir')->where('contract_id', 'C-1')
            ->update(['frequency_source' => 'ASSUMED']);

        $result = (new EirReadinessService())->assess('C-1');

        $this->assertFalse($result['ready']);
        $codes = array_column($result['issues'], 'code');
        $this->assertContains('FREQUENCY_ASSUMED', $codes);
        // The value itself is a legal frequency; only its provenance is not.
        $this->assertNotContains('FREQUENCY_INVALID', $codes);
    }

    public function test_pending_fee_blocks_contract_with_named_reason(): void
    {
        $this->seedReadyContract();
        DB::table('contract_fees')->insert(['contract_id' => 'C-1', 'amount' => 20, 'classification_status' => 'PENDING']);
        $result = (new EirReadinessService())->assess('C-1');
        $this->assertFalse($result['ready']);
        $this->assertContains('FEE_CLASSIFICATION_PENDING', array_column($result['issues'], 'code'));
    }

    public function test_principal_mismatch_and_equity_are_blocked(): void
    {
        $this->seedReadyContract();
        DB::table('contract_eir')->where('contract_id', 'C-1')->update(['instrument_type' => 'EQUITY_EXCLUDED', 'drawn_amount' => 2000]);
        $result = (new EirReadinessService())->assess('C-1');
        $codes = array_column($result['issues'], 'code');
        $this->assertContains('EQUITY_EXCLUDED', $codes);
        $this->assertContains('PRINCIPAL_NOT_RECONCILED', $codes);
    }

    public function test_missing_contract_profile_is_blocked(): void
    {
        $result = (new EirReadinessService())->assess('UNKNOWN');
        $this->assertSame('CONTRACT_PROFILE_MISSING', $result['issues'][0]['code']);
    }

    public function test_input_service_assembles_ordered_schedule_and_reviewed_adjustments(): void
    {
        $this->seedReadyContract();
        DB::table('contract_fees')->insert(['contract_id' => 'C-1', 'fee_type' => 'legal', 'amount' => 10, 'integral' => true, 'classification_status' => 'REVIEWED', 'cashflow_direction' => 'PAID']);
        DB::table('contract_fees')->insert(['contract_id' => 'C-1', 'fee_type' => 'monitoring', 'amount' => 99, 'integral' => false, 'classification_status' => 'REVIEWED', 'cashflow_direction' => 'RECEIVED']);

        $input = (new EirContractInputService(new EirReadinessService()))->assemble('C-1');

        $this->assertSame(980.0, $input['initial_net_investment']);
        $this->assertSame(30.0, $input['fee_adjustments']['received']);
        $this->assertSame(10.0, $input['fee_adjustments']['paid']);
        $this->assertCount(2, $input['fee_adjustments']['lines']);
        $this->assertSame([1, 2], array_column($input['cash_flows'], 'period'));
        $this->assertSame(['2025-02-01', '2025-03-01'], array_column($input['cash_flows'], 'due_date'));
        $this->assertSame(550.0, $input['cash_flows'][0]['amount']);
        $this->assertSame($input['contract_id'], $input['input_snapshot']['contract_id']);
    }

    public function test_input_service_refuses_blocked_contract_with_named_issues(): void
    {
        $this->seedReadyContract();
        DB::table('contract_fees')->insert(['contract_id' => 'C-1', 'fee_type' => 'legal', 'amount' => 10, 'classification_status' => 'PENDING']);

        try {
            (new EirContractInputService(new EirReadinessService()))->assemble('C-1');
            $this->fail('Expected a blocked-contract exception.');
        } catch (EirContractNotReadyException $e) {
            $this->assertSame('C-1', $e->contractId);
            $this->assertContains('FEE_CLASSIFICATION_PENDING', array_column($e->issues, 'code'));
        }
    }
}
