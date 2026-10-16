<?php

namespace Tests\Feature\Eir;

use App\Models\User;
use App\Services\ApprovalDocumentService;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Macro\MacroOverrideService;
use App\Support\Approvals;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Staging Module Specification v1, section 8.6 (acceptance 13): every approval
 * workflow records its proposal, decision (approve, reject or withdraw) and
 * documents in the one audit log, and refuses an approval without the
 * evidence its row of the specification's table requires.
 */
class ApprovalWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    private User $maker;

    private User $checker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->maker = User::create(['name' => 'Finance analyst', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $this->checker = User::create(['name' => 'Chief Finance Officer', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        foreach ([$this->maker, $this->checker] as $u) {
            $u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('eir.govern', 'web'));
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        [$this->maker, $this->checker] = [$this->maker->fresh(), $this->checker->fresh()];
        (new GovernanceSettingsSeeder())->run();
    }

    private function override(): int
    {
        return (new MacroOverrideService(app(\App\Services\Macro\MacroImportService::class)))
            ->propose('manual', 'PLR', null, ['period' => '2026-09-30', 'value' => 24.5, 'is_forecast' => false], 'Reserve Bank policy rate notice of 30 September 2026', $this->maker->id);
    }

    /** Every workflow names a table and columns that exist, so a decision and a document can always be recorded. */
    public function test_every_workflow_names_a_table_and_columns_that_exist(): void
    {
        foreach (Approvals::WORKFLOWS as $key => $w) {
            $this->assertTrue(Schema::hasTable($w['table']), "{$key}: table {$w['table']}");
            $this->assertTrue(Schema::hasColumn($w['table'], $w['status']), "{$key}: status column {$w['status']}");
            if ($w['maker'] !== null) {
                $this->assertTrue(Schema::hasColumn($w['table'], $w['maker']), "{$key}: maker column {$w['maker']}");
            }
        }
    }

    /** Acceptance 13: no approval without the evidence the workflow requires; the refusal names it; the bootstrap is exempt. */
    public function test_an_approval_needs_the_evidence_its_workflow_requires(): void
    {
        $id = $this->override();
        $service = app(MacroOverrideService::class);
        try {
            $service->approve($id, $this->checker->id);
            $this->fail('approved without its evidence');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('needs a supporting document', $e->getMessage());
            $this->assertStringContainsString('the source of the value', $e->getMessage());
        }
        // the bootstrap's own automated approvals are not MAIIC approvals and carry none
        Approvals::assertEvidence('write-off', 999, LoanBookBuildService::BOOTSTRAP_LABEL);
        // a workflow that requires none
        Approvals::assertEvidence('schedule', 999);

        (new ApprovalDocumentService())->attach('macro-override', $id, UploadedFile::fake()->createWithContent('RBM MPC statement.pdf', '%PDF policy rate 24.5'), 'correspondence', 'Monetary Policy Committee statement', $this->maker->id);
        try {
            $service->approve($id, $this->maker->id);
            $this->fail('the proposer approved their own override');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('cannot approve it', $e->getMessage());
        }
        $result = $service->approve($id, $this->checker->id);
        $this->assertArrayHasKey('batch_id', $result);
        $this->assertSame('APPROVED', DB::table('macro_override_requests')->where('id', $id)->value('status'));
    }

    /** Acceptance 13: reject by a second person and withdraw by the proposer, each with a reason, each in the audit log. */
    public function test_reject_and_withdraw_are_recorded_with_their_reasons(): void
    {
        $withdrawn = $this->override();
        try {
            Approvals::decide('macro-override', $withdrawn, 'withdraw', $this->checker->id, 'Not mine to withdraw at all');
            $this->fail('a second person withdrew a proposal');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('can withdraw it', $e->getMessage());
        }
        Approvals::decide('macro-override', $withdrawn, 'withdraw', $this->maker->id, 'Entered against the wrong month');

        $rejected = $this->override();
        try {
            Approvals::decide('macro-override', $rejected, 'reject', $this->maker->id, 'Rejecting my own proposal');
            $this->fail('the proposer rejected their own proposal');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('withdraws it', $e->getMessage());
        }
        try {
            Approvals::decide('macro-override', $rejected, 'reject', $this->checker->id, 'short');
            $this->fail('rejected without a reason');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }
        Approvals::decide('macro-override', $rejected, 'reject', $this->checker->id, 'The rate quoted is the old one, before the MPC change');

        $this->assertSame(['WITHDRAWN', 'REJECTED'], DB::table('macro_override_requests')->whereIn('id', [$withdrawn, $rejected])->orderBy('id')->pluck('status')->all());
        $this->assertSame(['Macro Override Proposed', 'Macro data override Withdrawn'], DB::table('audit_logs')->where('entity_type', 'macro_override_requests')->where('entity_id', $withdrawn)->orderBy('id')->pluck('action')->all());
        $reason = json_decode(DB::table('audit_logs')->where('action', 'Macro data override Rejected')->value('new_values'), true)['reason'];
        $this->assertSame('The rate quoted is the old one, before the MPC change', $reason);
        try {
            Approvals::decide('macro-override', $rejected, 'reject', $this->checker->id, 'Rejecting it a second time');
            $this->fail('a decided item was decided again');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('not waiting for a decision', $e->getMessage());
        }
    }

    /** The record page shows the item's history and documents; a decided item takes no more documents. */
    public function test_the_record_page_shows_history_and_documents(): void
    {
        $id = $this->override();
        $doc = (new ApprovalDocumentService())->attach('macro-override', $id, UploadedFile::fake()->createWithContent('Source.csv', 'period,value'), 'other', null, $this->maker->id);
        $this->actingAs($this->checker)->get(route('approvals.record', ['macro-override', $id]))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Approvals/Record')->where('pending', true)->where('evidence', 'the source of the value')
                ->has('documents', 1)->where('documents.0.name', 'Source.csv')->has('events', 2));
        Approvals::decide('macro-override', $id, 'withdraw', $this->maker->id, 'Superseded by a later entry');
        $this->expectException(\LogicException::class);
        (new ApprovalDocumentService())->attach('macro-override', $id, UploadedFile::fake()->createWithContent('Late.pdf', 'x'), 'other', null, $this->maker->id);
        $this->assertNotNull($doc);
    }

    /** A manual macro value entered on the screen is a proposal, not a change to the data. */
    public function test_a_manual_macro_value_waits_for_a_second_person(): void
    {
        // the macro screens also sit behind the IFRS 9 reports permission (RequireLogin)
        foreach (['macro.manage', 'reports.ifrs9'] as $p) {
            $this->maker->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($p, 'web'));
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $before = DB::table('macro_statistics_data')->count();
        $this->actingAs($this->maker->fresh())->from('/macro-statistics')->post(route('macro-statistics.manual'), ['series_code' => 'PLR', 'period' => '2026-09-30', 'value' => 24.5, 'reason' => 'Reserve Bank policy rate notice of 30 September 2026'])
            ->assertRedirect('/macro-statistics')->assertSessionHas('success');
        $this->assertSame($before, DB::table('macro_statistics_data')->count(), 'nothing is applied until a second person approves');
        $this->assertSame(1, DB::table('macro_override_requests')->where('status', 'PROPOSED')->count());
    }
}
