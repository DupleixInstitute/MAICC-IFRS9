<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\LoanGrossAmountOverrideService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A governed, per-loan exception from the build's own rebuilt gross (carrying) amount,
 * reconciling an already-closed, already-audited period to the figure the external audit
 * approved. Built to the identical maker-checker, audit-logged pattern as
 * LoanStagingBasisOverrideService, including the contract_id index-not-unique fix (migration
 * 2026_10_17_150000 found this the hard way for staging; this table starts correct).
 */
class LoanGrossAmountOverrideServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        Schema::create('loan_gross_amount_overrides', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id', 30)->index(); $t->string('reporting_period', 7)->nullable(); $t->decimal('override_amount', 20, 2);
            $t->string('reason', 2000);
            $t->unsignedBigInteger('proposed_by'); $t->unsignedBigInteger('approved_by')->nullable();
            $t->string('status', 20)->default('PROPOSED'); $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);
    }

    public function test_a_proposal_is_audit_logged_and_awaits_approval(): void
    {
        $service = new LoanGrossAmountOverrideService();
        $id = $service->propose('104430000099', '2025-12', 'MUSECO: audited workbook figure, pending MAIIC reasons.', 1, 0.00);

        $row = DB::table('loan_gross_amount_overrides')->where('id', $id)->first();
        $this->assertSame('PROPOSED', $row->status);
        $this->assertSame('104430000099', $row->contract_id);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Gross Amount Override Proposed')->count());
        $this->assertSame([], $service->active('2025-12'));
    }

    public function test_a_reason_is_required_to_propose(): void
    {
        $service = new LoanGrossAmountOverrideService();
        $this->expectException(\RuntimeException::class);
        $service->propose('104430000099', '2025-12', '', 1, 0.00);
    }

    public function test_the_same_person_cannot_propose_and_approve(): void
    {
        $service = new LoanGrossAmountOverrideService();
        $id = $service->propose('104430000099', '2025-12', 'Reason.', 1, 0.00);
        $this->expectException(\RuntimeException::class);
        $service->approve($id, 1);
    }

    public function test_a_second_person_approves_and_the_override_becomes_active(): void
    {
        $service = new LoanGrossAmountOverrideService();
        $id = $service->propose('104430000099', '2025-12', 'MUSECO: audited figure.', 1, 138123456.78);
        $result = $service->approve($id, 2);

        $this->assertSame('APPROVED', $result['status']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Gross Amount Override Approved')->count());
        $active = $service->active('2025-12');
        $this->assertArrayHasKey('104430000099', $active);
        $this->assertSame('MUSECO: audited figure.', $active['104430000099']['reason']);
        $this->assertSame(138123456.78, $active['104430000099']['override_amount']);
    }

    public function test_a_rejected_override_never_becomes_active(): void
    {
        $service = new LoanGrossAmountOverrideService();
        $id = $service->propose('104430000099', '2025-12', 'Reason.', 1, 0.00);
        $result = $service->reject($id, 2, 'Not enough evidence yet.');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Gross Amount Override Rejected')->count());
        $this->assertSame([], $service->active('2025-12'));
    }

    public function test_an_approved_override_can_be_withdrawn_and_stops_applying(): void
    {
        $service = new LoanGrossAmountOverrideService();
        $id = $service->propose('104430000099', '2025-12', 'Reason.', 1, 0.00);
        $service->approve($id, 2);
        $result = $service->withdraw($id, 1);

        $this->assertSame('WITHDRAWN', $result['status']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Gross Amount Override Withdrawn')->count());
        $this->assertSame([], $service->active('2025-12'));
    }

    public function test_a_contract_can_be_proposed_again_after_an_earlier_override_was_withdrawn(): void
    {
        $service = new LoanGrossAmountOverrideService();
        $first = $service->propose('104430000099', '2025-12', 'First pass.', 1, 0.00);
        $service->approve($first, 2);
        $service->withdraw($first, 1);

        $second = $service->propose('104430000099', '2025-12', 'Re-proposed after withdrawal.', 1, 500.00);
        $service->approve($second, 2);

        $this->assertNotSame($first, $second);
        $active = $service->active('2025-12');
        $this->assertSame(500.00, $active['104430000099']['override_amount']);
        $this->assertSame('Re-proposed after withdrawal.', $active['104430000099']['reason']);
    }

    public function test_a_contract_cannot_have_two_live_overrides_at_once(): void
    {
        $service = new LoanGrossAmountOverrideService();
        $service->propose('104430000099', '2025-12', 'First.', 1, 0.00);
        $this->expectException(\RuntimeException::class);
        $service->propose('104430000099', '2025-12', 'Second.', 1, 1.00);
    }
}
