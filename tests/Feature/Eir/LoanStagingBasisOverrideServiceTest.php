<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\LoanStagingBasisOverrideService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A governed, per-loan exception from the staging policy's own basis (December 2025 Loan Book
 * Investigation Findings v1, section 10): maker-checker like every other decision in this
 * system, every step audit-logged, and never a typed-in day count — only a decision to use the
 * loan's own already-computed directive count instead.
 */
class LoanStagingBasisOverrideServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        // these test the override's own behaviour; the evidence an approval needs is tested in ClientBookReconciliationTest
        config(['approvals.require_evidence' => false]);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        Schema::create('loan_staging_basis_overrides', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id', 30)->index(); $t->string('reporting_period', 7)->nullable(); $t->integer('override_days')->nullable(); $t->string('source', 40)->default('directive_count');
            $t->string('reason', 2000);
            $t->unsignedBigInteger('proposed_by'); $t->unsignedBigInteger('approved_by')->nullable();
            $t->string('status', 20)->default('PROPOSED'); $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);
    }

    public function test_a_proposal_is_audit_logged_and_awaits_approval(): void
    {
        $service = new LoanStagingBasisOverrideService();
        $id = $service->propose('104430000087', '2025-12', 'JAT: schedule-aware count matches the workbook, section 5.3.', 1);

        $row = DB::table('loan_staging_basis_overrides')->where('id', $id)->first();
        $this->assertSame('PROPOSED', $row->status);
        $this->assertSame('104430000087', $row->contract_id);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Staging Basis Override Proposed')->count());
        $this->assertSame([], $service->active('2025-12'));
    }

    public function test_a_reason_is_required_to_propose(): void
    {
        $service = new LoanStagingBasisOverrideService();
        $this->expectException(\RuntimeException::class);
        $service->propose('104430000087', '2025-12', '', 1);
    }

    public function test_the_same_person_cannot_propose_and_approve(): void
    {
        $service = new LoanStagingBasisOverrideService();
        $id = $service->propose('104430000087', '2025-12', 'Reason.', 1);
        $this->expectException(\RuntimeException::class);
        $service->approve($id, 1);
    }

    public function test_a_second_person_approves_and_the_override_becomes_active(): void
    {
        $service = new LoanStagingBasisOverrideService();
        $id = $service->propose('104430000087', '2025-12', 'JAT: section 5.3.', 1);
        $result = $service->approve($id, 2);

        $this->assertSame('APPROVED', $result['status']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Staging Basis Override Approved')->count());
        $active = $service->active('2025-12');
        $this->assertArrayHasKey('104430000087', $active);
        $this->assertSame('JAT: section 5.3.', $active['104430000087']['reason']);
    }

    public function test_a_rejected_override_never_becomes_active(): void
    {
        $service = new LoanStagingBasisOverrideService();
        $id = $service->propose('104430000087', '2025-12', 'Reason.', 1);
        $result = $service->reject($id, 2, 'Not enough evidence yet.');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Staging Basis Override Rejected')->count());
        $this->assertSame([], $service->active('2025-12'));
    }

    public function test_an_approved_override_can_be_withdrawn_and_stops_applying(): void
    {
        $service = new LoanStagingBasisOverrideService();
        $id = $service->propose('104430000087', '2025-12', 'Reason.', 1);
        $service->approve($id, 2);
        $result = $service->withdraw($id, 1, 'Withdrawn in the test: the figure is superseded.');

        $this->assertSame('WITHDRAWN', $result['status']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Staging Basis Override Withdrawn')->count());
        $this->assertSame([], $service->active('2025-12'));
    }

    /**
     * A real-world sequence surfaced a schema bug: contract_id was given a hard, permanent
     * unique constraint, so a withdrawn override's own row (still physically present) collided
     * with any later re-proposal for the same loan, even though the application check only
     * blocks a *live* (PROPOSED/APPROVED) one. Fixed by dropping the unique constraint in favour
     * of the application check alone (migration 2026_10_17_150000).
     */
    public function test_a_contract_can_be_proposed_again_after_an_earlier_override_was_withdrawn(): void
    {
        $service = new LoanStagingBasisOverrideService();
        $first = $service->propose('104430000087', '2025-12', 'First pass.', 1);
        $service->approve($first, 2);
        $service->withdraw($first, 1, 'Withdrawn in the test: the figure is superseded.');

        $second = $service->propose('104430000087', '2025-12', 'Re-proposed after withdrawal.', 1, 69);
        $service->approve($second, 2);

        $this->assertNotSame($first, $second);
        $active = $service->active('2025-12');
        $this->assertSame(69, $active['104430000087']['override_days']);
        $this->assertSame('Re-proposed after withdrawal.', $active['104430000087']['reason']);
    }

    public function test_a_contract_cannot_have_two_live_overrides_at_once(): void
    {
        $service = new LoanStagingBasisOverrideService();
        $service->propose('104430000087', '2025-12', 'First.', 1);
        $this->expectException(\RuntimeException::class);
        $service->propose('104430000087', '2025-12', 'Second.', 1);
    }
}
